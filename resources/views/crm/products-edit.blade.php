@extends('layouts.app')

@section('title', 'DTFTA CRM - Edit Product')
@section('page-title', 'Edit Product')

@section('content')
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
    @endphp

    <div style="margin-bottom: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="{{ route('crm.products.view', $product->id) }}" class="btn-secondary">View Product</a>

        <form method="POST" action="{{ route('crm.products.destroy', $product->id) }}" onsubmit="return confirm('Delete this product?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">Delete Product</button>
        </form>
    </div>

    <div class="chart-card">
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
                    <label for="images">Add Product Images</label>
                    <input
                        id="images"
                        name="images[]"
                        type="file"
                        class="filter-select"
                        accept="image/*"
                        multiple
                    >

                    <label style="margin-top: 8px; display: block;">
                        <input type="checkbox" name="remove_existing_images" value="1" {{ old('remove_existing_images') ? 'checked' : '' }}>
                        Remove existing images
                    </label>

                    @if(!empty($existingImages))
                        <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                            @foreach($existingImages as $imagePath)
                                <img
                                    src="{{ asset('storage/' . $imagePath) }}"
                                    alt="Product image"
                                    style="width: 72px; height: 72px; object-fit: cover; border-radius: 8px;"
                                >
                            @endforeach
                        </div>
                    @endif
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
                    <div id="colors-wrapper">
                        @foreach(($oldColors ?: ['']) as $color)
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
                <h4 style="margin-bottom: 8px;">Current Variants</h4>
                @if($product->variants->count())
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        @foreach($product->variants as $variant)
                            <span style="padding: 6px 10px; border-radius: 999px; background: #f3f4f6; color: #111827; font-size: 13px;">
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
                    <label>Available Print Areas</label>

                    @if($printAreas->count())
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
                            @foreach($printAreas as $printArea)
                                <label style="display: flex; align-items: flex-start; gap: 10px; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px;">
                                    <input
                                        type="checkbox"
                                        name="print_area_ids[]"
                                        value="{{ $printArea->id }}"
                                        {{ in_array($printArea->id, $oldPrintAreaIds) ? 'checked' : '' }}
                                        style="margin-top: 4px;"
                                    >

                                    <span>
                                        <strong>{{ $printArea->title ?: 'Untitled Area' }}</strong><br>

                                        <small style="color:#6b7280;">
                                            Size:
                                            {{ $printArea->area_width ?? '-' }}
                                            x
                                            {{ $printArea->area_height ?? '-' }}
                                            {{ $printArea->unit ?? '' }}
                                        </small><br>

                                        <small style="color:#6b7280;">
                                            Position:
                                            X {{ $printArea->position_x ?? '-' }},
                                            Y {{ $printArea->position_y ?? '-' }}
                                        </small>

                                        @if($printArea->tshirt_size)
                                            <br>
                                            <small style="color:#6b7280;">
                                                Garment Size: {{ $printArea->tshirt_size }}
                                            </small>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div style="padding: 12px; border: 1px dashed #d1d5db; border-radius: 8px; color: #6b7280;">
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
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        ${variants.map(variant => `
                            <span style="padding: 6px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 13px;">
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