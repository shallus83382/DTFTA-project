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
                'dtfta_type' => 'APPAREL_POD',
                'dtfta_template_id' => (string) $customProduct?->id,
                'dtfta_product_key' => $customProduct?->product_key ?? '',
                'dtfta_garment_brand' => $product?->brand ?? '',
                'dtfta_garment_style' => $product?->model_code ?? '',
                'dtfta_garment_color' => $baseVariant?->color ?? '',
                'dtfta_garment_size' => $baseVariant?->size ?? '',
                'dtfta_print_plan' => is_array($customProduct?->print_plan)
                    ? ($customProduct?->print_plan[0] ?? '')
                    : ($customProduct?->print_plan ?? ''),
            ],
        ];
    }
}