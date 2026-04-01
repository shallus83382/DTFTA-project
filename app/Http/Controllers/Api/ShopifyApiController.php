<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\AppSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\DB;
use App\Models\PartnerProfile;
use Illuminate\Http\JsonResponse;

class ShopifyApiController extends Controller
{
    public function __construct(private AppSignatureVerifier $appSignatureVerifier,private ShopifyService $ShopifyService)
    {
    }

    /**
     * POST /api/v1/brand-settings
     * List products with app signature verification (timestamp + empty payload).
     */
    public function CreateStoreBrandSettings(Request $request)
    {
        $shop = strtolower(trim((string) (
            $request->header('X-Shop')
            ?? $request->input('shop')
            ?? $request->input('shop_domain')
            ?? ''
        )));
        
        if (!$this->isValidShopDomain($shop)) {
            Log::warning('Create Shopify signed rejected: invalid shop domain', [
                'shop' => $shop,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.',
            ], 422);
        }

        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');

        Log::info('Store Brand setting API request received', [
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
            Log::warning('Brand Settings API rejected: invalid app signature', [
                'timestamp' => $timestamp,
                'signature_prefix' => $signature !== '' ? substr($signature, 0, 12) : null,
                'query' => $request->query(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid app signature',
            ], 401);
        }

        Log::info('Brand Settings API signed API signature verified', [
            'timestamp' => $timestamp,
            'query' => $request->query(),
        ]);

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


        return $this->buildSignedStoreBranding($request, $existingShop->id);
    } 
    
    public function buildSignedStoreBranding(Request $request,int $shop_id)
    {
        $validated = $request->validate([
            'brandName' => 'required|string|max:255',
            'returnAddress.street' => 'required|string',
            'returnAddress.city' => 'required|string',
            'returnAddress.state' => 'required|string',
            'returnAddress.zipCode' => 'required|string',
            'returnAddress.country' => 'required|string',
            'supportContact.email' => 'required|email',
            'supportContact.phone' => 'required|string',
        ]);

        $partnerProfile = PartnerProfile::updateOrCreate(
            ['shop_id' => $shop_id],
            [
            'brand_name' => $validated['brandName'],
            'return_address_street' => $validated['returnAddress']['street'],
            'return_address_city' => $validated['returnAddress']['city'],
            'return_address_state' => $validated['returnAddress']['state'],
            'return_address_zip' => $validated['returnAddress']['zipCode'],
            'return_address_country' => $validated['returnAddress']['country'],
            'support_email' => $validated['supportContact']['email'],
            'support_phone' => $validated['supportContact']['phone'],
        ]);

        return response()->json([
            'message' => 'Brand Settings API created successfully',
            'data' => $this->transformResponse($partnerProfile)
        ], 201);
    }


    /**
     * Get /api/v1/brand-settings
     * List products with app signature verification (timestamp + empty payload).
     */
    public function GetStoreBrandSettings(Request $request)
    {
        $shop = strtolower(trim((string) (
            $request->header('X-Shop')
            ?? $request->input('shop')
            ?? $request->input('shop_domain')
            ?? ''
        )));
        
        if (!$this->isValidShopDomain($shop)) {
            Log::warning('Brand Settings API rejected: invalid shop domain', [
                'shop' => $shop,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.',
            ], 422);
        }

        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');

        Log::info('Brand Settings API request received', [
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
            Log::warning('Brand Settings API rejected: invalid app signature', [
                'timestamp' => $timestamp,
                'signature_prefix' => $signature !== '' ? substr($signature, 0, 12) : null,
                'query' => $request->query(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid app signature',
            ], 401);
        }

        Log::info('Brand Settings API signed API signature verified', [
            'timestamp' => $timestamp,
            'query' => $request->query(),
        ]);

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


        return $this->getSignedStoreBranding($existingShop->id);
    }


    /**
     * Get /api/v1/dashboard-stats
     * List products with app signature verification (timestamp + empty payload).
     */
    public function GetStoreDashboardStats(Request $request)
    {
        $shop = strtolower(trim((string) (
            $request->header('X-Shop')
            ?? $request->input('shop')
            ?? $request->input('shop_domain')
            ?? ''
        )));
        
        if (!$this->isValidShopDomain($shop)) {
            Log::warning('Create Shopify signed rejected: invalid shop domain', [
                'shop' => $shop,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.',
            ], 422);
        }

        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');

        Log::info('Dashboard-stats API request received', [
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
            Log::warning('Dashboard-stats API rejected: invalid app signature', [
                'timestamp' => $timestamp,
                'signature_prefix' => $signature !== '' ? substr($signature, 0, 12) : null,
                'query' => $request->query(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid app signature',
            ], 401);
        }

        Log::info('Dashboard-status API signed API signature verified', [
            'timestamp' => $timestamp,
            'query' => $request->query(),
        ]);

        $existingShop = Shop::where('shop_domain', $shop)
            ->orderByDesc('id')
            ->with(['orders','jobs'])
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

        $totalOrders =  $existingShop ? $existingShop->orders->count() : 0;
        $pendingOrders =  $existingShop ? $existingShop->jobs->where('status', 'pending')->count() : 0;
        $inProduction =  $existingShop ? $existingShop->jobs->where('status', 'in_production')->count() : 0;
        $shipped =  $existingShop ? $existingShop->jobs->where('fulfillment_status', 'fulfilled')->count() : 0;
        $pendingOrders =  $existingShop ? $existingShop->jobs->where('status', 'pending')->count() : 0;
        $totalException = $existingShop ? $existingShop->jobs->where(function ($query) {
                                                                                        $query->where('status', 'failed')
                                                                                            ->orWhere('status', 'exception')
                                                                                            ->orWhere('status', 'cancelled');
                                                                                    })->count() : 0;

         $exceptionRate = $totalOrders > 0 ? round(($totalException / $totalOrders) * 100, 1) : 0;

         $fulfillmentRate = $totalOrders > 0 ? round(($shipped / $totalOrders) * 100, 1) : 0;

            $res = [
                    'totalOrders' => $totalOrders,
                    'pending'=> $pendingOrders,
                    'inProduction'=> $inProduction,
                    'shipped' => $shipped,
                    'exceptions'=> $exceptionRate,
                    'fulfillmentRate'=> $fulfillmentRate
            ]; 

        Log::info('Dashboard-status API Response', [
            'timestamp' => $timestamp,
            'shop_id' => 2,
            'totalOrders' => $totalOrders,
            'pending'=> $pendingOrders,
            'inProduction'=> $inProduction,
            'shipped' => $shipped,
            'exceptions'=> $exceptionRate,
            'fulfillmentRate'=> $fulfillmentRate,
            'query' => $request->query(),
        ]);


        return response()->json($res, 200);
    }

        /**
     * Get /api/v1/orders
     * List products with app signature verification (timestamp + empty payload).
     */
    public function GetStoreOrders(Request $request)
    {
        $shop = strtolower(trim((string) (
            $request->header('X-Shop')
            ?? $request->input('shop')
            ?? $request->input('shop_domain')
            ?? ''
        )));
        
        if (!$this->isValidShopDomain($shop)) {
            Log::warning('Create Shopify signed rejected: invalid shop domain', [
                'shop' => $shop,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.',
            ], 422);
        }

        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');

        Log::info('Orders API request received', [
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
            Log::warning('Orders API rejected: invalid app signature', [
                'timestamp' => $timestamp,
                'signature_prefix' => $signature !== '' ? substr($signature, 0, 12) : null,
                'query' => $request->query(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid app signature',
            ], 401);
        }

        Log::info('Orders API signed API signature verified', [
            'timestamp' => $timestamp,
            'query' => $request->query(),
        ]);

        $existingShop = Shop::where('shop_domain', 'mystore.myshopify.com')
            ->orderByDesc('id')
            ->first();

        if (!$existingShop) {
            return response()->json([
                'success' => false,
                'message' => 'Store does not exist.',
            ], 404);
        }

        Log::info('Signed Order request shop found', [
            'shop_domain' => $shop,
            'shop_id' => $existingShop->id,
        ]);

        $orders = Order::with(['orderItems', 'shipments'])
        ->where('shop_id', $existingShop->id)
        ->get();
    
        $orderRes = $orders->map(function ($order) {
            return [
                'order_id' => $order->id,
                'shopify_order_id' => $order->shopify_order_id,
                'order_number' => $order->order_number,
                'customer' => [
                    'email' => $order->customer_email,
                    'name' => $order->customer_name,
                ],
                'total_price' => $order->total_price,
                'status' => $order->fulfillment_status ?? $order->status,
                'items_count' => $order->orderItems->count(),
                'shipments_count' => $order->shipments->count(),
                'items' => $order->orderItems->map(fn ($item) => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'title' => $item->title,
                    'quantity' => $item->quantity,
                ]),
            ];
        })->values();

        


        return response()->json($orderRes, 200);
    }



    /**
     * Get /api/v1/fulfillment-status
     * List products with app signature verification (timestamp + empty payload).
     */
    public function GetStoreFulfillmentStatus(Request $request)
    {
        $shop = strtolower(trim((string) (
            $request->header('X-Shop')
            ?? $request->input('shop')
            ?? $request->input('shop_domain')
            ?? ''
        )));
        
        if (!$this->isValidShopDomain($shop)) {
            Log::warning('Create Shopify signed rejected: invalid shop domain', [
                'shop' => $shop,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.',
            ], 422);
        }

        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');

        Log::info('fulfillment-status API request received', [
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
            Log::warning('fulfillment-status API rejected: invalid app signature', [
                'timestamp' => $timestamp,
                'signature_prefix' => $signature !== '' ? substr($signature, 0, 12) : null,
                'query' => $request->query(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid app signature',
            ], 401);
        }

        Log::info('fulfillment-status API signed API signature verified', [
            'timestamp' => $timestamp,
            'query' => $request->query(),
        ]);

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


        return response()->json([
            'fulfillmentServiceConnected' => (bool) $existingShop->fulfillment_service_id,
            'locationCreated' => (bool) $existingShop->location_id
        ], 200);
    }



    public function getSignedStoreBranding(int $shopId): JsonResponse
    {
        $partnerProfile = PartnerProfile::where('shop_id', $shopId)->first();

        if (! $partnerProfile) {
            return response()->json([
                'message' => 'Brand details not found'
            ], 404);
        }

        return response()->json($this->transformResponse($partnerProfile), 200);
    }

    private function isValidShopDomain(string $shop): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/i', $shop);
    }

    private function transformResponse($partnerProfile)
    {
        return [
            'brandName' => $partnerProfile->brand_name,
            'returnAddress' => [
                'street' => $partnerProfile->return_address_street,
                'city' => $partnerProfile->return_address_city,
                'state' => $partnerProfile->return_address_state,
                'zipCode' => $partnerProfile->return_address_zip,
                'country' => $partnerProfile->return_address_country,
            ],
            'supportContact' => [
                'email' => $partnerProfile->support_email,
                'phone' => $partnerProfile->support_phone,
            ]
        ];
    }

}    