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
    </style>
    @endpush
@endonce

<p style="margin-bottom: 10px; color: #6b7280; font-size: 13px;">
    Enter any color name (or pick a preset). Upload front, back, left sleeve, and right sleeve mockups per color — saved as <code>placement_filename.ext</code> on S3 (from the uploaded file name).
</p>

<div id="colors-wrapper" data-predefined-colors='@json($predefinedColorNames)'>
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
