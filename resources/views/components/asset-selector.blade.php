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
document.addEventListener('DOMContentLoaded', function () {
    const wrapper = document.getElementById(@json($componentId));
    const categorySelect = document.getElementById(@json($componentId . '-category'));
    const assetSection = document.getElementById(@json($componentId . '-asset-section'));
    const assetList = document.getElementById(@json($componentId . '-asset-list'));
    const assetKeyInput = document.getElementById(@json($componentId . '-asset-key'));
    const selectedCategory = @json($selectedCategory);
    const selectedAssetKey = @json($selectedAssetKey);

    function renderAssets(assets) {
        assetList.innerHTML = '';
        assetSection.style.display = 'block';

        Object.keys(assets).forEach(function (key) {
            const asset = assets[key];

            const card = document.createElement('div');
            card.classList.add('asset-card');
            card.dataset.key = key;

            if (selectedAssetKey && selectedAssetKey === key && categorySelect.value === selectedCategory) {
                card.classList.add('active');
                assetKeyInput.value = key;
            }

            // card.innerHTML = `
            //     <img src="${asset.thumbnail_url}" alt="${asset.name}" class="asset-card-image">
            //     <div class="asset-card-name">${asset.name}</div>
            //     <div class="asset-card-key">${key}</div>
            // `;

             card.innerHTML = `
                <img src="${asset.thumbnail_url}" alt="${asset.name}" class="asset-card-image">
                <div class="asset-card-name">${asset.name}</div>
            `;
            card.addEventListener('click', function () {
                document.querySelectorAll('#{{ $componentId }}-asset-list > div').forEach(function (el) {
                    el.classList.remove('active');
                });

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

        fetch(`/asset-categories/${category}/assets`)
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

    if (selectedCategory) {
        loadAssets(selectedCategory);
    }
});
</script>