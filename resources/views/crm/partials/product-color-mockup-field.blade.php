@php
    $placement = $placement ?? 'front';
    $label = $label ?? ucfirst($placement) . ' mockup';
    $existingPath = $existingPath ?? '';
    $previewUrl = $previewUrl ?? '';
    $rowIndex = $rowIndex ?? 0;
    $fileInputName = $placement === 'back' ? "color_back[{$rowIndex}]" : "color_front[{$rowIndex}]";
    $existingInputName = $placement === 'back' ? "color_back_existing[{$rowIndex}]" : "color_front_existing[{$rowIndex}]";
    $removeInputName = $placement === 'back' ? "color_back_remove[{$rowIndex}]" : "color_front_remove[{$rowIndex}]";
    $hasImage = $previewUrl !== '';
@endphp

<div class="color-mockup-field">
    <label class="color-mockup-field-label">{{ $label }}</label>
    <div class="color-mockup-box {{ $hasImage ? 'has-image' : '' }}" data-placement="{{ $placement }}">
        <input
            type="file"
            name="{{ $fileInputName }}"
            class="color-mockup-file-input"
            accept="image/png,image/jpeg,image/jpg,image/webp,image/gif"
            tabindex="-1"
        >
        <input type="hidden" name="{{ $existingInputName }}" class="color-mockup-existing-input" value="{{ $existingPath }}">
        <input type="hidden" name="{{ $removeInputName }}" class="color-mockup-remove-input" value="">

        <button type="button" class="color-mockup-trigger color-mockup-trigger-add" aria-label="Upload {{ strtolower($label) }}">
            <span class="color-mockup-plus">+</span>
        </button>

        <div class="color-mockup-preview-wrap" @if(!$hasImage) hidden @endif>
            <img
                src="{{ $previewUrl }}"
                alt="{{ $label }}"
                class="color-mockup-preview-img"
                @if(!$hasImage) hidden @endif
            >
            <button type="button" class="color-mockup-trigger color-mockup-trigger-remove" aria-label="Remove {{ strtolower($label) }}">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
</div>
