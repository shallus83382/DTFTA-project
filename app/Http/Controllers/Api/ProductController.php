<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AppSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\PrintArea;
use App\Http\Resources\DtftaProductResource;
use App\Services\ShopifyService;

class ProductController extends Controller
{
    public function __construct(private AppSignatureVerifier $appSignatureVerifier,private ShopifyService $ShopifyService )
    {
    }


    /**
     * GET /api/v1/products/signed
     * List products with app signature verification (timestamp + empty payload).
     */
    public function signedIndex(Request $request)
    {
        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');

        Log::info('Products signed API request received', [
            'path' => $request->path(),
            'method' => $request->method(),
            'timestamp_present' => $timestamp !== '',
            'signature_present' => $signature !== '',
            'signature_prefix' => $signature !== '' ? substr($signature, 0, 12) : null,
            'query' => $request->query(),
            'ip' => $request->ip(),
        ]);

        $isValidSignature = $this->appSignatureVerifier->verify(
            $request,
            $timestamp,
            $signature,
            AppSignatureVerifier::MODE_TIMESTAMP_ONLY
        );

        if (!$isValidSignature) {
            Log::warning('Products Get API rejected: invalid app signature', [
                'timestamp' => $timestamp,
                'signature_prefix' => $signature !== '' ? substr($signature, 0, 12) : null,
                'query' => $request->query(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid app signature',
            ], 401);
        }

        Log::info('Products signed API signature verified', [
            'timestamp' => $timestamp,
            'query' => $request->query(),
        ]);

        return $this->buildSignedProductListResponse($request);
    }

    /**
     * GET /api/v1/products/get/{id}
     * Signed single product fetch (timestamp + signature).
     */
    public function signedShow(Request $request, $id)
    {
        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');

        Log::info('Products signed show API request received', [
            'path' => $request->path(),
            'method' => $request->method(),
            'product_id' => $id,
            'timestamp_present' => $timestamp !== '',
            'signature_present' => $signature !== '',
            'signature_prefix' => $signature !== '' ? substr($signature, 0, 12) : null,
            'ip' => $request->ip(),
        ]);

        $isValidSignature = $this->appSignatureVerifier->verify(
            $request,
            $timestamp,
            $signature,
            AppSignatureVerifier::MODE_TIMESTAMP_ONLY
        );

        if (!$isValidSignature) {
            Log::warning('Products signed show API rejected: invalid app signature', [
                'product_id' => $id,
                'timestamp' => $timestamp,
                'signature_prefix' => $signature !== '' ? substr($signature, 0, 12) : null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid app signature',
            ], 401);
        }

        $product = Product::with('shop')->find($id);
        if (!$product) {
            Log::warning('Products signed show API product not found', [
                'product_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        Log::info('Products signed show API success', [
            'product_id' => $id,
            'shop_id' => $product->shop_id,
            'sku' => $product->sku,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully.',
            'data' => $product,
        ]);
    }

    /**
     * Dedicated product list builder for signed endpoint.
     * Kept separate from CRM product index flow.
     */
    private function buildSignedProductListResponse(Request $request)
    {
        $query = Product::query()
            ->with([
                'variants' => function ($q) {
                    $q->where('is_active', true)
                    ->orderBy('color')
                    ->orderBy('size');
                },
                'printAreas' => function ($q) {
                    $q->where('is_active', true)
                    ->orderBy('display_order')
                    ->orderBy('title');
                },
            ])
            ->where('status', 'active');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                ->orWhere('brand', 'like', '%' . $search . '%')
                ->orWhere('model_code', 'like', '%' . $search . '%')
                ->orWhere('category', 'like', '%' . $search . '%');
            });
        }

        $products = $query
            ->orderByDesc('created_at')
            ->get();

        Log::info('Products signed API response built', [
            'count' => $products->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data' => DtftaProductResource::collection($products),
        ]);
    }

    /**
     * POST /api/v1/products/create-in-shopify
     * Create custom product in Shopify with variants, print areas, artwork, and metafields.
     */
    public function createInShopify(Request $request,int $shopId,)
    {
        try {
            $validated = $request->validate([
                'shop_id' => ['required', 'integer', 'exists:shops,id'],
                'product_key' => ['nullable', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'descriptionHtml' => ['nullable', 'string'],
                'vendor' => ['nullable', 'string', 'max:255'],
                'productType' => ['nullable', 'string', 'max:255'],
                'status' => ['nullable', Rule::in(['ACTIVE', 'DRAFT', 'ARCHIVED'])],
                'tags' => ['nullable', 'array'],
                'tags.*' => ['string'],

                'options' => ['nullable', 'array'],
                'options.*.name' => ['required_with:options', 'string', 'max:255'],
                'options.*.values' => ['required_with:options', 'array', 'min:1'],
                'options.*.values.*' => ['string', 'max:255'],

                'variants' => ['nullable', 'array'],
                'variants.*.price' => ['nullable'],
                'variants.*.compareAtPrice' => ['nullable'],
                'variants.*.sku' => ['nullable', 'string', 'max:255'],
                'variants.*.barcode' => ['nullable', 'string', 'max:255'],
                'variants.*.optionValues' => ['nullable', 'array'],
                'variants.*.optionValues.*.optionName' => ['required_with:variants.*.optionValues', 'string', 'max:255'],
                'variants.*.optionValues.*.name' => ['required_with:variants.*.optionValues', 'string', 'max:255'],

                'print_areas' => ['nullable', 'array'],
                'print_areas.*.placement' => ['nullable', 'string', 'max:100'],
                'print_areas.*.title' => ['nullable', 'string', 'max:255'],
                'print_areas.*.width' => ['nullable'],
                'print_areas.*.height' => ['nullable'],
                'print_areas.*.unit' => ['nullable', 'string', 'max:20'],
                'print_areas.*.position_x' => ['nullable'],
                'print_areas.*.position_y' => ['nullable'],

                'artwork' => ['nullable', 'array'],
                'artwork.*' => ['nullable', 'string'],

                'print_plan' => ['nullable'],
            ]);

            /**
             * If product_key is provided, try to load local product data
             * and auto-fill print areas when not explicitly sent.
             */
            if (!empty($validated['product_key']) && empty($validated['print_areas'])) {
                $product = Product::with([
                    'printAreas' => function ($q) {
                        $q->where('is_active', true)
                            ->orderBy('display_order')
                            ->orderBy('title');
                    },
                    'variants' => function ($q) {
                        $q->where('is_active', true)
                            ->orderBy('color')
                            ->orderBy('size');
                    },
                ])->where(function ($q) use ($validated) {
                    $q->where('sku', $validated['product_key'])
                        ->orWhere('model_code', $validated['product_key'])
                        ->orWhere('id', $validated['product_key']);
                })->first();

                if ($product) {
                    $validated['vendor'] = $validated['vendor'] ?? $product->brand;
                    $validated['productType'] = $validated['productType'] ?? $product->category;

                    $validated['print_areas'] = $product->printAreas->map(function ($area) {
                        return [
                            'placement' => $area->title ? strtolower(trim((string) $area->title)) : null,
                            'title' => $area->title,
                            'width' => $area->area_width,
                            'height' => $area->area_height,
                            'unit' => $area->unit,
                            'position_x' => $area->position_x,
                            'position_y' => $area->position_y,
                        ];
                    })->values()->all();
                }
            }

            Log::info('Create Shopify product API request received', [
                'shop_id' => $shopId,
                'title' => $validated['title'] ?? null,
                'product_key' => $validated['product_key'] ?? null,
                'has_options' => !empty($validated['options']),
                'variant_count' => count($validated['variants'] ?? []),
                'print_area_count' => count($validated['print_areas'] ?? []),
                'artwork_count' => count($validated['artwork'] ?? []),
            ]);

            $payload = [
                'title' => $validated['title'],
                'descriptionHtml' => $validated['descriptionHtml'] ?? null,
                'vendor' => $validated['vendor'] ?? null,
                'productType' => $validated['productType'] ?? null,
                'status' => $validated['status'] ?? 'DRAFT',
                'tags' => $validated['tags'] ?? [],
                'options' => $validated['options'] ?? [],
                'variants' => $validated['variants'] ?? [],
                'print_areas' => $validated['print_areas'] ?? [],
                'artwork' => $validated['artwork'] ?? [],
                'print_plan' => $validated['print_plan'] ?? null,
            ];

            $result = $this->ShopifyService->createCustomProductWithPrintAreas($shopId, $payload);

            if (!$result['success']) {
                Log::warning('Create Shopify product API failed', [
                    'shop_id' => $shopId,
                    'message' => $result['message'] ?? 'Unknown error',
                    'errors' => $result['errors'] ?? [],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create product in Shopify',
                    'errors' => $result['errors'] ?? [],
                    'data' => $result['data'] ?? null,
                ], $result['status'] ?? 422);
            }

            Log::info('Create Shopify product API success', [
                'shop_id' => $shopId,
                'product_id' => data_get($result, 'data.product.id'),
                'variant_count' => count(data_get($result, 'data.variants', [])),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully in Shopify.',
                'data' => $result['data'] ?? null,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Create Shopify product API validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Create Shopify product API exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while creating product in Shopify.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    

    public function createInShopifySigned(Request $request)
    {
        $shop = strtolower(trim((string) (
            $request->header('X-Shop')
            ?? $request->input('shop_domain')
            ?? ''
        )));

        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');

        if (!$this->isValidShopDomain($shop)) {
            Log::warning('Install rejected: invalid shop domain', [
                'shop' => $shop,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.'
            ], 422);
        }

        $isValidSignature = $this->appSignatureVerifier->verify(
            $request,
            $timestamp,
            $signature,
            AppSignatureVerifier::MODE_TIMESTAMP_ONLY
        );

        if (!$isValidSignature) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app signature',
            ], 401);
        }


        $existingShop = Shop::where('shop_domain', $shop)
            ->orderByDesc('id')
            ->first();

        if (!$existingShop) {
            return response()->json([
                'success' => false,
                'message' => 'store not exit',
            ], 401);
        }

        return $this->createInShopify($request,$existingShop->id);
    }

    /**
     * Update user (admin or self)
     */
    private function isValidShopDomain(string $shop): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9\\-]*\\.myshopify\\.com$/i', $shop);
    }


}
