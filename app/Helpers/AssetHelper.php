<?php

namespace App\Helpers;

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

        return $result;
    }

    public static function itemsWithUrls(string $category): array
    {
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
        return !empty(config("assets.categories.{$category}"));
    }

    public static function hasItem(string $category, string $itemKey): bool
    {
        return !empty(config("assets.categories.{$category}.items.{$itemKey}"));
    }

    public static function getAssetData(?string $category, ?string $key): ?array
    {
        if (!$category || !$key) {
            return null;
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
}