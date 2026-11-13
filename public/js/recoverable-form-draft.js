(function () {
    'use strict';

    var STORAGE_PREFIX = 'dtfta:recoverable-form:';
    var databasePromise;

    function openDatabase() {
        if (!window.indexedDB) {
            return Promise.reject(new Error('This browser does not support saving image drafts.'));
        }
        if (databasePromise) return databasePromise;

        databasePromise = new Promise(function (resolve, reject) {
            var request = indexedDB.open('dtfta-recoverable-drafts', 1);
            request.onupgradeneeded = function () {
                if (!request.result.objectStoreNames.contains('files')) {
                    request.result.createObjectStore('files', { keyPath: 'key' });
                }
            };
            request.onsuccess = function () { resolve(request.result); };
            request.onerror = function () { reject(request.error || new Error('Could not open draft storage.')); };
        });
        return databasePromise;
    }

    function deleteStoredFiles(key) {
        return openDatabase().then(function (db) {
            return new Promise(function (resolve, reject) {
                var transaction = db.transaction('files', 'readwrite');
                transaction.objectStore('files').delete(key);
                transaction.oncomplete = resolve;
                transaction.onerror = function () { reject(transaction.error || new Error('Could not remove saved draft images.')); };
            });
        });
    }

    function injectStyles() {
        if (document.getElementById('recoverable-draft-styles')) return;
        var style = document.createElement('style');
        style.id = 'recoverable-draft-styles';
        style.textContent =
            '.recoverable-draft-panel{margin:0 0 16px;padding:12px 14px;border:1px solid rgba(96,165,250,.4);border-radius:10px;background:rgba(30,58,138,.18);color:#dbeafe;font-size:14px}' +
            '.recoverable-draft-panel[hidden]{display:none}' +
            '.recoverable-draft-panel strong{display:block;margin-bottom:4px}' +
            '.recoverable-draft-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}' +
            '.recoverable-draft-status{color:#bfdbfe}' +
            '.recoverable-draft-error{color:#fecaca}';
        document.head.appendChild(style);
    }

    function fileKey(name, index) {
        return name + '#' + index;
    }

    function ignoredControl(control) {
        var type = (control.type || '').toLowerCase();
        return !control.name || control.disabled || type === 'button' || type === 'submit' ||
            type === 'reset' || control.name === '_token' || control.name === '_method';
    }

    function collect(form) {
        var controls = [];
        var files = [];
        var indices = {};
        Array.prototype.forEach.call(form.querySelectorAll('input[name], select[name], textarea[name]'), function (control) {
            if (ignoredControl(control)) return;
            var name = control.name;
            var index = indices[name] || 0;
            indices[name] = index + 1;
            var type = (control.type || '').toLowerCase();
            if (type === 'file') {
                var file = control.files && control.files[0];
                if (file) {
                    files.push({
                        id: fileKey(name, index),
                        name: name,
                        index: index,
                        fileName: file.name,
                        type: file.type,
                        size: file.size,
                        lastModified: file.lastModified,
                        file: file
                    });
                }
                controls.push({ name: name, index: index, type: 'file' });
                return;
            }

            var value = control.multiple
                ? Array.prototype.filter.call(control.options, function (option) { return option.selected; }).map(function (option) { return option.value; })
                : control.value;
            controls.push({
                name: name,
                index: index,
                type: type,
                value: value,
                checked: type === 'checkbox' || type === 'radio' ? control.checked : undefined
            });
        });

        var collections = [];
        Array.prototype.forEach.call(form.querySelectorAll('[data-draft-collection]'), function (container) {
            collections.push({
                name: container.getAttribute('data-draft-collection'),
                count: container.querySelectorAll(container.getAttribute('data-draft-row') || ':scope > *').length
            });
        });

        return { controls: controls, files: files, collections: collections };
    }

    function comparable(snapshot) {
        return JSON.stringify({
            controls: snapshot.controls,
            files: snapshot.files.map(function (file) {
                return {
                    id: file.id,
                    fileName: file.fileName,
                    type: file.type,
                    size: file.size,
                    lastModified: file.lastModified
                };
            }),
            collections: snapshot.collections
        });
    }

    function saveFiles(key, files) {
        if (!files.length && !window.indexedDB) return Promise.resolve();
        return openDatabase().then(function (db) {
            return new Promise(function (resolve, reject) {
                var transaction = db.transaction('files', 'readwrite');
                var store = transaction.objectStore('files');
                store.put({
                    key: key,
                    files: files.map(function (entry) {
                        return { id: entry.id, file: entry.file };
                    })
                });
                transaction.oncomplete = resolve;
                transaction.onerror = function () { reject(transaction.error || new Error('Could not save uploaded draft images.')); };
            });
        });
    }

    function readFiles(key) {
        return openDatabase().then(function (db) {
            return new Promise(function (resolve, reject) {
                var request = db.transaction('files', 'readonly').objectStore('files').get(key);
                request.onsuccess = function () { resolve(request.result ? request.result.files : []); };
                request.onerror = function () { reject(request.error || new Error('Could not read saved draft images.')); };
            });
        });
    }

    function attach(form) {
        var key = form.getAttribute('data-draft-key');
        if (!key) return;
        injectStyles();

        var storageKey = STORAGE_PREFIX + key;
        var banner = document.createElement('div');
        banner.className = 'recoverable-draft-panel';
        banner.setAttribute('role', 'status');
        banner.hidden = true;
        form.insertBefore(banner, form.firstChild);

        var baseline;
        var restoring = false;
        var submitting = false;
        var userEdited = false;
        var interactionBaselineCaptured = false;
        var persistedDuringUnload = false;
        var timer = null;
        var currentDraft = null;
        var recoveryPending = false;
        var savedFileSignature = '';
        var fileSaveQueue = Promise.resolve();

        function setStatus(message, isError) {
            banner.hidden = false;
            banner.innerHTML = '<strong class="' + (isError ? 'recoverable-draft-error' : 'recoverable-draft-status') + '">' +
                message.replace(/[&<>"']/g, function (char) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
                }) + '</strong>';
        }

        function removeDraft() {
            try {
                localStorage.removeItem(storageKey);
            } catch (error) {
                setStatus('Could not remove this saved draft: ' + error.message, true);
                return;
            }
            deleteStoredFiles(storageKey).catch(function (error) {
                setStatus('The draft was removed, but saved image cleanup failed: ' + error.message, true);
            });
            currentDraft = null;
            savedFileSignature = '';
            banner.hidden = true;
        }

        function signatureForFiles(files) {
            return files.map(function (file) {
                return [file.id, file.fileName, file.type, file.size, file.lastModified].join('|');
            }).join(';');
        }

        function saveDraft() {
            if (restoring) return Promise.resolve();
            var snapshot = collect(form);
            if (comparable(snapshot) === comparable(baseline)) {
                if (currentDraft && !recoveryPending) removeDraft();
                return Promise.resolve();
            }

            var savedAt = new Date().toISOString();
            var payload = {
                version: 1,
                savedAt: savedAt,
                path: window.location.pathname,
                controls: snapshot.controls,
                files: snapshot.files.map(function (file) {
                    return {
                        id: file.id,
                        name: file.name,
                        index: file.index,
                        fileName: file.fileName,
                        type: file.type,
                        size: file.size,
                        lastModified: file.lastModified
                    };
                }),
                collections: snapshot.collections
            };
            try {
                localStorage.setItem(storageKey, JSON.stringify(payload));
                currentDraft = payload;
                banner.innerHTML = '<strong class="recoverable-draft-status">Unsaved changes · draft saved ' +
                    new Date(savedAt).toLocaleString() + '</strong>';
                banner.hidden = false;
            } catch (error) {
                setStatus('Draft could not be saved. Check browser storage and try again. ' + error.message, true);
                console.error('Could not save the recoverable form draft.', error);
                return Promise.reject(error);
            }

            var nextFileSignature = signatureForFiles(snapshot.files);
            if (nextFileSignature === savedFileSignature) return Promise.resolve();
            fileSaveQueue = fileSaveQueue.catch(function () {}).then(function () {
                return saveFiles(storageKey, snapshot.files);
            });
            return fileSaveQueue.then(function () {
                savedFileSignature = nextFileSignature;
            }).catch(function (error) {
                if (payload.files.length) {
                    payload.files = [];
                    try {
                        localStorage.setItem(storageKey, JSON.stringify(payload));
                        currentDraft = payload;
                    } catch (storageError) {
                        error = storageError;
                    }
                }
                setStatus('Draft fields were saved, but uploaded images could not be saved. Keep this page open and retry. ' + error.message, true);
                console.error('Could not save recoverable draft images.', error);
                throw error;
            });
        }

        function stageFiles() {
            var files = collect(form).files;
            var signature = signatureForFiles(files);
            if (signature === savedFileSignature) return fileSaveQueue;
            fileSaveQueue = fileSaveQueue.catch(function () {}).then(function () {
                return saveFiles(storageKey, files);
            }).then(function () {
                savedFileSignature = signature;
            }).catch(function (error) {
                setStatus('Uploaded images could not be staged for draft recovery. ' + error.message, true);
                console.error('Could not stage recoverable draft images.', error);
                throw error;
            });
            return fileSaveQueue;
        }

        function scheduleSave() {
            if (restoring) return;
            clearTimeout(timer);
            if (!hasUnsavedChanges()) {
                if (!recoveryPending) {
                    if (currentDraft) removeDraft();
                    else banner.hidden = true;
                }
                return;
            }
            recoveryPending = false;
            timer = setTimeout(function () {
                if (!hasUnsavedChanges()) {
                    if (currentDraft) removeDraft();
                    else banner.hidden = true;
                    return;
                }
                banner.innerHTML = '<strong class="recoverable-draft-status">Unsaved changes · not saved yet</strong>';
                banner.hidden = false;
            }, 180);
        }

        function hasUnsavedChanges() {
            return userEdited && !!baseline && comparable(collect(form)) !== comparable(baseline);
        }

        function captureInteractionBaseline(event) {
            if (!event.isTrusted || interactionBaselineCaptured || !baseline) return;
            baseline = collect(form);
            interactionBaselineCaptured = true;
        }

        ['pointerdown', 'keydown', 'beforeinput', 'click'].forEach(function (eventName) {
            form.addEventListener(eventName, captureInteractionBaseline, true);
        });

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            var link = event.target.closest('a[href]');
            if (!link || link.target || link.hasAttribute('download') || !hasUnsavedChanges()) return;

            var destination;
            try {
                destination = new URL(link.href, window.location.href);
            } catch (error) {
                return;
            }
            if (destination.origin !== window.location.origin) return;
            if (destination.href === window.location.href) return;

            event.preventDefault();
            if (!window.confirm('You have unsaved changes. Leave this page? Your edits will be saved as a recoverable draft.')) return;

            clearTimeout(timer);
            saveDraft().then(function () {
                submitting = true;
                window.location.assign(destination.href);
            }).catch(function (error) {
                if (!window.confirm('The draft could not be saved (' + error.message + '). Leave this page anyway?')) return;
                submitting = true;
                window.location.assign(destination.href);
            });
        }, true);

        function setCollectionCount(savedCollections, name) {
            var state = savedCollections.filter(function (item) { return item.name === name; })[0];
            var container = form.querySelector('[data-draft-collection="' + name + '"]');
            if (!state || !container) return;
            var rowSelector = container.getAttribute('data-draft-row') || ':scope > *';
            var addSelector = container.getAttribute('data-draft-add');
            var removeSelector = container.getAttribute('data-draft-remove');
            var currentCount = function () { return container.querySelectorAll(rowSelector).length; };

            while (currentCount() < state.count && addSelector) {
                var addButton = form.querySelector(addSelector);
                if (!addButton) break;
                addButton.click();
            }
            while (currentCount() > state.count && removeSelector) {
                var rows = container.querySelectorAll(rowSelector);
                var previousCount = rows.length;
                var removeButton = rows[rows.length - 1].querySelector(removeSelector);
                if (!removeButton) break;
                removeButton.click();
                if (currentCount() >= previousCount) break;
            }
        }

        function applyControls(payload, filesFromDb) {
            var savedCollections = payload.collections || [];
            savedCollections.forEach(function (item) { setCollectionCount(savedCollections, item.name); });

            var grouped = {};
            Array.prototype.forEach.call(form.querySelectorAll('input[name], select[name], textarea[name]'), function (control) {
                if (ignoredControl(control)) return;
                if (!grouped[control.name]) grouped[control.name] = [];
                grouped[control.name].push(control);
            });

            payload.controls.forEach(function (saved) {
                var control = grouped[saved.name] && grouped[saved.name][saved.index];
                if (!control || saved.type === 'file') return;
                var type = (control.type || '').toLowerCase();
                if (type === 'checkbox' || type === 'radio') control.checked = !!saved.checked;
                else if (control.multiple && Array.isArray(saved.value)) {
                    Array.prototype.forEach.call(control.options, function (option) {
                        option.selected = saved.value.indexOf(option.value) >= 0;
                    });
                } else {
                    control.value = saved.value;
                }
                control.dispatchEvent(new Event('input', { bubbles: true }));
                control.dispatchEvent(new Event('change', { bubbles: true }));
            });

            var fileControls = {};
            Array.prototype.forEach.call(form.querySelectorAll('input[type="file"][name]'), function (control) {
                if (!fileControls[control.name]) fileControls[control.name] = [];
                fileControls[control.name].push(control);
            });
            payload.files.forEach(function (metadata) {
                var control = fileControls[metadata.name] && fileControls[metadata.name][metadata.index];
                var stored = filesFromDb.filter(function (item) { return item.id === metadata.id; })[0];
                if (!control || !stored || !stored.file) throw new Error('A saved image is missing from browser storage.');
                var transfer = new DataTransfer();
                transfer.items.add(new File([stored.file], metadata.fileName, {
                    type: metadata.type,
                    lastModified: metadata.lastModified
                }));
                control.files = transfer.files;
                control.dispatchEvent(new Event('change', { bubbles: true }));
            });
            form.dispatchEvent(new CustomEvent('recoverable-draft-restored', { bubbles: true }));
        }

        function showRecovery(payload) {
            recoveryPending = true;
            var date = new Date(payload.savedAt);
            banner.innerHTML =
                '<strong>Recoverable draft found</strong>' +
                '<span>Last saved ' + date.toLocaleString() + '. Resume your edits or discard this draft.</span>' +
                '<div class="recoverable-draft-actions">' +
                    '<button type="button" class="btn-primary" data-draft-resume>Resume draft</button>' +
                    '<button type="button" class="btn-secondary" data-draft-discard>Discard draft</button>' +
                '</div>';
            banner.hidden = false;
            banner.querySelector('[data-draft-resume]').addEventListener('click', function () {
                recoveryPending = false;
                restoring = true;
                var fileRead = (payload.files || []).length ? readFiles(storageKey) : Promise.resolve([]);
                fileRead.then(function (filesFromDb) {
                    applyControls(payload, filesFromDb);
                    currentDraft = payload;
                    setTimeout(function () {
                        restoring = false;
                        banner.innerHTML = '<strong class="recoverable-draft-status">Draft resumed · last saved ' +
                            new Date(payload.savedAt).toLocaleString() + '</strong>';
                        banner.hidden = false;
                    }, 0);
                }).catch(function (error) {
                    restoring = false;
                    setStatus('Draft could not be restored. Your saved copy was kept. ' + error.message, true);
                    console.error('Could not restore the recoverable form draft.', error);
                });
            });
            banner.querySelector('[data-draft-discard]').addEventListener('click', function () {
                recoveryPending = false;
                removeDraft();
                submitting = true;
                window.location.reload();
            });
        }

        form.addEventListener('input', function (event) {
            if (event.isTrusted) userEdited = true;
            scheduleSave();
        });
        form.addEventListener('change', function (event) {
            if (event.isTrusted) userEdited = true;
            if (event.target.type === 'file') {
                clearTimeout(timer);
                stageFiles().catch(function () {});
                return;
            }
            scheduleSave();
        });
        form.addEventListener('click', function (event) {
            if (event.target.closest('button:not([type="submit"]), a, .asset-selector-wrapper')) {
                if (event.isTrusted) userEdited = true;
                scheduleSave();
            }
        });
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            if (submitting) return;
            clearTimeout(timer);
            submitting = true;
            try {
                localStorage.removeItem(storageKey);
            } catch (error) {
                console.error('Could not clear the saved form draft before submit.', error);
            }
            currentDraft = null;
            banner.hidden = true;
            deleteStoredFiles(storageKey).catch(function (error) {
                console.error('Could not clear saved draft images before submit.', error);
            });
        });
        form.querySelectorAll('[data-draft-collection]').forEach(function (container) {
            new MutationObserver(scheduleSave).observe(container, { childList: true, subtree: true });
        });

        window.addEventListener('beforeunload', function (event) {
            if (submitting || !hasUnsavedChanges()) return;
            clearTimeout(timer);
            saveDraft().catch(function () {});
            persistedDuringUnload = true;
            event.preventDefault();
            event.returnValue = '';
        });
        window.addEventListener('focus', function () {
            if (!persistedDuringUnload || !hasUnsavedChanges() || !currentDraft) return;
            removeDraft();
            persistedDuringUnload = false;
            banner.innerHTML = '<strong class="recoverable-draft-status">Unsaved changes · not saved yet</strong>';
            banner.hidden = false;
        });

        setTimeout(function () {
            baseline = collect(form);
            var raw;
            try {
                raw = localStorage.getItem(storageKey);
                currentDraft = raw ? JSON.parse(raw) : null;
            } catch (error) {
                setStatus('Could not read the saved draft: ' + error.message, true);
                console.error('Could not read the recoverable form draft.', error);
                return;
            }
            if (!currentDraft || currentDraft.version !== 1) return;
            savedFileSignature = signatureForFiles(currentDraft.files || []);
            if (comparable(baseline) === comparable({
                controls: currentDraft.controls,
                files: [],
                collections: currentDraft.collections || []
            }) && !(currentDraft.files || []).length) {
                removeDraft();
                return;
            }
            showRecovery(currentDraft);
        }, 0);
    }

    function boot() {
        document.querySelectorAll('form[data-recoverable-draft]').forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(boot, 0); });
    } else {
        setTimeout(boot, 0);
    }
})();
