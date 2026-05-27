<script>
document.addEventListener('DOMContentLoaded', function () {
    const colorsWrapper = document.getElementById('colors-wrapper');
    const addColorBtn = document.getElementById('add-color-btn');
    const variantPreview = document.getElementById('variant-preview');
    const rowTemplate = document.getElementById('product-color-row-template');
    const productForm = colorsWrapper ? colorsWrapper.closest('form') : null;
    const predefinedColors = (function () {
        if (!colorsWrapper) return [];
        try {
            const raw = colorsWrapper.getAttribute('data-predefined-colors') || '[]';
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed.filter(Boolean) : [];
        } catch (e) {
            return [];
        }
    })();

    if (!colorsWrapper || !addColorBtn) {
        return;
    }

    function initColorSuggest(input) {
        if (!input || input.dataset.suggestBound === '1') {
            return;
        }
        input.dataset.suggestBound = '1';

        const wrap = input.closest('.color-name-suggest-wrap');
        const menu = wrap ? wrap.querySelector('.color-suggest-menu') : null;

        if (!menu || !predefinedColors.length) {
            return;
        }

        let activeIndex = -1;
        let currentItems = [];

        function closeMenu() {
            menu.hidden = true;
            menu.innerHTML = '';
            activeIndex = -1;
            currentItems = [];
        }

        function openMenu() {
            if (currentItems.length) {
                menu.hidden = false;
            }
        }

        function setActive(index) {
            activeIndex = index;
            const buttons = Array.from(menu.querySelectorAll('.color-suggest-item'));
            buttons.forEach(function (btn, i) {
                btn.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');
            });
            const activeBtn = buttons[activeIndex];
            if (activeBtn) {
                activeBtn.scrollIntoView({ block: 'nearest' });
            }
        }

        function choose(value) {
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            closeMenu();
        }

        function render() {
            const q = (input.value || '').trim().toLowerCase();
            const matches = predefinedColors
                .filter(function (name) {
                    return q === '' ? true : String(name).toLowerCase().includes(q);
                })
                .slice(0, 60);

            currentItems = matches;
            menu.innerHTML = '';
            matches.forEach(function (name, idx) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'color-suggest-item';
                btn.setAttribute('data-idx', String(idx));
                btn.setAttribute('data-value', String(name));
                btn.textContent = String(name);
                menu.appendChild(btn);
            });

            if (!matches.length) {
                closeMenu();
                return;
            }

            openMenu();
            setActive(-1);
        }

        input.addEventListener('focus', function () {
            render();
        });

        input.addEventListener('input', function () {
            render();
        });

        input.addEventListener('keydown', function (e) {
            if (menu.hidden) {
                if (e.key === 'ArrowDown' && predefinedColors.length) {
                    render();
                    e.preventDefault();
                }
                return;
            }

            if (e.key === 'Escape') {
                closeMenu();
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActive(Math.min(activeIndex + 1, currentItems.length - 1));
                return;
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(Math.max(activeIndex - 1, -1));
                return;
            }

            if (e.key === 'Enter') {
                if (activeIndex >= 0 && currentItems[activeIndex]) {
                    e.preventDefault();
                    choose(currentItems[activeIndex]);
                }
            }
        });

        menu.addEventListener('mousedown', function (e) {
            const btn = e.target && e.target.closest ? e.target.closest('.color-suggest-item') : null;
            if (!btn) return;
            e.preventDefault(); // keep focus on input
            const value = btn.getAttribute('data-value') || '';
            choose(value);
        });

        document.addEventListener('mousedown', function (e) {
            if (!wrap) return;
            if (wrap.contains(e.target)) return;
            closeMenu();
        });

        input.addEventListener('blur', function () {
            // allow click selection to run first
            setTimeout(closeMenu, 120);
        });
    }

    function initColorSuggests(root) {
        (root || colorsWrapper).querySelectorAll('.color-name-input').forEach(initColorSuggest);
    }

    function reindexColorRows() {
        colorsWrapper.querySelectorAll('.color-row').forEach(function (row, index) {
            const colorNameInput = row.querySelector('.color-name-input');
            if (colorNameInput) {
                colorNameInput.name = 'colors[' + index + ']';
            }

            const hexInput = row.querySelector('.color-hex-input');
            if (hexInput) {
                hexInput.name = 'color_hex[' + index + ']';
            }

            row.querySelectorAll('.color-mockup-box').forEach(function (box) {
                const placement = box.dataset.placement === 'back' ? 'back' : 'front';
                const fileInput = box.querySelector('.color-mockup-file-input');
                const existingInput = box.querySelector('.color-mockup-existing-input');
                const removeInput = box.querySelector('.color-mockup-remove-input');

                if (fileInput) {
                    fileInput.name = placement === 'back'
                        ? 'color_back[' + index + ']'
                        : 'color_front[' + index + ']';
                }
                if (existingInput) {
                    existingInput.name = placement === 'back'
                        ? 'color_back_existing[' + index + ']'
                        : 'color_front_existing[' + index + ']';
                }
                if (removeInput) {
                    removeInput.name = placement === 'back'
                        ? 'color_back_remove[' + index + ']'
                        : 'color_front_remove[' + index + ']';
                }
            });
        });
    }

    function initMockupBox(box) {
        if (!box || box.dataset.mockupBound === '1') {
            return;
        }
        box.dataset.mockupBound = '1';

        const fileInput = box.querySelector('.color-mockup-file-input');
        const existingInput = box.querySelector('.color-mockup-existing-input');
        const removeInput = box.querySelector('.color-mockup-remove-input');
        const addBtn = box.querySelector('.color-mockup-trigger-add');
        const removeBtn = box.querySelector('.color-mockup-trigger-remove');
        const previewWrap = box.querySelector('.color-mockup-preview-wrap');
        const previewImg = box.querySelector('.color-mockup-preview-img');
        let objectUrl = null;

        function revokeObjectUrl() {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
        }

        function setEmptyState() {
            box.classList.remove('has-image');
            if (previewWrap) {
                previewWrap.hidden = true;
            }
            if (previewImg) {
                previewImg.hidden = true;
                previewImg.removeAttribute('src');
            }
            if (addBtn) {
                addBtn.style.display = '';
            }
        }

        function setImageState(src) {
            if (!src) {
                setEmptyState();
                return;
            }

            box.classList.add('has-image');
            if (previewWrap) {
                previewWrap.hidden = false;
            }
            if (previewImg) {
                previewImg.src = src;
                previewImg.hidden = false;
            }
            if (addBtn) {
                addBtn.style.display = 'none';
            }
        }

        function hadSavedMockup() {
            if (existingInput && existingInput.value.trim() !== '') {
                return true;
            }

            const src = previewImg ? (previewImg.getAttribute('src') || '') : '';
            return src !== '' && !src.startsWith('blob:');
        }

        function clearMockup() {
            const shouldMarkRemove = hadSavedMockup();

            if (fileInput) {
                fileInput.value = '';
            }
            if (existingInput) {
                existingInput.value = '';
            }
            if (removeInput) {
                removeInput.value = shouldMarkRemove ? '1' : '';
            }

            revokeObjectUrl();
            setEmptyState();
        }

        if (previewImg && previewImg.getAttribute('src')) {
            setImageState(previewImg.getAttribute('src'));
        } else {
            setEmptyState();
        }

        if (addBtn && fileInput) {
            addBtn.addEventListener('click', function () {
                fileInput.click();
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                const file = fileInput.files && fileInput.files[0];
                if (!file) {
                    return;
                }

                if (removeInput) {
                    removeInput.value = '';
                }
                if (existingInput) {
                    existingInput.value = '';
                }

                revokeObjectUrl();
                objectUrl = URL.createObjectURL(file);
                setImageState(objectUrl);
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                clearMockup();
            });
        }
    }

    function initMockupBoxes(root) {
        (root || colorsWrapper).querySelectorAll('.color-mockup-box').forEach(initMockupBox);
    }

    function makeColorRow() {
        if (rowTemplate && rowTemplate.content) {
            const row = rowTemplate.content.firstElementChild.cloneNode(true);
            initMockupBoxes(row);
            initColorSuggests(row);
            return row;
        }

        return null;
    }

    function bindRemoveButtons() {
        colorsWrapper.querySelectorAll('.color-row .remove-row-btn').forEach(function (button) {
            button.onclick = function () {
                const row = this.closest('.color-row');
                const parent = row.parentElement;

                if (parent.querySelectorAll('.color-row').length > 1) {
                    row.remove();
                } else {
                    row.querySelectorAll('input[type="text"]').forEach(function (input) {
                        input.value = '';
                    });
                    row.querySelectorAll('input[type="color"]').forEach(function (input) {
                        input.value = '#e2e8f0';
                    });
                    row.querySelectorAll('.color-mockup-box').forEach(function (box) {
                        const removeBtn = box.querySelector('.color-mockup-trigger-remove');
                        if (removeBtn) {
                            removeBtn.click();
                        }
                    });
                }

                reindexColorRows();
                updateVariantPreview();
            };
        });
    }

    function getUniqueColorNames() {
        return Array.from(document.querySelectorAll('.color-name-input'))
            .map(function (input) { return input.value.trim(); })
            .filter(function (value) { return value !== ''; })
            .filter(function (value, index, arr) { return arr.indexOf(value) === index; });
    }

    function getUniqueSizes() {
        if (!document.querySelector('input[name="sizes[]"]')) {
            return [];
        }

        return Array.from(document.querySelectorAll('input[name="sizes[]"]'))
            .map(function (input) { return input.value.trim(); })
            .filter(function (value) { return value !== ''; })
            .filter(function (value, index, arr) { return arr.indexOf(value) === index; });
    }

    function updateVariantPreview() {
        if (!variantPreview) {
            return;
        }

        const colors = getUniqueColorNames();
        const sizes = getUniqueSizes();

        if (!colors.length || !sizes.length) {
            variantPreview.innerHTML = '<span style="color:#6b7280;">No variants yet.</span>';
            return;
        }

        const variants = [];
        colors.forEach(function (color) {
            sizes.forEach(function (size) {
                variants.push(color + ' / ' + size);
            });
        });

        variantPreview.innerHTML = `
            <div style="margin-bottom: 8px;"><strong>Total Variants:</strong> ${variants.length}</div>
            <div class="variant-chip-wrap" style="display: flex; flex-wrap: wrap; gap: 8px;">
                ${variants.map(function (variant) {
                    return '<span class="variant-chip">' + variant + '</span>';
                }).join('')}
            </div>
        `;
    }

    addColorBtn.addEventListener('click', function () {
        const row = makeColorRow();
        if (row) {
            colorsWrapper.appendChild(row);
            reindexColorRows();
            bindRemoveButtons();
            updateVariantPreview();
        }
    });

    colorsWrapper.addEventListener('input', updateVariantPreview);
    document.getElementById('sizes-wrapper')?.addEventListener('input', updateVariantPreview);

    if (productForm) {
        productForm.addEventListener('submit', function () {
            reindexColorRows();
        });
    }

    reindexColorRows();
    initMockupBoxes(colorsWrapper);
    initColorSuggests(colorsWrapper);
    bindRemoveButtons();
    updateVariantPreview();
});
</script>
