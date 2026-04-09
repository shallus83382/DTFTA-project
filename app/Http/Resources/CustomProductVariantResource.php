<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customProduct = $this->customProduct;
        $product = $customProduct?->product;
        $baseVariant = $this->productVariant;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'sku' => $this->sku,
            'price' => $this->price,
            'barcode' => $this->barcode,
            'option_values' => $this->option_values,
            'shopify_variant_id'=>$this->shopify_variant_id,
            'dtfta' => [
                'templateId' => (string) $customProduct?->id,
                'productKey' => $customProduct?->product_key ?? '',
                'garmentBrand' => $product?->brand ?? '',
                'garmentStyle' => $product?->model_code ?? '',
                'color' => $baseVariant?->color ?? '',
                'size' => $baseVariant?->size ?? '',
                'printPlan' => is_array($customProduct?->print_plan)
                    ? ($customProduct?->print_plan[0] ?? '')
                    : ($customProduct?->print_plan ?? ''),
            ],
        ];
    }
}