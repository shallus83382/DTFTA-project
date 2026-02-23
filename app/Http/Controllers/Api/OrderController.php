<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{
    /**
     * POST /api/v1/orders
     * Creates or updates an order record
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'shopify_order_id' => 'required|string',
            'order_number' => 'nullable|string',
            'customer_email' => 'nullable|email',
            'customer_name' => 'nullable|string',
            'total_price' => 'nullable|numeric',
            'currency' => 'nullable|string|max:10',
            'status' => 'nullable|string',
            'fulfillment_status' => 'nullable|string',
            'financial_status' => 'nullable|string',
            'created_at_shopify' => 'nullable|date',
            'updated_at_shopify' => 'nullable|date',
            'closed_at' => 'nullable|date',
            'cancelled_at' => 'nullable|date',
            'payload' => 'nullable',
            'order_json' => 'nullable',
            'raw_data' => 'nullable'
        ]);

        $resolvedStatus = $validated['fulfillment_status'] ?? $validated['status'] ?? 'pending';
        $resolvedPayload = $validated['payload']
            ?? $validated['raw_data']
            ?? $validated['order_json']
            ?? ($request->getContent() ?: null);

        $data = [
            'shop_id' => $validated['shop_id'],
            'shopify_order_id' => $validated['shopify_order_id'],
            'order_number' => $validated['order_number'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'total_price' => $validated['total_price'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'status' => $resolvedStatus,
            'fulfillment_status' => $resolvedStatus,
            'financial_status' => $validated['financial_status'] ?? null,
            'created_at_shopify' => $validated['created_at_shopify'] ?? null,
            'updated_at_shopify' => $validated['updated_at_shopify'] ?? null,
            'closed_at' => $validated['closed_at'] ?? null,
            'cancelled_at' => $validated['cancelled_at'] ?? null,
            'raw_data' => $resolvedPayload,
            'payload' => $resolvedPayload
        ];

        $order = Order::updateOrCreate(
            [
                'shopify_order_id' => $data['shopify_order_id'],
                'shop_id' => $data['shop_id']
            ],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Order created/updated successfully.',
            'data' => $order
        ], 201);
    }

    /**
     * GET /api/v1/orders
     * Get all orders with optional filtering by shop_id and fulfillment_status
     */
    public function index(Request $request)
    {
        $query = Order::with(['shop']);

        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->query('shop_id'));
        }

        if ($request->has('fulfillment_status')) {
            $status = $request->query('fulfillment_status');
            $query->where(function ($q) use ($status) {
                $q->where('fulfillment_status', $status)->orWhere('status', $status);
            });
        }

        if ($request->has('status')) {
            $status = $request->query('status');
            $query->where(function ($q) use ($status) {
                $q->where('status', $status)->orWhere('fulfillment_status', $status);
            });
        }

        $orders = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $orders
        ]);
    }

    /**
     * GET /api/v1/orders/{id}
     * Get a specific order with all details
     */
    public function show($id)
    {
        $order = Order::with('shop')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully.',
            'data' => $order
        ]);
    }

    /**
     * PUT /api/v1/orders/{id}
     * Update an order
     */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'nullable|string',
            'fulfillment_status' => 'nullable|string',
            'total_price' => 'nullable|numeric',
            'currency' => 'nullable|string|max:10',
            'customer_email' => 'nullable|email',
            'customer_name' => 'nullable|string',
            'financial_status' => 'nullable|string',
            'created_at_shopify' => 'nullable|date',
            'updated_at_shopify' => 'nullable|date',
            'closed_at' => 'nullable|date',
            'cancelled_at' => 'nullable|date',
            'raw_data' => 'nullable',
            'payload' => 'nullable',
            'order_json' => 'nullable'
        ]);

        $updates = [];
        if (isset($validated['status']) || isset($validated['fulfillment_status'])) {
            $status = $validated['fulfillment_status'] ?? $validated['status'];
            $updates['status'] = $status;
            $updates['fulfillment_status'] = $status;
        }
        if (isset($validated['total_price'])) $updates['total_price'] = $validated['total_price'];
        if (isset($validated['currency'])) $updates['currency'] = $validated['currency'];
        if (isset($validated['customer_email'])) $updates['customer_email'] = $validated['customer_email'];
        if (isset($validated['customer_name'])) $updates['customer_name'] = $validated['customer_name'];
        if (isset($validated['financial_status'])) $updates['financial_status'] = $validated['financial_status'];
        if (isset($validated['created_at_shopify'])) $updates['created_at_shopify'] = $validated['created_at_shopify'];
        if (isset($validated['updated_at_shopify'])) $updates['updated_at_shopify'] = $validated['updated_at_shopify'];
        if (isset($validated['closed_at'])) $updates['closed_at'] = $validated['closed_at'];
        if (isset($validated['cancelled_at'])) $updates['cancelled_at'] = $validated['cancelled_at'];
        if (isset($validated['raw_data'])) {
            $updates['raw_data'] = $validated['raw_data'];
            $updates['payload'] = $validated['raw_data'];
        } elseif (isset($validated['payload'])) {
            $updates['raw_data'] = $validated['payload'];
            $updates['payload'] = $validated['payload'];
        } elseif (isset($validated['order_json'])) {
            $updates['raw_data'] = $validated['order_json'];
            $updates['payload'] = $validated['order_json'];
        }

        if (!empty($updates)) {
            $order->update($updates);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully.',
            'data' => $order
        ]);
    }

    /**
     * DELETE /api/v1/orders/{id}
     * Delete an order
     */
    public function destroy($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully.'
        ]);
    }

    /**
     * GET /api/v1/orders/{id}/summary
     * Get order summary for fulfillment
     */
    public function summary($id)
    {
        $order = Order::with('orderItems', 'shipments')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order summary retrieved successfully.',
            'data' => [
                'order_id' => $order->id,
                'shopify_order_id' => $order->shopify_order_id,
                'order_number' => $order->order_number,
                'customer' => [
                    'email' => $order->customer_email,
                    'name' => $order->customer_name
                ],
                'total_price' => $order->total_price,
                'status' => $order->fulfillment_status ?? $order->status,
                'items_count' => $order->orderItems->count(),
                'shipments_count' => $order->shipments->count(),
                'items' => $order->orderItems->map(fn($item) => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'title' => $item->title,
                    'quantity' => $item->quantity
                ])
            ]
        ]);
    }
}
