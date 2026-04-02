<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DtftaProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $commonColors = collect(config('dtfta.common_colors', []));
        $brandNames = collect(config('dtfta.brand_names', []));
        $blanks = collect(config('dtfta.blanks', []));

        $images = is_array($this->images) ? $this->images : [];

        $blank = $blanks->first(function ($blank) {
            return strtolower((string) ($blank['brandCode'] ?? '')) === strtolower((string) $this->brand)
                && strtolower((string) ($blank['model'] ?? '')) === strtolower((string) $this->model_code);
        });

        $brandCode = $brandNames->first(function ($item) {
            return strtolower((string) ($item['name'] ?? '')) === strtolower((string) $this->brand);
        });

        $variants = $this->whenLoaded('variants', function () use ($commonColors) {
            return $this->variants->map(function ($variant) use ($commonColors) {
                $color = $commonColors->first(function ($item) use ($variant) {
                    return strtolower((string) ($item['name'] ?? '')) === strtolower((string) $variant->color);
                });

                return [
                    'id' => $variant->id,
                    'colorCode' => $color['code'] ?? null,
                    'colorName' => $color['name'] ?? $variant->color,
                    'size' => $variant->size,
                    'sku' => $variant->sku,
                    'is_active' => (bool) $variant->is_active,
                ];
            })->values();
        }, collect());

        $colors = $this->whenLoaded('variants', function () use ($variants) {
            return collect($variants)
                ->pluck('colorName')
                ->filter()
                ->unique()
                ->values();
        }, collect());

        $sizes = $this->whenLoaded('variants', function () {
            return $this->variants
                ->pluck('size')
                ->filter()
                ->unique()
                ->values();
        }, collect());


        $printAreas = $this->whenLoaded('printAreas', function () {
            return $this->printAreas->map(function ($printArea) {
                
                // $appUrl = config('app.url');
                // $imageUrl = null;

                // if ($printArea->images) {
                //     if (str_contains($appUrl, 'ngrok')) {
                //         $imageUrl = 'https://murray-frames-ethernet-fashion.trycloudflare.com' . Storage::url($printArea->images);
                //     } else {
                //         $imageUrl = url(Storage::url($printArea->images));
                //     }
                // }

                return [
                    'id' => $printArea->id,
                    'title' => $printArea->title,
                    'area_width' => $printArea->area_width,
                    'area_height' => $printArea->area_height,
                    'unit' => $printArea->unit,
                    'position_x' => $printArea->position_x,
                    'position_y' => $printArea->position_y,
                    'tshirt_size' => $printArea->tshirt_size,
                    'display_order' => $printArea->display_order,
                    'is_active' => (bool) $printArea->is_active,
                   // 'image' => $printArea->images ? url(Storage::url($printArea->images)) : null,
                    'image' => $printArea->images['asset_key'],
                ];
            })->values();
        }, collect());

        $fallbackImage = !empty($blank['image']) ? asset($blank['image']) : null;
        $finalImages = !empty($images) ? $images : ($fallbackImage ? [$fallbackImage] : []);

        return [
            'id' => $this->id,
            'productKey' => strtolower($brandCode['code'] . '-' . $this->model_code),
            'name' => $blank['name'] ?? $this->title,
            'category' => $blank['category'] ?? $this->category,
            'brandCode' => $brandCode['code'] ?? null,
            'brand' => $brandCode['name'] ?? null,
            'style' => $blank['style'] ?? $this->model_code,
            'model' => $blank['model'] ?? $this->model_code,
            // 'image' => url(Storage::url($images[0])) ?? asset($fallbackImage),
            // 'images' => collect($finalImages)
            //     ->map(fn ($path) => asset('storage/' . ltrim($path, '/')))
            //     ->values()
            //     ->all(),
            'image' => $this->images['asset_key'],
            'description' => $this->description,
            'status' => $this->status,
            'price' => 0,
            'currency' => 'USD',
            'colors' => $colors,
            'sizes' => $sizes,
            'variants' => $variants,
            'print_areas' => $printAreas,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}