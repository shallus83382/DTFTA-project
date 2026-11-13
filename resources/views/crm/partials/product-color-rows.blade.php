@php
    use Illuminate\Support\Facades\Storage;

    $colorRows = $colorRows ?? [['name' => '', 'hex' => '', 'front' => null, 'back' => null, 'left_sleeve' => null, 'right_sleeve' => null]];
    $cloudfrontBase = rtrim((string) config('app.cloudfront_url'), '/');
    $colorMockupDisplayUrl = function (?string $path) use ($cloudfrontBase) {
        if (!$path) {
            return '';
        }
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'assets/')) {
            return $cloudfrontBase . '/' . $path;
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : $cloudfrontBase . '/' . $path;
    };
    $predefinedColorNames = collect(config('dtfta.common_colors', []))
        ->pluck('name')
        ->filter()
        ->values()
        ->all();
@endphp

@once
    @push('styles')
    <style>
        .color-mockup-field-label {
            font-size: 12px;
            color: #64748b;
            display: block;
            margin-bottom: 6px;
        }

        .product-edit-card .color-mockup-field-label,
        .product-add-card .color-mockup-field-label {
            color: #a9bddb;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
        }

        .color-mockup-box {
            position: relative;
            width: 100%;
            max-width: 120px;
            aspect-ratio: 1;
            border-radius: 10px;
            border: 1px dashed rgba(148, 163, 184, 0.45);
            background: rgba(15, 23, 42, 0.35);
            overflow: hidden;
        }

        .product-edit-card .color-mockup-box,
        .product-add-card .color-mockup-box {
            border-color: rgba(148, 163, 184, 0.35);
            background: rgba(15, 23, 42, 0.55);
        }

        .color-mockup-file-input {
            position: absolute;
            width: 0;
            height: 0;
            opacity: 0;
            pointer-events: none;
        }

        .color-mockup-trigger {
            border: none;
            background: transparent;
            cursor: pointer;
            padding: 0;
            margin: 0;
            line-height: 1;
        }

        .color-mockup-trigger-add {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: color 0.15s ease, background 0.15s ease;
        }

        .product-edit-card .color-mockup-trigger-add,
        .product-add-card .color-mockup-trigger-add {
            color: #94a3b8;
        }

        .color-mockup-trigger-add:hover {
            background: rgba(59, 130, 246, 0.08);
            color: #3b82f6;
        }

        .color-mockup-plus {
            font-size: 28px;
            font-weight: 300;
            line-height: 1;
        }

        .color-mockup-box.has-image .color-mockup-trigger-add {
            display: none;
        }

        .color-mockup-preview-wrap {
            position: absolute;
            inset: 0;
        }

        .color-mockup-preview-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            background: #fff;
        }

        .color-mockup-trigger-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.82);
            color: #f8fafc;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
            z-index: 2;
        }

        .color-mockup-trigger-remove:hover {
            background: #dc2626;
        }

        .color-mockup-box:not(.has-image) .color-mockup-preview-wrap {
            display: none !important;
        }

        /* Custom color suggestions (datalist can't be styled) */
        .color-name-suggest-wrap {
            position: relative;
        }

        .color-suggest-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 50;
            max-height: 220px;
            overflow: auto;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.28);
            background: linear-gradient(165deg, rgba(30, 41, 59, 0.98), rgba(15, 23, 42, 0.98));
            box-shadow: 0 18px 36px rgba(2, 6, 23, 0.45);
            padding: 6px;
        }

        .color-suggest-menu[hidden] {
            display: none;
        }

        .color-suggest-item {
            width: 100%;
            text-align: left;
            border: 1px solid transparent;
            background: transparent;
            color: #e2e8f0;
            padding: 8px 10px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            line-height: 1.2;
        }

        .color-suggest-item:hover,
        .color-suggest-item[aria-selected="true"] {
            background: linear-gradient(140deg, rgba(59, 130, 246, 0.18), rgba(59, 130, 246, 0.05));
            border-color: rgba(96, 165, 250, 0.35);
            color: #f8fafc;
        }

        .mockup-zip-import-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin: 14px 0;
        }

        .mockup-zip-import-status {
            color: #9fb1cf;
            font-size: 13px;
        }

        .mockup-zip-review {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(2, 6, 23, 0.82);
        }

        .mockup-zip-review[hidden] {
            display: none;
        }

        .mockup-zip-review-dialog {
            width: min(1120px, 96vw);
            max-height: 92vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 14px;
            background: #0f172a;
            color: #e2e8f0;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.55);
        }

        .mockup-zip-review-header,
        .mockup-zip-review-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }

        .mockup-zip-review-header h3 {
            margin: 0;
            color: #f8fafc;
        }

        .mockup-zip-review-content {
            display: grid;
            gap: 12px;
            min-height: 0;
            padding: 16px;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .mockup-zip-review-footer {
            justify-content: flex-end;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
            border-bottom: 0;
        }

        .mockup-zip-review-color {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(100px, 150px);
            gap: 12px;
            align-items: end;
            padding: 12px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 10px;
            background: rgba(15, 23, 42, 0.45);
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
        }

        .mockup-zip-review-color label {
            display: grid;
            gap: 5px;
            color: #a9bddb;
            font-size: 11px;
            text-transform: uppercase;
            min-width: 0;
        }

        .mockup-zip-review-color > div:not(.mockup-zip-review-files) {
            grid-column: 1 / -1;
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .mockup-zip-review-color input[type="text"],
        .mockup-zip-review-color input[type="color"] {
            min-height: 36px;
            box-sizing: border-box;
        }

        .mockup-zip-review-files {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 12px;
            min-width: 0;
        }

        .mockup-zip-review-file {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            align-content: start;
            gap: 8px;
            padding: 10px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 9px;
            background: rgba(15, 23, 42, 0.55);
            font-size: 12px;
            color: #cbd5e1;
            min-width: 0;
            max-width: 100%;
        }

        .mockup-zip-review-file img {
            width: 100%;
            height: clamp(150px, 25vh, 240px);
            object-fit: contain;
            background-color: #fff;
            border-radius: 7px;
            cursor: zoom-in;
        }

        .mockup-zip-review-file select {
            width: 100%;
            max-width: 100%;
        }

        .mockup-zip-review-file span {
            overflow-wrap: anywhere;
        }

        .mockup-zip-review-warning {
            color: #fbbf24;
            font-size: 12px;
        }

        .mockup-zip-review-error {
            color: #fca5a5;
            font-size: 12px;
        }

        .mockup-zip-review-missing {
            color: #f87171;
            font-size: 12px;
            font-weight: 700;
        }

        @media (max-width: 760px) {
            .mockup-zip-review {
                padding: 8px;
            }

            .mockup-zip-review-dialog {
                width: 100%;
                max-height: 96vh;
            }

            .mockup-zip-review-color,
            .mockup-zip-review-file {
                grid-template-columns: 1fr;
            }
        }
    </style>
    @endpush
@endonce

<p style="margin-bottom: 10px; color: #6b7280; font-size: 13px;">
    Enter any color name (or pick a preset). Upload front, back, left sleeve, and right sleeve mockups per color — saved as <code>placement_filename.ext</code> on S3 (from the uploaded file name).
</p>

<div class="mockup-zip-import-tools">
    <button type="button" class="btn-secondary" id="mockup-zip-import-button">Import Mockups ZIP</button>
    <input type="file" id="mockup-zip-import-input" accept=".zip,application/zip" hidden>
    <span class="mockup-zip-import-status" id="mockup-zip-import-status" role="status" aria-live="polite">
        ZIP images will populate the color rows below. Review names and swatches, then save the product normally.
    </span>
</div>
<div class="mockup-zip-review" id="mockup-zip-review" role="dialog" aria-modal="true" aria-labelledby="mockup-zip-review-title" hidden>
    <div class="mockup-zip-review-dialog">
        <div class="mockup-zip-review-header">
            <h3 id="mockup-zip-review-title">Review ZIP Images</h3>
            <button type="button" class="btn-secondary" id="mockup-zip-review-close" aria-label="Close ZIP preview">Close</button>
        </div>
        <div class="mockup-zip-review-content" id="mockup-zip-review-content"></div>
        <div class="mockup-zip-review-footer">
            <button type="button" class="btn-secondary" id="mockup-zip-review-cancel">Cancel</button>
        </div>
    </div>
</div>

<div id="colors-wrapper"
     data-predefined-colors='@json($predefinedColorNames)'
     data-color-keys='@json(config("dtfta.common_colors", []))'>
    @foreach($colorRows as $index => $row)
        @php
            $colorName = old('colors.' . $index, $row['name'] ?? '');
            $colorHex = old('color_hex.' . $index, $row['hex'] ?? '');
            $frontPath = old('color_front_existing.' . $index, $row['front'] ?? null);
            $backPath = old('color_back_existing.' . $index, $row['back'] ?? null);
            $leftSleevePath = old('color_left_sleeve_existing.' . $index, $row['left_sleeve'] ?? null);
            $rightSleevePath = old('color_right_sleeve_existing.' . $index, $row['right_sleeve'] ?? null);
            $frontUrl = $colorMockupDisplayUrl($frontPath);
            $backUrl = $colorMockupDisplayUrl($backPath);
            $leftSleeveUrl = $colorMockupDisplayUrl($leftSleevePath);
            $rightSleeveUrl = $colorMockupDisplayUrl($rightSleevePath);
        @endphp
        <div class="dynamic-row color-row product-color-row" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; margin-bottom: 30px; align-items: start;">
            <div class="color-name-suggest-wrap">
                <label style="font-size: 12px; color: #64748b; display: block; margin-bottom: 4px;">Color name</label>
                <input
                    type="text"
                    name="colors[{{ $index }}]"
                    class="filter-select color-name-input"
                    value="{{ $colorName }}"
                    placeholder="e.g. Sage Green"
                    autocomplete="off"
                    required
                >
                <div class="color-suggest-menu" hidden></div>
            </div>
            <div>
                <label style="font-size: 12px; color: #64748b; display: block; margin-bottom: 4px;">Swatch</label>
                <input
                    type="color"
                    name="color_hex[{{ $index }}]"
                    class="filter-select color-hex-input"
                    value="{{ $colorHex ?: '#e2e8f0' }}"
                    style="padding: 4px; height: 38px; width: 100%; max-width: 120px;"
                >
            </div>
            <div>
                @include('crm.partials.product-color-mockup-field', [
                    'placement' => 'front',
                    'label' => 'Front mockup',
                    'existingPath' => $frontPath ?? '',
                    'previewUrl' => $frontUrl,
                    'rowIndex' => $index,
                ])
            </div>
            <div>
                @include('crm.partials.product-color-mockup-field', [
                    'placement' => 'back',
                    'label' => 'Back mockup',
                    'existingPath' => $backPath ?? '',
                    'previewUrl' => $backUrl,
                    'rowIndex' => $index,
                ])
            </div>
            <div>
                @include('crm.partials.product-color-mockup-field', [
                    'placement' => 'left_sleeve',
                    'label' => 'Left sleeve',
                    'existingPath' => $leftSleevePath ?? '',
                    'previewUrl' => $leftSleeveUrl,
                    'rowIndex' => $index,
                ])
            </div>
            <div>
                @include('crm.partials.product-color-mockup-field', [
                    'placement' => 'right_sleeve',
                    'label' => 'Right sleeve',
                    'existingPath' => $rightSleevePath ?? '',
                    'previewUrl' => $rightSleeveUrl,
                    'rowIndex' => $index,
                ])
            </div>
            <div style="padding-top: 22px;">
                <button type="button" class="btn-danger remove-row-btn">Remove</button>
            </div>
        </div>
    @endforeach
</div>

<template id="product-color-row-template">
    <div class="dynamic-row color-row product-color-row" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; margin-bottom: 30px; align-items: start;">
        <div class="color-name-suggest-wrap">
            <label style="font-size: 12px; color: #64748b; display: block; margin-bottom: 4px;">Color name</label>
            <input type="text" name="colors[]" class="filter-select color-name-input" placeholder="e.g. Sage Green" autocomplete="off" required>
            <div class="color-suggest-menu" hidden></div>
        </div>
        <div>
            <label style="font-size: 12px; color: #64748b; display: block; margin-bottom: 4px;">Swatch</label>
            <input type="color" name="color_hex[]" class="filter-select color-hex-input" value="#e2e8f0" style="padding: 4px; height: 38px; width: 100%; max-width: 120px;">
        </div>
        <div>
            @include('crm.partials.product-color-mockup-field', [
                'placement' => 'front',
                'label' => 'Front mockup',
                'existingPath' => '',
                'previewUrl' => '',
            ])
        </div>
        <div>
            @include('crm.partials.product-color-mockup-field', [
                'placement' => 'back',
                'label' => 'Back mockup',
                'existingPath' => '',
                'previewUrl' => '',
            ])
        </div>
        <div>
            @include('crm.partials.product-color-mockup-field', [
                'placement' => 'left_sleeve',
                'label' => 'Left sleeve',
                'existingPath' => '',
                'previewUrl' => '',
            ])
        </div>
        <div>
            @include('crm.partials.product-color-mockup-field', [
                'placement' => 'right_sleeve',
                'label' => 'Right sleeve',
                'existingPath' => '',
                'previewUrl' => '',
            ])
        </div>
        <div style="padding-top: 22px;">
            <button type="button" class="btn-danger remove-row-btn">Remove</button>
        </div>
    </div>
</template>

<button type="button" class="btn-secondary" id="add-color-btn" style="margin-top: 8px;">
    Add Color
</button>
