<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * POST /api/v1/products
     * Create or update product for a shop.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'nullable|exists:shops,id',
            'shopify_product_id' => 'nullable|string|max:255',
            'title' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'sub_category' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'product_type' => 'nullable|string|max:255',
            'tags' => 'nullable',
            'price' => 'nullable|numeric|min:0',
            'regular_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'tax_class' => 'nullable|string|max:255',
            'stock_quantity' => 'nullable|integer|min:0',
            'stock_status' => 'nullable|in:in_stock,out_of_stock',
            'track_inventory' => 'nullable|boolean',
            'status' => 'nullable|in:active,draft,archived,inactive',
            'featured_image' => 'nullable|string|max:2048',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'nullable|string|max:2048',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'shipping_class' => 'nullable|string|max:255',
            'options' => 'nullable|array',
            'images' => 'nullable|array',
            'payload' => 'nullable',
        ]);

        $query = Product::query()->where('shop_id', $validated['shop_id']);
        if (!empty($validated['shopify_product_id'])) {
            $query->where('shopify_product_id', $validated['shopify_product_id']);
        } elseif (!empty($validated['sku'])) {
            $query->where('sku', $validated['sku']);
        } else {
            $query->whereRaw('1 = 0');
        }

        $existing = $query->first();

        $payload = [
            'shop_id' => $validated['shop_id'],
            'shopify_product_id' => $validated['shopify_product_id'] ?? null,
            'title' => $validated['title'],
            'sku' => $validated['sku'] ?? null,
            'description' => $validated['description'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'category' => $validated['category'] ?? null,
            'sub_category' => $validated['sub_category'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'product_type' => $validated['product_type'] ?? null,
            'tags' => $this->normalizeTags($validated['tags'] ?? null),
            'regular_price' => $validated['regular_price'] ?? ($validated['price'] ?? null),
            'sale_price' => $validated['sale_price'] ?? null,
            'price' => $validated['regular_price'] ?? ($validated['price'] ?? null),
            'currency' => $validated['currency'] ?? 'USD',
            'tax_class' => $validated['tax_class'] ?? null,
            'stock_quantity' => $validated['stock_quantity'] ?? 0,
            'stock_status' => $validated['stock_status'] ?? (($validated['stock_quantity'] ?? 0) > 0 ? 'in_stock' : 'out_of_stock'),
            'track_inventory' => (bool) ($validated['track_inventory'] ?? true),
            'status' => $validated['status'] ?? 'active',
            'featured_image' => $validated['featured_image'] ?? null,
            'gallery_images' => $validated['gallery_images'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'length' => $validated['length'] ?? null,
            'width' => $validated['width'] ?? null,
            'height' => $validated['height'] ?? null,
            'shipping_class' => $validated['shipping_class'] ?? null,
            'options' => $validated['options'] ?? null,
            'images' => $validated['images'] ?? null,
            'payload' => $validated['payload'] ?? null,
        ];

        if ($existing) {
            $existing->update($payload);
            $product = $existing->fresh('shop');
        } else {
            $product = Product::create($payload)->load('shop');
        }

        return response()->json([
            'success' => true,
            'message' => $existing ? 'Product updated successfully.' : 'Product created successfully.',
            'data' => $product,
        ], $existing ? 200 : 201);
    }

    /**
     * GET /api/v1/products
     * List products with optional filters.
     */
    public function index(Request $request)
    {
        $query = Product::with('shop');

        if ($request->filled('shop_id')) {
            $query->where('shop_id', (int) $request->query('shop_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($sub) use ($search) {
                $sub->where('title', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('shopify_product_id', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhere('sub_category', 'like', '%' . $search . '%')
                    ->orWhere('brand', 'like', '%' . $search . '%')
                    ->orWhere('product_type', 'like', '%' . $search . '%');
            });
        }

        $perPage = max(5, min((int) $request->query('per_page', 20), 100));
        $products = $query->orderByDesc('created_at')->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data' => $products,
        ]);
    }

    /**
     * GET /api/v1/products/{id}
     * Show single product.
     */
    public function show($id)
    {
        $product = Product::with('shop')->find($id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully.',
            'data' => $product,
        ]);
    }

    /**
     * PUT /api/v1/products/{id}
     * Update product.
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $validated = $request->validate([
            'shop_id' => 'sometimes|exists:shops,id',
            'shopify_product_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'shopify_product_id')
                    ->ignore($product->id)
                    ->where(function ($query) use ($request, $product) {
                        $shopId = (int) ($request->input('shop_id', $product->shop_id));
                        return $query->where('shop_id', $shopId);
                    }),
            ],
            'title' => 'sometimes|required|string|max:255',
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'sku')
                    ->ignore($product->id)
                    ->where(function ($query) use ($request, $product) {
                        $shopId = (int) ($request->input('shop_id', $product->shop_id));
                        return $query->where('shop_id', $shopId);
                    }),
            ],
            'description' => 'sometimes|nullable|string',
            'short_description' => 'sometimes|nullable|string|max:255',
            'category' => 'sometimes|nullable|string|max:255',
            'sub_category' => 'sometimes|nullable|string|max:255',
            'brand' => 'sometimes|nullable|string|max:255',
            'product_type' => 'sometimes|nullable|string|max:255',
            'tags' => 'sometimes|nullable',
            'price' => 'sometimes|nullable|numeric|min:0',
            'regular_price' => 'sometimes|nullable|numeric|min:0',
            'sale_price' => 'sometimes|nullable|numeric|min:0',
            'currency' => 'sometimes|nullable|string|max:10',
            'tax_class' => 'sometimes|nullable|string|max:255',
            'stock_quantity' => 'sometimes|nullable|integer|min:0',
            'stock_status' => 'sometimes|nullable|in:in_stock,out_of_stock',
            'track_inventory' => 'sometimes|boolean',
            'status' => 'sometimes|nullable|in:active,draft,archived,inactive',
            'featured_image' => 'sometimes|nullable|string|max:2048',
            'gallery_images' => 'sometimes|nullable|array',
            'gallery_images.*' => 'nullable|string|max:2048',
            'weight' => 'sometimes|nullable|numeric|min:0',
            'length' => 'sometimes|nullable|numeric|min:0',
            'width' => 'sometimes|nullable|numeric|min:0',
            'height' => 'sometimes|nullable|numeric|min:0',
            'shipping_class' => 'sometimes|nullable|string|max:255',
            'options' => 'sometimes|nullable|array',
            'images' => 'sometimes|nullable|array',
            'payload' => 'sometimes|nullable',
        ]);

        if (array_key_exists('tags', $validated)) {
            $validated['tags'] = $this->normalizeTags($validated['tags']);
        }

        if (array_key_exists('regular_price', $validated)) {
            $validated['price'] = $validated['regular_price'];
        } elseif (array_key_exists('price', $validated)) {
            $validated['regular_price'] = $validated['price'];
        }

        if (array_key_exists('stock_quantity', $validated) && !array_key_exists('stock_status', $validated)) {
            $validated['stock_status'] = ((int) $validated['stock_quantity']) > 0 ? 'in_stock' : 'out_of_stock';
        }

        if (array_key_exists('featured_image', $validated)) {
            $oldFeatured = (string) ($product->featured_image ?? '');
            $newFeatured = (string) ($validated['featured_image'] ?? '');
            if ($oldFeatured !== '' && $oldFeatured !== $newFeatured) {
                $this->deleteManagedPublicFile($oldFeatured);
            }
        }

        if (array_key_exists('gallery_images', $validated)) {
            $oldGallery = is_array($product->gallery_images) ? $product->gallery_images : [];
            $newGallery = is_array($validated['gallery_images']) ? $validated['gallery_images'] : [];
            $removedPaths = array_diff($oldGallery, $newGallery);
            foreach ($removedPaths as $removedPath) {
                $this->deleteManagedPublicFile((string) $removedPath);
            }
        }

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product->fresh('shop'),
        ]);
    }

    /**
     * DELETE /api/v1/products/{id}
     * Soft-delete product.
     */
    public function destroy($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $this->deleteManagedPublicFile((string) ($product->featured_image ?? ''));
        $gallery = is_array($product->gallery_images) ? $product->gallery_images : [];
        foreach ($gallery as $galleryPath) {
            $this->deleteManagedPublicFile((string) $galleryPath);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }
    /**
     * Delete a file from the public disk if it's a relative path.
     * This helps clean up old images when they are replaced or removed.
     */
    private function deleteManagedPublicFile(string $path): void
    {
        $path = trim($path);
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
    /**
     * Normalize tags input into an array of trimmed strings.
     * Accepts either an array of tags or a comma-separated string.
     */
    private function normalizeTags($tags): ?array
    {
        if (is_array($tags)) {
            $normalized = array_values(array_filter(array_map(function ($tag) {
                return trim((string) $tag);
            }, $tags), fn($tag) => $tag !== ''));
            return empty($normalized) ? null : $normalized;
        }

        if (is_string($tags)) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $tags)), fn($tag) => $tag !== ''));
            return empty($parts) ? null : $parts;
        }

        return null;
    }
}
