<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderItem;
use App\Models\Order;

class OrderItemController extends Controller
{
    /**
     * POST /api/v1/orders/{order_id}/items
     * Create order items for an order
     */
    public function store(Request $request, $order_id)
    {
        $order = Order::find($order_id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.line_item_id' => 'required|string',
            'items.*.shopify_line_item_id' => 'nullable|string',
            'items.*.sku' => 'required|string',
            'items.*.title' => 'required|string',
            'items.*.variant_title' => 'nullable|string',
            'items.*.dtfta_type' => 'nullable|string',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.fulfillment_status' => 'nullable|string',
            'items.*.properties' => 'nullable|array',
            'items.*.payload' => 'nullable|array',
            'items.*.status' => 'nullable|string'
        ]);

        $createdItems = [];
        foreach ($validated['items'] as $itemData) {
            // Set order_id for the item
            $itemData['order_id'] = $order_id;
            
            // Set defaults
            if (!isset($itemData['quantity']) || is_null($itemData['quantity'])) {
                $itemData['quantity'] = 1;
            }
            if (!isset($itemData['status']) || is_null($itemData['status'])) {
                $itemData['status'] = 'pending';
            }
            
            $item = OrderItem::create($itemData);
            $createdItems[] = $item;
        }

        return response()->json([
            'success' => true,
            'message' => 'Order items created successfully.',
            'data' => $createdItems
        ], 201);
    }

    /**
     * GET /api/v1/orders/{order_id}/items
     * Get all items for an order
     */
    public function index($order_id)
    {
        $order = Order::find($order_id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        $items = OrderItem::with('order')->where('order_id', $order_id)->get();

        return response()->json([
            'success' => true,
            'message' => 'Items retrieved successfully.',
            'data' => $items
        ]);
    }

    /**
     * GET /api/v1/order-items/{item_id}
     * Get a specific order item
     */
    public function show($item_id)
    {
        $item = OrderItem::with('order')->find($item_id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Order item not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item retrieved successfully.',
            'data' => $item
        ]);
    }

    /**
     * PUT /api/v1/order-items/{item_id}
     * Update an order item
     */
    public function update(Request $request, $item_id)
    {
        $item = OrderItem::find($item_id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Order item not found.'
            ], 404);
        }

        $validated = $request->validate([
            'quantity' => 'nullable|integer|min:1',
            'fulfillment_status' => 'nullable|string',
            'payload' => 'nullable|array'
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully.',
            'data' => $item
        ]);
    }

    /**
     * DELETE /api/v1/order-items/{item_id}
     * Delete an order item
     */
    public function destroy($item_id)
    {
        $item = OrderItem::find($item_id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Order item not found.'
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully.'
        ]);
    }
}
