<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'product_key' => $this->product_key,
            'print_plan' => $this->print_plan,
            'base_product' => [
                'id' => $product?->id,
                'brand' => $product?->brand,
                'style' => $product?->model_code,
            ],
            'variants' => CustomProductVariantResource::collection($this->variants),
            'artworks_by_placement' => $this->artworks
                ->filter(fn ($artwork) => !empty($artwork->placement))
                ->mapWithKeys(function ($artwork) {
                    return [
                        $artwork->placement => [
                            'id' => $artwork->id,
                            'placement' => $artwork->placement,
                            'title' => $artwork->title,
                            'artwork_url' => $artwork->artwork_url,
                            'custom_product_variant_id' => $artwork->custom_product_variant_id,
                            'meta' => $artwork->meta,
                        ]
                    ];
                }),
        ];
    }
}