<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Webhook;
use App\Models\FailedWebhook;
use App\Models\Shop;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use App\Models\OrderItem;

class WebhookController extends Controller
{
    /**
     * POST /webhooks/shopify
     * Handle incoming Shopify webhooks
     */
    public function handle(Request $request)
    {
        try {
            $hmac = $request->header('X-Shopify-Hmac-SHA256');
            $topic = $request->header('X-Shopify-Topic');
            $shopDomain = $request->header('X-Shopify-Shop-Domain');
            $webhookId = $request->header('X-Shopify-Webhook-Id');
            $shoppingApiVersion = $request->header('X-Shopify-Api-Version');

            // Verify HMAC signature
            if (!$this->verifyHmac($request, $hmac)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid HMAC signature'
                ], 401);
            }

            // Get shop by domain
            $shop = Shop::where('shop_domain', $shopDomain)->first();
            if (!$shop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shop not found'
                ], 404);
            }

            // Store webhook
            $webhook = Webhook::create([
                'shop_id' => $shop->id,
                'event_type' => $topic,
                'topic' => $topic,
                'webhook_id' => $webhookId,
                'shopify_webhook_id' => $webhookId,
                'payload' => $request->all(),
                'created_at_shopify' => now()
            ]);

            // Process webhook based on topic
            $this->processWebhook($webhook, $topic, $request->all());

            $webhook->update(['processed' => true, 'processed_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Webhook Error: ' . $e->getMessage());

            // Store as failed webhook for retry
            if (isset($webhook)) {
                FailedWebhook::create([
                    'webhook_id' => $webhook->id,
                    'shop_id' => $shop->id ?? null,
                    'event_type' => $topic ?? null,
                    'topic' => $topic ?? null,
                    'payload' => $request->all(),
                    'error_message' => $e->getMessage(),
                    'retry_count' => 0,
                    'max_retries' => 5,
                    'next_retry_at' => now()->addMinutes(5)
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Process webhook based on topic
     */
    private function processWebhook($webhook, $topic, $payload)
    {
        switch ($topic) {
            case 'orders/create':
                $this->handleOrderCreate($webhook->shop_id, $payload);
                break;
            case 'orders/updated':
                $this->handleOrderUpdate($webhook->shop_id, $payload);
                break;
            case 'orders/deleted':
                $this->handleOrderDelete($webhook->shop_id, $payload);
                break;
            case 'fulfillments/create':
                $this->handleFulfillmentCreate($webhook->shop_id, $payload);
                break;
            case 'fulfillments/update':
                $this->handleFulfillmentUpdate($webhook->shop_id, $payload);
                break;
        }
    }

    /**
     * Handle orders/create webhook
     */
    private function handleOrderCreate($shopId, $payload)
    {
        if (!isset($payload['id'])) {
            return;
        }

        $order = Order::updateOrCreate(
            [
                'shopify_order_id' => $payload['id'],
                'shop_id' => $shopId
            ],
            [
                'order_number' => $payload['order_number'] ?? null,
                'customer_email' => $payload['customer']['email'] ?? null,
                'customer_name' => $payload['customer']['first_name'] . ' ' . $payload['customer']['last_name'] ?? null,
                'total_price' => $payload['total_price'] ?? 0,
                'currency' => $payload['currency'] ?? 'USD',
                'fulfillment_status' => $payload['fulfillment_status'] ?? 'unfulfilled',
                'financial_status' => $payload['financial_status'] ?? null,
                'created_at_shopify' => $payload['created_at'] ?? now(),
                'updated_at_shopify' => $payload['updated_at'] ?? now(),
                'payload' => $payload
            ]
        );

        // Create order items
        if (isset($payload['line_items'])) {
            foreach ($payload['line_items'] as $item) {
                OrderItem::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'line_item_id' => $item['id']
                    ],
                    [
                        'shopify_line_item_id' => $item['id'] ?? null,
                        'sku' => $item['sku'] ?? null,
                        'title' => $item['title'] ?? null,
                        'variant_title' => $item['variant_title'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                        'price' => $item['price'] ?? 0,
                        'fulfillment_status' => $item['fulfillment_status'] ?? 'unfulfilled',
                        'properties' => $item['properties'] ?? null,
                        'payload' => $item,
                        'status' => 'pending'
                    ]
                );
            }
        }
    }

    /**
     * Handle orders/updated webhook
     */
    private function handleOrderUpdate($shopId, $payload)
    {
        if (!isset($payload['id'])) {
            return;
        }

        Order::where('shopify_order_id', $payload['id'])
            ->where('shop_id', $shopId)
            ->update([
                'fulfillment_status' => $payload['fulfillment_status'] ?? null,
                'financial_status' => $payload['financial_status'] ?? null,
                'updated_at_shopify' => $payload['updated_at'] ?? now(),
                'payload' => $payload
            ]);
    }

    /**
     * Handle orders/deleted webhook
     */
    private function handleOrderDelete($shopId, $payload)
    {
        if (!isset($payload['id'])) {
            return;
        }

        Order::where('shopify_order_id', $payload['id'])
            ->where('shop_id', $shopId)
            ->delete();
    }

    /**
     * Handle fulfillments/create webhook
     */
    private function handleFulfillmentCreate($shopId, $payload)
    {
        // Implement fulfillment tracking update logic
    }

    /**
     * Handle fulfillments/update webhook
     */
    private function handleFulfillmentUpdate($shopId, $payload)
    {
        // Implement fulfillment status update logic
    }

    /**
     * Verify HMAC signature
     */
    private function verifyHmac($request, $hmac)
    {
        // If HMAC is not provided and debug mode is on, allow it for testing
        if (!$hmac && env('WEBHOOK_DEBUG', false)) {
            Log::warning('Webhook: HMAC verification skipped (debug mode enabled)');
            return true;
        }

        // If no secret configured, log warning but allow for testing
        $secret = config('services.shopify.webhook_secret');
        if (!$secret) {
            if (env('WEBHOOK_DEBUG', false)) {
                Log::warning('Webhook: No webhook secret configured, skipping HMAC verification');
                return true;
            }
            Log::error('Webhook: No webhook secret configured');
            return false;
        }

        $data = $request->getContent();
        $calculatedHmac = base64_encode(
            hash_hmac('sha256', $data, $secret, true)
        );

        if (env('WEBHOOK_DEBUG', false)) {
            Log::debug('Webhook debug: provided HMAC', ['provided' => $hmac]);
            Log::debug('Webhook debug: calculated HMAC', ['calculated' => $calculatedHmac]);
            Log::debug('Webhook debug: payload length', ['len' => strlen($data)]);
        }

        return hash_equals($calculatedHmac, $hmac);
    }

    /**
     * GET /webhooks/status
     * Check webhook processing status
     */
    public function status(Request $request)
    {
        $failedCount = FailedWebhook::where('retry_count', '<', 5)->count();
        $pendingCount = Webhook::where('processed', false)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'failed_webhooks' => $failedCount,
                'pending_webhooks' => $pendingCount,
                'timestamp' => now()
            ]
        ]);
    }
}
