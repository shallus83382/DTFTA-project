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
            $item['url'] = asset($item['path']);
            $item['thumbnail_url'] = asset($item['thumbnail'] ?? $item['path']);
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
            'url' => asset($item['path']),
            'thumbnail' => asset($item['thumbnail'] ?? $item['path']),
            'key' => $key,
            'category' => $category,
        ];
    }
}