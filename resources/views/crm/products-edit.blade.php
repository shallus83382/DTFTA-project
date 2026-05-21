@extends('layouts.app')

@section('title', 'DTFTA CRM - Edit Product')
@section('page-title', 'Edit Product')

@push('styles')
<style>
    .product-edit-page {
        display: grid;
        gap: 14px;
    }

    .product-edit-toolbar {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: stretch;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.85));
    }

    .product-edit-toolbar form {
        margin: 0;
        display: flex;
    }

    .product-edit-toolbar .btn-secondary,
    .product-edit-toolbar .btn-danger {
        height: 48px;
        min-height: 48px;
        min-width: 170px;
        padding: 0 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        line-height: 1;
        white-space: nowrap;
    }

    .product-edit-card {
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: linear-gradient(160deg, rgba(30, 41, 59, 0.94), rgba(15, 23, 42, 0.94));
        box-shadow: 0 14px 30px rgba(2, 6, 23, 0.26);
    }

    .product-edit-card h3 {
        margin-bottom: 14px !important;
        color: #f8fafc;
        font-size: 22px;
        letter-spacing: -0.01em;
    }

    .product-edit-card h4 {
        color: #e2e8f0;
        font-size: 17px;
        margin-bottom: 10px !important;
    }

    .product-edit-card p {
        color: #9fb1cf !important;
    }

    .product-edit-card .filters-section {
        margin-bottom: 0 !important;
        padding: 12px;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.62), rgba(15, 23, 42, 0.38));
        box-shadow: none;
        gap: 10px;
    }

    .product-edit-card .filter-group {
        margin-bottom: 0;
    }

    .product-edit-card .filter-group label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #a9bddb;
        font-weight: 700;
    }

    .product-edit-card .filter-select {
        border-radius: 10px;
        border-color: rgba(148, 163, 184, 0.28);
        background-color: rgba(15, 23, 42, 0.78);
        color: #f8fafc;
        font-size: 13px;
    }

    .product-edit-card .btn-primary,
    .product-edit-card .btn-secondary,
    .product-edit-card .btn-danger {
        border-radius: 10px;
        font-weight: 700;
    }

    .product-edit-card .dynamic-row {
        align-items: center;
    }

    .product-edit-card .dynamic-row .filter-select {
        height: 38px;
    }

    .product-edit-card #variant-preview {
        border: 1px solid rgba(148, 163, 184, 0.2) !important;
        border-radius: 10px !important;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.62), rgba(15, 23, 42, 0.38)) !important;
        color: #dbeafe;
    }

    .product-edit-card #variant-preview span {
        color: #9fb1cf !important;
    }

    .product-edit-card .variant-chip-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .product-edit-card .variant-chip {
        padding: 6px 11px;
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.28);
        background: linear-gradient(135deg, rgba(51, 65, 85, 0.75), rgba(30, 41, 59, 0.72));
        color: #e2e8f0 !important;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        display: inline-flex;
        align-items: center;
    }

    .product-edit-card .current-variant-chip {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(30, 64, 175, 0.16));
        border-color: rgba(96, 165, 250, 0.38);
        color: #dbeafe !important;
    }

    .product-edit-card .print-area-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 14px;
    }

    .product-edit-card .print-area-section-title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #a9bddb;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .product-edit-card .print-area-option {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px;
        border: 1px solid rgba(96, 165, 250, 0.24);
        border-radius: 12px;
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.74), rgba(15, 23, 42, 0.68));
        color: #dbeafe;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04), 0 10px 22px rgba(2, 6, 23, 0.24);
        transition: border-color 0.2s ease, transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        min-height: 112px;
        overflow: hidden;
    }

    .product-edit-card .print-area-option::before {
        content: "";
        position: absolute;
        inset: 0 auto auto 0;
        height: 2px;
        width: 100%;
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.68), rgba(56, 189, 248, 0.2));
        opacity: 0.7;
        pointer-events: none;
    }

    .product-edit-card .print-area-option:hover {
        border-color: rgba(96, 165, 250, 0.45);
        transform: translateY(-2px);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(15, 23, 42, 0.65));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06), 0 16px 28px rgba(2, 6, 23, 0.3);
    }

    .product-edit-card .print-area-option.is-selected {
        border-color: rgba(96, 165, 250, 0.72);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(15, 23, 42, 0.72));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07), 0 14px 28px rgba(30, 64, 175, 0.25);
    }

    .product-edit-card .print-area-option strong {
        color: #f8fafc;
        font-size: 15px;
        line-height: 1.2;
        font-weight: 700;
    }

    .product-edit-card .print-area-option small {
        color: #b8cae5 !important;
        font-size: 12px;
        line-height: 1.35;
        letter-spacing: 0.01em;
    }

    .product-edit-card .print-area-content {
        display: grid;
        gap: 6px;
        min-width: 0;
    }

    .product-edit-card .print-area-meta {
        display: grid;
        grid-template-columns: 1fr;
        gap: 3px;
    }

    .product-edit-card .print-area-meta-line {
        color: #bfd0e8 !important;
        font-size: 12px;
        line-height: 1.35;
        letter-spacing: 0.01em;
        text-transform: none;
    }

    .product-edit-card .print-area-option input[type="checkbox"] {
        accent-color: #3b82f6;
        width: 16px;
        height: 16px;
        margin-top: 2px;
    }
</style>
@endpush

@section('content')
    <div class="product-edit-page">
    @if($errors->any())
        <div class="chart-card" style="margin-bottom: 16px; border: 1px solid #991b1b;">
            <strong>Validation failed:</strong>
            <ul style="margin: 8px 0 0 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $existingImages = is_array($product->images) ? $product->images : [];
        $oldColors = old('colors', $selectedColors ?? $product->variants->pluck('color')->unique()->values()->all());
        $oldSizes = old('sizes', $selectedSizes ?? $product->variants->pluck('size')->unique()->values()->all());
        $oldPrintAreaIds = old('print_area_ids', $product->printAreas->pluck('id')->all());
        $predefinedColors = collect(config('dtfta.common_colors', []))
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    @endphp

    <div class="product-edit-toolbar" style="margin-bottom: 16px;">
        <a href="{{ route('crm.products.view', $product->id) }}" class="btn-secondary">View Product</a>

        <form method="POST" action="{{ route('crm.products.destroy', $product->id) }}" onsubmit="return confirm('Delete this product?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">Delete Product</button>
        </form>
    </div>

    <div class="chart-card product-edit-card">
        <h3 style="margin-bottom: 14px;">Edit Product #{{ $product->id }}</h3>

        <form method="POST" action="{{ route('crm.products.update', $product->id) }}" enctype="multipart/form-data">
            @csrf

            <div class="filters-section">
                <div class="filter-group">
                    <label for="title">Title</label>
                    <input
                        id="title"
                        name="title"
                        type="text"
                        class="filter-select"
                        value="{{ old('title', $product->title) }}"
                        required
                    >
                </div>

                <div class="filter-group">
                    <label for="brand">Brand</label>
                    <input
                        id="brand"
                        name="brand"
                        type="text"
                        class="filter-select"
                        value="{{ old('brand', $product->brand) }}"
                    >
                </div>

                <div class="filter-group">
                    <label for="model_code">Model Code</label>
                    <input
                        id="model_code"
                        name="model_code"
                        type="text"
                        class="filter-select"
                        value="{{ old('model_code', $product->model_code) }}"
                        placeholder="e.g. 6210"
                    >
                </div>

                <div class="filter-group">
                    <label for="category">Category</label>
                    <input
                        id="category"
                        name="category"
                        type="text"
                        class="filter-select"
                        value="{{ old('category', $product->category) }}"
                        placeholder="e.g. T-Shirt"
                    >
                </div>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="filter-select" required>
                        @foreach($productStatusOptions as $statusOption)
                            <option value="{{ $statusOption }}" {{ old('status', $product->status) === $statusOption ? 'selected' : '' }}>
                                {{ ucfirst($statusOption) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label for="price">Base Price</label>
                    <input
                        id="price"
                        name="price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="filter-select"
                        value="{{ old('price', $product->price ?? '0.00') }}"
                        required
                    >
                </div>

                <div class="filter-group" style="width: 100%;">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        class="filter-select"
                        rows="4"
                    >{{ old('description', $product->description) }}</textarea>
                </div>

                <div class="filter-group" style="width: 100%;">
                <x-asset-selector
                        :selected-category="old('images[category]', $product->images['category'] ?? null)"
                        :selected-asset-key="old('images[asset_key]', $product->images['asset_key'] ?? null)"
                        category-input-name="images[category]"
                        asset-input-name="images[asset_key]"
                        />   
                </div>
            </div>

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">

            <h4 style="margin-bottom: 12px;">Variant Options</h4>
            <p style="margin-bottom: 14px; color: #6b7280;">
                Update available colors and sizes. Variants and SKUs are generated automatically in the backend.
            </p>

            <div class="filters-section">
                <div class="filter-group" style="width: 100%;">
                    <label>Colors</label>
                    <!-- <div style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                        <select id="predefined-color-select" class="filter-select" style="max-width: 320px;">
                            <option value="">Select predefined color</option>
                        </select>
                        <button type="button" class="btn-secondary" id="add-predefined-color-btn">Add Selected Color</button>
                    </div> -->
                    <div id="colors-wrapper">
                        @foreach(($oldColors ?: ['']) as $color)
                            <div class="dynamic-row color-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <select name="colors[]" class="filter-select" required>
                                    <option value="">Select color</option>
                                    @foreach($predefinedColors as $predefinedColor)
                                        <option value="{{ $predefinedColor }}" {{ $color === $predefinedColor ? 'selected' : '' }}>
                                            {{ $predefinedColor }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn-danger remove-row-btn">Remove</button>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="btn-secondary" id="add-color-btn" style="margin-top: 8px;">
                        Add Color
                    </button>
                </div>

                <div class="filter-group" style="width: 100%;">
                    <label>Sizes</label>
                    <div id="sizes-wrapper">
                        @foreach(($oldSizes ?: ['']) as $size)
                            <div class="dynamic-row size-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <input
                                    type="text"
                                    name="sizes[]"
                                    class="filter-select"
                                    value="{{ $size }}"
                                    placeholder="e.g. XL"
                                    required
                                >
                                <button type="button" class="btn-danger remove-row-btn">Remove</button>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="btn-secondary" id="add-size-btn" style="margin-top: 8px;">
                        Add Size
                    </button>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <h4 style="margin-bottom: 8px;">Variant Preview</h4>
                <p style="margin-bottom: 12px; color: #6b7280;">
                    This preview is based on selected colors and sizes. Actual variants will be updated automatically after save.
                </p>

                <div id="variant-preview" style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; min-height: 60px; background: #fafafa;">
                    <span style="color:#6b7280;">No variants yet.</span>
                </div>
            </div>

            <div style="margin-top: 16px;">
                <h4 style="margin-bottom: 8px;">Variant Prices</h4>
                <p style="margin-bottom: 12px; color: #6b7280;">
                    Set price per existing variant. New color/size combinations use Base Price by default.
                </p>
                @if($product->variants->count())
                    <div class="filters-section">
                        @foreach($product->variants as $variant)
                            <div class="dynamic-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
                                <div class="filter-select" style="display:flex; align-items:center; min-height:38px; opacity:0.9;">
                                    {{ $variant->color }} / {{ $variant->size }} ({{ $variant->sku }})
                                </div>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="variant_prices[{{ $variant->id }}]"
                                    class="filter-select"
                                    value="{{ old('variant_prices.' . $variant->id, $variant->price ?? $product->price ?? '0.00') }}"
                                    placeholder="Variant price"
                                >
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="color:#6b7280;">No variants found.</div>
                @endif
            </div>

            <div style="margin-top: 16px;">
                <h4 style="margin-bottom: 8px;">Current Variants</h4>
                @if($product->variants->count())
                    <div class="variant-chip-wrap" style="display: flex; flex-wrap: wrap; gap: 8px;">
                        @foreach($product->variants as $variant)
                            <span class="variant-chip current-variant-chip">
                                {{ $variant->color }} / {{ $variant->size }} / {{ $variant->sku }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <div style="color:#6b7280;">No variants found.</div>
                @endif
            </div>

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">

            <h4 style="margin-bottom: 12px;">Print Areas</h4>
            <p style="margin-bottom: 14px; color: #6b7280;">
                Select one or more print areas for this product.
            </p>

            <div class="filters-section">
                <div class="filter-group" style="width: 100%;">
                    <label class="print-area-section-title">Available Print Areas</label>

                    @if($printAreas->count())
                        <div class="print-area-grid">
                            @foreach($printAreas as $printArea)
                                @php
                                    $isSelected = in_array($printArea->id, $oldPrintAreaIds);
                                @endphp
                                <label class="print-area-option {{ $isSelected ? 'is-selected' : '' }}">
                                    <input
                                        type="checkbox"
                                        name="print_area_ids[]"
                                        value="{{ $printArea->id }}"
                                        {{ $isSelected ? 'checked' : '' }}
                                    >

                                    <span class="print-area-content">
                                        <strong>{{ $printArea->title ?: 'Untitled Area' }}</strong>

                                        <span class="print-area-meta">
                                            <small class="print-area-meta-line">
                                                Size: {{ $printArea->area_width ?? '-' }} x {{ $printArea->area_height ?? '-' }} {{ $printArea->unit ?? '' }}
                                            </small>

                                            <small class="print-area-meta-line">
                                                Position: X {{ $printArea->position_x ?? '-' }}, Y {{ $printArea->position_y ?? '-' }}
                                            </small>

                                            @if($printArea->price !== null)
                                                <small class="print-area-meta-line">
                                                    Price: ${{ number_format((float) $printArea->price, 2) }}
                                                </small>
                                            @endif
                                        </span>

                                        @if($printArea->tshirt_size)
                                            <small class="print-area-meta-line">
                                                Garment Size: {{ $printArea->tshirt_size }}
                                            </small>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div style="padding: 14px; border: 1px dashed rgba(148, 163, 184, 0.45); border-radius: 10px; color: #9fb1cf;">
                            No print areas available.
                        </div>
                    @endif
                </div>
            </div>

            <div style="margin-top: 12px; display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">Save Changes</button>
                <a href="{{ route('crm.products') }}" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const colorsWrapper = document.getElementById('colors-wrapper');
            const sizesWrapper = document.getElementById('sizes-wrapper');
            const addColorBtn = document.getElementById('add-color-btn');
            const addPredefinedColorBtn = document.getElementById('add-predefined-color-btn');
            const predefinedColorSelect = document.getElementById('predefined-color-select');
            const addSizeBtn = document.getElementById('add-size-btn');
            const variantPreview = document.getElementById('variant-preview');
            const predefinedColors = @json($predefinedColors);

            function makeRow(name, placeholder, rowClass) {
                const row = document.createElement('div');
                row.className = `dynamic-row ${rowClass}`;
                row.style.display = 'flex';
                row.style.gap = '10px';
                row.style.marginBottom = '10px';

                if (name === 'colors') {
                    const optionsHtml = predefinedColors.map(color => `<option value="${color}">${color}</option>`).join('');
                    row.innerHTML = `
                        <select name="colors[]" class="filter-select" required>
                            <option value="">Select color</option>
                            ${optionsHtml}
                        </select>
                        <button type="button" class="btn-danger remove-row-btn">Remove</button>
                    `;
                } else {
                    row.innerHTML = `
                        <input
                            type="text"
                            name="${name}[]"
                            class="filter-select"
                            placeholder="${placeholder}"
                            required
                        >
                        <button type="button" class="btn-danger remove-row-btn">Remove</button>
                    `;
                }

                return row;
            }

            function bindRemoveButtons() {
                document.querySelectorAll('.remove-row-btn').forEach(function (button) {
                    button.onclick = function () {
                        const row = this.closest('.dynamic-row');
                        const parent = row.parentElement;

                        if (parent.querySelectorAll('.dynamic-row').length > 1) {
                            row.remove();
                        } else {
                            const input = row.querySelector('input, select');
                            if (input) input.value = '';
                        }

                        updateVariantPreview();
                    };
                });
            }

            function getUniqueValues(selector) {
                return Array.from(document.querySelectorAll(selector))
                    .map(input => input.value.trim())
                    .filter(value => value !== '')
                    .filter((value, index, arr) => arr.indexOf(value) === index);
            }

            function updateVariantPreview() {
                const colors = getUniqueValues('select[name="colors[]"]');
                const sizes = getUniqueValues('input[name="sizes[]"]');

                if (!colors.length || !sizes.length) {
                    variantPreview.innerHTML = '<span style="color:#6b7280;">No variants yet.</span>';
                    return;
                }

                const variants = [];
                colors.forEach(color => {
                    sizes.forEach(size => {
                        variants.push(`${color} / ${size}`);
                    });
                });

                variantPreview.innerHTML = `
                    <div style="margin-bottom: 8px;"><strong>Total Variants:</strong> ${variants.length}</div>
                    <div class="variant-chip-wrap" style="display: flex; flex-wrap: wrap; gap: 8px;">
                        ${variants.map(variant => `
                            <span class="variant-chip">
                                ${variant}
                            </span>
                        `).join('')}
                    </div>
                `;
            }

            function refreshPredefinedColorOptions() {
                if (!predefinedColorSelect) return;

                const selectedColors = new Set(
                    getUniqueValues('select[name="colors[]"]').map(color => color.toLowerCase())
                );

                predefinedColorSelect.innerHTML = '<option value="">Select predefined color</option>';

                predefinedColors.forEach(function (color) {
                    if (!selectedColors.has(String(color).toLowerCase())) {
                        const option = document.createElement('option');
                        option.value = color;
                        option.textContent = color;
                        predefinedColorSelect.appendChild(option);
                    }
                });
            }

            function refreshColorRowOptions() {
                const colorSelects = Array.from(colorsWrapper.querySelectorAll('select[name="colors[]"]'));
                const selectedCounts = {};

                colorSelects.forEach(function (select) {
                    const value = String(select.value || '').trim().toLowerCase();
                    if (!value) return;
                    selectedCounts[value] = (selectedCounts[value] || 0) + 1;
                });

                colorSelects.forEach(function (select) {
                    const currentValue = String(select.value || '').trim();
                    const currentLower = currentValue.toLowerCase();

                    const availableOptions = predefinedColors.filter(function (color) {
                        const lowerColor = String(color).toLowerCase();
                        if (lowerColor === currentLower) return true;
                        return !selectedCounts[lowerColor];
                    });

                    const optionsHtml = ['<option value="">Select color</option>']
                        .concat(
                            availableOptions.map(function (color) {
                                const selected = color === currentValue ? ' selected' : '';
                                return `<option value="${color}"${selected}>${color}</option>`;
                            })
                        )
                        .join('');

                    select.innerHTML = optionsHtml;
                    if (currentValue) {
                        select.value = currentValue;
                    }
                });
            }

            addColorBtn.addEventListener('click', function () {
                colorsWrapper.appendChild(makeRow('colors', 'Select color', 'color-row'));
                bindRemoveButtons();
                refreshColorRowOptions();
                refreshPredefinedColorOptions();
            });

            addSizeBtn.addEventListener('click', function () {
                sizesWrapper.appendChild(makeRow('sizes', 'e.g. XL', 'size-row'));
                bindRemoveButtons();
            });

            if (addPredefinedColorBtn && predefinedColorSelect) {
                addPredefinedColorBtn.addEventListener('click', function () {
                    const selectedColor = predefinedColorSelect.value.trim();
                    if (!selectedColor) return;

                    const colorRows = Array.from(colorsWrapper.querySelectorAll('select[name="colors[]"]'));
                    const emptyInput = colorRows.find(input => !input.value.trim());

                    if (emptyInput) {
                        emptyInput.value = selectedColor;
                    } else {
                        const row = makeRow('colors', 'Select color', 'color-row');
                        row.querySelector('select[name="colors[]"]').value = selectedColor;
                        colorsWrapper.appendChild(row);
                        bindRemoveButtons();
                    }

                    predefinedColorSelect.value = '';
                    updateVariantPreview();
                    refreshColorRowOptions();
                    refreshPredefinedColorOptions();
                });
            }

            document.addEventListener('input', function (event) {
                if (
                    event.target.matches('select[name="colors[]"]') ||
                    event.target.matches('input[name="sizes[]"]')
                ) {
                    updateVariantPreview();
                    refreshColorRowOptions();
                    refreshPredefinedColorOptions();
                }
            });

            bindRemoveButtons();
            updateVariantPreview();
            refreshColorRowOptions();
            refreshPredefinedColorOptions();
        });
    </script>
    @endpush
@endsection