@extends('layouts.app')

@section('title', 'DTFTA CRM - Product Details')
@section('page-title', 'Product Details')

@section('content')

<div style="margin-bottom: 20px; display: flex; gap: 10px;">
   
    <a href="{{ route('crm.products.edit', $product->id) }}" class="btn-primary">Edit</a>
</div>

<div class="chart-card" style="padding: 28px; border-radius: 16px;">

    {{-- Header --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
        <div>
            <h2 style="margin:0; font-size:22px;">{{ $product->title }}</h2>
            <div style="color:#94a3b8; font-size:14px;">Product ID #{{ $product->id }}</div>
        </div>

        <div style="text-align:right;">
            <div style="font-weight:600; font-size:18px;">
                {{ $product->regular_price ? number_format($product->regular_price,2) : '-' }}
                {{ $product->currency ?? 'USD' }}
            </div>
            <div style="font-size:13px; color:#64748b;">
                {{ ucfirst($product->status ?? 'active') }}
            </div>
        </div>
    </div>

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    {{-- Basic Info --}}
    <h4 style="margin-bottom:15px;">Basic Information</h4>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:15px; margin-bottom:25px;">
        <div><strong>Store:</strong><br>{{ $product->shop->shop_domain ?? '-' }}</div>
        <div><strong>SKU:</strong><br>{{ $product->sku ?: '-' }}</div>
        <div><strong>Stock:</strong><br>{{ (int)$product->stock_quantity }} ({{ $product->stock_status ?? 'in_stock' }})</div>
        <div><strong>Track Inventory:</strong><br>{{ $product->track_inventory ? 'Yes' : 'No' }}</div>
    </div>

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    {{-- Catalog --}}
    <h4 style="margin-bottom:15px;">Catalog</h4>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:15px; margin-bottom:25px;">
        <div><strong>Category:</strong><br>{{ $product->category ?: '-' }}</div>
        <div><strong>Sub Category:</strong><br>{{ $product->sub_category ?: '-' }}</div>
        <div><strong>Brand:</strong><br>{{ $product->brand ?: '-' }}</div>
        <div><strong>Product Type:</strong><br>{{ $product->product_type ?: '-' }}</div>
        <div style="grid-column:1/-1;"><strong>Description:</strong><br>{{ $product->description ?: '-' }}</div>
    </div>

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    {{-- Print Area --}}
    <h4 style="margin-bottom:15px;">Print Area</h4>

    <div style="display:flex; gap:30px; flex-wrap:wrap; margin-bottom:25px;">

        <div style="flex:1; min-width:260px;">
            <div style="margin-bottom:10px;"><strong>Title:</strong> {{ $product->printArea->title ?? '-' }}</div>
            <div style="margin-bottom:10px;"><strong>Width:</strong> {{ $product->printArea->area_width ?? '-' }}</div>
            <div style="margin-bottom:10px;"><strong>Height:</strong> {{ $product->printArea->area_height ?? '-' }}</div>
            <div><strong>T-Shirt Size:</strong> {{ $product->printArea->tshirt_size ?? '-' }}</div>
        </div>

        <div style="flex:1; min-width:260px;">
            <strong>Placement Image</strong><br><br>

            @if(!empty($product->printArea->images))
                <img src="{{ asset('storage/' . $product->printArea->images) }}"
                     style="width:260px; height:260px; object-fit:contain; border-radius:14px; border:1px solid #e2e8f0; padding:12px;">
            @else
                <div style="color:#94a3b8;">No placement image</div>
            @endif
        </div>

    </div>

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    {{-- Shipping --}}
    <h4 style="margin-bottom:15px;">Shipping</h4>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:25px;">
        <div><strong>Weight:</strong><br>{{ $product->weight ?? '-' }}</div>
        <div><strong>Length:</strong><br>{{ $product->length ?? '-' }}</div>
        <div><strong>Width:</strong><br>{{ $product->width ?? '-' }}</div>
        <div><strong>Height:</strong><br>{{ $product->height ?? '-' }}</div>
    </div>

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    {{-- Media --}}
    <h4 style="margin-bottom:15px;">Media</h4>

    <div style="display:flex; gap:30px; flex-wrap:wrap;">

        <div>
            <strong>Featured Image</strong><br><br>
            @if(!empty($product->featured_image))
                <img src="{{ asset('storage/' . $product->featured_image) }}"
                     style="width:180px; height:180px; object-fit:cover; border-radius:12px;">
            @else
                <div style="color:#94a3b8;">No featured image</div>
            @endif
        </div>

        <div>
            <strong>Gallery Images</strong><br><br>
            @if(!empty($product->gallery_images))
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    @foreach($product->gallery_images as $galleryImage)
                        <img src="{{ asset('storage/' . $galleryImage) }}"
                             style="width:120px; height:120px; object-fit:cover; border-radius:10px;">
                    @endforeach
                </div>
            @else
                <div style="color:#94a3b8;">No gallery images</div>
            @endif
        </div>

    </div>

</div>

@endsection
