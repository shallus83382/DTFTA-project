@extends('layouts.app')

@section('title', 'DTFTA CRM - Add Product')
@section('page-title', 'Add Product')

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

    <form action="{{ route('crm.print-areas.store') }}" method="POST" enctype="multipart/form-data">
        @csrf



        {{-- ================= PRINT AREAS SECTION ================= --}}
        @php
            $resolvedRows = old('print_areas') ?? [
                [
                    'title' => '',
                    'area_width' => null,
                    'area_height' => null,
                    'unit' => 'mm',
                    'position_x' => null,
                    'position_y' => null,
                    'tshirt_size' => null,
                    'display_order' => 0,
                    'is_active' => true,
                ],
            ];
        @endphp

        <div class="chart-card" style="margin-top:18px;">

            <div id="printAreasContainer" style="display:flex; flex-direction:column; gap:12px;">

                @foreach ($resolvedRows as $index => $row)
                    <div class="print-area-row chart-card" data-print-area-row
                        style="padding:12px; border:1px solid #334155;">

                        <div class="filters-section">

                            <div class="filter-group">
                                <label>Placement Title</label>
                                <input type="text" class="filter-select" name="print_areas[{{ $index }}][title]"
                                    value="{{ $row['title'] ?? '' }}" placeholder="Front Chest">
                            </div>
                            <div class="filter-group">
                                <label>T-Shirt Size</label>
                                <input type="text" class="filter-select"
                                    name="print_areas[{{ $index }}][tshirt_size]"
                                    value="{{ $row['tshirt_size'] ?? '' }}" placeholder="S, M, L, XL">
                            </div>
                            <div class="filter-group">
                                <label>Area Width</label>
                                <input type="number" step="0.01" min="0" class="filter-select"
                                    name="print_areas[{{ $index }}][area_width]"
                                    value="{{ $row['area_width'] ?? '' }}">
                            </div>
                            <div class="filter-group">
                                <label>Area Height</label>
                                <input type="number" step="0.01" min="0" class="filter-select"
                                    name="print_areas[{{ $index }}][area_height]"
                                    value="{{ $row['area_height'] ?? '' }}">
                            </div>
                            <div class="filter-group">
                                <label>Unit</label>
                                <select class="filter-select" name="print_areas[{{ $index }}][unit]">
                                    @foreach (['mm', 'cm', 'in', 'px'] as $unit)
                                        <option value="{{ $unit }}"
                                            {{ ($row['unit'] ?? 'mm') === $unit ? 'selected' : '' }}>
                                            {{ strtoupper($unit) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Position X</label>
                                <input type="number" step="0.01" class="filter-select"
                                    name="print_areas[{{ $index }}][position_x]"
                                    value="{{ $row['position_x'] ?? '' }}" placeholder="0">
                            </div>
                            <div class="filter-group">
                                <label>Position Y</label>
                                <input type="number" step="0.01" class="filter-select"
                                    name="print_areas[{{ $index }}][position_y]"
                                    value="{{ $row['position_y'] ?? '' }}" placeholder="0">
                            </div>
                            <div class="filter-group">
                                <label>Display Order</label>
                                <input type="number" min="0" class="filter-select"
                                    name="print_areas[{{ $index }}][display_order]"
                                    value="{{ $row['display_order'] ?? $index }}">
                            </div>
                            <!-- <div class="filter-group">
                                <label>Placement Image</label>
                                <input type="file" class="filter-select" name="print_area_images[{{ $index }}]"
                                    accept="image/*">
                            </div> -->

                            <div class="filter-group">
                                <label>Active</label>
                                <select class="filter-select" name="print_areas[{{ $index }}][is_active]">
                                    <option value="1" selected>Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>

                        </div>

                        <div style="margin-top:20px;">
                            <x-asset-selector
                            category-input-name="print_areas[{{ $index }}][images][category]"
                            asset-input-name="print_areas[{{ $index }}][images][asset_key]"
                            />   
                        </div>

                        <div style="margin-top:20px;">
                            <button type="submit" class="btn-primary">
                                Save Product
                            </button>
                        </div>

                    </div>
                @endforeach

            </div>
        </div>



    </form>

@endsection
