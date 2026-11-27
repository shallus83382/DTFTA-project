<?php

namespace App\Helpers;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\Storage;

class AssetHelper
{
    public static function all(): array
    {
        return config('assets.categories', []);
    }

    public static function categoriesForSelect(): array
    {
        $categories = self::all();
        $result = [];

        foreach ($categories as $key => $category) {
            $result[$key] = $category['label'] ?? $key;
        }

        foreach (ProductCategory::query()->orderBy('name')->get(['id', 'name']) as $category) {
            $result[self::productCategoryKey($category->id)] = $category->name;
        }

        return $result;
    }

    public static function itemsWithUrls(string $category): array
    {
        if ($productCategory = self::findProductCategory($category)) {
            $assetKey = self::productCategoryAssetKey($productCategory->id);
            $url = self::productCategoryImageUrl($productCategory);

            return [
                $assetKey => [
                    'key' => $assetKey,
                    'name' => $productCategory->name,
                    'url' => $url,
                    'thumbnail_url' => $url,
                ],
            ];
        }

        $items = config("assets.categories.{$category}.items", []);

        foreach ($items as $itemKey => &$item) {
            $item['key'] = $itemKey;
            $item['url'] = self::resolveUrl($item['path']);
            $item['thumbnail_url'] = self::resolveUrl($item['thumbnail'] ?? $item['path']);
        }

        return $items;
    }

    public static function hasCategory(string $category): bool
    {
        return !empty(config("assets.categories.{$category}")) || self::findProductCategory($category) !== null;
    }

    public static function hasItem(string $category, string $itemKey): bool
    {
        if ($productCategory = self::findProductCategory($category)) {
            return $itemKey === self::productCategoryAssetKey($productCategory->id);
        }

        return !empty(config("assets.categories.{$category}.items.{$itemKey}"));
    }

    public static function getAssetData(?string $category, ?string $key): ?array
    {
        if (!$category || !$key) {
            return null;
        }

        if ($productCategory = self::findProductCategory($category)) {
            if ($key !== self::productCategoryAssetKey($productCategory->id)) {
                return null;
            }

            $url = self::productCategoryImageUrl($productCategory);

            return [
                'name' => $productCategory->name,
                'url' => $url,
                'thumbnail' => $url,
                'key' => $key,
                'category' => $category,
            ];
        }

        $item = config("assets.categories.{$category}.items.{$key}");

        if (!$item) {
            return null;
        }

        return [
            'name' => $item['name'] ?? $key,
            'url' => self::resolveUrl($item['path']),
            'thumbnail' => self::resolveUrl($item['thumbnail'] ?? $item['path']),
            'key' => $key,
            'category' => $category,
        ];
    }

    private static function resolveUrl(?string $path): string
    {
        if (!$path) {
            return '';
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'assets/')) {
            return rtrim((string) config('app.cloudfront_url'), '/').'/'.$path;
        }

        return asset($path);
    }

    private static function productCategoryKey(int $id): string
    {
        return 'product-category-'.$id;
    }

    private static function productCategoryAssetKey(int $id): string
    {
        return 'product-category-image-'.$id;
    }

    private static function findProductCategory(string $key): ?ProductCategory
    {
        if (!preg_match('/^product-category-(\d+)$/D', $key, $matches)) {
            return null;
        }

        return ProductCategory::query()->find((int) $matches[1]);
    }

    private static function productCategoryImageUrl(ProductCategory $category): string
    {
        $disk = str_starts_with($category->image_path, 'product-category/') ? 's3' : 'public';

        return Storage::disk($disk)->url($category->image_path);
    }
}