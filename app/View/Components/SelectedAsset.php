<?php

namespace App\View\Components;

use App\Helpers\AssetHelper;
use Illuminate\View\Component;

class SelectedAsset extends Component
{
    public $asset, $dimension;

    public function __construct($category = null, $assetKey = null,$dimension = '50px')
    {
        $this->asset = AssetHelper::getAssetData($category, $assetKey);
        $this->dimension = $dimension;
    }

    public function render()
    {
        return view('components.selected-asset');
    }
}