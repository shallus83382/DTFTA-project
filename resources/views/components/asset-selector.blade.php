@php
    $componentId = 'asset-selector-' . uniqid();
@endphp

<div id="{{ $componentId }}" class="asset-selector-wrapper">
    <div style="margin-bottom: 20px;">
        <label for="{{ $componentId }}-category"><strong>Select Category</strong></label><br>
        <select
            id="{{ $componentId }}-category"
            name="{{ $categoryInputName }}"
            style="width: 300px; padding: 8px;"
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
        <label><strong>Select Asset</strong></label>
        <div
            id="{{ $componentId }}-asset-list"
            style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px;"
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
            card.style.width = '150px';
            card.style.border = '1px solid #ddd';
            card.style.padding = '10px';
            card.style.cursor = 'pointer';
            card.style.textAlign = 'center';
            card.style.borderRadius = '6px';
            card.style.backgroundColor = '#fff';
            card.dataset.key = key;

            if (selectedAssetKey && selectedAssetKey === key && categorySelect.value === selectedCategory) {
                card.style.border = '2px solid green';
                card.style.backgroundColor = '#f5f5f5';
                assetKeyInput.value = key;
            }

            card.innerHTML = `
                <img src="${asset.thumbnail_url}" alt="${asset.name}" style="width: 100px; height: 100px; object-fit: contain;">
                <div style="margin-top: 10px; font-size: 14px; color: #666;">${asset.name}</div>
                <div style="margin-top: 5px; font-size: 12px; ">${key}</div>
            `;

            card.addEventListener('click', function () {
                document.querySelectorAll('#{{ $componentId }}-asset-list > div').forEach(function (el) {
                    el.style.border = '1px solid #ddd';
                    el.style.backgroundColor = '#fff';
                });

                card.style.border = '2px solid green';
                card.style.backgroundColor = '#f5f5f5';
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