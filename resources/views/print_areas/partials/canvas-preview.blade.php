{{--
    Customizer canvas preview for one print-area form row.

    Expects:
      $previewData   PrintAreaPreviewData::payload()
      $index         row index used in the print_areas[...] input names
      $region        ['design_position_x' => ..., 'design_position_y' => ..., 'design_area_width' => ..., 'design_area_height' => ...]
      $printAreaId   (optional) id of the print area being edited
      $submitLabel   (optional) label of the form's submit button
--}}
@php
    $index = $index ?? 0;
    $region = $region ?? [];
    $printAreaId = $printAreaId ?? null;
    $submitLabel = $submitLabel ?? 'Save';
    $previewId = 'print-area-preview-' . $index;
    $regionValue = function (string $key) use ($index, $region) {
        $value = old("print_areas.{$index}.{$key}", $region[$key] ?? null);
        return $value === null ? '' : (string) $value;
    };
@endphp

<div class="chart-card" style="margin-top:20px; padding:20px;">
    <input type="hidden" name="print_areas[{{ $index }}][design_position_x]" value="{{ $regionValue('design_position_x') }}" data-pap-region="x">
    <input type="hidden" name="print_areas[{{ $index }}][design_position_y]" value="{{ $regionValue('design_position_y') }}" data-pap-region="y">
    <input type="hidden" name="print_areas[{{ $index }}][design_area_width]" value="{{ $regionValue('design_area_width') }}" data-pap-region="w">
    <input type="hidden" name="print_areas[{{ $index }}][design_area_height]" value="{{ $regionValue('design_area_height') }}" data-pap-region="h">

    <div id="{{ $previewId }}"
         data-print-area-preview
         data-index="{{ $index }}"
         data-print-area-id="{{ $printAreaId }}"
         data-submit-label="{{ $submitLabel }}">
        <p class="pap-note" style="color:#94a3b8;">Loading canvas preview…</p>
    </div>
</div>

@once
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/fabric@7.2.0/dist/index.min.js"></script>
        <script src="{{ asset('js/print-area-preview.js') }}?v={{ @filemtime(public_path('js/print-area-preview.js')) ?: '1' }}"></script>
        <script>
            (function () {
                var previewData = @json($previewData);

                // Snapshot asset keys now: this script runs before DOMContentLoaded, while the
                // asset selector still holds the saved value (it clears and refills it on load).
                var initialAssetKeys = {};
                document.querySelectorAll('input[name$="[images][asset_key]"]').forEach(function (el) {
                    initialAssetKeys[el.name] = el.value;
                });

                function boot() {
                    document.querySelectorAll('[data-print-area-preview]').forEach(function (mount) {
                        if (mount.__pap) return;
                        var index = mount.getAttribute('data-index');
                        var card = mount.closest('.chart-card');
                        var form = mount.closest('form');
                        var field = function (name) {
                            return form ? form.querySelector('[name="print_areas[' + index + '][' + name + ']"]') : null;
                        };
                        var val = function (name) {
                            var el = field(name);
                            return el ? el.value : '';
                        };
                        var regionInputs = {};
                        card.querySelectorAll('[data-pap-region]').forEach(function (el) {
                            regionInputs[el.getAttribute('data-pap-region')] = el;
                        });
                        var printAreaId = mount.getAttribute('data-print-area-id');
                        // The asset selector clears its hidden input while it reloads assets on page load;
                        // use the saved value until the admin actually touches the selector.
                        var assetTouched = false;
                        var assetInput = field('images][asset_key');
                        var assetKey = function () {
                            if (!assetInput) return '';
                            return assetInput.value || (assetTouched ? '' : (initialAssetKeys[assetInput.name] || ''));
                        };

                        mount.innerHTML = '';
                        var preview = new window.PrintAreaPreview(mount, {
                            mode: 'form',
                            data: previewData,
                            printAreaId: printAreaId ? Number(printAreaId) : null,
                            submitLabel: mount.getAttribute('data-submit-label'),
                            regionInputs: regionInputs,
                            readArea: function () {
                                return {
                                    id: printAreaId ? Number(printAreaId) : 0,
                                    title: val('title'),
                                    unit: val('unit'),
                                    area_width: val('area_width'),
                                    area_height: val('area_height'),
                                    position_x: val('position_x'),
                                    position_y: val('position_y'),
                                    asset_key: assetKey()
                                };
                            }
                        });
                        mount.__pap = preview;
                        if (!preview.canvas) return;

                        // Live-update when the form fields that affect the canvas change.
                        var timer = null;
                        var changed = function () {
                            clearTimeout(timer);
                            timer = setTimeout(function () { preview.areaFieldsChanged(); }, 120);
                        };
                        ['title', 'unit', 'area_width', 'area_height', 'position_x', 'position_y'].forEach(function (name) {
                            var el = field(name);
                            if (!el) return;
                            el.addEventListener('input', changed);
                            el.addEventListener('change', changed);
                        });
                        // The asset selector sets its hidden input from script (no input event). Setting
                        // .value on a hidden input rewrites its value attribute, so watch that.
                        if (assetInput && window.MutationObserver) {
                            new MutationObserver(changed).observe(assetInput, { attributes: true, attributeFilter: ['value'] });
                        }
                        if (form) {
                            form.addEventListener('click', function (e) {
                                if (e.target.closest('.asset-selector-wrapper .asset-card')) { assetTouched = true; setTimeout(changed, 0); }
                            });
                            form.addEventListener('change', function (e) {
                                if (e.target.closest('.asset-selector-wrapper')) { assetTouched = true; setTimeout(changed, 0); }
                            });

                            // Warn before leaving with an unsaved region change; not when submitting.
                            var submitting = false;
                            form.addEventListener('submit', function () { submitting = true; });
                            window.addEventListener('beforeunload', function (e) {
                                if (!submitting && preview.isDirty()) {
                                    e.preventDefault();
                                    e.returnValue = '';
                                }
                            });
                        }
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', boot);
                } else {
                    boot();
                }
            })();
        </script>
    @endpush
@endonce
