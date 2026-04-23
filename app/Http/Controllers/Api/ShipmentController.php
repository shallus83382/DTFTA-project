<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\Job;
use App\Models\Shop;
use App\Services\BillingService;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShipmentController extends Controller
{
    protected $shopifyService;
    protected $billingService;

    public function __construct(ShopifyService $shopifyService, BillingService $billingService)
    {
        $this->shopifyService = $shopifyService;
        $this->billingService = $billingService;
    }

    /**
     * POST /api/v1/shipments
     * Create a shipment (fulfillment)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'shop_id' => 'required|exists:shops,id',
            'job_id' => 'nullable|exists:jobs,id',
            'fulfillment_service_id' => 'nullable|exists:fulfillment_services,id',
            'line_items' => 'nullable|array',
            'carrier' => 'nullable|string|max:100',
            'tracking_number' => 'required|string|max:255',
            'tracking_company' => 'nullable|string',
            'tracking_url' => 'nullable|url'
        ]);



        $jobId = Job::where('order_id', $validated['order_id'])->first();

        if (!$jobId) {
            return response()->json([
                'success' => false,
                'message' => 'No job found for this order. Create a job before creating shipment.'
            ], 422);
        }
        
        $payload = [
            'job_id' => $jobId->id,
            'order_id' => $validated['order_id'],
            'shop_id' => $validated['shop_id'],
            'fulfillment_service_id' => $validated['fulfillment_service_id'] ?? null,
            'carrier' => $validated['carrier'] ?? ($validated['tracking_company'] ?? 'manual'),
            'tracking_number' => $validated['tracking_number'],
            'tracking_company' => $validated['tracking_company'] ?? $validated['carrier'] ?? null,
            'tracking_url' => $validated['tracking_url'] ?? null,
            'status' => 'billing_pending',
            'line_items' => $validated['line_items'] ?? [],
            'shipped_at' => now(),
        ];


        $shipment = Shipment::create($payload);

        try {
            $order = Order::find($validated['order_id']);
            $shop = Shop::find($validated['shop_id']);

            if (!$order || !$shop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order or shop not found.',
                ], 404);
            }

            $billingResult = $this->billingService->chargePreFulfillment($shop, $order, $shipment);
            if (!($billingResult['success'] ?? false)) {
                $shipment->update([
                    'status' => 'billing_pending',
                    'payload' => array_merge((array) $shipment->payload, [
                        'billing' => $billingResult,
                    ]),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $billingResult['message'] ?? 'Billing approval required before fulfillment.',
                    'code' => data_get($billingResult, 'data.code', 'BILLING_REQUIRED'),
                    'billing_confirmation_url' => data_get($billingResult, 'data.billing_confirmation_url'),
                    'errors' => $billingResult['errors'] ?? [],
                ], $billingResult['status'] ?? 402);
            }

            $data = $jobId->payload ; // or json_decode($json, true);

            $response = $this->shopifyService->createFulfillment(
                $order->shop_id,
                $order->shopify_order_id,
                [
                    'line_items_by_fulfillment_order' => [
                        [
                            'fulfillment_order_id' => $data['fulfillment_order']['legacy_id'],
                            'fulfillment_order_line_items' => collect($data['fulfillment_order']['line_items'] ?? [])
                                ->map(function ($item) {
                                    return [
                                        'id' => $item['id'] ?? null,
                                        'quantity' => $item['remaining_quantity'] ?? $item['total_quantity'] ?? 1,
                                    ];
                                })
                                ->filter(fn ($item) => !empty($item['id']))
                                ->values()
                                ->all(),
                        ]
                    ],
                    'tracking_info' => [
                        'number' => $validated['tracking_number'] ?? null,
                        'company' => $validated['tracking_company'] ?? null,
                        'url' => $validated['tracking_url'] ?? null,
                    ],
                ]
            );

            if ($response['success']) {
                $shipment->update([
                    'shipment_id' => $this->legacyIdFromGid($response['data']['id']) ?? null,
                    'status' => 'created',
                    'created_at_shopify' => now(),
                    'updated_at_shopify' => now(),
                    'payload' => $response['data'] ?? []
                ]);
            } else {
                $shipment->update([
                    'status' => 'exception',
                    'payload' => array_merge((array) $shipment->payload, [
                        'shopify_fulfillment_error' => $response,
                    ]),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to create Shopify fulfillment.',
                    'errors' => $response['errors'] ?? [],
                ], $response['status'] ?? 422);
            }
        } catch (\Exception $e) {
            Log::error('Shopify Fulfillment Error: ' . $e->getMessage());
            $shipment->update([
                'status' => 'exception',
                'payload' => array_merge((array) $shipment->payload, [
                    'exception' => $e->getMessage(),
                ]),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create fulfillment.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully.',
            'data' => $shipment
        ], 201);
    }

    private function legacyIdFromGid(string $gid): string
    {
        return Str::afterLast($gid, '/');
    }

    /**
     * GET /api/v1/shipments
     * Get all shipments with filters
     */
    public function index(Request $request)
    {
        $query = Shipment::with(['order', 'shop', 'fulfillmentService']);

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('carrier')) {
            $query->where('carrier', $request->carrier);
        }

        $shipments = $query->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Shipments retrieved successfully.',
            'data' => $shipments
        ]);
    }

    /**
     * GET /api/v1/shipments/{id}
     * Get a specific shipment
     */
    public function show($id)
    {
        $shipment = Shipment::with('order', 'shop', 'fulfillmentService')->find($id);

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shipment retrieved successfully.',
            'data' => $shipment
        ]);
    }

    /**
     * PUT /api/v1/shipments/{id}
     * Update a shipment
     */
    public function update(Request $request, $id)
    {
        $shipment = Shipment::find($id);

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.'
            ], 404);
        }

        $validated = $request->validate([
            'carrier' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string',
            'tracking_company' => 'nullable|string',
            'tracking_url' => 'nullable|url',
            'status' => 'nullable|string'
        ]);

        if (isset($validated['carrier']) && !isset($validated['tracking_company'])) {
            $validated['tracking_company'] = $validated['carrier'];
        }

        $validated['updated_at_shopify'] = now();
        $shipment->update($validated);

        try {
            $shouldSyncTracking = isset($validated['tracking_number']) || isset($validated['tracking_company']) || isset($validated['tracking_url']) || isset($validated['carrier']);
            if ($shouldSyncTracking && !empty($shipment->shipment_id)) {
                $this->shopifyService->updateFulfillmentTracking(
                    (int) $shipment->shop_id,
                    $shipment->shipment_id,
                    [
                        'tracking_number' => $validated['tracking_number'] ?? $shipment->tracking_number,
                        'tracking_company' => $validated['tracking_company'] ?? $validated['carrier'] ?? $shipment->tracking_company,
                        'tracking_url' => $validated['tracking_url'] ?? $shipment->tracking_url,
                        'notify_customer' => true,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error('Shopify tracking update sync failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Shipment updated successfully.',
            'data' => $shipment
        ]);
    }

    /**
     * DELETE /api/v1/shipments/{id}
     * Delete a shipment
     */
    public function destroy($id)
    {
        $shipment = Shipment::find($id);

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.'
            ], 404);
        }

        $shipment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shipment deleted successfully.'
        ]);
    }

    /**
     * POST /api/v1/shipments/{id}/cancel
     * Cancel a shipment
     */
    public function cancel(Request $request, $id)
    {
        $shipment = Shipment::find($id);

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.'
            ], 404);
        }

        try {
            $order = Order::find($shipment->order_id);
            $response = $this->shopifyService->cancelFulfillment(
                $shipment->shop_id,
                $order->shopify_order_id,
                $shipment->shipment_id
            );

            if ($response['success']) {
                $shipment->update(['status' => 'cancelled']);
            }
        } catch (\Exception $e) {
            Log::error('Cancel Fulfillment Error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Shipment cancelled successfully.',
            'data' => $shipment
        ]);
    }
}
