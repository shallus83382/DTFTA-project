<?php

namespace App\View\Components;

use App\Helpers\AssetHelper;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AssetSelector extends Component
{
    public array $categories;
    public ?string $selectedCategory;
    public ?string $selectedAssetKey;
    public string $categoryInputName;
    public string $assetInputName;
    /**
     * Create a new component instance.
     */
    public function __construct(
        ?string $selectedCategory = null,
        ?string $selectedAssetKey = null,
        string $categoryInputName = 'category',
        string $assetInputName = 'asset_key'
    )
    {
        $this->categories = AssetHelper::categoriesForSelect();
        $this->selectedCategory = $selectedCategory;
        $this->selectedAssetKey = $selectedAssetKey;
        $this->categoryInputName = $categoryInputName;
        $this->assetInputName = $assetInputName;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.asset-selector');
    }
}
