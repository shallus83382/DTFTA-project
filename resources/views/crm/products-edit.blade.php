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

    <div style="margin-bottom: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="{{ route('crm.products') }}" class="btn-secondary">Back to Products</a>
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
                    <label for="shop_id">Store</label>
                    <select id="shop_id" name="shop_id" class="filter-select" required>
                        @foreach($shopsForProducts as $shop)
                            <option value="{{ $shop->id }}" {{ (string) old('shop_id', $product->shop_id) === (string) $shop->id ? 'selected' : '' }}>
                                {{ $shop->shop_domain }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label for="title">Title</label>
                    <input id="title" name="title" type="text" class="filter-select" value="{{ old('title', $product->title) }}" required>
                </div>
                <div class="filter-group">
                    <label for="sku">SKU</label>
                    <input id="sku" name="sku" type="text" class="filter-select" value="{{ old('sku', $product->sku) }}">
                </div>
                <div class="filter-group">
                    <label for="category">Category</label>
                    <input id="category" name="category" type="text" class="filter-select" value="{{ old('category', $product->category) }}">
                </div>
                <div class="filter-group">
                    <label for="sub_category">Sub-Category</label>
                    <input id="sub_category" name="sub_category" type="text" class="filter-select" value="{{ old('sub_category', $product->sub_category) }}">
                </div>
                <div class="filter-group">
                    <label for="brand">Brand</label>
                    <input id="brand" name="brand" type="text" class="filter-select" value="{{ old('brand', $product->brand) }}">
                </div>
                <div class="filter-group">
                    <label for="product_type">Product Type</label>
                    <input id="product_type" name="product_type" type="text" class="filter-select" value="{{ old('product_type', $product->product_type) }}">
                </div>
                <div class="filter-group">
                    <label for="tags">Tags / Keywords</label>
                    <input id="tags" name="tags" type="text" class="filter-select" value="{{ old('tags', implode(', ', $product->tags ?? [])) }}" placeholder="tag1, tag2">
                </div>
                <div class="filter-group">
                    <label for="shopify_product_id">Shopify Product ID</label>
                    <input id="shopify_product_id" name="shopify_product_id" type="text" class="filter-select" value="{{ old('shopify_product_id', $product->shopify_product_id) }}">
                </div>
                <div class="filter-group">
                    <label for="regular_price">Regular Price</label>
                    <input id="regular_price" name="regular_price" type="number" min="0" step="0.01" class="filter-select" value="{{ old('regular_price', $product->regular_price ?? $product->price) }}">
                </div>
                <div class="filter-group">
                    <label for="sale_price">Sale / Discount Price</label>
                    <input id="sale_price" name="sale_price" type="number" min="0" step="0.01" class="filter-select" value="{{ old('sale_price', $product->sale_price) }}">
                </div>
                <div class="filter-group">
                    <label for="currency">Currency</label>
                    <input id="currency" name="currency" type="text" class="filter-select" value="{{ old('currency', $product->currency ?? 'USD') }}">
                </div>
                <div class="filter-group">
                    <label for="tax_class">Tax Class</label>
                    <input id="tax_class" name="tax_class" type="text" class="filter-select" value="{{ old('tax_class', $product->tax_class) }}">
                </div>
                <div class="filter-group">
                    <label for="stock_quantity">Stock Qty</label>
                    <input id="stock_quantity" name="stock_quantity" type="number" min="0" class="filter-select" value="{{ old('stock_quantity', (int) $product->stock_quantity) }}">
                </div>
                <div class="filter-group">
                    <label for="stock_status">Stock Status</label>
                    <select id="stock_status" name="stock_status" class="filter-select" required>
                        @foreach($stockStatusOptions as $stockStatusOption)
                            <option value="{{ $stockStatusOption }}" {{ old('stock_status', $product->stock_status ?? 'in_stock') === $stockStatusOption ? 'selected' : '' }}>
                                {{ $stockStatusOption === 'in_stock' ? 'In Stock' : 'Out of Stock' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label for="track_inventory">Track Inventory</label>
                    <select id="track_inventory" name="track_inventory" class="filter-select">
                        <option value="1" {{ (string) old('track_inventory', $product->track_inventory ? '1' : '0') === '1' ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ (string) old('track_inventory', $product->track_inventory ? '1' : '0') === '0' ? 'selected' : '' }}>No</option>
                    </select>
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
                    <label for="weight">Weight</label>
                    <input id="weight" name="weight" type="number" min="0" step="0.001" class="filter-select" value="{{ old('weight', $product->weight) }}">
                </div>
                <div class="filter-group">
                    <label for="length">Length</label>
                    <input id="length" name="length" type="number" min="0" step="0.01" class="filter-select" value="{{ old('length', $product->length) }}">
                </div>
                <div class="filter-group">
                    <label for="width">Width</label>
                    <input id="width" name="width" type="number" min="0" step="0.01" class="filter-select" value="{{ old('width', $product->width) }}">
                </div>
                <div class="filter-group">
                    <label for="height">Height</label>
                    <input id="height" name="height" type="number" min="0" step="0.01" class="filter-select" value="{{ old('height', $product->height) }}">
                </div>
                <div class="filter-group">
                    <label for="shipping_class">Shipping Class</label>
                    <input id="shipping_class" name="shipping_class" type="text" class="filter-select" value="{{ old('shipping_class', $product->shipping_class) }}">
                </div>
                <div class="filter-group">
                    <label for="featured_image">Featured Image</label>
                    <input id="featured_image" name="featured_image" type="file" class="filter-select" accept="image/*">
                    @if(!empty($product->featured_image))
                        <label style="margin-top: 8px; display: block;">
                            <input type="checkbox" name="remove_featured_image" value="1">
                            Remove current featured image
                        </label>
                        <div style="margin-top: 8px;">
                            <img src="{{ asset('storage/' . $product->featured_image) }}" alt="Featured image" style="width: 84px; height: 84px; object-fit: cover; border-radius: 8px;">
                        </div>
                    @endif
                </div>
                <div class="filter-group">
                    <label for="gallery_images">Gallery Images (multiple)</label>
                    <input id="gallery_images" name="gallery_images[]" type="file" class="filter-select" accept="image/*" multiple>
                    <label style="margin-top: 8px; display: block;">
                        <input type="checkbox" name="remove_existing_gallery" value="1">
                        Remove existing gallery images
                    </label>
                    @if(!empty($product->gallery_images))
                        <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                            @foreach($product->gallery_images as $galleryImage)
                                <img src="{{ asset('storage/' . $galleryImage) }}" alt="Gallery image" style="width: 68px; height: 68px; object-fit: cover; border-radius: 8px;">
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="filter-group" style="width: 100%;">
                    <label for="short_description">Short Description</label>
                    <input id="short_description" name="short_description" type="text" class="filter-select" value="{{ old('short_description', $product->short_description) }}">
                </div>
                <div class="filter-group" style="width: 100%;">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="filter-select" rows="3">{{ old('description', $product->description) }}</textarea>
                </div>
            </div>

            <div style="margin-top: 12px; display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">Save Changes</button>
                <a href="{{ route('crm.products') }}" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
