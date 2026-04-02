<?php

namespace App\Http\Controllers;

use App\Helpers\AssetHelper;
use Illuminate\Http\JsonResponse;

class StaticAssetController extends Controller
{
    public function assetsByCategory(string $category): JsonResponse
    {
        if (!AssetHelper::hasCategory($category)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid category.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'category' => $category,
            'assets' => AssetHelper::itemsWithUrls($category),
        ]);
    }
}