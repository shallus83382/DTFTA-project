<?php

namespace App\Http\Resources;

use App\Helpers\AssetHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DtftaProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $commonColors = collect(config('dtfta.common_colors', []));
        $blanks = collect(config('dtfta.blanks', []));

        $images = is_array($this->images) ? $this->images : [];
        $brandName = trim((string) $this->brand);
        $brandCode = $brandName !== '' ? Str::upper(Str::slug($brandName, '_')) : '';
        $brandKey = Str::slug($brandName);
        $modelKey = Str::slug((string) $this->model_code);

        $blank = $blanks->first(function ($blank) {
            return strtolower((string) ($blank['brandCode'] ?? '')) === strtolower((string) $this->brand)
                && strtolower((string) ($blank['model'] ?? '')) === strtolower((string) $this->model_code);
        });

        $variants = $this->whenLoaded('variants', function () {
            return $this->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'colorCode' => $this->resolveColorKey((string) $variant->color),
                    'colorName' => $variant->color,
                    'size' => $variant->size,
                    'sku' => $variant->sku,
                    'is_active' => (bool) $variant->is_active,
                    'price' => $variant->price,
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
            $variantSizes = $this->variants
                ->pluck('size')
                ->filter()
                ->unique()
                ->values();

            $orderedSizes = collect($this->size_order ?? [])
                ->map(fn ($size) => trim((string) $size))
                ->filter()
                ->unique(fn ($size) => mb_strtolower($size))
                ->filter(fn ($size) => $variantSizes->contains(
                    fn ($variantSize) => strcasecmp((string) $variantSize, $size) === 0
                ))
                ->values();

            $orderedKeys = $orderedSizes->map(fn ($size) => mb_strtolower($size));

            return $orderedSizes
                ->concat($variantSizes->reject(
                    fn ($size) => $orderedKeys->contains(mb_strtolower((string) $size))
                ))
                ->values();
        }, collect());


        $printAreas = $this->whenLoaded('printAreas', function () {
            return $this->printAreas->map(function ($printArea) {
                $printAreaImages = is_array($printArea->images) ? $printArea->images : [];
                $assetCategory = (string) ($printAreaImages['category'] ?? '');
                $assetKey = (string) ($printAreaImages['asset_key'] ?? '');
                $dynamicAsset = str_starts_with($assetCategory, 'product-category-')
                    ? AssetHelper::getAssetData($assetCategory, $assetKey)
                    : null;
                
                // $appUrl = config('app.url');
                // $imageUrl = null;

                // if ($printArea->images) {
                //     if (str_contains($appUrl, 'ngrok')) {
                //         $imageUrl = 'https://murray-frames-ethernet-fashion.trycloudflare.com' . Storage::url($printArea->images);
                //     } else {
                //         $imageUrl = url(Storage::url($printArea->images));
                //     }
                // }

                $payload = [
                    'id' => $printArea->id,
                    'title' => $printArea->title,
                    'area_width' => $printArea->area_width,
                    'area_height' => $printArea->area_height,
                    'unit' => $printArea->unit,
                    'position_x' => $printArea->position_x,
                    'position_y' => $printArea->position_y,
                    'tshirt_size' => $printArea->tshirt_size,
                    'display_order' => $printArea->display_order,
                    'price' => $printArea->price,
                    'is_active' => (bool) $printArea->is_active,
                   // 'image' => $printArea->images ? url(Storage::url($printArea->images)) : null,
                    'image' => $assetKey,
                    'image_url' => $dynamicAsset['url'] ?? null,
                ];

                // Exact customizer canvas region (logical 0-500 space) set in the CRM preview.
                // The Shopify app reads these in getDesignRegionFromPrintArea() and only
                // accepts strings, so they are cast explicitly. Omitted when not set so the
                // app keeps deriving the region from area/position fields.
                if (method_exists($printArea, 'hasDesignRegion') && $printArea->hasDesignRegion()) {
                    $payload['design_position_x'] = number_format((float) $printArea->design_position_x, 2, '.', '');
                    $payload['design_position_y'] = number_format((float) $printArea->design_position_y, 2, '.', '');
                    $payload['design_area_width'] = number_format((float) $printArea->design_area_width, 2, '.', '');
                    $payload['design_area_height'] = number_format((float) $printArea->design_area_height, 2, '.', '');
                }

                return $payload;
            })->values();
        }, collect());

        $fallbackImage = !empty($blank['image']) ? asset($blank['image']) : null;
        $finalImages = !empty($images) ? $images : ($fallbackImage ? [$fallbackImage] : []);

        $showcaseImage = null;
        if (is_array($this->images)) {
            $showcaseImage = $this->images['showcase_image_url'] ?? null;
            if (!$showcaseImage && !empty($this->images['showcase_image_path'])) {
                $disk = config('filesystems.default', 'public');
                $showcaseImage = Storage::disk($disk)->url($this->images['showcase_image_path']);
            }
        } elseif (is_string($this->images) && !empty($this->images)) {
            $showcaseImage = $this->images;
        }

        $resolvedImage = $showcaseImage ?: ($this->images['asset_key'] ?? $fallbackImage);

        return [
            'id' => $this->id,
            'productKey' => trim($brandKey . '-' . $modelKey, '-'),
            'name' => $blank['name'] ?? $this->title,
            'category' => $blank['category'] ?? $this->category,
            'brandCode' => $brandCode,
            'brand' => $brandName !== '' ? $brandName : null,
            'style' => $blank['style'] ?? $this->model_code,
            'model' => $blank['model'] ?? $this->model_code,
            'image' => $resolvedImage,
            'images' => $showcaseImage ? [$showcaseImage] : (!empty($images) ? $images : ($fallbackImage ? [$fallbackImage] : [])),
            'description' => $this->description,
            'status' => $this->status,
            'price' => $this->price,
            'currency' => 'USD',
            'colors' => $colors,
            'sizes' => $sizes,
            'variants' => $variants,
            'print_areas' => $printAreas,
            'color_mockups' => is_array($this->color_mockups) ? $this->color_mockups : (object) [],
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    private function resolveColorKey(string $colorName): string
    {
        $normalized = strtolower(trim($colorName));

        $commonColors = collect(config('dtfta.common_colors', []));
        $match = $commonColors->first(function ($item) use ($normalized) {
            return strtolower(trim((string) ($item['name'] ?? ''))) === $normalized;
        });

        if (is_array($match) && !empty($match['code'])) {
            return Str::lower(trim((string) $match['code']));
        }

        return Str::slug($colorName);
    }
}