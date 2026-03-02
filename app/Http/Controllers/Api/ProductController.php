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

class ProductController extends Controller
{
    public function __construct(private AppSignatureVerifier $appSignatureVerifier)
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
        $query = Product::with('shop');
        $appliedFilters = [];

        if ($request->filled('shop_id')) {
            $query->where('shop_id', (int) $request->query('shop_id'));
            $appliedFilters['shop_id'] = (int) $request->query('shop_id');
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
            $appliedFilters['status'] = (string) $request->query('status');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $appliedFilters['search'] = $search;
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

        Log::info('Products signed API response built', [
            'applied_filters' => $appliedFilters,
            'per_page' => $perPage,
            'current_page' => $products->currentPage(),
            'returned_count' => count($products->items()),
            'total' => $products->total(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data' => $products,
        ]);
    }
}
