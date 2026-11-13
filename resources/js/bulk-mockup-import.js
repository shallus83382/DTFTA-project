import JSZip from 'jszip';

const placements = [
    { value: 'front', label: 'Front', pattern: /(?:^|[\s._-])front(?:$|[\s._-])/i },
    { value: 'back', label: 'Back', pattern: /(?:^|[\s._-])back(?:$|[\s._-])/i },
    { value: 'left_sleeve', label: 'Left Sleeve', pattern: /(?:^|[\s._-])(?:left(?:[\s._-]*sleeve)?|sleeve[\s._-]*left)(?:$|[\s._-])/i },
    { value: 'right_sleeve', label: 'Right Sleeve', pattern: /(?:^|[\s._-])(?:right(?:[\s._-]*sleeve)?|sleeve[\s._-]*right)(?:$|[\s._-])/i },
];

const defaultSwatch = '#e2e8f0';
const genericFolderNames = new Set(['mockup', 'mockups', 'image', 'images', 'asset', 'assets', 'color', 'colors']);
const namedSwatches = {
    black: '#000000',
    brown: '#8b4513',
    green: '#008000',
    orange: '#ffa500',
    white: '#ffffff',
    blue: '#0000ff',
    red: '#ff0000',
    clay: '#b66a50',
    antique_gold: '#c9a227',
    banana_cream: '#fff1a8',
    blue_jean: '#5d8aa8',
    bone: '#e3dac9',
    cardinal: '#c41e3a',
    classic_orange: '#ff8c00',
    cool_blue: '#4f83cc',
    cream: '#fffdd0',
    dark_chocholate: '#3c2415',
    dark_chocolate: '#3c2415',
    desert_pink: '#d8a39d',
    forest_green: '#228b22',
    gold: '#d4af37',
    graphite_black: '#383838',
    heather_gray: '#9b9b9b',
    heavy_metal: '#2f4f4f',
    indigo: '#4b0082',
    kelly_green: '#4cbb17',
    light_blue: '#add8e6',
    light_grey: '#d3d3d3',
    light_gray: '#d3d3d3',
    light_olive: '#b5b35c',
    light_pink: '#ffb6c1',
    maroon: '#800000',
    mauve: '#e0b0ff',
    midnight_navy: '#191970',
    military_green: '#4b5320',
    natural: '#f5f0e1',
    oatmeal: '#d6c6a5',
    oatmeall: '#d6c6a5',
    oxblood: '#4a0000',
    periblue: '#ccccff',
    purple_rush: '#7851a9',
    royal: '#4169e1',
    royal_pine: '#01796f',
    sand: '#c2b280',
    shiitake: '#a0896c',
    stonewash_denim: '#738ba5',
    tahiti_blue: '#00a6a6',
    tan: '#d2b48c',
    teal: '#008080',
    turquoise: '#40e0d0',
    watermelon: '#fc6c85',
    hot_pink: '#ff69b4',
};

function normalizeColor(value) {
    return String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
}

function slugColor(value) {
    return normalizeColor(value).replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
}

function cleanSegment(segment) {
    let value = String(segment || '').replace(/\.[^.]+$/, '');
    placements.forEach(({ pattern }) => {
        value = value.replace(pattern, ' ');
    });
    return value.replace(/[_\-.]+/g, ' ').replace(/\s+/g, ' ').trim();
}

function detectPlacement(path) {
    const matches = placements.filter(({ pattern }) => pattern.test(path));
    return {
        placement: matches.length === 1 ? matches[0].value : '',
        ambiguousPlacement: matches.length > 1,
    };
}

function parseEntryName(path) {
    const parts = path.replace(/\\/g, '/').split('/').filter(Boolean);
    const filename = parts.pop() || '';
    const filenameColor = cleanSegment(filename);
    const folderColors = parts
        .filter((part) => !genericFolderNames.has(part.toLowerCase()))
        .map(cleanSegment)
        .filter(Boolean);
    const folderColor = folderColors[folderColors.length - 1] || '';
    const conflictingFolder = folderColors.some((color) => normalizeColor(color) !== normalizeColor(folderColor));

    return {
        ...detectPlacement(filename.replace(/\.[^.]+$/, '')),
        color: filenameColor || folderColor,
        ambiguousColor: Boolean(filenameColor && folderColor && normalizeColor(filenameColor) !== normalizeColor(folderColor))
            || conflictingFolder,
    };
}

function guessColorSwatch(name) {
    const key = slugColor(name);
    if (/^#[0-9a-f]{6}$/i.test(String(name).trim())) return String(name).trim().toLowerCase();
    if (namedSwatches[key]) return namedSwatches[key];

    if (typeof document === 'undefined') return null;

    const candidate = key.replace(/_/g, '');
    const context = document.createElement('canvas').getContext('2d');
    if (!context) return null;
    context.fillStyle = '#010203';
    context.fillStyle = candidate;
    const parsedColor = context.fillStyle;
    return /^#[0-9a-f]{6}$/i.test(parsedColor) && parsedColor !== '#010203'
        ? parsedColor.toLowerCase()
        : null;
}

export { guessColorSwatch, parseEntryName };

function imageExtension(filename) {
    return /\.(png|jpe?g|webp|gif)$/i.test(filename);
}

function makeElement(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
}

function initializeInlineMockupImport() {
    const colorsWrapper = document.getElementById('colors-wrapper');
    const importButton = document.getElementById('mockup-zip-import-button');
    const zipInput = document.getElementById('mockup-zip-import-input');
    const status = document.getElementById('mockup-zip-import-status');
    const review = document.getElementById('mockup-zip-review');
    const reviewContent = document.getElementById('mockup-zip-review-content');
    const reviewFooter = review?.querySelector('.mockup-zip-review-footer');
    const closeReviewButton = document.getElementById('mockup-zip-review-close');
    const cancelReviewButton = document.getElementById('mockup-zip-review-cancel');
    const addColorButton = document.getElementById('add-color-btn');

    if (!colorsWrapper || !importButton || !zipInput || !status || !review || !reviewContent || !reviewFooter || !addColorButton) return;

    let predefinedColorKeys = {};
    try {
        const colors = JSON.parse(colorsWrapper.dataset.colorKeys || '[]');
        colors.forEach(({ name, code }) => {
            if (name && code) {
                predefinedColorKeys[normalizeColor(name)] = String(code).toLowerCase();
                predefinedColorKeys[normalizeColor(code)] = String(code).toLowerCase();
            }
        });
    } catch (error) {
        status.textContent = 'Color presets could not be loaded. You can still map ZIP colors by name.';
    }

    function colorKey(name) {
        const normalized = normalizeColor(name);
        return predefinedColorKeys[normalized] || slugColor(name);
    }

    function findColorRows(name) {
        const key = colorKey(name);
        return Array.from(colorsWrapper.querySelectorAll('.color-row')).filter((row) => {
            const input = row.querySelector('.color-name-input');
            return input && input.value.trim() && colorKey(input.value) === key;
        });
    }

    function rowHasImage(row, placement) {
        const box = row.querySelector(`.color-mockup-box[data-placement="${placement}"]`);
        return Boolean(
            box
            && (
                box.querySelector('.color-mockup-existing-input')?.value.trim()
                || box.querySelector('.color-mockup-file-input')?.files?.length
            )
        );
    }

    function findEmptyColorRow() {
        return Array.from(colorsWrapper.querySelectorAll('.color-row')).find((row) => {
            return !row.querySelector('.color-name-input')?.value.trim();
        }) || null;
    }

    function setStatus(message, isError = false) {
        status.textContent = message;
        status.classList.toggle('mockup-zip-review-error', isError);
    }

    const items = [];
    const swatches = new Map();
    const manualSwatches = new Set();
    let previewUrls = [];

    function buildGroups() {
        const groups = new Map();
        items.forEach((item) => {
            const key = item.color.trim() ? colorKey(item.color) : '__unassigned__';
            if (!groups.has(key)) {
                groups.set(key, {
                    key,
                    color: item.color.trim(),
                    items: [],
                    names: new Set(),
                });
            }
            const group = groups.get(key);
            group.items.push(item);
            group.names.add(normalizeColor(item.color));
        });
        return Array.from(groups.values());
    }

    function occupiedTarget(item) {
        const rows = findColorRows(item.color);
        if (rows.length !== 1) return false;
        return rowHasImage(rows[0], item.placement);
    }

    function renderSummary() {
        reviewContent.replaceChildren();
        const groups = buildGroups();
        const duplicateSlots = [];
        const duplicateColors = [];
        const ambiguousRows = [];

        if (items.some((item) => item.selected && (item.ambiguousColor || item.ambiguousPlacement))) {
            reviewContent.appendChild(makeElement('div', 'mockup-zip-review-warning', 'Ambiguous filename match — verify the color and view.'));
        }

        groups.forEach((group) => {
            const slots = new Map();
            const selectedInGroup = group.items.filter((item) => item.selected);
            const selectedNames = new Set(selectedInGroup.map((item) => normalizeColor(item.color)));
            if (selectedNames.size > 1) duplicateColors.push(group);
            selectedInGroup.filter((item) => item.placement).forEach((item) => {
                if (!slots.has(item.placement)) slots.set(item.placement, []);
                slots.get(item.placement).push(item);
            });
            slots.forEach((mapped, placement) => {
                if (mapped.length > 1) {
                    duplicateSlots.push(`${group.color} / ${placements.find((item) => item.value === placement)?.label || placement}`);
                }
            });

            const matchedRows = findColorRows(group.color);
            if (matchedRows.length > 1) ambiguousRows.push(group.color);
            const existingRow = matchedRows.length === 1 ? matchedRows[0] : null;
            const nameInput = existingRow?.querySelector('.color-name-input');
            const swatchInput = existingRow?.querySelector('.color-hex-input');
            const existingHex = swatchInput?.value;
            const guessedHex = guessColorSwatch(group.color);
            const initialHex = swatches.get(group.key)
                || (existingHex && existingHex !== defaultSwatch ? existingHex : null)
                || guessedHex
                || defaultSwatch;
            swatches.set(group.key, initialHex);

            const card = makeElement('section', 'mockup-zip-review-color');
            const nameLabel = makeElement('label', '', 'Color name');
            const nameControl = document.createElement('input');
            nameControl.type = 'text';
            nameControl.maxLength = 100;
            nameControl.value = group.color;
            nameControl.placeholder = 'Enter color name';
            nameControl.addEventListener('change', () => {
                group.items.forEach((item) => {
                    item.color = nameControl.value;
                    item.ambiguousColor = false;
                    item.replace = false;
                });
                if (nameControl.value.trim()) {
                    const newKey = colorKey(nameControl.value);
                    const guessedHex = guessColorSwatch(nameControl.value) || defaultSwatch;
                    swatches.set(newKey, guessedHex);
                    manualSwatches.delete(newKey);
                    colorInput.value = guessedHex;
                }
                renderReview();
            });
            nameLabel.appendChild(nameControl);
            card.appendChild(nameLabel);

            const swatchLabel = makeElement('label', '', 'Swatch color');
            const colorInput = document.createElement('input');
            colorInput.type = 'color';
            colorInput.value = /^#[0-9a-f]{6}$/i.test(initialHex) ? initialHex : defaultSwatch;
            colorInput.setAttribute('aria-label', `Swatch color for ${group.color}`);
            colorInput.addEventListener('input', () => {
                group.items.forEach((item) => { item.colorHex = colorInput.value; });
                const key = colorKey(nameControl.value);
                swatches.set(key, colorInput.value);
                manualSwatches.add(key);
            });
            swatchLabel.appendChild(colorInput);
            card.appendChild(swatchLabel);

            const message = makeElement('div', '', existingRow ? 'Matches an existing color row.' : 'A new color row will be added to the product form.');
            card.appendChild(message);

            const files = makeElement('div', 'mockup-zip-review-files');
            group.items.forEach((item) => {
                const fileRow = makeElement('div', 'mockup-zip-review-file');
                const image = document.createElement('img');
                image.src = item.previewUrl;
                image.alt = `Preview ${item.name}`;
                image.title = 'Full image preview';
                fileRow.appendChild(image);

                const fileName = makeElement('span', '', item.name);
                fileRow.appendChild(fileName);

                const placementSelect = document.createElement('select');
                placementSelect.className = 'filter-select';
                placementSelect.setAttribute('aria-label', `Placement for ${item.name}`);
                placementSelect.add(new Option('Choose view', ''));
                placements.forEach(({ value, label }) => placementSelect.add(new Option(label, value)));
                placementSelect.value = item.placement;
                placementSelect.addEventListener('change', () => {
                    item.placement = placementSelect.value;
                    item.ambiguousPlacement = false;
                    item.replace = false;
                    renderReview();
                });
                fileRow.appendChild(placementSelect);

                const controls = makeElement('label');
                controls.style.display = 'flex';
                controls.style.alignItems = 'center';
                controls.style.gap = '6px';
                const include = document.createElement('input');
                include.type = 'checkbox';
                include.checked = item.selected;
                include.addEventListener('change', () => {
                    item.selected = include.checked;
                    renderReview();
                });
                controls.appendChild(include);
                controls.appendChild(document.createTextNode('Include this image'));
                fileRow.appendChild(controls);

                if (!item.placement) {
                    fileRow.appendChild(makeElement('span', 'mockup-zip-review-warning', 'Choose a view.'));
                }
                const isOccupied = occupiedTarget(item);
                if (item.selected && isOccupied) {
                    const replaceLabel = makeElement('label', 'mockup-zip-review-warning');
                    replaceLabel.style.gridColumn = '2 / -1';
                    const replace = document.createElement('input');
                    replace.type = 'checkbox';
                    replace.checked = item.replace;
                    replace.addEventListener('change', () => {
                        item.replace = replace.checked;
                        renderReview();
                    });
                    replaceLabel.appendChild(replace);
                    replaceLabel.appendChild(document.createTextNode(' Replace existing image'));
                    fileRow.appendChild(replaceLabel);
                }

                files.appendChild(fileRow);
            });
            card.appendChild(files);

            const mappedSlots = new Set(selectedInGroup.map((item) => item.placement).filter(Boolean));
            const missing = placements.filter(({ value }) => {
                return !mappedSlots.has(value) && !(existingRow && rowHasImage(existingRow, value));
            }).map(({ label }) => label);
            if (selectedInGroup.length && missing.length) {
                card.appendChild(makeElement('div', 'mockup-zip-review-missing', `Missing images: ${missing.join(', ')}`));
            }
            if (selectedInGroup.length && matchedRows.length > 1) {
                card.appendChild(makeElement('div', 'mockup-zip-review-error', 'More than one existing row matches this color. Resolve duplicate color rows before importing.'));
            }
            reviewContent.appendChild(card);
        });

        if (duplicateColors.length) {
            reviewContent.appendChild(makeElement('div', 'mockup-zip-review-error', 'Files in the same color group have different color names mapping to one color key. Use one name for every view.'));
        }
        if (duplicateSlots.length) {
            reviewContent.appendChild(makeElement('div', 'mockup-zip-review-error', `Duplicate image mappings: ${duplicateSlots.join(', ')}. Uncheck or remap duplicates.`));
        }
        if (ambiguousRows.length) {
            reviewContent.appendChild(makeElement('div', 'mockup-zip-review-error', `Duplicate existing form rows: ${[...new Set(ambiguousRows)].join(', ')}.`));
        }
        const invalidItems = items.some((item) => item.selected && (
            !item.color.trim() || !item.placement
        ));
        const willImportAtLeastOne = items.some((item) => {
            return item.selected && item.color.trim() && item.placement && (!occupiedTarget(item) || item.replace);
        });
        if (items.some((item) => item.selected) && !willImportAtLeastOne) {
            reviewContent.appendChild(makeElement('div', 'mockup-zip-review-warning', 'All selected images target occupied slots. Choose replacement for at least one image or uncheck occupied images.'));
        }
        return willImportAtLeastOne
            && !invalidItems
            && duplicateColors.length === 0
            && duplicateSlots.length === 0
            && ambiguousRows.length === 0;
    }

    function renderReview() {
        const canApply = renderSummary();
        const apply = makeElement('button', 'btn-primary', 'Add Images to Product Form');
        apply.type = 'button';
        apply.disabled = !canApply;
        apply.addEventListener('click', applyImportToForm);
        const existingApply = reviewFooter.querySelector('[data-mockup-apply]');
        existingApply?.remove();
        apply.dataset.mockupApply = 'true';
        reviewFooter.prepend(apply);
    }

    function closeReview() {
        review.hidden = true;
    }

    function applyImportToForm() {
        const groups = buildGroups().filter((group) => group.items.some((item) => item.selected));
        let addedImages = 0;
        let skippedImages = 0;

        groups.forEach((group) => {
            let rows = findColorRows(group.color);
            let row = rows.length === 1 ? rows[0] : null;
            if (!row) {
                row = findEmptyColorRow();
                if (!row) {
                    addColorButton.click();
                    row = colorsWrapper.querySelector('.color-row:last-child');
                }
            }
            if (!row) return;

            const nameInput = row.querySelector('.color-name-input');
            const hexInput = row.querySelector('.color-hex-input');
            const groupHex = swatches.get(group.key) || group.items.find((item) => item.colorHex)?.colorHex || defaultSwatch;
            if (nameInput) {
                nameInput.value = group.color;
                nameInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (hexInput) {
                hexInput.value = groupHex;
                hexInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            group.items.filter((item) => item.selected).forEach((item) => {
                const box = row.querySelector(`.color-mockup-box[data-placement="${item.placement}"]`);
                const fileInput = box?.querySelector('.color-mockup-file-input');
                if (!fileInput) return;

                const occupied = rowHasImage(row, item.placement);
                if (occupied && !item.replace) {
                    skippedImages++;
                    return;
                }

                const transfer = new DataTransfer();
                transfer.items.add(item.file);
                fileInput.files = transfer.files;
                fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                addedImages++;
            });
        });

        review.hidden = true;
        setStatus(`${addedImages} image(s) added to the color rows${skippedImages ? `; ${skippedImages} occupied slot(s) left unchanged` : ''}. Review each color name and swatch, then submit the product form to save everything.`, addedImages === 0);
    }

    async function readZip(file) {
        if (!file) {
            setStatus('Choose a ZIP file first.', true);
            return;
        }
        if (file.size > 100 * 1024 * 1024) {
            setStatus('ZIP files must be 100 MB or smaller.', true);
            return;
        }

        importButton.disabled = true;
        review.hidden = true;
        reviewContent.replaceChildren();
        reviewFooter.querySelector('[data-mockup-apply]')?.remove();
        items.splice(0, items.length);
        previewUrls.forEach((url) => URL.revokeObjectURL(url));
        previewUrls = [];
        setStatus('Reading ZIP and preparing image mappings...');

        try {
            const zip = await JSZip.loadAsync(file);
            const entries = Object.values(zip.files).filter((entry) => {
                return !entry.dir
                    && imageExtension(entry.name)
                    && !entry.name.split('/').some((part) => part.startsWith('__MACOSX'));
            });

            if (!entries.length) throw new Error('No PNG, JPEG, WebP, or GIF images were found in this ZIP.');
            if (entries.length > 500) throw new Error('The ZIP contains more than 500 image files. Split it into smaller imports.');

            let totalBytes = 0;
            for (const entry of entries) {
                const declaredSize = entry._data?.uncompressedSize;
                if (Number.isFinite(declaredSize) && declaredSize > 10 * 1024 * 1024) {
                    throw new Error(`${entry.name} is larger than the 10 MB per-image limit.`);
                }
                if (Number.isFinite(declaredSize)) {
                    totalBytes += declaredSize;
                    if (totalBytes > 100 * 1024 * 1024) throw new Error('The extracted images exceed the 100 MB total limit.');
                }

                const blob = await entry.async('blob');
                if (blob.size > 10 * 1024 * 1024) throw new Error(`${entry.name} is larger than the 10 MB per-image limit.`);
                if (!Number.isFinite(declaredSize)) {
                    totalBytes += blob.size;
                    if (totalBytes > 100 * 1024 * 1024) throw new Error('The extracted images exceed the 100 MB total limit.');
                }

                const parsed = parseEntryName(entry.name);
                const mime = blob.type || `image/${entry.name.split('.').pop().toLowerCase().replace('jpg', 'jpeg')}`;
                const previewUrl = URL.createObjectURL(blob);
                previewUrls.push(previewUrl);
                items.push({
                    name: entry.name,
                    color: parsed.color,
                    colorHex: defaultSwatch,
                    placement: parsed.placement,
                    ambiguousColor: parsed.ambiguousColor,
                    ambiguousPlacement: parsed.ambiguousPlacement,
                    selected: true,
                    replace: false,
                    file: new File([blob], entry.name.split('/').pop(), { type: mime }),
                    previewUrl,
                });
            }

            review.hidden = false;
            renderReview();
            setStatus(`${items.length} image(s) ready. Review the color names, swatches, and placements, then add them to the product form.`);
        } catch (error) {
            review.hidden = true;
            setStatus(error instanceof Error ? error.message : 'The ZIP could not be read.', true);
        } finally {
            importButton.disabled = false;
            zipInput.value = '';
        }
    }

    importButton.addEventListener('click', () => zipInput.click());
    zipInput.addEventListener('change', () => readZip(zipInput.files?.[0]));
    closeReviewButton?.addEventListener('click', closeReview);
    cancelReviewButton?.addEventListener('click', closeReview);
    review.addEventListener('click', (event) => {
        if (event.target === review) closeReview();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !review.hidden) closeReview();
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initializeInlineMockupImport);
}
