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
use App\Http\Resources\CustomProductResource;
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
                'artworkUrls.*' => ['nullable'],
                'variantArtworkPayload' => ['nullable', 'array'],
                'variantArtworkPayload.*.variantId' => ['required_with:variantArtworkPayload', 'string'],
                'variantArtworkPayload.*.variantSku' => ['nullable', 'string'],
                'variantArtworkPayload.*.colorCode' => ['nullable', 'string'],
                'variantArtworkPayload.*.colorName' => ['nullable', 'string'],
                'variantArtworkPayload.*.printPlan' => ['nullable', 'string'],
                'variantArtworkPayload.*.artworkUrls' => ['nullable', 'array'],
                'variantArtworkPayload.*.artworkUrls.*' => ['nullable'],
                'variantArtworkPayload.*.printableAreas' => ['nullable', 'array'],
                'variantArtworkPayload.*.printableAreas.*.placement' => ['nullable', 'string'],
                'variantArtworkPayload.*.printableAreas.*.title' => ['nullable', 'string'],
                'variantArtworkPayload.*.printableAreas.*.artwork' => ['nullable', 'string'],
                'variantArtworkPayload.*.printableAreas.*.printSize' => ['nullable', 'array'],
                'variantArtworkPayload.*.printableAreas.*.designableRegion' => ['nullable', 'array'],
                'variantArtworkPayload.*.printableAreas.*.unit' => ['nullable', 'string'],
                'variantArtworkPayload.*.printableAreas.*.backgroundImage' => ['nullable', 'string'],
                'variantArtworkPayload.*.printableAreas.*.editorState' => ['nullable'],
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
    
            $shop = Shop::find($shopId);
    
            if (!$shop) {
                DB::rollBack();
    
                return response()->json([
                    'success' => false,
                    'message' => 'Shop not found.',
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
            $tags = array_values(array_filter([$product->category, $product->brand]));
    
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
    
            $variantArtworkPayload = collect($validated['variantArtworkPayload'] ?? []);
            $normalizedArtworkUrls = $this->normalizeArtworkUrlsPayload($request->input('artworkUrls', []));
            $artworksSource = collect($normalizedArtworkUrls);

            Log::info('Create Shopify product artwork payload received', [
                'shop_id' => $shopId,
                'product_id' => $product->id,
                'variant_artwork_payload_count' => $variantArtworkPayload->count(),
                'legacy_artwork_urls_count' => $artworksSource->count(),
            ]);
    
            $fallbackArtworks = $artworksSource
                ->map(function ($item, $index) use ($printAreas, $shopId, $product) {
                    $resolvedPlacement = data_get($item, 'placement') ?: ($printAreas[$index]['placement'] ?? null);
                    $sourceValue = (string) data_get($item, 'source', '');
    
                    $storedUrl = $this->storeArtworkFile(
                        $sourceValue,
                        $shopId,
                        $product->id,
                        $resolvedPlacement,
                        $index
                    );
    
                    return [
                        'custom_product_variant_id' => null,
                        'product_variant_id' => null,
                        'variant_payload_id' => null,
                        'variant_sku' => null,
                        'variant_color_code' => null,
                        'variant_color_name' => null,
                        'placement' => $resolvedPlacement,
                        'title' => 'Artwork ' . ($index + 1),
                        'artwork_url' => $storedUrl,
                        'meta' => [
                            'source_code' => $sourceValue,
                            'source' => 'legacy_artworkUrls',
                            'payload_keys' => data_get($item, 'keys', []),
                            'payload_color_token' => data_get($item, 'color_token'),
                        ],
                    ];
                })
                ->all();

            $productColorOrder = $product->variants
                ->pluck('color')
                ->filter(fn ($color) => filled($color))
                ->map(fn ($color) => strtolower(trim((string) $color)))
                ->unique()
                ->values();

            $placementOccurrence = [];

            $expandedFallbackArtworks = collect($fallbackArtworks)
                ->flatMap(function ($artworkItem) use ($product, $productColorOrder, &$placementOccurrence) {
                    $sourceValue = (string) data_get($artworkItem, 'meta.source_code', '');
                    $artworkUrl = (string) ($artworkItem['artwork_url'] ?? '');
                    $placement = strtolower(trim((string) ($artworkItem['placement'] ?? 'artwork')));
                    $colorTokenFromPayload = (string) data_get($artworkItem, 'meta.payload_color_token', '');

                    $colorToken = $colorTokenFromPayload !== ''
                        ? strtolower($colorTokenFromPayload)
                        : $this->extractColorTokenFromArtworkSource($sourceValue !== '' ? $sourceValue : $artworkUrl);

                    if ($colorToken === null || $colorToken === '') {
                        $placementOccurrence[$placement] = ($placementOccurrence[$placement] ?? -1) + 1;
                        $colorIndex = $placementOccurrence[$placement];
                        $colorToken = (string) ($productColorOrder->get($colorIndex) ?? '');
                    }

                    if ($colorToken === null || $colorToken === '') {
                        return [$artworkItem];
                    }

                    $matchedVariants = $product->variants
                        ->filter(function ($variant) use ($colorToken) {
                            return $this->colorValuesMatch((string) ($variant->color ?? ''), $colorToken);
                        })
                        ->values();

                    if ($matchedVariants->isEmpty()) {
                        return [$artworkItem];
                    }

                    return $matchedVariants->map(function ($variant) use ($artworkItem, $colorToken) {
                        $meta = $artworkItem['meta'] ?? [];
                        $meta['fallback_color_token'] = $colorToken;
                        $meta['source'] = 'legacy_artworkUrls_expanded_by_color';
                        $meta['resolved_product_variant_id'] = $variant->id;
                        $meta['resolved_variant_sku'] = $variant->sku;
                        $meta['resolved_variant_color'] = $variant->color;
                        $meta['resolved_variant_size'] = $variant->size;

                        return array_merge($artworkItem, [
                            'product_variant_id' => $variant->id,
                            'variant_sku' => $variant->sku,
                            'variant_color_code' => $colorToken,
                            'variant_color_name' => $variant->color,
                            'variant_size' => $variant->size,
                            'title' => trim(($variant->color ?: $colorToken) . ($variant->size ? ' ' . $variant->size : '') . ' ' . ($artworkItem['title'] ?? 'Artwork')),
                            'meta' => $meta,
                        ]);
                    });
                })
                ->values()
                ->all();
    
            $variantArtworks = $variantArtworkPayload
                ->flatMap(function ($variantItem, $variantIndex) use ($shopId, $product) {
                    $variantId = (string) data_get($variantItem, 'variantId', '');
                    $variantSku = (string) data_get($variantItem, 'variantSku', '');
                    $colorCode = (string) data_get($variantItem, 'colorCode', '');
                    $colorName = (string) data_get($variantItem, 'colorName', '');
                    $printPlan = (string) data_get($variantItem, 'printPlan', '');
                    $variantPrintableAreas = collect(data_get($variantItem, 'printableAreas', []));
                    $variantSize = trim((string) data_get($variantItem, 'size', data_get($variantItem, 'variantSize', '')));

                    $targetVariants = collect();

                    if ($variantId !== '' && ctype_digit($variantId)) {
                        $targetVariants = $product->variants->where('id', (int) $variantId)->values();
                    }

                    if ($targetVariants->isEmpty() && $variantSku !== '') {
                        $targetVariants = $product->variants
                            ->filter(fn ($variant) => strcasecmp((string) $variant->sku, $variantSku) === 0)
                            ->values();
                    }

                    // Frontend artwork is often generated by color only.
                    // If no exact variant is provided, apply to all size variants of that color.
                    if ($targetVariants->isEmpty() && ($colorCode !== '' || $colorName !== '')) {
                        $needleColors = array_values(array_filter([
                            strtolower(trim($colorCode)),
                            strtolower(trim($colorName)),
                        ]));

                        $targetVariants = $product->variants
                            ->filter(function ($variant) use ($needleColors, $variantSize) {
                                $variantColor = strtolower(trim((string) ($variant->color ?? '')));
                                $colorMatch = collect($needleColors)->contains(function ($needleColor) use ($variantColor) {
                                    return $this->colorValuesMatch($variantColor, $needleColor);
                                });

                                if (!$colorMatch) {
                                    return false;
                                }

                                if ($variantSize === '') {
                                    return true;
                                }

                                return strcasecmp((string) $variant->size, $variantSize) === 0;
                            })
                            ->values();
                    }

                    if ($targetVariants->isEmpty() && $variantSku !== '') {
                        $variantSkuUpper = strtoupper($variantSku);
                        $targetVariants = $product->variants
                            ->filter(function ($variant) use ($variantSkuUpper) {
                                $dbSkuUpper = strtoupper((string) ($variant->sku ?? ''));
                                if ($dbSkuUpper === '') {
                                    return false;
                                }

                                return $dbSkuUpper === $variantSkuUpper
                                    || str_contains($dbSkuUpper, $variantSkuUpper)
                                    || str_contains($variantSkuUpper, $dbSkuUpper);
                            })
                            ->values();
                    }

                    if ($targetVariants->isEmpty()) {
                        $targetVariants = collect([null]);
                    }

                    Log::info('Variant artwork payload mapping resolved', [
                        'shop_id' => $shopId,
                        'product_id' => $product->id,
                        'variant_payload' => [
                            'variant_id' => $variantId,
                            'variant_sku' => $variantSku,
                            'color_code' => $colorCode,
                            'color_name' => $colorName,
                            'size' => $variantSize,
                            'printable_areas_count' => $variantPrintableAreas->count(),
                        ],
                        'matched_product_variant_ids' => $targetVariants
                            ->filter(fn ($v) => $v !== null)
                            ->map(fn ($v) => $v->id)
                            ->values()
                            ->all(),
                        'matched_product_variant_skus' => $targetVariants
                            ->filter(fn ($v) => $v !== null)
                            ->map(fn ($v) => $v->sku)
                            ->values()
                            ->all(),
                    ]);

                    return $variantPrintableAreas
                        ->filter(function ($area) {
                            return filled(data_get($area, 'artwork'));
                        })
                        ->values()
                        ->flatMap(function ($area, $areaIndex) use ($shopId, $product, $variantId, $variantSku, $colorCode, $colorName, $printPlan, $variantIndex, $targetVariants, $variantSize) {
                            $placement = strtolower(trim((string) data_get($area, 'placement', data_get($area, 'title', ''))));
                            $artworkSource = data_get($area, 'artwork');

                            $storedUrl = $this->storeArtworkFile(
                                $artworkSource,
                                $shopId,
                                $product->id,
                                $placement,
                                ($variantIndex * 1000) + $areaIndex
                            );

                            return $targetVariants->map(function ($targetVariant) use ($variantId, $variantSku, $colorCode, $colorName, $printPlan, $placement, $storedUrl, $area, $artworkSource, $variantSize) {
                                $resolvedColor = $targetVariant?->color ?: ($colorName ?: $colorCode);
                                $resolvedSize = $targetVariant?->size ?: ($variantSize !== '' ? $variantSize : null);
                                $resolvedSku = $targetVariant?->sku ?: $variantSku;

                                return [
                                    'custom_product_variant_id' => null,
                                    'product_variant_id' => $targetVariant?->id ?? (ctype_digit($variantId) ? (int) $variantId : $variantId),
                                    'variant_payload_id' => $variantId,
                                    'variant_sku' => $resolvedSku,
                                    'variant_color_code' => $colorCode,
                                    'variant_color_name' => $resolvedColor,
                                    'variant_size' => $resolvedSize,
                                    'placement' => $placement ?: null,
                                    'title' => trim(($resolvedColor ?: $colorCode ?: 'Variant') . ($resolvedSize ? ' ' . $resolvedSize : '') . ' ' . ucfirst($placement ?: 'artwork')),
                                    'artwork_url' => $storedUrl,
                                    'meta' => [
                                        'source' => 'variantArtworkPayload',
                                        'variant_id' => $variantId,
                                        'variant_sku' => $variantSku,
                                        'color_code' => $colorCode,
                                        'color_name' => $resolvedColor,
                                        'size' => $resolvedSize,
                                        'print_plan' => $printPlan,
                                        'print_size' => data_get($area, 'printSize'),
                                        'designable_region' => data_get($area, 'designableRegion'),
                                        'background_image' => data_get($area, 'backgroundImage'),
                                        'editor_state' => data_get($area, 'editorState'),
                                        'title' => data_get($area, 'title'),
                                        'unit' => data_get($area, 'unit'),
                                        'source_code' => $artworkSource,
                                    ],
                                ];
                            });
                        });
                })
                ->all();
    
            $artworks = !empty($variantArtworks) ? $variantArtworks : $expandedFallbackArtworks;

            // Keep full artwork list for local DB linkage, but only upload unique files to Shopify product media.
            $shopifyArtwork = collect($artworks)
                ->filter(function ($artwork) {
                    return filled($artwork['artwork_url'] ?? $artwork['url'] ?? null);
                })
                ->unique(function ($artwork) {
                    $url = strtolower(trim((string) ($artwork['artwork_url'] ?? $artwork['url'] ?? '')));
                    $sourceCode = trim((string) data_get($artwork, 'meta.source_code', ''));

                    return $url !== '' ? 'url:' . $url : 'source:' . md5($sourceCode);
                })
                ->values()
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
                'artwork' => $shopifyArtwork,
                'print_plan' => $validated['printPlan'] ?? null,
            ];
    
    
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
    
            $customVariantMap = [];
    
            foreach ($variants as $variant) {
                $createdVariant = $customProduct->variants()->create([
                    'product_variant_id' => $variant['db_variant_id'] ?? null,
                    'title' => null,
                    'sku' => $variant['sku'] ?? null,
                    'barcode' => $variant['barcode'] ?? null,
                    'price' => $variant['price'] ?? null,
                    'compare_at_price' => $variant['compareAtPrice'] ?? null,
                    'option_values' => $variant['optionValues'] ?? [],
                    'source_payload' => $variant,
                ]);
    
                if (!empty($variant['db_variant_id'])) {
                    $customVariantMap[(string) $variant['db_variant_id']] = $createdVariant;
                }
    
                if (!empty($variant['sku'])) {
                    $customVariantMap['sku:' . $variant['sku']] = $createdVariant;
                }
            }
    
            foreach ($artworks as $artwork) {
                $linkedCustomVariant = null;
    
                if (!empty($artwork['product_variant_id']) && isset($customVariantMap[(string) $artwork['product_variant_id']])) {
                    $linkedCustomVariant = $customVariantMap[(string) $artwork['product_variant_id']];
                } elseif (!empty($artwork['variant_sku']) && isset($customVariantMap['sku:' . $artwork['variant_sku']])) {
                    $linkedCustomVariant = $customVariantMap['sku:' . $artwork['variant_sku']];
                }
    
                $meta = $artwork['meta'] ?? [];
                if (!$linkedCustomVariant && !empty($artwork['variant_sku'])) {
                    $needleSku = strtoupper((string) $artwork['variant_sku']);
                    $linkedCustomVariant = $customProduct->variants()
                        ->get()
                        ->first(function ($customVariant) use ($needleSku) {
                            $dbSku = strtoupper((string) $customVariant->sku);
                            if ($dbSku === '') {
                                return false;
                            }

                            return $dbSku === $needleSku
                                || str_contains($dbSku, $needleSku)
                                || str_contains($needleSku, $dbSku);
                        });
                }

                if (!$linkedCustomVariant) {
                    $needleColor = strtolower(trim((string) ($artwork['variant_color_name'] ?? $artwork['variant_color_code'] ?? data_get($meta, 'color_name', data_get($meta, 'color_code', '')))));
                    $needleSize = trim((string) ($artwork['variant_size'] ?? data_get($meta, 'size', '')));

                    $linkedCustomVariant = $customProduct->variants()
                        ->get()
                        ->first(function ($customVariant) use ($needleColor, $needleSize) {
                            $optionValues = collect($customVariant->option_values ?? []);
                            $colorOption = strtolower(trim((string) optional($optionValues->first(fn ($opt) => strtolower((string) ($opt['optionName'] ?? '')) === 'color'))['name'] ?? ''));
                            $sizeOption = trim((string) optional($optionValues->first(fn ($opt) => strtolower((string) ($opt['optionName'] ?? '')) === 'size'))['name'] ?? '');

                            if ($needleColor === '' || !$this->colorValuesMatch($colorOption, $needleColor)) {
                                return false;
                            }

                            if ($needleSize === '') {
                                return true;
                            }

                            return strcasecmp($sizeOption, $needleSize) === 0;
                        });
                }

                if ($linkedCustomVariant) {
                    $meta['linked_custom_product_variant_id'] = $linkedCustomVariant->id;
                }

                Log::info('Custom artwork linked to local variant', [
                    'shop_id' => $shopId,
                    'custom_product_id' => $customProduct->id,
                    'product_variant_id' => $artwork['product_variant_id'] ?? null,
                    'variant_sku' => $artwork['variant_sku'] ?? null,
                    'variant_color' => $artwork['variant_color_name'] ?? $artwork['variant_color_code'] ?? null,
                    'variant_size' => $artwork['variant_size'] ?? data_get($meta, 'size'),
                    'artwork_url' => $artwork['artwork_url'] ?? $artwork['url'] ?? null,
                    'resolved_custom_product_variant_id' => $linkedCustomVariant?->id,
                    'resolved_custom_product_variant_sku' => $linkedCustomVariant?->sku,
                ]);
    
                $customProduct->artworks()->create([
                    'custom_product_variant_id' => $linkedCustomVariant?->id,
                    'placement' => $artwork['placement'] ?? null,
                    'title' => $artwork['title'] ?? null,
                    'artwork_url' => $artwork['artwork_url'] ?? $artwork['url'] ?? null,
                    'meta' => $meta,
                ]);
            }
    
            $payload['line_item_meta'] = [
                ['template_id' => $customProduct->id],
            ];
    



            $result = $this->ShopifyService->createCustomProductWithPrintAreas($shopId, $payload);
          //$result = [];
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
    
            $shopifyVariantIds = [];
            $customVariants = $customProduct->variants()->get();

            $expectedVariantCount = $customProduct->variants()->count();
            $fromBulkCreate = is_array($shopifyVariants) ? $shopifyVariants : [];

            if (!empty($shopifyProductId)) {
                $variantsFetchResult = $this->ShopifyService->getProductVariantsByProductId(
                    (int) $shopId,
                    (string) $shopifyProductId
                );

                if ($variantsFetchResult['success'] ?? false) {
                    $fetched = data_get($variantsFetchResult, 'data.variants', []);
                    if (!is_array($fetched)) {
                        $fetched = [];
                    }

                    // productVariantsBulkCreate returns all new variant GIDs immediately; a follow-up
                    // product { variants } query can briefly return only a subset. Merge by id so we
                    // never drop variants that only exist on the bulk-create response.
                    //
                    // Apply bulk-create nodes *after* the product query: the query response can be
                    // sparse (missing SKU / selectedOptions) for some variants; overwriting rich
                    // bulk-create payloads with those nodes breaks local matching for most variants.
                    $byVariantId = [];
                    foreach ($fetched as $v) {
                        $id = data_get($v, 'id');
                        if ($id !== null && $id !== '') {
                            $byVariantId[(string) $id] = $v;
                        }
                    }
                    foreach ($fromBulkCreate as $v) {
                        $id = data_get($v, 'id');
                        if ($id !== null && $id !== '') {
                            $byVariantId[(string) $id] = $v;
                        }
                    }
                    $shopifyVariants = array_values($byVariantId);

                    if ($expectedVariantCount > 0 && count($shopifyVariants) < $expectedVariantCount) {
                        Log::warning('Shopify variant list shorter than local variants after bulk/query merge', [
                            'shop_id' => $shopId,
                            'shopify_product_id' => $shopifyProductId,
                            'expected_variant_count' => $expectedVariantCount,
                            'from_bulk_create_count' => count($fromBulkCreate),
                            'from_product_query_count' => count($fetched),
                            'merged_count' => count($shopifyVariants),
                        ]);
                    }
                } else {
                    Log::warning('Fallback Shopify variant fetch failed', [
                        'shop_id' => $shopId,
                        'shopify_product_id' => $shopifyProductId,
                        'expected_variant_count' => $expectedVariantCount,
                        'initial_variant_count' => count($fromBulkCreate),
                        'result' => $variantsFetchResult,
                    ]);
                }
            }
    
            $mappedCustomVariantIds = [];
            foreach ($shopifyVariants as $shopifyVariant) {
                $shopifyVariantId = data_get($shopifyVariant, 'id');
                if (!$shopifyVariantId) {
                    continue;
                }

                $sku = data_get($shopifyVariant, 'inventoryItem.sku') ?? data_get($shopifyVariant, 'sku');
                $normalizedSku = strtoupper(trim((string) $sku));

                $matchedCustomVariant = null;
                if ($normalizedSku !== '') {
                    $matchedCustomVariant = $customVariants
                        ->first(function ($customVariant) use ($normalizedSku, $mappedCustomVariantIds) {
                            if (in_array((int) $customVariant->id, $mappedCustomVariantIds, true)) {
                                return false;
                            }

                            return strtoupper(trim((string) $customVariant->sku)) === $normalizedSku;
                        });
                }

                if (!$matchedCustomVariant) {
                    $matchedCustomVariant = $this->matchCustomVariantByShopifySelectedOptions(
                        $customVariants->reject(fn ($variant) => in_array((int) $variant->id, $mappedCustomVariantIds, true)),
                        $shopifyVariant
                    );
                }

                if (!$matchedCustomVariant) {
                    continue;
                }

                $shopifyVariantIds[] = $shopifyVariantId;
                $mappedCustomVariantIds[] = (int) $matchedCustomVariant->id;
    
                $matchedCustomVariant->update([
                    'shopify_variant_id' => $shopifyVariantId,
                ]);
    
                $customProduct->artworks()
                    ->where('custom_product_variant_id', $matchedCustomVariant->id)
                    ->get()
                    ->each(function ($artwork) use ($shopifyVariantId) {
                        $meta = $artwork->meta ?? [];
                        $meta['shopify_variant_id'] = $shopifyVariantId;
                        $artwork->update(['meta' => $meta]);
                    });
            }

            $shopifyMediaNodes = collect(data_get($result, 'data.media', []));
            $mediaByUrl = [];

            foreach ($shopifyMediaNodes as $mediaNode) {
                $mediaId = data_get($mediaNode, 'id');
                $mediaUrl = trim((string) data_get($mediaNode, 'image.url', ''));
                $mediaAlt = trim((string) data_get($mediaNode, 'alt', ''));

                if (!$mediaId) {
                    continue;
                }

                if ($mediaUrl !== '') {
                    $mediaByUrl[strtolower($mediaUrl)] = $mediaId;
                }

                if ($mediaAlt !== '') {
                    $mediaByUrl['alt:' . strtolower($mediaAlt)] = $mediaId;
                }
            }

            $variantMediaMap = [];

            $customProduct->artworks()
                ->whereNotNull('custom_product_variant_id')
                ->get()
                ->each(function ($artwork) use (&$variantMediaMap, $mediaByUrl) {
                    $artworkUrl = trim((string) $artwork->artwork_url);
                    $title = trim((string) $artwork->title);
                    $meta = $artwork->meta ?? [];

                    $shopifyMediaId = null;
                    if ($artworkUrl !== '' && isset($mediaByUrl[strtolower($artworkUrl)])) {
                        $shopifyMediaId = $mediaByUrl[strtolower($artworkUrl)];
                    } elseif ($title !== '' && isset($mediaByUrl['alt:' . strtolower($title)])) {
                        $shopifyMediaId = $mediaByUrl['alt:' . strtolower($title)];
                    }

                    if (!$shopifyMediaId || empty($meta['shopify_variant_id'])) {
                        return;
                    }

                    $meta['shopify_media_id'] = $shopifyMediaId;
                    $artwork->update(['meta' => $meta]);

                    $variantId = (string) $meta['shopify_variant_id'];
                    $variantMediaMap[$variantId] = $variantMediaMap[$variantId] ?? [];
                    $variantMediaMap[$variantId][] = $shopifyMediaId;
                });

            Log::info('Prepared Shopify variant-media map', [
                'shop_id' => $shopId,
                'custom_product_id' => $customProduct->id,
                'shopify_product_id' => $shopifyProductId,
                'shopify_media_count' => $shopifyMediaNodes->count(),
                'variant_media_map' => $variantMediaMap,
            ]);

            $variantMediaAssignments = [];
            foreach ($variantMediaMap as $shopifyVariantId => $mediaIds) {
                $uniqueMediaIds = array_values(array_unique($mediaIds));
                $singleMediaIdForVariant = !empty($uniqueMediaIds) ? [$uniqueMediaIds[0]] : [];

                $attachToVariant = $this->ShopifyService->attachMediaToProductVariant(
                    (int) $shopId,
                    (string) $shopifyProductId,
                    (string) $shopifyVariantId,
                    $singleMediaIdForVariant
                );

                $variantMediaAssignments[] = [
                    'variant_id' => $shopifyVariantId,
                    'media_ids' => $singleMediaIdForVariant,
                    'success' => $attachToVariant['success'] ?? false,
                    'message' => $attachToVariant['message'] ?? null,
                    'errors' => $attachToVariant['errors'] ?? [],
                    'data' => $attachToVariant['data'] ?? null,
                ];

                Log::info('Shopify variant media attach result', [
                    'shop_id' => $shopId,
                    'custom_product_id' => $customProduct->id,
                    'shopify_product_id' => $shopifyProductId,
                    'shopify_variant_id' => $shopifyVariantId,
                    'media_ids' => $singleMediaIdForVariant,
                    'success' => $attachToVariant['success'] ?? false,
                    'message' => $attachToVariant['message'] ?? null,
                    'errors' => $attachToVariant['errors'] ?? [],
                ]);

                if (!($attachToVariant['success'] ?? false)) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $attachToVariant['message'] ?? 'Failed to link media to Shopify variants.',
                        'errors' => $attachToVariant['errors'] ?? [],
                        'data' => $attachToVariant['data'] ?? null,
                    ], $attachToVariant['status'] ?? 422);
                }
            }
    
            $deliveryProfileResult = null;
    
            if (!empty($shop->shipping_profile_id) && !empty($shopifyVariantIds)) {
                $deliveryProfileResult = $this->ShopifyService->assignVariantsToDeliveryProfile(
                    (int) $shopId,
                    (string) $shop->shipping_profile_id,
                    $shopifyVariantIds
                );
    
                if (!($deliveryProfileResult['success'] ?? false)) {
                    DB::rollBack();
    
                    Log::warning('Assign variants to delivery profile failed', [
                        'shop_id' => $shopId,
                        'shipping_profile_id' => $shop->shipping_profile_id,
                        'variant_ids' => $shopifyVariantIds,
                        'result' => $deliveryProfileResult,
                    ]);
    
                    return response()->json([
                        'success' => false,
                        'message' => $deliveryProfileResult['message'] ?? 'Failed to assign variants to delivery profile.',
                        'errors' => $deliveryProfileResult['errors'] ?? [],
                        'data' => $deliveryProfileResult['data'] ?? null,
                    ], $deliveryProfileResult['status'] ?? 422);
                }
            }
    
            $inventoryAssignmentResults = [];
    
            if (!empty($shop->location_id) && !empty($shopifyVariantIds)) {
                foreach ($shopifyVariantIds as $shopifyVariantId) {
                    $inventoryResult = $this->ShopifyService->assignVariantToInventoryLocation(
                        (int) $shopId,
                        (string) $shopifyVariantId,
                        (string) $shop->location_id
                    );
    
                    $inventoryAssignmentResults[] = [
                        'variant_id' => $shopifyVariantId,
                        'success' => $inventoryResult['success'] ?? false,
                        'message' => $inventoryResult['message'] ?? null,
                        'data' => $inventoryResult['data'] ?? null,
                        'errors' => $inventoryResult['errors'] ?? [],
                    ];
    
                    if (!($inventoryResult['success'] ?? false)) {
                        DB::rollBack();
    
                        Log::warning('Assign variant to inventory location failed', [
                            'shop_id' => $shopId,
                            'location_id' => $shop->location_id,
                            'variant_id' => $shopifyVariantId,
                            'result' => $inventoryResult,
                        ]);
    
                        return response()->json([
                            'success' => false,
                            'message' => $inventoryResult['message'] ?? 'Failed to assign variant to inventory location.',
                            'errors' => $inventoryResult['errors'] ?? [],
                            'data' => $inventoryResult['data'] ?? null,
                        ], $inventoryResult['status'] ?? 422);
                    }
                }
            }
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Product created successfully in Shopify.',
                'data' => [
                    'custom_product_id' => $customProduct->id,
                    'shopify' => $result['data'] ?? null,
                    'variant_media_assignments' => $variantMediaAssignments,
                    'delivery_profile_assignment' => $deliveryProfileResult,
                    'inventory_location_assignments' => $inventoryAssignmentResults,
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
    


        /**
     * GET /api/v1/custom-products/{customProduct}
     * List products with app signature verification (timestamp + empty payload).
     */
    public function customProduct(customProduct $customProduct, Request $request)
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
            Log::warning('Custom Products API rejected: invalid shop domain', [
                'shop' => $shop,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.',
            ], 422);
        }

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

        Log::info('Custom Products API request received', [
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
            Log::warning('Custom Products Get API rejected: invalid app signature', [
                'timestamp' => $timestamp,
                'signature_prefix' => $signature !== '' ? substr($signature, 0, 12) : null,
                'query' => $request->query(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid app signature',
            ], 401);
        }

        Log::info('Custom Products signed API signature verified', [
            'timestamp' => $timestamp,
            'query' => $request->query(),
        ]);

        $customProduct->load([
            'product',
            'variants.customProduct.product',
            'variants.productVariant',
            'artworks',
        ]);
    
        $resource = new CustomProductResource($customProduct);

        $response = [
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data' => $resource->toArray($request),
        ];
        
        return response()->json($response);
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

    private function colorValuesMatch(?string $left, ?string $right): bool
    {
        $normalize = function (?string $value): string {
            return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value))) ?: '';
        };

        $leftNorm = $normalize($left);
        $rightNorm = $normalize($right);

        if ($leftNorm === '' || $rightNorm === '') {
            return false;
        }

        if ($leftNorm === $rightNorm) {
            return true;
        }

        if (str_starts_with($leftNorm, $rightNorm) || str_starts_with($rightNorm, $leftNorm)) {
            return true;
        }

        $aliases = [
            'blk' => ['black'],
            'black' => ['blk'],
            'wht' => ['white'],
            'white' => ['wht'],
            'gry' => ['gray', 'grey'],
            'gray' => ['gry', 'grey'],
            'grey' => ['gry', 'gray'],
            'nav' => ['navy'],
            'navy' => ['nav'],
            'red' => ['rd'],
            'rd' => ['red'],
            'blu' => ['blue'],
            'blue' => ['blu'],
            'grn' => ['green'],
            'green' => ['grn'],
        ];

        return in_array($rightNorm, $aliases[$leftNorm] ?? [], true)
            || in_array($leftNorm, $aliases[$rightNorm] ?? [], true);
    }

    private function extractColorTokenFromArtworkSource(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $pathPart = parse_url($trimmed, PHP_URL_PATH);
        $target = is_string($pathPart) && $pathPart !== '' ? $pathPart : $trimmed;
        $filename = strtolower(pathinfo($target, PATHINFO_FILENAME));

        if ($filename === '') {
            return null;
        }

        // Expected naming examples:
        // 20260417104543_blk__front_1
        // 20260417104543_red__back_6
        if (preg_match('/_(?<color>[a-z0-9]+)__/', $filename, $matches)) {
            return $matches['color'] ?? null;
        }

        return null;
    }

    private function normalizeArtworkUrlsPayload(mixed $artworkUrls): array
    {
        if (!is_array($artworkUrls)) {
            return [];
        }

        $normalized = [];
        $walker = function (mixed $value, array $path = []) use (&$walker, &$normalized) {
            if (is_array($value)) {
                foreach ($value as $key => $child) {
                    $nextPath = $path;
                    if (is_string($key) || is_int($key)) {
                        $nextPath[] = (string) $key;
                    }
                    $walker($child, $nextPath);
                }
                return;
            }

            if (!is_string($value)) {
                return;
            }

            $source = trim($value);
            if ($source === '') {
                return;
            }

            $placement = $this->extractPlacementFromArtworkSegments($path);
            $colorFromPath = $this->extractColorTokenFromArtworkSegments($path);
            $colorFromSource = $this->extractColorTokenFromArtworkSource($source);

            $normalized[] = [
                'source' => $source,
                'placement' => $placement,
                'color_token' => $colorFromPath ?: $colorFromSource,
                'keys' => $path,
            ];
        };

        $walker($artworkUrls, []);

        return $normalized;
    }

    private function extractPlacementFromArtworkSegments(array $segments): ?string
    {
        foreach (array_reverse($segments) as $segment) {
            $normalizedSegment = strtolower(trim((string) $segment));
            if ($normalizedSegment === '') {
                continue;
            }

            $compact = preg_replace('/[^a-z0-9]+/', '', $normalizedSegment) ?: '';
            $tokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', $normalizedSegment) ?: []));
            $tokens[] = $compact;

            foreach ($tokens as $token) {
                if ($token === '') {
                    continue;
                }

                if (str_contains($token, 'front')) {
                    return str_starts_with($token, 'ls') ? 'ls-front' : 'front';
                }

                if (str_contains($token, 'back')) {
                    return str_starts_with($token, 'ls') ? 'ls-back' : 'back';
                }

                if (str_contains($token, 'sleeve')) {
                    return 'sleeve';
                }
            }
        }

        return null;
    }

    private function extractColorTokenFromArtworkSegments(array $segments): ?string
    {
        $knownColors = [
            'blk', 'black', 'red', 'rd', 'blue', 'blu', 'nav', 'navy',
            'wht', 'white', 'gry', 'gray', 'grey', 'grn', 'green',
        ];

        foreach ($segments as $segment) {
            $normalizedSegment = strtolower(trim((string) $segment));
            if ($normalizedSegment === '') {
                continue;
            }

            $tokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', $normalizedSegment) ?: []));
            $compact = preg_replace('/[^a-z0-9]+/', '', $normalizedSegment) ?: '';
            if ($compact !== '') {
                $tokens[] = $compact;
            }

            foreach ($tokens as $token) {
                if ($token === '') {
                    continue;
                }

                foreach ($knownColors as $knownColor) {
                    if ($token === $knownColor || str_starts_with($token, $knownColor) || str_ends_with($token, $knownColor)) {
                        return $knownColor;
                    }
                }
            }
        }

        return null;
    }

    /**
     * When Shopify GraphQL omits variant SKU, match by option values (Color + Size).
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\CustomProductVariant>  $customVariants
     */
    private function matchCustomVariantByShopifySelectedOptions($customVariants, array $shopifyVariant): ?\App\Models\CustomProductVariant
    {
        $selectedOptions = data_get($shopifyVariant, 'selectedOptions', []);
        $opts = collect($selectedOptions);
        $shopifyColor = strtolower(trim((string) optional($opts->first(fn ($o) => strcasecmp((string) ($o['name'] ?? ''), 'Color') === 0))['value'] ?? ''));
        $shopifySize = trim((string) optional($opts->first(fn ($o) => strcasecmp((string) ($o['name'] ?? ''), 'Size') === 0))['value'] ?? '');
        $title = trim((string) data_get($shopifyVariant, 'title', ''));

        if ($title !== '') {
            $titleParts = array_values(array_filter(array_map('trim', explode('/', $title)), fn ($part) => $part !== ''));
            if ($shopifyColor === '' && count($titleParts) > 0) {
                $shopifyColor = strtolower($titleParts[0]);
            }
            if ($shopifySize === '' && count($titleParts) > 1) {
                $shopifySize = trim((string) end($titleParts));
            }
        }

        if ($shopifyColor === '' && $shopifySize === '') {
            return null;
        }

        $matches = $customVariants->filter(function ($customVariant) use ($shopifyColor, $shopifySize) {
            $optionValues = collect($customVariant->option_values ?? []);
            $cvColor = strtolower(trim((string) optional($optionValues->first(fn ($o) => strcasecmp((string) ($o['optionName'] ?? ''), 'Color') === 0))['name'] ?? ''));
            $cvSize = trim((string) (optional($optionValues->first(fn ($o) => strcasecmp((string) ($o['optionName'] ?? ''), 'Size') === 0))['name'] ?? ''));

            $colorOk = $shopifyColor === '' || $this->colorValuesMatch($cvColor, $shopifyColor);
            $sizeOk = $shopifySize === '' || strcasecmp($cvSize, $shopifySize) === 0;

            return $colorOk && $sizeOk;
        });

        if ($matches->count() === 1) {
            return $matches->first();
        }

        // Avoid wrong "one-per-color" collapsing when Shopify variant has no size info.
        if ($shopifySize === '') {
            return null;
        }

        return $matches->first();
    }
}