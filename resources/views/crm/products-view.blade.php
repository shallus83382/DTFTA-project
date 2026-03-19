@extends('layouts.app')

@section('title', 'DTFTA CRM - Product Details')
@section('page-title', 'Product Details')

@section('content')

@php
    $productImages = is_array($product->images) ? $product->images : [];
    $status = strtolower((string) ($product->status ?? 'active'));
    $badgeClass = match($status) {
        'active' => 'status-shipped',
        'draft' => 'status-artwork',
        'archived' => 'status-cancelled',
        'inactive' => 'status-production',
        default => 'status-production',
    };
@endphp

<div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="{{ route('crm.products.edit', $product->id) }}" class="btn-primary">Edit</a>
    <a href="{{ route('crm.products') }}" class="btn-secondary">Back to Products</a>
</div>

<div class="chart-card" style="padding: 28px; border-radius: 16px;">

    {{-- Header --}}
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:25px; flex-wrap: wrap;">
        <div>
            <h2 style="margin:0; font-size:22px;">{{ $product->title }}</h2>
            <div style="color:#94a3b8; font-size:14px;">Product ID #{{ $product->id }}</div>
        </div>

        <div style="text-align:right;">
            <div style="margin-bottom: 8px;">
                <span class="status-badge {{ $badgeClass }}">{{ ucfirst($status) }}</span>
            </div>
            <div style="font-size:13px; color:#64748b;">
                Created: {{ optional($product->created_at)->format('M d, Y h:i A') ?: '-' }}
            </div>
        </div>
    </div>

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    {{-- Basic Info --}}
    <h4 style="margin-bottom:15px;">Basic Information</h4>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:15px; margin-bottom:25px;">
        <div><strong>Title:</strong><br>{{ $product->title ?: '-' }}</div>
        <div><strong>Brand:</strong><br>{{ $product->brand ?: '-' }}</div>
        <div><strong>Model Code:</strong><br>{{ $product->model_code ?: '-' }}</div>
        <div><strong>Category:</strong><br>{{ $product->category ?: '-' }}</div>
        <div style="grid-column:1/-1;"><strong>Description:</strong><br>{{ $product->description ?: '-' }}</div>
    </div>

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    {{-- Product Images --}}
    <h4 style="margin-bottom:15px;">Product Images</h4>
    @if(count($productImages))
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:25px;">
            @foreach($productImages as $image)
                <img
                    src="{{ asset('storage/' . $image) }}"
                    alt="Product image"
                    style="width:160px; height:160px; object-fit:cover; border-radius:12px; border:1px solid #e5e7eb;"
                >
            @endforeach
        </div>
    @else
        <div style="margin-bottom:25px; color:#94a3b8;">No product images uploaded.</div>
    @endif

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    {{-- Variants --}}
    <h4 style="margin-bottom:15px;">Variants</h4>

    <div style="margin-bottom: 14px; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:15px;">
        <div>
            <strong>Available Colors:</strong><br>
            {{ $product->variants->pluck('color')->unique()->filter()->values()->implode(', ') ?: '-' }}
        </div>
        <div>
            <strong>Available Sizes:</strong><br>
            {{ $product->variants->pluck('size')->unique()->filter()->values()->implode(', ') ?: '-' }}
        </div>
        <div>
            <strong>Total Variants:</strong><br>
            {{ $product->variants->count() }}
        </div>
    </div>

    @if($product->variants->count())
        <div style="overflow-x:auto; margin-bottom:25px;">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Color</th>
                        <th>Size</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($product->variants as $variant)
                        @php
                            $variantStatus = $variant->is_active ? 'Active' : 'Inactive';
                            $variantBadgeClass = $variant->is_active ? 'status-shipped' : 'status-cancelled';
                        @endphp
                        <tr>
                            <td>#{{ $variant->id }}</td>
                            <td>{{ $variant->sku }}</td>
                            <td>{{ $variant->color }}</td>
                            <td>{{ $variant->size }}</td>
                            <td><span class="status-badge {{ $variantBadgeClass }}">{{ $variantStatus }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="margin-bottom:25px; color:#94a3b8;">No variants found.</div>
    @endif

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    {{-- Print Areas --}}
    <h4 style="margin-bottom:15px;">Assigned Print Areas</h4>

    @if($product->printAreas->count())
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:15px; margin-bottom:25px;">
            @foreach($product->printAreas as $printArea)
                @php
                    $printAreaImages = is_array($printArea->images) ? $printArea->images : [];
                    $printAreaFirstImage = $printAreaImages[0] ?? null;
                @endphp

                <div style="border:1px solid #e5e7eb; border-radius:12px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:12px;">
                        <div>
                            <strong style="font-size:16px;">{{ $printArea->title ?: 'Untitled Area' }}</strong>
                        </div>
                        <div>
                            @if($printArea->is_active)
                                <span class="status-badge status-shipped">Active</span>
                            @else
                                <span class="status-badge status-cancelled">Inactive</span>
                            @endif
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; font-size:14px; margin-bottom:12px;">
                        <div>
                            <strong>Width:</strong><br>
                            {{ $printArea->area_width ?? '-' }}
                        </div>
                        <div>
                            <strong>Height:</strong><br>
                            {{ $printArea->area_height ?? '-' }}
                        </div>
                        <div>
                            <strong>Unit:</strong><br>
                            {{ $printArea->unit ?: '-' }}
                        </div>
                        <div>
                            <strong>Display Order:</strong><br>
                            {{ $printArea->display_order ?? 0 }}
                        </div>
                        <div>
                            <strong>Position X:</strong><br>
                            {{ $printArea->position_x ?? '-' }}
                        </div>
                        <div>
                            <strong>Position Y:</strong><br>
                            {{ $printArea->position_y ?? '-' }}
                        </div>
                        <div style="grid-column:1/-1;">
                            <strong>T-Shirt Size:</strong><br>
                            {{ $printArea->tshirt_size ?: '-' }}
                        </div>
                    </div>

                    @if($printAreaFirstImage)
                        <div>
                            <strong>Preview:</strong><br><br>
                            <img
                                src="{{ asset('storage/' . $printAreaFirstImage) }}"
                                alt="Print area image"
                                style="width:100%; max-width:220px; height:220px; object-fit:contain; border-radius:12px; border:1px solid #e5e7eb; padding:10px;"
                            >
                        </div>
                    @else
                        <div style="color:#94a3b8;">No print area image.</div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div style="margin-bottom:25px; color:#94a3b8;">No print areas assigned.</div>
    @endif

</div>

@endsection