<?php

namespace App\Services;

use App\Models\Artwork;
use App\Models\CustomProduct;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateShopifyCustomProductService
{
    public function __construct(
        private ShopifyService $shopifyService
    ) {
    }

    public function handle(Request $request, int $shopId)
    {
        try {
            $validated = $this->validateCreateInShopifyRequest($request);

            if (empty($validated['productId']) && empty($validated['productKey'])) {
                return $this->errorResponse('Either productId or productKey is required.', 422);
            }

            $product = $this->findProductForShopifyCreate($validated);
            if (!$product) {
                return $this->errorResponse('Product not found in database.', 404);
            }

            $shop = Shop::find($shopId);
            if (!$shop) {
                return $this->errorResponse('Shop not found.', 404);
            }

            $productVariants = $product->variants->values();
            $payloadContext = $this->buildShopifyPayloadContext(
                $request,
                $validated,
                $product,
                $shopId,
                $productVariants
            );

            $payload = $payloadContext['payload'];

            $shopifyCreateResult = $this->shopifyService->createCustomProductWithPrintAreas($shopId, $payload);

            if (!($shopifyCreateResult['success'] ?? false)) {
                // Log::warning('Create Shopify product failed', [
                //     'shop_id' => $shopId,
                //     'product_id' => $product->id,
                //     'message' => $shopifyCreateResult['message'] ?? 'Unknown error',
                //     'errors' => $shopifyCreateResult['errors'] ?? [],
                //     'variant_count' => count($payload['variants'] ?? []),
                //     'artwork_count' => count($payload['artwork'] ?? []),
                // ]);

                return response()->json([
                    'success' => false,
                    'message' => $shopifyCreateResult['message'] ?? 'Failed to create product in Shopify.',
                    'errors' => $shopifyCreateResult['errors'] ?? [],
                    'data' => $shopifyCreateResult['data'] ?? null,
                    'requested_payload' => $payload,
                ], $shopifyCreateResult['status'] ?? 422);
            }

            DB::beginTransaction();

            $customProduct = $this->createLocalCustomProduct(
                $shopId,
                $product,
                $validated,
                $payload
            );

            $customVariants = $this->bulkInsertLocalCustomVariants(
                $customProduct,
                $payloadContext['variants']
            );

            $customVariantIndexes = $this->buildCustomVariantIndexes($customVariants);

            $this->bulkInsertLocalArtworks(
                $customProduct,
                $payloadContext['artworks'],
                $customVariantIndexes
            );

            $syncResult = $this->syncCreatedShopifyProductToLocalState(
                $shopId,
                $shop,
                $customProduct,
                $shopifyCreateResult
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully in Shopify.',
                'data' => [
                    'custom_product_id' => $customProduct->id,
                    'shopify' => $shopifyCreateResult['data'] ?? null,
                    'variant_media_assignments' => $syncResult['variant_media_assignments'],
                    'delivery_profile_assignment' => $syncResult['delivery_profile_assignment'],
                    'inventory_location_assignments' => $syncResult['inventory_location_assignments'],
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

    private function validateCreateInShopifyRequest(Request $request): array
    {
        return $request->validate([
            'productId' => ['nullable', 'integer', 'exists:products,id'],
            'productKey' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'printPlan' => ['nullable'],
            'artworkUrls' => ['nullable', 'array'],
            'artworkUrls.*' => ['nullable'],
            'artworkUrls.*.artworkUrl' => ['nullable', 'string'],
            'artworkUrls.*.customArtworkUrl' => ['nullable', 'string'],
            'artworkUrls.*.colorCode' => ['nullable', 'string'],
            'artworkUrls.*.placement' => ['nullable', 'string'],
            'artworkUrls.*.designableRegion' => ['nullable', 'array'],
            'artworkUrls.*.printSize' => ['nullable', 'array'],
            'artworkUrls.*.libraryArtworkId' => ['nullable', 'string', 'max:64'],
            'artworkUrls.*.layersMeta' => ['nullable', 'array', 'max:48'],
            'artworkUrls.*.layersMeta.*.layerId' => ['required', 'string', 'max:80'],
            'artworkUrls.*.layersMeta.*.kind' => ['required', 'string', 'in:image,text,vector,other'],
            'artworkUrls.*.layersMeta.*.label' => ['required', 'string', 'max:256'],
            'artworkUrls.*.layersMeta.*.unit' => ['nullable', 'string', 'max:16'],
            'artworkUrls.*.layersMeta.*.left' => ['required', 'numeric'],
            'artworkUrls.*.layersMeta.*.top' => ['required', 'numeric'],
            'artworkUrls.*.layersMeta.*.width' => ['required', 'numeric', 'min:0'],
            'artworkUrls.*.layersMeta.*.height' => ['required', 'numeric', 'min:0'],
            'artworkUrls.*.layersMeta.*.centerX' => ['required', 'numeric'],
            'artworkUrls.*.layersMeta.*.centerY' => ['required', 'numeric'],
            'artworkUrls.*.layersMeta.*.rotation' => ['required', 'numeric', 'between:-360,360'],
            'artworkUrls.*.layersMeta.*.centerXMin' => ['required', 'numeric'],
            'artworkUrls.*.layersMeta.*.centerXMax' => ['required', 'numeric'],
            'artworkUrls.*.layersMeta.*.centerYMin' => ['required', 'numeric'],
            'artworkUrls.*.layersMeta.*.centerYMax' => ['required', 'numeric'],
            'artworkUrls.*.layersMeta.*.libraryArtworkId' => ['nullable', 'string', 'max:64'],
            'artworkUrls.*.layersMeta.*.artworkId' => ['nullable', 'string', 'max:64'],
            'artworkUrls.*.layersMeta.*.previewUrl' => ['nullable', 'string', 'max:4096'],
        ]);
    }

    private function findProductForShopifyCreate(array $validated): ?Product
    {
        return Product::with([
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
    }

    private function buildShopifyPayloadContext(
        Request $request,
        array $validated,
        Product $product,
        int $shopId,
        Collection $productVariants
    ): array {
        $title = $validated['title']
            ?? $product->title
            ?? $product->name
            ?? 'Custom Product';

        $descriptionHtml = $product->description ?? null;
        $vendor = 'DFTA';
        $productType = $product->category ?? null;
        $status = 'DRAFT';
        $tags = array_values(array_filter([$product->category, $product->brand]));

        $colors = $productVariants->pluck('color')->filter()->unique()->values()->all();
        $sizes = $productVariants->pluck('size')->filter()->unique()->values()->all();

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

        $variants = $productVariants->map(function ($variant) use ($product) {
            return [
                'db_variant_id' => $variant->id,
                'price' => $variant->price ?? $product->price ?? 1,
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

        $artworks = $this->resolveArtworkPayloads(
            $request,
            $validated,
            $product,
            $shopId,
            $printAreas,
            $productVariants
        );

        $shopifyArtwork = collect($artworks)
            ->filter(fn ($artwork) => filled($artwork['artwork_url'] ?? $artwork['url'] ?? null))
            ->unique(function ($artwork) {
                $url = strtolower(trim((string) ($artwork['artwork_url'] ?? $artwork['url'] ?? '')));
                $sourceCode = trim((string) data_get($artwork, 'meta.source_code', ''));
                return $url !== '' ? 'url:' . $url : 'source:' . md5($sourceCode);
            })
            ->values()
            ->all();

        return [
            'variants' => $variants,
            'artworks' => $artworks,
            'payload' => [
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
            ],
        ];
    }

    private function resolveArtworkPayloads(
        Request $request,
        array $validated,
        Product $product,
        int $shopId,
        array $printAreas,
        Collection $productVariants
    ): array {
        $normalizedArtworkUrls = $this->normalizeArtworkUrlsPayload($request->input('artworkUrls', []));
        $legacyArtworkSource = collect($normalizedArtworkUrls);

        // Log::info('Create Shopify product artwork payload received', [
        //     'shop_id' => $shopId,
        //     'product_id' => $product->id,
        //     'artwork_urls_count' => $legacyArtworkSource->count(),
        // ]);

        $fallbackArtworks = $legacyArtworkSource
            ->map(function ($item, $index) use ($printAreas, $shopId, $product) {
                $resolvedPlacement = data_get($item, 'placement') ?: ($printAreas[$index]['placement'] ?? null);
                $sourceValue = (string) data_get($item, 'source', '');
                $verifiedLibraryArtworkId = $this->verifyShopArtworkBelongsToShop(
                    $shopId,
                    data_get($item, 'library_artwork_id')
                );

                $preparedLayersMeta = $this->prepareLayersMetaForStorage(
                    $shopId,
                    data_get($item, 'layers_meta')
                );

                $storedUrl = $this->storeArtworkFile(
                    $sourceValue,
                    $shopId,
                    $product->id,
                    $resolvedPlacement,
                    $index
                );
                $customArtworkSource = (string) data_get($item, 'custom_artwork_source', '');
                $storedCustomUrl = $this->isArtworkSourceValue($customArtworkSource)
                    ? $this->storeArtworkFile(
                        $customArtworkSource,
                        $shopId,
                        $product->id,
                        $resolvedPlacement,
                        $index + 10000
                    )
                    : null;

                $meta = [
                    'source_code' => $storedUrl,
                    'source' => 'legacy_artworkUrls',
                    'payload_keys' => data_get($item, 'keys', []),
                    'payload_color_token' => data_get($item, 'color_token'),
                    'designable_region' => data_get($item, 'designable_region'),
                    'print_size' => data_get($item, 'print_size'),
                    'custom_artwork_url' => $storedCustomUrl,
                    'library_artwork_id' => $verifiedLibraryArtworkId,
                ];
                if ($preparedLayersMeta !== null) {
                    $meta['layers_meta'] = $preparedLayersMeta;
                }

                return [
                    'custom_product_variant_id' => null,
                    'product_variant_id' => null,
                    'variant_payload_id' => null,
                    'variant_sku' => null,
                    'variant_color_code' => null,
                    'variant_color_name' => null,
                    'variant_size' => null,
                    'placement' => $resolvedPlacement,
                    'title' => 'Artwork ' . ($index + 1),
                    'artwork_url' => $storedUrl,
                    'meta' => $meta,
                ];
            })
            ->values()
            ->all();

        $productColorOrder = $productVariants
            ->pluck('color')
            ->filter(fn ($color) => filled($color))
            ->map(fn ($color) => strtolower(trim((string) $color)))
            ->unique()
            ->values();

        $placementOccurrence = [];

        $expandedFallbackArtworks = collect($fallbackArtworks)
            ->flatMap(function ($artworkItem) use ($productVariants, $productColorOrder, &$placementOccurrence) {
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

                $matchedVariants = $productVariants
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

        return $expandedFallbackArtworks;
    }

    private function buildProductVariantIndexes(Collection $variants): array
    {
        return [
            'by_id' => $variants
                ->filter(fn ($v) => filled($v->id))
                ->keyBy(fn ($v) => (string) $v->id),
            'by_sku' => $variants
                ->filter(fn ($v) => filled($v->sku))
                ->keyBy(fn ($v) => strtoupper(trim((string) $v->sku))),
        ];
    }

    private function resolveTargetProductVariants(array $variantItem, Collection $productVariants, array $indexes): Collection
    {
        $variantId = (string) data_get($variantItem, 'variantId', '');
        $variantSku = strtoupper(trim((string) data_get($variantItem, 'variantSku', '')));
        $colorCode = strtolower(trim((string) data_get($variantItem, 'colorCode', '')));
        $colorName = strtolower(trim((string) data_get($variantItem, 'colorName', '')));
        $variantSize = trim((string) data_get($variantItem, 'size', data_get($variantItem, 'variantSize', '')));

        if ($variantId !== '' && ctype_digit($variantId) && isset($indexes['by_id'][$variantId])) {
            return collect([$indexes['by_id'][$variantId]]);
        }

        if ($variantSku !== '' && isset($indexes['by_sku'][$variantSku])) {
            return collect([$indexes['by_sku'][$variantSku]]);
        }

        if ($colorCode !== '' || $colorName !== '') {
            $needleColors = array_values(array_filter([$colorCode, $colorName]));
            $matched = $productVariants->filter(function ($variant) use ($needleColors, $variantSize) {
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
            })->values();

            if ($matched->isNotEmpty()) {
                return $matched;
            }
        }

        if ($variantSku !== '') {
            $matched = $productVariants->filter(function ($variant) use ($variantSku) {
                $dbSkuUpper = strtoupper((string) ($variant->sku ?? ''));
                if ($dbSkuUpper === '') {
                    return false;
                }
                return $dbSkuUpper === $variantSku
                    || str_contains($dbSkuUpper, $variantSku)
                    || str_contains($variantSku, $dbSkuUpper);
            })->values();

            if ($matched->isNotEmpty()) {
                return $matched;
            }
        }

        return collect([null]);
    }

    private function createLocalCustomProduct(int $shopId, Product $product, array $validated, array $payload): CustomProduct
    {
        return CustomProduct::create([
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
    }

    private function bulkInsertLocalCustomVariants(CustomProduct $customProduct, array $variants): Collection
    {
        if (empty($variants)) {
            return collect();
        }

        $now = now();
        $rows = [];

        foreach ($variants as $variant) {
            $rows[] = [
                'custom_product_id' => $customProduct->id,
                'product_variant_id' => $variant['db_variant_id'] ?? null,
                'title' => null,
                'sku' => $variant['sku'] ?? null,
                'barcode' => $variant['barcode'] ?? null,
                'price' => $variant['price'] ?? null,
                'compare_at_price' => $variant['compareAtPrice'] ?? null,
                'option_values' => json_encode($variant['optionValues'] ?? []),
                'source_payload' => json_encode($variant),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $customProduct->variants()->insert($rows);

        return $customProduct->variants()->get();
    }

    private function buildCustomVariantIndexes(Collection $customVariants): array
    {
        $byProductVariantId = [];
        $bySku = [];
        $byColorSize = [];

        foreach ($customVariants as $customVariant) {
            if (!empty($customVariant->product_variant_id)) {
                $byProductVariantId[(string) $customVariant->product_variant_id] = $customVariant;
            }

            if (!empty($customVariant->sku)) {
                $bySku['sku:' . strtoupper(trim((string) $customVariant->sku))] = $customVariant;
            }

            $optionValues = collect($customVariant->option_values ?? []);
            $colorOption = strtolower(trim((string) optional(
                $optionValues->first(fn ($opt) => strtolower((string) ($opt['optionName'] ?? '')) === 'color')
            )['name'] ?? ''));
            $sizeOption = strtoupper(trim((string) optional(
                $optionValues->first(fn ($opt) => strtolower((string) ($opt['optionName'] ?? '')) === 'size')
            )['name'] ?? ''));

            $byColorSize[$colorOption . '|' . $sizeOption] = $customVariant;
        }

        return [
            'by_product_variant_id' => $byProductVariantId,
            'by_sku' => $bySku,
            'by_color_size' => $byColorSize,
            'all' => $customVariants,
        ];
    }

    private function bulkInsertLocalArtworks(CustomProduct $customProduct, array $artworks, array $customVariantIndexes): Collection
    {
        if (empty($artworks)) {
            return collect();
        }

        $now = now();
        $rows = [];

        foreach ($artworks as $artwork) {
            $meta = $artwork['meta'] ?? [];
            $linkedCustomVariant = $this->resolveLinkedCustomVariant($artwork, $customVariantIndexes);

            if ($linkedCustomVariant) {
                $meta['linked_custom_product_variant_id'] = $linkedCustomVariant->id;
            }

            // Log::info('Custom artwork linked to local variant', [
            //     'shop_id' => $customProduct->shop_id,
            //     'custom_product_id' => $customProduct->id,
            //     'product_variant_id' => $artwork['product_variant_id'] ?? null,
            //     'variant_sku' => $artwork['variant_sku'] ?? null,
            //     'variant_color' => $artwork['variant_color_name'] ?? $artwork['variant_color_code'] ?? null,
            //     'variant_size' => $artwork['variant_size'] ?? data_get($meta, 'size'),
            //     'artwork_url' => $artwork['artwork_url'] ?? $artwork['url'] ?? null,
            //     'resolved_custom_product_variant_id' => $linkedCustomVariant?->id,
            //     'resolved_custom_product_variant_sku' => $linkedCustomVariant?->sku,
            // ]);

            $rows[] = [
                'custom_product_id' => $customProduct->id,
                'custom_product_variant_id' => $linkedCustomVariant?->id,
                'placement' => $artwork['placement'] ?? null,
                'title' => $artwork['title'] ?? null,
                'artwork_url' => $artwork['artwork_url'] ?? $artwork['url'] ?? null,
                'meta' => json_encode($meta),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $customProduct->artworks()->insert($rows);

        return $customProduct->artworks()->get();
    }

    private function resolveLinkedCustomVariant(array $artwork, array $indexes): ?object
    {
        if (!empty($artwork['product_variant_id'])) {
            $key = (string) $artwork['product_variant_id'];
            if (isset($indexes['by_product_variant_id'][$key])) {
                return $indexes['by_product_variant_id'][$key];
            }
        }

        if (!empty($artwork['variant_sku'])) {
            $skuKey = 'sku:' . strtoupper(trim((string) $artwork['variant_sku']));
            if (isset($indexes['by_sku'][$skuKey])) {
                return $indexes['by_sku'][$skuKey];
            }

            $needleSku = strtoupper(trim((string) $artwork['variant_sku']));
            $fuzzySkuMatch = $indexes['all']->first(function ($customVariant) use ($needleSku) {
                $dbSku = strtoupper((string) $customVariant->sku);
                if ($dbSku === '') {
                    return false;
                }
                return $dbSku === $needleSku
                    || str_contains($dbSku, $needleSku)
                    || str_contains($needleSku, $dbSku);
            });

            if ($fuzzySkuMatch) {
                return $fuzzySkuMatch;
            }
        }

        $meta = $artwork['meta'] ?? [];
        $needleColor = strtolower(trim((string) (
            $artwork['variant_color_name']
            ?? $artwork['variant_color_code']
            ?? data_get($meta, 'color_name', data_get($meta, 'color_code', ''))
        )));
        $needleSize = strtoupper(trim((string) ($artwork['variant_size'] ?? data_get($meta, 'size', ''))));

        if ($needleColor !== '') {
            $key = $needleColor . '|' . $needleSize;
            if (isset($indexes['by_color_size'][$key])) {
                return $indexes['by_color_size'][$key];
            }

            return $indexes['all']->first(function ($customVariant) use ($needleColor, $needleSize) {
                $optionValues = collect($customVariant->option_values ?? []);
                $colorOption = strtolower(trim((string) optional(
                    $optionValues->first(fn ($opt) => strtolower((string) ($opt['optionName'] ?? '')) === 'color')
                )['name'] ?? ''));
                $sizeOption = strtoupper(trim((string) optional(
                    $optionValues->first(fn ($opt) => strtolower((string) ($opt['optionName'] ?? '')) === 'size')
                )['name'] ?? ''));

                if ($needleColor === '' || !$this->colorValuesMatch($colorOption, $needleColor)) {
                    return false;
                }

                if ($needleSize === '') {
                    return true;
                }

                return $sizeOption === $needleSize;
            });
        }

        return null;
    }

    private function syncCreatedShopifyProductToLocalState(int $shopId, Shop $shop, CustomProduct $customProduct, array $shopifyCreateResult): array
    {
        $shopifyProductId = data_get($shopifyCreateResult, 'data.product.id');
        $shopifyVariantsFromCreate = data_get($shopifyCreateResult, 'data.variants', []);

        $customProduct->update([
            'shopify_product_id' => $shopifyProductId,
        ]);

        if (!empty($shopifyProductId)) {
            $templateMetafieldResult = $this->shopifyService->setDtftaTemplateIdMetafield(
                (int) $shopId,
                (string) $shopifyProductId,
                (int) $customProduct->id
            );

            if (!($templateMetafieldResult['success'] ?? false)) {
                throw new \RuntimeException(
                    $templateMetafieldResult['message'] ?? 'Failed to set DTFTA template id metafield.'
                );
            }
        }

        $customVariants = $customProduct->variants()->get();
        $customArtworks = $customProduct->artworks()->get();

        $shopifyVariants = $this->fetchMergedShopifyVariants(
            $shopId,
            $shopifyProductId,
            $shopifyVariantsFromCreate,
            $customVariants->count()
        );

        $shopifyVariantIds = $this->mapShopifyVariantsToLocalVariants(
            $customVariants,
            $customArtworks,
            $shopifyVariants
        );

        $variantMediaAssignments = $this->attachShopifyMediaToVariants(
            $shopId,
            $shopifyProductId,
            $customProduct,
            $customArtworks,
            data_get($shopifyCreateResult, 'data.media', [])
        );

        $deliveryProfileResult = null;
        if (!empty($shop->shipping_profile_id) && !empty($shopifyVariantIds)) {
            $deliveryProfileResult = $this->shopifyService->assignVariantsToDeliveryProfile(
                (int) $shopId,
                (string) $shop->shipping_profile_id,
                $shopifyVariantIds
            );

            if (!($deliveryProfileResult['success'] ?? false)) {
                throw new \RuntimeException(
                    $deliveryProfileResult['message'] ?? 'Failed to assign variants to delivery profile.'
                );
            }
        }

        $inventoryAssignmentResults = [];
        if (!empty($shop->location_id) && !empty($shopifyVariantIds)) {
            foreach ($shopifyVariantIds as $shopifyVariantId) {
                $inventoryResult = $this->shopifyService->assignVariantToInventoryLocation(
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
                    throw new \RuntimeException(
                        $inventoryResult['message'] ?? 'Failed to assign variant to inventory location.'
                    );
                }
            }
        }

        return [
            'variant_media_assignments' => $variantMediaAssignments,
            'delivery_profile_assignment' => $deliveryProfileResult,
            'inventory_location_assignments' => $inventoryAssignmentResults,
        ];
    }

    private function fetchMergedShopifyVariants(int $shopId, ?string $shopifyProductId, $fromBulkCreate, int $expectedVariantCount): array
    {
        $fromBulkCreate = is_array($fromBulkCreate) ? $fromBulkCreate : [];
        $shopifyVariants = $fromBulkCreate;

        if (!empty($shopifyProductId)) {
            $variantsFetchResult = $this->shopifyService->getProductVariantsByProductId(
                (int) $shopId,
                (string) $shopifyProductId
            );

            if ($variantsFetchResult['success'] ?? false) {
                $fetched = data_get($variantsFetchResult, 'data.variants', []);
                if (!is_array($fetched)) {
                    $fetched = [];
                }

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
                    // Log::warning('Shopify variant list shorter than local variants after bulk/query merge', [
                    //     'shop_id' => $shopId,
                    //     'shopify_product_id' => $shopifyProductId,
                    //     'expected_variant_count' => $expectedVariantCount,
                    //     'from_bulk_create_count' => count($fromBulkCreate),
                    //     'from_product_query_count' => count($fetched),
                    //     'merged_count' => count($shopifyVariants),
                    // ]);
                }
            } else {
                // Log::warning('Fallback Shopify variant fetch failed', [
                //     'shop_id' => $shopId,
                //     'shopify_product_id' => $shopifyProductId,
                //     'expected_variant_count' => $expectedVariantCount,
                //     'initial_variant_count' => count($fromBulkCreate),
                //     'result' => $variantsFetchResult,
                // ]);
            }
        }

        return $shopifyVariants;
    }

    private function mapShopifyVariantsToLocalVariants(Collection $customVariants, Collection $customArtworks, array $shopifyVariants): array
    {
        $shopifyVariantIds = [];
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
                $matchedCustomVariant = $customVariants->first(function ($customVariant) use ($normalizedSku, $mappedCustomVariantIds) {
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

            $customArtworks
                ->where('custom_product_variant_id', $matchedCustomVariant->id)
                ->each(function ($artwork) use ($shopifyVariantId) {
                    $meta = $artwork->meta ?? [];
                    $meta['shopify_variant_id'] = $shopifyVariantId;
                    $artwork->update(['meta' => $meta]);
                });
        }

        return $shopifyVariantIds;
    }

    private function attachShopifyMediaToVariants(int $shopId, ?string $shopifyProductId, CustomProduct $customProduct, Collection $customArtworks, array $mediaNodes): array
    {
        $shopifyMediaNodes = collect($mediaNodes);
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

        $customArtworks
            ->whereNotNull('custom_product_variant_id')
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

        // Log::info('Prepared Shopify variant-media map', [
        //     'shop_id' => $shopId,
        //     'custom_product_id' => $customProduct->id,
        //     'shopify_product_id' => $shopifyProductId,
        //     'shopify_media_count' => $shopifyMediaNodes->count(),
        //     'variant_media_map' => $variantMediaMap,
        // ]);

        $variantMediaAssignments = [];

        foreach ($variantMediaMap as $shopifyVariantId => $mediaIds) {
            $uniqueMediaIds = array_values(array_unique($mediaIds));
            $singleMediaIdForVariant = !empty($uniqueMediaIds) ? [$uniqueMediaIds[0]] : [];

            $attachToVariant = null;
            $maxAttempts = 3;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $attachToVariant = $this->shopifyService->attachMediaToProductVariant(
                    (int) $shopId,
                    (string) $shopifyProductId,
                    (string) $shopifyVariantId,
                    $singleMediaIdForVariant
                );

                if ($attachToVariant['success'] ?? false) {
                    break;
                }

                $errorText = strtolower((string) ($attachToVariant['message'] ?? ''));
                $isNonReadyMediaError = str_contains($errorText, 'non-ready media');
                if (!$isNonReadyMediaError || $attempt === $maxAttempts) {
                    break;
                }

                // Shopify media can remain in processing state briefly.
                usleep(900000);
            }

            $variantMediaAssignments[] = [
                'variant_id' => $shopifyVariantId,
                'media_ids' => $singleMediaIdForVariant,
                'success' => $attachToVariant['success'] ?? false,
                'message' => $attachToVariant['message'] ?? null,
                'errors' => $attachToVariant['errors'] ?? [],
                'data' => $attachToVariant['data'] ?? null,
            ];

            // Log::info('Shopify variant media attach result', [
            //     'shop_id' => $shopId,
            //     'custom_product_id' => $customProduct->id,
            //     'shopify_product_id' => $shopifyProductId,
            //     'shopify_variant_id' => $shopifyVariantId,
            //     'media_ids' => $singleMediaIdForVariant,
            //     'success' => $attachToVariant['success'] ?? false,
            //     'message' => $attachToVariant['message'] ?? null,
            //     'errors' => $attachToVariant['errors'] ?? [],
            // ]);

            if (!($attachToVariant['success'] ?? false)) {
                $errorText = strtolower((string) ($attachToVariant['message'] ?? ''));
                if (str_contains($errorText, 'non-ready media')) {
                    // Log::warning('Skipping variant-media attach due to non-ready media after retries', [
                    //     'shop_id' => $shopId,
                    //     'custom_product_id' => $customProduct->id,
                    //     'shopify_product_id' => $shopifyProductId,
                    //     'shopify_variant_id' => $shopifyVariantId,
                    //     'media_ids' => $singleMediaIdForVariant,
                    // ]);
                    continue;
                }

                throw new \RuntimeException(
                    $attachToVariant['message'] ?? 'Failed to link media to Shopify variants.'
                );
            }
        }

        return $variantMediaAssignments;
    }

    private function errorResponse(string $message, int $status = 422, array $extra = [])
    {
        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
        ], $extra), $status);
    }

    /*
     |--------------------------------------------------------------------------
     | Existing helpers expected to already exist in your codebase
     |--------------------------------------------------------------------------
     |
     | Move these into this service too, or inject another collaborator that
     | provides them:
     |
     | - normalizeArtworkUrlsPayload()
     | - storeArtworkFile()
     | - extractColorTokenFromArtworkSource()
     | - colorValuesMatch()
     | - matchCustomVariantByShopifySelectedOptions()
     |
     */

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
    
        if(config('filesystems.default') === 's3') {
            Storage::disk('s3')->put($path, $binaryData);
            return Storage::disk('s3')->url($path);
        }

        Storage::disk('local')->put($path, $binaryData);
    
        return Storage::disk('local')->url($path);
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
                // New structured payload support:
                // artworkUrls[color_placement] = { artworkUrl, colorCode, placement, designableRegion, printSize }
                $structuredArtworkUrl = trim((string) data_get($value, 'artworkUrl', ''));
                if ($this->isArtworkSourceValue($structuredArtworkUrl)) {
                    $placement = $this->sanitizePlacement((string) data_get($value, 'placement', ''))
                        ?: $this->extractPlacementFromArtworkSegments($path);
                    $colorToken = $this->sanitizeColorToken((string) data_get($value, 'colorCode', ''))
                        ?: $this->extractColorTokenFromArtworkSegments($path)
                        ?: $this->extractColorTokenFromArtworkSource($structuredArtworkUrl);

                    $normalized[] = [
                        'source' => $structuredArtworkUrl,
                        'custom_artwork_source' => trim((string) data_get($value, 'customArtworkUrl', '')),
                        'placement' => $placement,
                        'color_token' => $colorToken,
                        'keys' => $path,
                        'designable_region' => data_get($value, 'designableRegion'),
                        'print_size' => data_get($value, 'printSize'),
                        'library_artwork_id' => trim((string) data_get($value, 'libraryArtworkId', '')),
                        'layers_meta' => $this->normalizeLayersMetaPayload(data_get($value, 'layersMeta')),
                    ];
                    return;
                }

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
            if (!$this->isArtworkSourceValue($source)) {
                return;
            }

            $placement = $this->extractPlacementFromArtworkSegments($path);
            $colorFromPath = $this->extractColorTokenFromArtworkSegments($path);
            $colorFromSource = $this->extractColorTokenFromArtworkSource($source);

            $normalized[] = [
                'source' => $source,
                'custom_artwork_source' => '',
                'placement' => $placement,
                'color_token' => $colorFromPath ?: $colorFromSource,
                'keys' => $path,
                'designable_region' => null,
                'print_size' => null,
                'library_artwork_id' => '',
                'layers_meta' => null,
            ];
        };

        $walker($artworkUrls, []);

        return $normalized;
    }

    /**
     * Confirms the Remix app-reported library asset id exists on this shop's artworks table.
     */
    private function verifyShopArtworkBelongsToShop(int $shopId, mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $id = trim((string) $raw);
        if ($id === '') {
            return null;
        }

        if (! ctype_digit($id)) {
            Log::warning('Create Shopify product: libraryArtworkId is not a numeric id', [
                'shop_id' => $shopId,
                'library_artwork_id' => $id,
            ]);

            return null;
        }

        $exists = Artwork::query()
            ->where('shop_id', $shopId)
            ->where('id', (int) $id)
            ->exists();

        if (! $exists) {
            Log::warning('Create Shopify product: library artwork id not found for shop', [
                'shop_id' => $shopId,
                'library_artwork_id' => $id,
            ]);

            return null;
        }

        return $id;
    }

    /**
     * @param  mixed  $raw  Request `layersMeta` array (camelCase from Remix).
     * @return array<int, array<string, mixed>>|null
     */
    private function normalizeLayersMetaPayload(mixed $raw): ?array
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $out = [];
        foreach ($raw as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * Sanitize and verify per-layer library artwork ids; strip unsafe preview URLs.
     *
     * @param  array<int, array<string, mixed>>|null  $layersMeta
     * @return array<int, array<string, mixed>>|null
     */
    private function prepareLayersMetaForStorage(int $shopId, mixed $layersMeta): ?array
    {
        if (! is_array($layersMeta) || $layersMeta === []) {
            return null;
        }

        $kinds = ['image', 'text', 'vector', 'other'];
        $out = [];
        $seenLayerIds = [];

        foreach (array_slice($layersMeta, 0, 48) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $layerId = trim((string) data_get($row, 'layerId', ''));
            if ($layerId === '' || isset($seenLayerIds[$layerId])) {
                continue;
            }
            $seenLayerIds[$layerId] = true;

            $kind = strtolower(trim((string) data_get($row, 'kind', 'other')));
            if (! in_array($kind, $kinds, true)) {
                $kind = 'other';
            }

            $label = mb_substr(trim((string) data_get($row, 'label', 'Layer')), 0, 256) ?: 'Layer';
            $unit = data_get($row, 'unit');
            $unitStr = is_string($unit) ? mb_substr(trim($unit), 0, 16) : null;
            $unitStr = ($unitStr !== null && $unitStr !== '') ? $unitStr : null;

            $previewRaw = data_get($row, 'previewUrl');
            $previewUrl = null;
            if (is_string($previewRaw)) {
                $p = trim($previewRaw);
                if ($p !== '' && strlen($p) <= 4096 && ! str_starts_with(strtolower($p), 'data:')) {
                    $previewUrl = $p;
                }
            }

            $layerLibId = $this->verifyShopArtworkBelongsToShop(
                $shopId,
                data_get($row, 'libraryArtworkId') ?: data_get($row, 'artworkId')
            );

            $entry = [
                'layer_id' => mb_substr($layerId, 0, 80),
                'kind' => $kind,
                'label' => $label,
                'left' => (float) data_get($row, 'left', 0),
                'top' => (float) data_get($row, 'top', 0),
                'width' => max(0, (float) data_get($row, 'width', 0)),
                'height' => max(0, (float) data_get($row, 'height', 0)),
                'center_x' => (float) data_get($row, 'centerX', 0),
                'center_y' => (float) data_get($row, 'centerY', 0),
                'rotation' => min(360, max(-360, (float) data_get($row, 'rotation', 0))),
                'center_x_min' => (float) data_get($row, 'centerXMin', 0),
                'center_x_max' => (float) data_get($row, 'centerXMax', 0),
                'center_y_min' => (float) data_get($row, 'centerYMin', 0),
                'center_y_max' => (float) data_get($row, 'centerYMax', 0),
            ];

            if ($unitStr !== null) {
                $entry['unit'] = $unitStr;
            }
            if ($layerLibId !== null) {
                $entry['library_artwork_id'] = $layerLibId;
                $entry['artwork_id'] = $layerLibId;
            }
            if ($previewUrl !== null) {
                $entry['preview_url'] = $previewUrl;
            }

            $out[] = $entry;
        }

        return $out === [] ? null : $out;
    }

    private function isArtworkSourceValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if ($this->isDataUrl($value)) {
            return true;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }

        return str_starts_with($value, '/');
    }

    private function sanitizePlacement(string $placement): ?string
    {
        $normalized = strtolower(trim($placement));
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9_-]+/i', '-', $normalized);
        return $normalized !== '' ? $normalized : null;
    }

    private function sanitizeColorToken(string $color): ?string
    {
        $normalized = strtolower(trim($color));
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', '', $normalized) ?: '';
        return $normalized !== '' ? $normalized : null;
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
