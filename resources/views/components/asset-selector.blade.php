@php
    $componentId = 'asset-selector-' . uniqid();
@endphp

<div id="{{ $componentId }}" class="asset-selector-wrapper">
    <div style="margin-bottom: 20px;">
        <label for="{{ $componentId }}-category" class="asset-selector-label"><strong>Select Category</strong></label><br>
        <select
            id="{{ $componentId }}-category"
            name="{{ $categoryInputName }}"
            class="filter-select asset-selector-category"
        >
            <option value="">Select Category</option>
            @foreach($categories as $key => $label)
                <option value="{{ $key }}" {{ $selectedCategory === $key ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
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
        const assetSection = document.getElementById(componentId + '-asset-section');
        const assetList = document.getElementById(componentId + '-asset-list');
        const assetKeyInput = document.getElementById(componentId + '-asset-key');

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

                card.innerHTML = `
                    <img src="${asset.thumbnail_url}" alt="${asset.name}" class="asset-card-image">
                    <div class="asset-card-name">${asset.name}</div>
                `;

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
            loadAssets(this.value);
        });

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