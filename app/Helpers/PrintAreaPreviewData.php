<?php

namespace App\Helpers;

use App\Models\PrintArea;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the JSON the CRM print-area preview (public/js/print-area-preview.js) needs.
 *
 * Background image resolution mirrors the Shopify app (design-assets.ts →
 * getPrintAreaBackgroundImageUrl): the product's colour mockup for the placement
 * first, then the print area's catalog asset.
 */
class PrintAreaPreviewData
{
    public const PLACEMENTS = ['front', 'back', 'left_sleeve', 'right_sleeve'];

    /**
     * Products (with colour mockups and linked print area ids) for the product picker.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function products(): array
    {
        return Product::query()
            ->with(['printAreas' => function ($q) {
                $q->orderBy('display_order')->orderBy('title');
            }])
            ->orderBy('title')
            ->get()
            ->map(fn (Product $product) => self::product($product))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function product(Product $product): array
    {
        $colors = [];
        $mockups = is_array($product->color_mockups) ? $product->color_mockups : [];

        foreach ($mockups as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $urls = [];
            foreach (self::PLACEMENTS as $placement) {
                $url = self::mockupUrl($entry[$placement] ?? null);
                if ($url !== '') {
                    $urls[$placement] = $url;
                }
            }

            $colors[] = [
                'key' => (string) $key,
                'name' => (string) ($entry['name'] ?? $key),
                'hex' => (string) ($entry['hex'] ?? ''),
                'mockups' => (object) $urls,
            ];
        }

        $title = trim((string) $product->title);
        $label = $title !== '' ? $title : trim($product->brand . ' ' . $product->model_code);

        return [
            'id' => $product->id,
            'label' => $label !== '' ? $label : 'Product #' . $product->id,
            'status' => (string) $product->status,
            'colors' => $colors,
            'print_area_ids' => $product->printAreas->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /**
     * All print areas, in the shape the customizer receives from DtftaProductResource,
     * plus asset URLs and how many products share each area.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function printAreas(): array
    {
        return PrintArea::query()
            ->withCount('products')
            ->orderBy('display_order')
            ->orderBy('title')
            ->get()
            ->map(fn (PrintArea $area) => self::printArea($area))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function printArea(PrintArea $area): array
    {
        $assetKey = is_array($area->images) ? (string) ($area->images['asset_key'] ?? '') : '';
        $assetCategory = is_array($area->images) ? (string) ($area->images['category'] ?? '') : '';

        return [
            'id' => $area->id,
            'title' => (string) $area->title,
            'unit' => (string) ($area->unit ?? ''),
            'area_width' => $area->area_width !== null ? (string) $area->area_width : '',
            'area_height' => $area->area_height !== null ? (string) $area->area_height : '',
            'position_x' => $area->position_x !== null ? (string) $area->position_x : '',
            'position_y' => $area->position_y !== null ? (string) $area->position_y : '',
            'design_position_x' => $area->design_position_x !== null ? (string) $area->design_position_x : '',
            'design_position_y' => $area->design_position_y !== null ? (string) $area->design_position_y : '',
            'design_area_width' => $area->design_area_width !== null ? (string) $area->design_area_width : '',
            'design_area_height' => $area->design_area_height !== null ? (string) $area->design_area_height : '',
            'display_order' => (int) $area->display_order,
            'is_active' => (bool) $area->is_active,
            'asset_key' => $assetKey,
            'asset_category' => $assetCategory,
            'products_count' => (int) ($area->products_count ?? 0),
            'edit_url' => route('crm.print-areas.edit', $area->id),
            'region_url' => route('crm.print-areas.region.update', $area->id),
        ];
    }

    /**
     * Every catalog asset keyed by asset_key → [primary, fallback] URLs.
     *
     * The Shopify app loads the `.png` version of each customizer asset
     * (PRODUCT_KEY_TO_DESIGN_ASSET), while config/assets.php lists `.jpg`. The
     * preview tries the app's `.png` first and falls back to the configured file.
     *
     * @return array<string, array{primary: string, fallback: string, name: string}>
     */
    public static function assets(): array
    {
        $result = [];

        foreach (config('assets.categories', []) as $category) {
            foreach (($category['items'] ?? []) as $key => $item) {
                $path = ltrim((string) ($item['path'] ?? ''), '/');
                if ($path === '') {
                    continue;
                }

                $configured = self::assetUrl($path);
                $pngPath = preg_replace('/\.(jpe?g|webp)$/i', '.png', $path);
                $primary = $pngPath !== $path ? self::assetUrl($pngPath) : $configured;

                $result[(string) $key] = [
                    'primary' => $primary,
                    'fallback' => $configured,
                    'name' => (string) ($item['name'] ?? $key),
                ];
            }
        }

        return $result;
    }

    /**
     * Full payload for the preview script.
     *
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        return [
            'products' => self::products(),
            'printAreas' => self::printAreas(),
            'assets' => (object) self::assets(),
            'logicalSize' => PrintArea::DESIGN_LOGICAL_SIZE,
            'minEdge' => PrintArea::DESIGN_MIN_EDGE,
        ];
    }

    private static function assetUrl(string $path): string
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'assets/')) {
            return rtrim((string) config('app.cloudfront_url'), '/') . '/' . $path;
        }

        return asset($path);
    }

    /** Same rules as the colour mockup previews on the product pages. */
    private static function mockupUrl(mixed $path): string
    {
        if (!is_string($path) || trim($path) === '') {
            return '';
        }

        $path = ltrim(trim($path), '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $cloudfrontBase = rtrim((string) config('app.cloudfront_url'), '/');

        if (str_starts_with($path, 'assets/')) {
            return $cloudfrontBase . '/' . $path;
        }

        try {
            return Storage::disk('public')->exists($path)
                ? Storage::disk('public')->url($path)
                : $cloudfrontBase . '/' . $path;
        } catch (\Throwable $e) {
            return $cloudfrontBase . '/' . $path;
        }
    }
}
