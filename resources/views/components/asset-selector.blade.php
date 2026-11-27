@php
    $componentId = 'asset-selector-' . uniqid();
@endphp

<div id="{{ $componentId }}" class="asset-selector-wrapper">
    <div style="margin-bottom: 20px;">
        <label id="{{ $componentId }}-category-label" class="asset-selector-label"><strong>Select Category</strong></label><br>
        <select
            id="{{ $componentId }}-category"
            name="{{ $categoryInputName }}"
            class="asset-selector-category-native"
            tabindex="-1"
            aria-hidden="true"
        >
            <option value="">Select Category</option>
            @foreach($categories as $key => $label)
                <option value="{{ $key }}" {{ $selectedCategory === $key ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <div class="asset-category-picker">
            <button
                type="button"
                id="{{ $componentId }}-category-trigger"
                class="filter-select asset-selector-category-trigger"
                aria-haspopup="listbox"
                aria-expanded="false"
                aria-controls="{{ $componentId }}-category-menu"
                aria-labelledby="{{ $componentId }}-category-label {{ $componentId }}-category-value"
            >
                <span id="{{ $componentId }}-category-value">{{ $selectedCategory && isset($categories[$selectedCategory]) ? $categories[$selectedCategory] : 'Select Category' }}</span>
                <span class="asset-category-chevron" aria-hidden="true"></span>
            </button>
            <div
                id="{{ $componentId }}-category-menu"
                class="asset-selector-category-menu"
                role="listbox"
                aria-labelledby="{{ $componentId }}-category-label"
                hidden
            >
                @foreach($categories as $key => $label)
                    <button
                        type="button"
                        class="asset-selector-category-option"
                        role="option"
                        data-value="{{ $key }}"
                        aria-selected="{{ $selectedCategory === $key ? 'true' : 'false' }}"
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <input type="hidden" name="{{ $assetInputName }}" id="{{ $componentId }}-asset-key" value="{{ $selectedAssetKey }}">

    <div id="{{ $componentId }}-asset-section" style="display: none; margin-top: 20px;">
        <label class="asset-selector-label"><strong>Select Asset</strong></label>
        <div
            id="{{ $componentId }}-asset-list"
            class="asset-selector-list"
        ></div>
    </div>
</div>

<script>
(function() {
    const componentId = @json($componentId);
    const selectedCategory = @json($selectedCategory);
    const selectedAssetKey = @json($selectedAssetKey);
    const baseUrl = @json(url('asset-categories'));

    function initAssetSelector() {
        const wrapper = document.getElementById(componentId);
        if (!wrapper) return;

        const categorySelect = document.getElementById(componentId + '-category');
        const categoryTrigger = document.getElementById(componentId + '-category-trigger');
        const categoryValue = document.getElementById(componentId + '-category-value');
        const categoryMenu = document.getElementById(componentId + '-category-menu');
        const categoryOptions = Array.from(categoryMenu.querySelectorAll('.asset-selector-category-option'));
        const assetSection = document.getElementById(componentId + '-asset-section');
        const assetList = document.getElementById(componentId + '-asset-list');
        const assetKeyInput = document.getElementById(componentId + '-asset-key');

        function closeCategoryMenu(returnFocus) {
            categoryMenu.hidden = true;
            categoryTrigger.setAttribute('aria-expanded', 'false');
            if (returnFocus) categoryTrigger.focus();
        }

        function openCategoryMenu() {
            categoryMenu.hidden = false;
            categoryTrigger.setAttribute('aria-expanded', 'true');
            const selectedOption = categoryOptions.find(function(option) {
                return option.dataset.value === categorySelect.value;
            });
            (selectedOption || categoryOptions[0])?.focus();
        }

        function syncCategoryPicker() {
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];
            categoryValue.textContent = selectedOption ? selectedOption.textContent.trim() : 'Select Category';
            categoryOptions.forEach(function(option) {
                option.setAttribute('aria-selected', option.dataset.value === categorySelect.value ? 'true' : 'false');
            });
        }

        categoryTrigger.addEventListener('click', function() {
            if (categoryMenu.hidden) {
                openCategoryMenu();
            } else {
                closeCategoryMenu(false);
            }
        });

        categoryTrigger.addEventListener('keydown', function(event) {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                if (categoryMenu.hidden) openCategoryMenu();
            }
        });

        categoryOptions.forEach(function(option, index) {
            option.addEventListener('click', function() {
                categorySelect.value = option.dataset.value;
                categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
                closeCategoryMenu(true);
            });

            option.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeCategoryMenu(true);
                } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const direction = event.key === 'ArrowDown' ? 1 : -1;
                    const nextIndex = (index + direction + categoryOptions.length) % categoryOptions.length;
                    categoryOptions[nextIndex].focus();
                } else if (event.key === 'Home' || event.key === 'End') {
                    event.preventDefault();
                    categoryOptions[event.key === 'Home' ? 0 : categoryOptions.length - 1].focus();
                }
            });
        });

        document.addEventListener('click', function(event) {
            if (!wrapper.contains(event.target)) closeCategoryMenu(false);
        });

        function renderAssets(assets) {
            assetList.innerHTML = '';
            assetSection.style.display = 'block';

            Object.keys(assets || {}).forEach(function (key) {
                const asset = assets[key];
                const card = document.createElement('div');
                card.classList.add('asset-card');
                card.dataset.key = key;

                if (assetKeyInput.value === key || (selectedAssetKey && selectedAssetKey === key && categorySelect.value === selectedCategory)) {
                    card.classList.add('active');
                    assetKeyInput.value = key;
                }

                const image = document.createElement('img');
                image.src = asset.thumbnail_url;
                image.alt = asset.name;
                image.className = 'asset-card-image';

                const name = document.createElement('div');
                name.className = 'asset-card-name';
                name.textContent = asset.name;

                card.appendChild(image);
                card.appendChild(name);

                card.addEventListener('click', function () {
                    const allCards = assetList.querySelectorAll('.asset-card');
                    allCards.forEach(function (el) { el.classList.remove('active'); });

                    card.classList.add('active');
                    assetKeyInput.value = key;
                });

                assetList.appendChild(card);
            });
        }

        function loadAssets(category) {
            assetList.innerHTML = '';
            assetSection.style.display = 'none';
            assetKeyInput.value = '';

            if (!category) {
                return;
            }

            fetch(`${baseUrl}/${encodeURIComponent(category)}/assets`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        return;
                    }
                    renderAssets(data.assets);
                })
                .catch(error => {
                    console.error('Error loading assets:', error);
                });
        }

        categorySelect.addEventListener('change', function () {
            syncCategoryPicker();
            loadAssets(this.value);
        });

        syncCategoryPicker();
        if (categorySelect.value) {
            loadAssets(categorySelect.value);
        } else if (selectedCategory) {
            categorySelect.value = selectedCategory;
            loadAssets(selectedCategory);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAssetSelector);
    } else {
        initAssetSelector();
    }
})();
</script>