@extends('layouts.app')

@section('title', 'DTFTA CRM - Add Product')
@section('page-title', 'Add Product')

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

    <div class="chart-card">
        <h3 style="margin-bottom: 14px;">Create New Product</h3>
        <form method="POST" action="{{ route('crm.products.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="filters-section">
                <div class="filter-group">
                    <label for="shop_id">Store</label>
                    <select id="shop_id" name="shop_id" class="filter-select" required>
                        <option value="">Select store</option>
                        @foreach($shopsForProducts as $shop)
                            <option value="{{ $shop->id }}" {{ old('shop_id') == $shop->id ? 'selected' : '' }}>
                                {{ $shop->shop_domain }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label for="title">Title</label>
                    <input id="title" name="title" type="text" class="filter-select" value="{{ old('title') }}" required>
                </div>
                <div class="filter-group">
                    <label for="sku">SKU</label>
                    <input id="sku" name="sku" type="text" class="filter-select" value="{{ old('sku') }}">
                </div>
                <div class="filter-group">
                    <label for="category">Category</label>
                    <input id="category" name="category" type="text" class="filter-select" value="{{ old('category') }}">
                </div>
                <div class="filter-group">
                    <label for="sub_category">Sub-Category</label>
                    <input id="sub_category" name="sub_category" type="text" class="filter-select" value="{{ old('sub_category') }}">
                </div>
                <div class="filter-group">
                    <label for="brand">Brand</label>
                    <input id="brand" name="brand" type="text" class="filter-select" value="{{ old('brand') }}">
                </div>
                <div class="filter-group">
                    <label for="product_type">Product Type</label>
                    <input id="product_type" name="product_type" type="text" class="filter-select" value="{{ old('product_type') }}">
                </div>
                <div class="filter-group">
                    <label for="tags">Tags / Keywords</label>
                    <input id="tags" name="tags" type="text" class="filter-select" value="{{ old('tags') }}" placeholder="tag1, tag2">
                </div>
                <div class="filter-group">
                    <label for="shopify_product_id">Shopify Product ID</label>
                    <input id="shopify_product_id" name="shopify_product_id" type="text" class="filter-select" value="{{ old('shopify_product_id') }}">
                </div>
                <div class="filter-group">
                    <label for="regular_price">Regular Price</label>
                    <input id="regular_price" name="regular_price" type="number" min="0" step="0.01" class="filter-select" value="{{ old('regular_price') }}">
                </div>
                <div class="filter-group">
                    <label for="sale_price">Sale / Discount Price</label>
                    <input id="sale_price" name="sale_price" type="number" min="0" step="0.01" class="filter-select" value="{{ old('sale_price') }}">
                </div>
                <div class="filter-group">
                    <label for="currency">Currency</label>
                    <input id="currency" name="currency" type="text" class="filter-select" value="{{ old('currency', 'USD') }}">
                </div>
                <div class="filter-group">
                    <label for="tax_class">Tax Class</label>
                    <input id="tax_class" name="tax_class" type="text" class="filter-select" value="{{ old('tax_class') }}">
                </div>
                <div class="filter-group">
                    <label for="stock_quantity">Stock Qty</label>
                    <input id="stock_quantity" name="stock_quantity" type="number" min="0" class="filter-select" value="{{ old('stock_quantity', 0) }}">
                </div>
                <div class="filter-group">
                    <label for="stock_status">Stock Status</label>
                    <select id="stock_status" name="stock_status" class="filter-select" required>
                        @foreach($stockStatusOptions as $stockStatusOption)
                            <option value="{{ $stockStatusOption }}" {{ old('stock_status', 'in_stock') === $stockStatusOption ? 'selected' : '' }}>
                                {{ $stockStatusOption === 'in_stock' ? 'In Stock' : 'Out of Stock' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label for="track_inventory">Track Inventory</label>
                    <select id="track_inventory" name="track_inventory" class="filter-select">
                        <option value="1" {{ old('track_inventory', '1') == '1' ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ old('track_inventory') === '0' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="filter-select" required>
                        @foreach($productStatusOptions as $statusOption)
                            <option value="{{ $statusOption }}" {{ old('status', 'active') === $statusOption ? 'selected' : '' }}>
                                {{ ucfirst($statusOption) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label for="weight">Weight</label>
                    <input id="weight" name="weight" type="number" min="0" step="0.001" class="filter-select" value="{{ old('weight') }}">
                </div>
                <div class="filter-group">
                    <label for="length">Length</label>
                    <input id="length" name="length" type="number" min="0" step="0.01" class="filter-select" value="{{ old('length') }}">
                </div>
                <div class="filter-group">
                    <label for="width">Width</label>
                    <input id="width" name="width" type="number" min="0" step="0.01" class="filter-select" value="{{ old('width') }}">
                </div>
                <div class="filter-group">
                    <label for="height">Height</label>
                    <input id="height" name="height" type="number" min="0" step="0.01" class="filter-select" value="{{ old('height') }}">
                </div>
                <div class="filter-group">
                    <label for="shipping_class">Shipping Class</label>
                    <input id="shipping_class" name="shipping_class" type="text" class="filter-select" value="{{ old('shipping_class') }}">
                </div>
                <div class="filter-group">
                    <label for="featured_image">Featured Image</label>
                    <input id="featured_image" name="featured_image" type="file" class="filter-select" accept="image/*">
                </div>
                <div class="filter-group">
                    <label for="gallery_images">Gallery Images</label>
                    <input id="gallery_images" name="gallery_images[]" type="file" class="filter-select" accept="image/*" multiple>
                </div>
                <div class="filter-group" style="width: 100%;">
                    <label for="short_description">Short Description</label>
                    <input id="short_description" name="short_description" type="text" class="filter-select" value="{{ old('short_description') }}">
                </div>
                <div class="filter-group" style="width: 100%;">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="filter-select" rows="3">{{ old('description') }}</textarea>
                </div>
            </div>

            <div style="margin-top: 12px; display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">Create Product</button>
                <a href="{{ route('crm.products') }}" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
