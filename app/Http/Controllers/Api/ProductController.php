<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Models\CustomProduct;
use App\Services\AppSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\PrintArea;
use App\Http\Resources\DtftaProductResource;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(private AppSignatureVerifier $appSignatureVerifier, private ShopifyService $ShopifyService)
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

    
    private function createInShopify(Request $request, int $shopId)
    {

        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'productId' => ['nullable', 'integer', 'exists:products,id'],
                'productKey' => ['nullable', 'string', 'max:255'],
                'title' => ['nullable', 'string', 'max:255'],
                'printPlan' => ['nullable'],
                'artworkUrls' => ['nullable', 'array'],
                'artworkUrls.*' => ['nullable', 'string'],
            ]);

            if (empty($validated['productId']) && empty($validated['productKey'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either productId or productKey is required.',
                ], 422);
            }

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
            ])
                ->when(!empty($validated['productId']), function ($q) use ($validated) {
                    $q->where('id', $validated['productId']);
                })
                ->when(empty($validated['productId']) && !empty($validated['productKey']), function ($q) use ($validated) {
                    $q->where(function ($sub) use ($validated) {
                        $sub->where('sku', $validated['productKey'])
                            ->orWhere('model_code', $validated['productKey'])
                            ->orWhere('id', $validated['productKey']);
                    });
                })
                ->first();

            if (!$product) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Product not found in database.',
                ], 404);
            }

            $title = $validated['title']
                ?? $product->title
                ?? $product->name
                ?? 'Custom Product';

            $descriptionHtml = $product->description ?? null;
            $vendor = 'DFTA';
            $productType = $product->category ?? null;
            $status = 'DRAFT';
            $tags = [$product->category,$product->brand];

            $colors = $product->variants->pluck('color')->filter()->unique()->values()->all();
            $sizes = $product->variants->pluck('size')->filter()->unique()->values()->all();

            $options = array_values(array_filter([
                !empty($colors) ? [
                    'name' => 'Color',
                    'values' => $colors,
                ] : null,
                !empty($sizes) ? [
                    'name' => 'Size',
                    'values' => $sizes,
                ] : null,
            ]));

            $variants = $product->variants->map(function ($variant) {
                return [
                    'db_variant_id' => $variant->id,
                    'price' => 1,
                    'compareAtPrice' => $variant->compare_at_price,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'optionValues' => array_values(array_filter([
                        $variant->color ? [
                            'optionName' => 'Color',
                            'name' => $variant->color,
                        ] : null,
                        $variant->size ? [
                            'optionName' => 'Size',
                            'name' => $variant->size,
                        ] : null,
                    ])),
                ];
            })->values()->all();

            $printAreas = $product->printAreas->map(function ($area) {
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

            /*
             * FIX:
             * artworkUrls may be associative, e.g.
             * [
             *   "ls front" => "data:image/png;base64,...",
             *   "back" => "data:image/png;base64,..."
             * ]
             *
             * So we must not assume the map key is numeric.
             */
            $artworksSource = collect($validated['artworkUrls'] ?? []);

            $artworks = $artworksSource
                ->map(function ($artworkValue, $key) use ($printAreas, $shopId, $product) {
                    $placementFromKey = is_string($key) ? strtolower(trim($key)) : null;
            
                    return [
                        'placement' => $placementFromKey,
                        'source' => $artworkValue,
                    ];
                })
                ->values()
                ->map(function ($item, $index) use ($printAreas, $shopId, $product) {
                    $storedUrl = $this->storeArtworkFile(
                        $item['source'],
                        $shopId,
                        $product->id,
                        $item['placement'] ?: ($printAreas[$index]['placement'] ?? null),
                        $index
                    );
            
                    return [
                        'placement' => $item['placement'] ?: ($printAreas[$index]['placement'] ?? null),
                        'title' => 'Artwork ' . ($index + 1),
                        'url' => $storedUrl,
                        'source_code' => $item['source'],
                    ];
                })
                ->all();

            $payload = [
                'title' => $title,
                'descriptionHtml' => $descriptionHtml,
                'vendor' => $vendor,
                'productType' => $productType,
                'status' => $status,
                'tags' => $tags,
                'options' => $options,
                'variants' => $variants,
                'print_areas' => $printAreas,
                'artwork' => $artworks,
                'print_plan' => $validated['printPlan'] ?? null,
            ];

            Log::info('Create Shopify product request prepared', [
                'shop_id' => $shopId,
                'product_id' => $product->id,
                'product_key' => $validated['productKey'] ?? null,
                'title' => $payload['title'],
                'variant_count' => count($variants),
                'print_area_count' => count($printAreas),
                'artwork_count' => count($artworks),
                'artwork_keys' => array_keys($validated['artworkUrls'] ?? []),
            ]);

            $customProduct = CustomProduct::create([
                'shop_id' => $shopId,
                'product_id' => $product->id,
                'product_key' => $validated['productKey'] ?? (string) $product->id,
                'title' => $payload['title'],
                'vendor' => $payload['vendor'],
                'product_type' => $payload['productType'],
                'status' => $payload['status'],
                'description_html' => $payload['descriptionHtml'],
                'tags' => $payload['tags'],
                'options' => $payload['options'],
                'print_areas' => $payload['print_areas'],
                'print_plan' => $payload['print_plan'],
            ]);

            foreach ($variants as $variant) {
                $customProduct->variants()->create([
                    'product_variant_id' => $variant['db_variant_id'] ?? null,
                    'title' => null,
                    'sku' => $variant['sku'] ?? null,
                    'barcode' => $variant['barcode'] ?? null,
                    'price' => $variant['price'] ?? null,
                    'compare_at_price' => $variant['compareAtPrice'] ?? null,
                    'option_values' => $variant['optionValues'] ?? [],
                    'source_payload' => $variant,
                ]);
            }

            foreach ($artworks as $artwork) {
                $customProduct->artworks()->create([
                    'custom_product_variant_id' => null,
                    'placement' => $artwork['placement'] ?? null,
                    'title' => $artwork['title'] ?? null,
                    'artwork_url' => $artwork['url'] ?? null,
                    // 'meta' => [
                    //     'source_code' => $artwork['source_code'] ?? null,
                    // ],
                ]);
            }

            $payload['line_item_meta'] = [
                                                ['template_id'=>$customProduct->id],
                                         ];

            $result = $this->ShopifyService->createCustomProductWithPrintAreas($shopId, $payload);

            if (!($result['success'] ?? false)) {
                DB::rollBack();

                Log::warning('Create Shopify product failed', [
                    'shop_id' => $shopId,
                    'message' => $result['message'] ?? 'Unknown error',
                    'errors' => $result['errors'] ?? [],
                    'payload' => $payload,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create product in Shopify.',
                    'errors' => $result['errors'] ?? [],
                    'data' => $result['data'] ?? null,
                    'requested_payload' => $payload,
                ], $result['status'] ?? 422);
            }

            $shopifyProductId = data_get($result, 'data.product.id');
            $shopifyVariants = data_get($result, 'data.variants', []);

            $customProduct->update([
                'shopify_product_id' => $shopifyProductId,
            ]);


            foreach ($shopifyVariants as $shopifyVariant) {
                $sku = data_get($shopifyVariant, 'inventoryItem.sku');
                $shopifyVariantId = data_get($shopifyVariant, 'id');


                if (!$sku || !$shopifyVariantId) {
                    continue;
                }

                $customProduct->variants()
                    ->where('sku', $sku)
                    ->update([
                        'shopify_variant_id' => $shopifyVariantId,
                    ]);



            }

            DB::commit();


            return response()->json([
                'success' => true,
                'message' => 'Product created successfully in Shopify.',
                'data' => [
                    'custom_product_id' => $customProduct->id,
                    'shopify' => $result['data'] ?? null,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            Log::warning('Create Shopify product validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Create Shopify product exception', [
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

    /**
     * POST /api/v1/products/create-in-shopify-signed
     */
    public function createInShopifySigned(Request $request)
    {
        $shop = strtolower(trim((string) (
            $request->header('X-Shop')
            ?? $request->input('shop')
            ?? $request->input('shop_domain')
            ?? ''
        )));

        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');

        if (!$this->isValidShopDomain($shop)) {
            Log::warning('Create Shopify signed rejected: invalid shop domain', [
                'shop' => $shop,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.',
            ], 422);
        }

        // Enable signature verification in production
        // $isValidSignature = $this->appSignatureVerifier->verify(
        //     $request,
        //     $timestamp,
        //     $signature,
        //     AppSignatureVerifier::MODE_TIMESTAMP_ONLY
        // );
        //
        // if (!$isValidSignature) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Invalid app signature',
        //     ], 401);
        // }

        $existingShop = Shop::where('shop_domain', $shop)
            ->orderByDesc('id')
            ->first();

        if (!$existingShop) {
            return response()->json([
                'success' => false,
                'message' => 'Store does not exist.',
            ], 404);
        }

        Log::info('Signed request shop found', [
            'shop_domain' => $shop,
            'shop_id' => $existingShop->id,
        ]);

        return $this->createInShopify($request, $existingShop->id);
    }

    private function isValidShopDomain(string $shop): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/i', $shop);
    }

    private function storeArtworkFile(
        string $source,
        int $shopId,
        int $productId,
        ?string $placement,
        int $index
    ): string {
        if ($this->isDataUrl($source)) {
            return $this->storeBase64Artwork($source, $shopId, $productId, $placement, $index);
        }
    
        // Already a normal URL, keep as is
        return $source;
    }
    
    private function isDataUrl(string $value): bool
    {
        return str_starts_with($value, 'data:');
    }
    
    private function storeBase64Artwork(
        string $dataUrl,
        int $shopId,
        int $productId,
        ?string $placement,
        int $index
    ): string {
        if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $dataUrl, $matches)) {
            throw new \Exception('Invalid base64 artwork format.');
        }
    
        $mimeType = strtolower($matches[1]);
        $base64Data = $matches[2];
    
        $extension = $this->mimeTypeToExtension($mimeType);
    
        if (!$extension) {
            throw new \Exception('Unsupported artwork mime type: ' . $mimeType);
        }
    
        $binaryData = base64_decode($base64Data, true);
    
        if ($binaryData === false) {
            throw new \Exception('Failed to decode base64 artwork.');
        }
    
        $safePlacement = $placement
            ? preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($placement))
            : 'artwork';
    
        $fileName = sprintf(
            '%s_%s_%s.%s',
            now()->format('YmdHis'),
            $safePlacement,
            $index + 1,
            $extension
        );
    
        $path = "artworks/shop_{$shopId}/product_{$productId}/{$fileName}";
    
        Storage::disk('public')->put($path, $binaryData);
    
        return Storage::disk('public')->url($path);
    }
    
    private function mimeTypeToExtension(string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => null,
        };
    }
}