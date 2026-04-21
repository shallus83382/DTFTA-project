@extends('layouts.app')

@section('title', 'DTFTA CRM - View Print Area')
@section('page-title', 'Print Area Details')

@section('content')

<div class="chart-card" style="margin-top:20px; padding:20px;">

    <h4 style="margin-bottom:20px; border-bottom:1px solid #334155; padding-bottom:10px;">
        Print Area Details
    </h4>

    <div class="chart-card"
         style="padding:20px; border:1px solid #334155; border-radius:12px; background:#0f172a;">

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:18px;">

            <div>
                <label>Placement Title</label>
                <div class="filter-select">{{ $area->title ?? '-' }}</div>
            </div>

            <!-- <div>
                <label>T-Shirt Size</label>
                <div class="filter-select">{{ $area->tshirt_size ?? '-' }}</div>
            </div> -->

            <div>
                <label>Area Size</label>
                <div class="filter-select">
                    {{ $area->area_width ?? 0 }} x 
                    {{ $area->area_height ?? 0 }} 
                    {{ strtoupper($area->unit ?? '') }}
                </div>
            </div>

            <div>
                <label>Position X</label>
                <div class="filter-select">{{ $area->position_x ?? 0 }}</div>
            </div>

            <div>
                <label>Position Y</label>
                <div class="filter-select">{{ $area->position_y ?? 0 }}</div>
            </div>

            <div>
                <label>Display Order</label>
                <div class="filter-select">{{ $area->display_order ?? 0 }}</div>
            </div>

            <div>
                <label>Status</label>
                <div class="filter-select">
                    @if($area->is_active)
                        <span style="color:#22c55e; font-weight:600;">Active</span>
                    @else
                        <span style="color:#ef4444; font-weight:600;">Inactive</span>
                    @endif
                </div>
            </div>

            <div>
                <label>Placement Image</label>
                @if(!empty($area->images))
                    <x-selected-asset :category="$area->images['category'] ?? null" :asset-key="$area->images['asset_key'] ?? null" dimension="120px" />
                @else
                    <div class="filter-select">No Image</div>
                @endif
            </div>

        </div>

    </div>

</div>

@endsection