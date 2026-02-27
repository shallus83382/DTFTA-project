@extends('layouts.app')

@section('title', 'DTFTA CRM - Product Details')
@section('page-title', 'Product Details')

@section('content')
    <div style="margin-bottom: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="{{ route('crm.products') }}" class="btn-secondary">Back to Products</a>
        <a href="{{ route('crm.products.edit', $product->id) }}" class="btn-primary">Edit Product</a>
    </div>

    <div class="chart-card" style="margin-bottom: 16px;">
        <h3 style="margin-bottom: 14px;">{{ $product->title }}</h3>
        <div class="store-detail-grid">
            <div><span class="detail-label">Product ID:</span> <strong>#{{ $product->id }}</strong></div>
            <div><span class="detail-label">Store:</span> {{ $product->shop->shop_domain ?? '-' }}</div>
            <div><span class="detail-label">SKU:</span> {{ $product->sku ?: '-' }}</div>
            <div><span class="detail-label">Shopify Product ID:</span> {{ $product->shopify_product_id ?: '-' }}</div>
            <div><span class="detail-label">Status:</span> {{ ucfirst($product->status ?? 'active') }}</div>
            <div><span class="detail-label">Stock:</span> {{ (int) $product->stock_quantity }} ({{ ($product->stock_status ?? 'in_stock') === 'in_stock' ? 'In Stock' : 'Out of Stock' }})</div>
            <div><span class="detail-label">Track Inventory:</span> {{ $product->track_inventory ? 'Yes' : 'No' }}</div>
        </div>
    </div>

    <div class="chart-card" style="margin-bottom: 16px;">
        <h3 style="margin-bottom: 14px;">Catalog</h3>
        <div class="store-detail-grid">
            <div><span class="detail-label">Category:</span> {{ $product->category ?: '-' }}</div>
            <div><span class="detail-label">Sub-Category:</span> {{ $product->sub_category ?: '-' }}</div>
            <div><span class="detail-label">Brand:</span> {{ $product->brand ?: '-' }}</div>
            <div><span class="detail-label">Product Type:</span> {{ $product->product_type ?: '-' }}</div>
            <div><span class="detail-label">Tags:</span> {{ !empty($product->tags) ? implode(', ', $product->tags) : '-' }}</div>
            <div><span class="detail-label">Short Description:</span> {{ $product->short_description ?: '-' }}</div>
            <div><span class="detail-label">Description:</span> {{ $product->description ?: '-' }}</div>
        </div>
    </div>

    <div class="chart-card" style="margin-bottom: 16px;">
        <h3 style="margin-bottom: 14px;">Pricing</h3>
        <div class="store-detail-grid">
            <div><span class="detail-label">Regular Price:</span> {{ $product->regular_price !== null ? number_format((float) $product->regular_price, 2) : '-' }} {{ $product->currency ?? 'USD' }}</div>
            <div><span class="detail-label">Sale Price:</span> {{ $product->sale_price !== null ? number_format((float) $product->sale_price, 2) : '-' }} {{ $product->currency ?? 'USD' }}</div>
            <div><span class="detail-label">Tax Class:</span> {{ $product->tax_class ?: '-' }}</div>
        </div>
    </div>

    <div class="chart-card" style="margin-bottom: 16px;">
        <h3 style="margin-bottom: 14px;">Shipping</h3>
        <div class="store-detail-grid">
            <div><span class="detail-label">Weight:</span> {{ $product->weight ?? '-' }}</div>
            <div><span class="detail-label">Length:</span> {{ $product->length ?? '-' }}</div>
            <div><span class="detail-label">Width:</span> {{ $product->width ?? '-' }}</div>
            <div><span class="detail-label">Height:</span> {{ $product->height ?? '-' }}</div>
            <div><span class="detail-label">Shipping Class:</span> {{ $product->shipping_class ?: '-' }}</div>
        </div>
    </div>

    <div class="chart-card">
        <h3 style="margin-bottom: 14px;">Media</h3>
        <div style="display: flex; flex-direction: column; gap: 14px;">
            <div>
                <div style="margin-bottom: 8px; color: #94a3b8;">Featured Image</div>
                @if(!empty($product->featured_image))
                    <img src="{{ asset('storage/' . $product->featured_image) }}" alt="Featured image" style="width: 140px; height: 140px; object-fit: cover; border-radius: 10px;">
                @else
                    <div style="color: #94a3b8;">No featured image</div>
                @endif
            </div>
            <div>
                <div style="margin-bottom: 8px; color: #94a3b8;">Gallery Images</div>
                @if(!empty($product->gallery_images))
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        @foreach($product->gallery_images as $galleryImage)
                            <img src="{{ asset('storage/' . $galleryImage) }}" alt="Gallery image" style="width: 100px; height: 100px; object-fit: cover; border-radius: 10px;">
                        @endforeach
                    </div>
                @else
                    <div style="color: #94a3b8;">No gallery images</div>
                @endif
            </div>
        </div>
    </div>

  
@endsection
