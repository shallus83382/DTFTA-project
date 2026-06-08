@extends('layouts.app')

@section('title', 'DTFTA CRM - Product Details')
@section('page-title', 'Product Details')

@push('styles')
<style>
    .product-view-page {
        display: grid;
        gap: 14px;
    }

    .product-view-toolbar {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.85));
    }

    .product-view-card {
        border-radius: 16px !important;
        border: 1px solid rgba(148, 163, 184, 0.22) !important;
        background: linear-gradient(160deg, rgba(30, 41, 59, 0.94), rgba(15, 23, 42, 0.94)) !important;
        box-shadow: 0 14px 30px rgba(2, 6, 23, 0.26) !important;
        padding: 20px !important;
    }

    .product-view-card h2,
    .product-view-card h4 {
        color: #f8fafc !important;
    }

    .product-view-card hr {
        border-top-color: rgba(148, 163, 184, 0.2) !important;
    }

    .product-view-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 22px;
    }

    .product-view-item {
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 10px;
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.72), rgba(15, 23, 42, 0.65));
        padding: 10px 12px;
    }

    .product-view-print-card {
        border: 1px solid rgba(148, 163, 184, 0.2) !important;
        border-radius: 12px !important;
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.75), rgba(15, 23, 42, 0.68));
    }

    .product-view-print-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .product-view-print-title {
        font-size: 17px;
        color: #f8fafc;
        font-weight: 700;
        letter-spacing: -0.01em;
    }

    .product-view-print-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 12px;
        margin-bottom: 12px;
    }

    .product-view-print-stat {
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 9px;
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.62));
        padding: 8px 10px;
        display: grid;
        gap: 2px;
    }

    .product-view-print-stat-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #9fb1cf;
        font-weight: 700;
    }

    .product-view-print-stat-value {
        font-size: 13px;
        color: #e2e8f0;
        font-weight: 600;
        line-height: 1.25;
        word-break: break-word;
    }

    .product-view-print-empty {
        margin-top: 4px;
        border: 1px dashed rgba(148, 163, 184, 0.28);
        border-radius: 10px;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.55), rgba(15, 23, 42, 0.4));
        padding: 11px 12px;
        color: #94a3b8 !important;
        font-size: 12px;
    }

    @media (max-width: 760px) {
        .product-view-print-meta {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

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

<div class="product-view-page">
<div class="product-view-toolbar" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="{{ route('crm.products.edit', $product->id) }}" class="btn-primary">Edit</a>
    <a href="{{ route('crm.products') }}" class="btn-secondary">Back to Products</a>
</div>

<div class="chart-card product-view-card" style="padding: 28px; border-radius: 16px;">

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
    <div class="product-view-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:15px; margin-bottom:25px;">
        <div class="product-view-item"><strong>Title:</strong><br>{{ $product->title ?: '-' }}</div>
        <div class="product-view-item"><strong>Brand:</strong><br>{{ $product->brand ?: '-' }}</div>
        <div class="product-view-item"><strong>Model Code:</strong><br>{{ $product->model_code ?: '-' }}</div>
        <div class="product-view-item"><strong>Category:</strong><br>{{ $product->category ?: '-' }}</div>
        <div class="product-view-item" style="grid-column:1/-1;"><strong>Description:</strong><br>{{ $product->description ?: '-' }}</div>
    </div>

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    {{-- Product Images --}}
    <h4 style="margin-bottom:15px;">Product Images</h4>
    @if(count($productImages))
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:25px;">
          <x-selected-asset :category="$productImages['category'] ?? null" :asset-key="$productImages['asset_key'] ?? null" dimension="120px" />
        </div>
    @else
        <div style="margin-bottom:25px; color:#94a3b8;">No product images uploaded.</div>
    @endif

    <hr style="margin:20px 0; border-color:#f1f5f9;">

    @php
        use Illuminate\Support\Facades\Storage;

        $colorMockups = is_array($product->color_mockups) ? $product->color_mockups : [];
        $cloudfrontBase = rtrim((string) config('app.cloudfront_url'), '/');
        $colorMockupDisplayUrl = function (?string $path) use ($cloudfrontBase) {
            if (!$path) {
                return null;
            }
            $path = ltrim($path, '/');

            if (str_starts_with($path, 'assets/')) {
                return $cloudfrontBase . '/' . $path;
            }

            return Storage::disk('public')->exists($path)
                ? Storage::disk('public')->url($path)
                : $cloudfrontBase . '/' . $path;
        };
    @endphp

    @if(count($colorMockups))
        <h4 style="margin-bottom:15px;">Color Mockups</h4>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:15px; margin-bottom:25px;">
            @foreach($colorMockups as $mockupKey => $mockup)
                @php
                    $mockupName = $mockup['name'] ?? $mockupKey;
                    $mockupHex = $mockup['hex'] ?? '#e2e8f0';
                    $frontUrl = !empty($mockup['front']) ? $colorMockupDisplayUrl($mockup['front']) : null;
                    $backUrl = !empty($mockup['back']) ? $colorMockupDisplayUrl($mockup['back']) : null;
                    $leftSleeveUrl = !empty($mockup['left_sleeve']) ? $colorMockupDisplayUrl($mockup['left_sleeve']) : null;
                    $rightSleeveUrl = !empty($mockup['right_sleeve']) ? $colorMockupDisplayUrl($mockup['right_sleeve']) : null;
                @endphp
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                        <span style="width:18px; height:18px; border-radius:4px; background:{{ $mockupHex }}; border:1px solid #cbd5e1;"></span>
                        <strong>{{ $mockupName }}</strong>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        @if($frontUrl)
                            <div>
                                <small style="color:#fff;">Front</small><br>
                                <img src="{{ $frontUrl }}" alt="{{ $mockupName }} front" style="max-height:100px; border-radius:6px; background:#fff;">
                            </div>
                        @endif
                        @if($backUrl)
                            <div>
                                <small style="color:#fff;">Back</small><br>
                                <img src="{{ $backUrl }}" alt="{{ $mockupName }} back" style="max-height:100px; border-radius:6px; background:#fff;">
                            </div>
                        @endif
                        @if($leftSleeveUrl)
                            <div>
                                <small style="color:#fff;">Left sleeve</small><br>
                                <img src="{{ $leftSleeveUrl }}" alt="{{ $mockupName }} left sleeve" style="max-height:100px; border-radius:6px; background:#fff;">
                            </div>
                        @endif
                        @if($rightSleeveUrl)
                            <div>
                                <small style="color:#fff;">Right sleeve</small><br>
                                <img src="{{ $rightSleeveUrl }}" alt="{{ $mockupName }} right sleeve" style="max-height:100px; border-radius:6px; background:#fff;">
                            </div>
                        @endif
                        @if(!$frontUrl && !$backUrl && !$leftSleeveUrl && !$rightSleeveUrl)
                            <span style="color:#94a3b8;">No mockup images.</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <hr style="margin:20px 0; border-color:#f1f5f9;">
    @endif

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
                @endphp

                <div class="product-view-print-card" style="border:1px solid #e5e7eb; border-radius:12px; padding:16px;">
                    <div class="product-view-print-head" style="display:flex; justify-content:space-between; gap:12px; margin-bottom:12px;">
                        <div>
                            <strong class="product-view-print-title" style="font-size:16px;">{{ $printArea->title ?: 'Untitled Area' }}</strong>
                        </div>
                        <div>
                            @if($printArea->is_active)
                                <span class="status-badge status-shipped">Active</span>
                            @else
                                <span class="status-badge status-cancelled">Inactive</span>
                            @endif
                        </div>
                    </div>

                    <div class="product-view-print-meta" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; font-size:14px; margin-bottom:12px;">
                        <div class="product-view-print-stat">
                            <span class="product-view-print-stat-label">Width</span>
                            <span class="product-view-print-stat-value">{{ $printArea->area_width ?? '-' }}</span>
                        </div>
                        <div class="product-view-print-stat">
                            <span class="product-view-print-stat-label">Height</span>
                            <span class="product-view-print-stat-value">{{ $printArea->area_height ?? '-' }}</span>
                        </div>
                        <div class="product-view-print-stat">
                            <span class="product-view-print-stat-label">Unit</span>
                            <span class="product-view-print-stat-value">{{ $printArea->unit ?: '-' }}</span>
                        </div>
                        <div class="product-view-print-stat">
                            <span class="product-view-print-stat-label">Display Order</span>
                            <span class="product-view-print-stat-value">{{ $printArea->display_order ?? 0 }}</span>
                        </div>
                        <div class="product-view-print-stat">
                            <span class="product-view-print-stat-label">Position X</span>
                            <span class="product-view-print-stat-value">{{ $printArea->position_x ?? '-' }}</span>
                        </div>
                        <div class="product-view-print-stat">
                            <span class="product-view-print-stat-label">Position Y</span>
                            <span class="product-view-print-stat-value">{{ $printArea->position_y ?? '-' }}</span>
                        </div>
                        <div class="product-view-print-stat" style="grid-column:1/-1;">
                            <span class="product-view-print-stat-label">T-Shirt Size</span>
                            <span class="product-view-print-stat-value">{{ $printArea->tshirt_size ?: '-' }}</span>
                        </div>
                    </div>

                    @if($printAreaImages)
                        <div>
                            <strong>Preview:</strong><br><br>
                            <x-selected-asset :category="$printAreaImages['category'] ?? null" :asset-key="$printAreaImages['asset_key'] ?? null" dimension="120px" />
                        </div>
                    @else
                        <div class="product-view-print-empty" style="color:#94a3b8;">No print area image.</div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div style="margin-bottom:25px; color:#94a3b8;">No print areas assigned.</div>
    @endif

</div>
</div>

@endsection