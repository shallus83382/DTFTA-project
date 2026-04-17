@extends('layouts.app')

@section('title', 'DTFTA CRM - Add Product')
@section('page-title', 'Add Product')

@push('styles')
<style>
    .product-add-page {
        display: grid;
        gap: 14px;
    }

    .product-add-card {
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: linear-gradient(160deg, rgba(30, 41, 59, 0.94), rgba(15, 23, 42, 0.94));
        box-shadow: 0 14px 30px rgba(2, 6, 23, 0.26);
        position: relative;
        overflow: hidden;
    }

    .product-add-card::before {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        height: 1px;
        background: linear-gradient(90deg, rgba(56, 189, 248, 0.28), rgba(45, 212, 191, 0.15), rgba(56, 189, 248, 0.28));
        pointer-events: none;
    }

    .product-add-card h3 {
        margin-bottom: 14px !important;
        color: #f8fafc;
        font-size: 24px;
        line-height: 1.1;
        letter-spacing: -0.01em;
    }

    .product-add-card h4 {
        margin-bottom: 10px !important;
        color: #e2e8f0;
        font-size: 17px;
    }

    .product-add-card p {
        color: #9fb1cf !important;
    }

    .product-add-card .filters-section {
        margin-bottom: 0 !important;
        padding: 12px;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.62), rgba(15, 23, 42, 0.38));
        box-shadow: none;
        gap: 10px;
    }

    .product-add-card .filter-group {
        margin-bottom: 0;
    }

    .product-add-card .filter-group label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #a9bddb;
        font-weight: 700;
    }

    .product-add-card .filter-select {
        border-radius: 10px;
        border-color: rgba(148, 163, 184, 0.28);
        background-color: rgba(15, 23, 42, 0.78);
        color: #f8fafc;
        font-size: 13px;
    }

    .product-add-card textarea.filter-select {
        min-height: 92px;
    }

    .product-add-card input.filter-select::placeholder,
    .product-add-card textarea.filter-select::placeholder {
        color: #7f93b2;
    }

    .product-add-card .dynamic-row {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        align-items: center;
    }

    .product-add-card .dynamic-row .filter-select {
        height: 38px;
    }

    .product-add-card .btn-primary,
    .product-add-card .btn-secondary,
    .product-add-card .btn-danger {
        border-radius: 10px;
        font-weight: 700;
    }

    .product-add-card #variant-preview {
        padding: 12px !important;
        border: 1px solid rgba(148, 163, 184, 0.2) !important;
        border-radius: 10px !important;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.62), rgba(15, 23, 42, 0.38)) !important;
        color: #dbeafe;
    }

    .product-add-card .variant-chip-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .product-add-card .variant-chip {
        padding: 6px 11px;
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.28);
        background: linear-gradient(135deg, rgba(51, 65, 85, 0.75), rgba(30, 41, 59, 0.72));
        color: #e2e8f0;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        display: inline-flex;
        align-items: center;
    }

    .product-add-card .print-area-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 14px;
    }

    .product-add-card .print-area-section-title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #a9bddb;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .product-add-card .print-area-option {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        border-color: rgba(96, 165, 250, 0.24) !important;
        border-radius: 12px;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04), 0 10px 22px rgba(2, 6, 23, 0.24);
        transition: border-color 0.2s ease, transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        padding: 14px 14px !important;
        min-height: 112px;
        overflow: hidden;
    }

    .product-add-card .print-area-option::before {
        content: "";
        position: absolute;
        inset: 0 auto auto 0;
        height: 2px;
        width: 100%;
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.68), rgba(56, 189, 248, 0.2));
        opacity: 0.7;
        pointer-events: none;
    }

    .product-add-card .print-area-option:hover {
        border-color: rgba(96, 165, 250, 0.45) !important;
        transform: translateY(-2px);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(15, 23, 42, 0.65));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06), 0 16px 28px rgba(2, 6, 23, 0.3);
    }

    .product-add-card .print-area-option.is-selected {
        border-color: rgba(96, 165, 250, 0.72) !important;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(15, 23, 42, 0.72));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07), 0 14px 28px rgba(30, 64, 175, 0.25);
    }

    .product-add-card .print-area-option strong {
        color: #f8fafc;
        font-size: 15px;
        line-height: 1.2;
        font-weight: 700;
    }

    .product-add-card .print-area-content {
        display: grid;
        gap: 6px;
        min-width: 0;
    }

    .product-add-card .print-area-meta {
        display: grid;
        grid-template-columns: 1fr;
        gap: 3px;
    }

    .product-add-card .print-area-meta-line {
        font-size: 12px;
        color: #bfd0e8 !important;
        line-height: 1.35;
        letter-spacing: 0.01em;
        text-transform: none;
    }

    .product-add-card .print-area-option input[type="checkbox"] {
        accent-color: #3b82f6;
        width: 16px;
        height: 16px;
        margin-top: 2px !important;
    }
</style>
@endpush

@section('content')
    <div class="product-add-page">
    @if ($errors->any())
        <div class="chart-card" style="margin-bottom: 16px; border: 1px solid #991b1b;">
            <strong>Validation failed:</strong>
            <ul style="margin: 8px 0 0 18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="chart-card product-add-card">
        <h3 style="margin-bottom: 14px;">Create New Product</h3>

        <form method="POST" action="{{ route('crm.products.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="filters-section">
                <div class="filter-group">
                    <label for="title">Title</label>
                    <input
                        id="title"
                        name="title"
                        type="text"
                        class="filter-select"
                        value="{{ old('title') }}"
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
                        value="{{ old('brand') }}"
                    >
                </div>

                <div class="filter-group">
                    <label for="model_code">Model Code</label>
                    <input
                        id="model_code"
                        name="model_code"
                        type="text"
                        class="filter-select"
                        value="{{ old('model_code') }}"
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
                        value="{{ old('category') }}"
                        placeholder="e.g. T-Shirt"
                    >
                </div>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="filter-select" required>
                        @foreach ($productStatusOptions as $statusOption)
                            <option value="{{ $statusOption }}"
                                {{ old('status', 'active') === $statusOption ? 'selected' : '' }}>
                                {{ ucfirst($statusOption) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group" style="width: 100%;">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        class="filter-select"
                        rows="4"
                    >{{ old('description') }}</textarea>
                </div>

                <div class="filter-group" style="width: 100%;">
                    <x-asset-selector category-input-name="images[category]" asset-input-name="images[asset_key]" />   
                </div>
            </div>

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">

            <h4 style="margin-bottom: 12px;">Variant Options</h4>
            <p style="margin-bottom: 14px; color: #6b7280;">
                Select colors and sizes. Variants and SKUs will be generated automatically in the backend.
            </p>

            <div class="filters-section">
                <div class="filter-group" style="width: 100%;">
                    <label>Colors</label>
                    <div id="colors-wrapper">
                        @php
                            $oldColors = old('colors', ['']);
                        @endphp

                        @foreach($oldColors as $index => $color)
                            <div class="dynamic-row color-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <input
                                    type="text"
                                    name="colors[]"
                                    class="filter-select"
                                    value="{{ $color }}"
                                    placeholder="e.g. Black"
                                    required
                                >
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
                        @php
                            $oldSizes = old('sizes', ['']);
                        @endphp

                        @foreach($oldSizes as $index => $size)
                            <div class="dynamic-row size-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <input
                                    type="text"
                                    name="sizes[]"
                                    class="filter-select"
                                    value="{{ $size }}"
                                    placeholder="e.g. S"
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

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">

            <h4 style="margin-bottom: 12px;">Print Areas</h4>
            <p style="margin-bottom: 14px; color: #6b7280;">
                Select one or more print areas for this product.
            </p>

            <div class="filters-section">
                <div class="filter-group" style="width: 100%;">
                    <label for="print_area_ids" class="print-area-section-title">Available Print Areas</label>

                    @if($printAreas->count())
                        <div class="print-area-grid">
                            @foreach ($printAreas as $printArea)
                                @php
                                    $isSelected = in_array($printArea->id, old('print_area_ids', []));
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

            <div style="margin-top: 20px;">
                <h4 style="margin-bottom: 8px;">Variant Preview</h4>
                <p style="margin-bottom: 12px; color: #6b7280;">
                    This preview is based on selected colors and sizes. Actual variants will be created automatically after save.
                </p>

                <div id="variant-preview" style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; min-height: 60px; background: #fafafa;">
                    <span style="color:#6b7280;">No variants yet.</span>
                </div>
            </div>

            <div style="margin-top: 16px; display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">Create Product</button>
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
            const addSizeBtn = document.getElementById('add-size-btn');
            const variantPreview = document.getElementById('variant-preview');

            function makeRow(name, placeholder, rowClass) {
                const row = document.createElement('div');
                row.className = `dynamic-row ${rowClass}`;
                row.style.display = 'flex';
                row.style.gap = '10px';
                row.style.marginBottom = '10px';

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
                            const input = row.querySelector('input');
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
                const colors = getUniqueValues('input[name="colors[]"]');
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

            addColorBtn.addEventListener('click', function () {
                colorsWrapper.appendChild(makeRow('colors', 'e.g. Black', 'color-row'));
                bindRemoveButtons();
            });

            addSizeBtn.addEventListener('click', function () {
                sizesWrapper.appendChild(makeRow('sizes', 'e.g. XL', 'size-row'));
                bindRemoveButtons();
            });

            document.addEventListener('input', function (event) {
                if (
                    event.target.matches('input[name="colors[]"]') ||
                    event.target.matches('input[name="sizes[]"]')
                ) {
                    updateVariantPreview();
                }
            });

            bindRemoveButtons();
            updateVariantPreview();
        });
    </script>
    @endpush
@endsection