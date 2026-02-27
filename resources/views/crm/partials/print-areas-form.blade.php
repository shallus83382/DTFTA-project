@php
    $resolvedRows = old('print_areas');
    if (!is_array($resolvedRows) || empty($resolvedRows)) {
        $resolvedRows = [];
        if (!empty($printAreas)) {
            foreach ($printAreas as $area) {
                $resolvedRows[] = [
                    'id' => $area->id ?? null,
                    'title' => $area->title ?? '',
                    'existing_image' => $area->placement_image ?? null,
                    'area_width' => $area->area_width ?? null,
                    'area_height' => $area->area_height ?? null,
                    'unit' => $area->unit ?? 'mm',
                    'position_x' => $area->position_x ?? null,
                    'position_y' => $area->position_y ?? null,
                    'tshirt_size' => $area->tshirt_size ?? null,
                    'display_order' => $area->display_order ?? 0,
                    'is_active' => (bool) ($area->is_active ?? true),
                ];
            }
        }
    }
    if (empty($resolvedRows)) {
        $resolvedRows = [[
            'id' => null,
            'title' => '',
            'existing_image' => null,
            'area_width' => null,
            'area_height' => null,
            'unit' => 'mm',
            'position_x' => null,
            'position_y' => null,
            'tshirt_size' => null,
            'display_order' => 0,
            'is_active' => true,
        ]];
    }
@endphp

<div class="chart-card" style="margin-top: 18px;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px;">
        <h4 style="margin: 0;">Print Areas</h4>
        <button type="button" class="btn-secondary" id="addPrintAreaBtn">Add Print Area</button>
    </div>
    <div id="printAreasContainer" style="display: flex; flex-direction: column; gap: 12px;">
        @foreach($resolvedRows as $index => $row)
            <div class="print-area-row chart-card" data-print-area-row style="padding: 12px; border: 1px solid #334155;">
                <input type="hidden" name="print_areas[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
                <input type="hidden" name="print_areas[{{ $index }}][existing_image]" value="{{ $row['existing_image'] ?? '' }}">
                <div class="filters-section">
                    <div class="filter-group">
                        <label>Placement Title</label>
                        <input type="text" class="filter-select" name="print_areas[{{ $index }}][title]" value="{{ $row['title'] ?? '' }}" placeholder="Front Chest">
                    </div>
                    <div class="filter-group">
                        <label>T-Shirt Size</label>
                        <input type="text" class="filter-select" name="print_areas[{{ $index }}][tshirt_size]" value="{{ $row['tshirt_size'] ?? '' }}" placeholder="S, M, L, XL">
                    </div>
                    <div class="filter-group">
                        <label>Area Width</label>
                        <input type="number" step="0.01" min="0" class="filter-select" name="print_areas[{{ $index }}][area_width]" value="{{ $row['area_width'] ?? '' }}">
                    </div>
                    <div class="filter-group">
                        <label>Area Height</label>
                        <input type="number" step="0.01" min="0" class="filter-select" name="print_areas[{{ $index }}][area_height]" value="{{ $row['area_height'] ?? '' }}">
                    </div>
                    <div class="filter-group">
                        <label>Unit</label>
                        <select class="filter-select" name="print_areas[{{ $index }}][unit]">
                            @foreach(['mm', 'cm', 'in', 'px'] as $unit)
                                <option value="{{ $unit }}" {{ ($row['unit'] ?? 'mm') === $unit ? 'selected' : '' }}>{{ strtoupper($unit) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Position X</label>
                        <input type="number" step="0.01" class="filter-select" name="print_areas[{{ $index }}][position_x]" value="{{ $row['position_x'] ?? '' }}" placeholder="0">
                    </div>
                    <div class="filter-group">
                        <label>Position Y</label>
                        <input type="number" step="0.01" class="filter-select" name="print_areas[{{ $index }}][position_y]" value="{{ $row['position_y'] ?? '' }}" placeholder="0">
                    </div>
                    <div class="filter-group">
                        <label>Display Order</label>
                        <input type="number" min="0" class="filter-select" name="print_areas[{{ $index }}][display_order]" value="{{ $row['display_order'] ?? $index }}">
                    </div>
                    <div class="filter-group">
                        <label>Placement Image</label>
                        <input type="file" class="filter-select" name="print_area_images[{{ $index }}]" accept="image/*">
                        @if(!empty($row['existing_image']))
                            <div style="margin-top: 8px;">
                                <img src="{{ str_starts_with($row['existing_image'], 'http') ? $row['existing_image'] : asset('storage/' . $row['existing_image']) }}" alt="Placement image" style="width: 72px; height: 72px; object-fit: cover; border-radius: 8px;">
                            </div>
                            <label style="display: inline-block; margin-top: 8px;">
                                <input type="checkbox" name="print_areas[{{ $index }}][remove_image]" value="1">
                                Remove image
                            </label>
                        @endif
                    </div>
                    <div class="filter-group">
                        <label>Active</label>
                        <select class="filter-select" name="print_areas[{{ $index }}][is_active]">
                            <option value="1" {{ !isset($row['is_active']) || $row['is_active'] ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ isset($row['is_active']) && !$row['is_active'] ? 'selected' : '' }}>No</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 8px;">
                    <button type="button" class="btn-danger" data-remove-print-area>Remove</button>
                </div>
            </div>
        @endforeach
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                const container = document.getElementById('printAreasContainer');
                const addBtn = document.getElementById('addPrintAreaBtn');
                if (!container || !addBtn) return;

                let printAreaIndex = container.querySelectorAll('[data-print-area-row]').length;

                function template(index) {
                    return `
                        <div class="print-area-row chart-card" data-print-area-row style="padding: 12px; border: 1px solid #334155;">
                            <input type="hidden" name="print_areas[${index}][id]" value="">
                            <input type="hidden" name="print_areas[${index}][existing_image]" value="">
                            <div class="filters-section">
                                <div class="filter-group"><label>Placement Title</label><input type="text" class="filter-select" name="print_areas[${index}][title]" placeholder="Front Chest"></div>
                                <div class="filter-group"><label>T-Shirt Size</label><input type="text" class="filter-select" name="print_areas[${index}][tshirt_size]" placeholder="S, M, L, XL"></div>
                                <div class="filter-group"><label>Area Width</label><input type="number" step="0.01" min="0" class="filter-select" name="print_areas[${index}][area_width]"></div>
                                <div class="filter-group"><label>Area Height</label><input type="number" step="0.01" min="0" class="filter-select" name="print_areas[${index}][area_height]"></div>
                                <div class="filter-group">
                                    <label>Unit</label>
                                    <select class="filter-select" name="print_areas[${index}][unit]">
                                        <option value="mm">MM</option><option value="cm">CM</option><option value="in">IN</option><option value="px">PX</option>
                                    </select>
                                </div>
                                <div class="filter-group"><label>Position X</label><input type="number" step="0.01" class="filter-select" name="print_areas[${index}][position_x]" placeholder="0"></div>
                                <div class="filter-group"><label>Position Y</label><input type="number" step="0.01" class="filter-select" name="print_areas[${index}][position_y]" placeholder="0"></div>
                                <div class="filter-group"><label>Display Order</label><input type="number" min="0" class="filter-select" name="print_areas[${index}][display_order]" value="${index}"></div>
                                <div class="filter-group"><label>Placement Image</label><input type="file" class="filter-select" name="print_area_images[${index}]" accept="image/*"></div>
                                <div class="filter-group">
                                    <label>Active</label>
                                    <select class="filter-select" name="print_areas[${index}][is_active]">
                                        <option value="1" selected>Yes</option><option value="0">No</option>
                                    </select>
                                </div>
                            </div>
                            <div style="margin-top: 8px;"><button type="button" class="btn-danger" data-remove-print-area>Remove</button></div>
                        </div>`;
                }

                addBtn.addEventListener('click', function () {
                    container.insertAdjacentHTML('beforeend', template(printAreaIndex));
                    printAreaIndex += 1;
                });

                container.addEventListener('click', function (event) {
                    const removeBtn = event.target.closest('[data-remove-print-area]');
                    if (!removeBtn) return;
                    const rows = container.querySelectorAll('[data-print-area-row]');
                    if (rows.length <= 1) {
                        return;
                    }
                    const row = removeBtn.closest('[data-print-area-row]');
                    if (row) {
                        row.remove();
                    }
                });
            })();
        </script>
    @endpush
@endonce

