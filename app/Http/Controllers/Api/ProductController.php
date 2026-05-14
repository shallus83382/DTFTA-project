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
use App\Services\CreateShopifyCustomProductService;

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

    
    private function createInShopify(
        Request $request,
        int $shopId,
    ) {
        return (new CreateShopifyCustomProductService($this->ShopifyService))->handle($request, $shopId);
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

}