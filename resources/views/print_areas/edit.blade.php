@extends('layouts.app')

@section('title', 'DTFTA CRM - Edit Print Area')
@section('page-title', 'Edit Print Area')

@section('content')

    @if ($errors->any())
        <div class="alert alert-danger" style="margin-bottom:15px;">
            <ul style="margin:0;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('crm.print-areas.update', $printArea->id) }}" method="POST" enctype="multipart/form-data">
        @csrf


        @php
            $unit = config('crm.features.print_areas.constraints.unit', 'px');
            $minWidth = config('crm.features.print_areas.constraints.width.min', 1);
            $maxWidth = config('crm.features.print_areas.constraints.width.max', 1000);
            $minHeight = config('crm.features.print_areas.constraints.height.min', 1);
            $maxHeight = config('crm.features.print_areas.constraints.height.max', 1000);
            $resolvedRows = old('print_areas') ?? [
                [
                    'title' => $printArea->title ?? '',
                    'area_width' => $printArea->area_width ?? null,
                    'area_height' => $printArea->area_height ?? null,
                    'unit' => $printArea->unit ?? $unit,
                    'position_x' => $printArea->position_x ?? null,
                    'position_y' => $printArea->position_y ?? null,
                    'tshirt_size' => $printArea->tshirt_size ?? null,
                    'display_order' => $printArea->display_order ?? 0,
                    'is_active' => $printArea->is_active ?? true,
                ],
            ];
        @endphp

        <div class="chart-card" style="margin-top:18px;">

            <div id="printAreasContainer" style="display:flex; flex-direction:column; gap:12px;">

                @foreach ($resolvedRows as $index => $row)
                    <div class="print-area-row chart-card" style="padding:12px; border:1px solid #334155;">

                        <div class="filters-section">

                            <div class="filter-group">
                                <label>Placement Title</label>
                                <input type="text" class="filter-select" name="print_areas[0][title]"
                                    value="{{ old('print_areas.0.title', $row['title']) }}">
                            </div>

                            <div class="filter-group" style="display:none;">
                                <label>T-Shirt Size</label>
                                <input type="hidden" class="filter-select" name="print_areas[0][tshirt_size]"
                                    value="{{ old('print_areas.0.tshirt_size', $row['tshirt_size']) }}">
                            </div>

                            <div class="filter-group">
                                <label>Area Width</label>
                                <input type="number" step="0.01" min="{{ $minWidth }}" max="{{ $maxWidth }}" class="filter-select"
                                    name="print_areas[0][area_width]"
                                    value="{{ old('print_areas.0.area_width', $row['area_width']) }}">
                            </div>

                            <div class="filter-group">
                                <label>Area Height</label>
                                <input type="number" step="0.01" min="{{ $minHeight }}" max="{{ $maxHeight }}" class="filter-select"
                                    name="print_areas[0][area_height]"
                                    value="{{ old('print_areas.0.area_height', $row['area_height']) }}">
                            </div>

                            <div class="filter-group">
                                <label>Unit</label>
                                <input type="hidden" name="print_areas[0][unit]" value="{{ $unit }}">
                                <input type="text" class="filter-select" value="{{ strtoupper($unit) }}" readonly>
                            </div>

                            <div class="filter-group">
                                <label>Position X</label>
                                <input type="number" step="0.01" class="filter-select" name="print_areas[0][position_x]"
                                    value="{{ old('print_areas.0.position_x', $row['position_x']) }}">
                            </div>

                            <div class="filter-group">
                                <label>Position Y</label>
                                <input type="number" step="0.01" class="filter-select" name="print_areas[0][position_y]"
                                    value="{{ old('print_areas.0.position_y', $row['position_y']) }}">
                            </div>

                            <div class="filter-group">
                                <label>Display Order</label>
                                <input type="number" min="0" class="filter-select"
                                    name="print_areas[0][display_order]"
                                    value="{{ old('print_areas.0.display_order', $row['display_order']) }}">
                            </div>

                            <!-- <input type="file" id="imageInput" class="filter-select" name="print_area_images[0]"
                                accept="image/*"> -->

                        <div class="filter-group">
                            <label>Active</label>
                            <select class="filter-select" name="print_areas[0][is_active]">
                                <option value="1"
                                    {{ old('print_areas.0.is_active', $row['is_active']) ? 'selected' : '' }}>
                                    Yes
                                </option>
                                <option value="0"
                                    {{ !old('print_areas.0.is_active', $row['is_active']) ? 'selected' : '' }}>
                                    No
                                </option>
                            </select>
                        </div>

                    </div>
                    <div style="margin-top:20px;">
                        <x-asset-selector
                        :selected-category="old('print_areas[0][images][category]', $printArea->images['category'] ?? null)"
                        :selected-asset-key="old('print_areas[0][images][asset_key]', $printArea->images['asset_key'] ?? null)"
                        category-input-name="print_areas[0][images][category]"
                        asset-input-name="print_areas[0][images][asset_key]"
                        />   
                    </div>


                    <div style="margin-top:20px;">
                        <button type="submit" class="btn-primary">
                            Update Print Area
                        </button>
                    </div>

            </div>
            @endforeach

        </div>
        </div>

    </form>
<script>
document.getElementById('imageInput').addEventListener('change', function(e) {

    const file = e.target.files[0];

    if (file) {
        const reader = new FileReader();

        reader.onload = function(event) {
            document.getElementById('imagePreview').src = event.target.result;
        }

        reader.readAsDataURL(file);
    }

});
</script>
@endsection
