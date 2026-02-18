<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Webhook;
use App\Models\FailedWebhook;
use App\Models\Shop;
use App\Models\Order;
use App\Models\Job;
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
            $shopDomain = $this->resolveShopDomain($request);
            $webhookId = $request->header('X-Shopify-Webhook-Id');

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
                    'message' => 'Shop not found',
                    'shop_domain' => $shopDomain
                ], 404);
            }

            // Idempotency guard
            if ($webhookId && Webhook::where('shop_id', $shop->id)
                ->where('topic', $topic)
                ->where(function ($query) use ($webhookId) {
                    $query->where('webhook_id', $webhookId)
                        ->orWhere('shopify_webhook_id', $webhookId);
                })
                ->exists()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Duplicate webhook ignored'
                ], 200);
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
            case 'orders/cancelled':
                $this->handleOrderCancelled($webhook->shop_id, $payload);
                break;
            case 'app/uninstalled':
                $this->handleAppUninstalled($webhook->shop_id);
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
                'customer_name' => trim(($payload['customer']['first_name'] ?? '') . ' ' . ($payload['customer']['last_name'] ?? '')),
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
            $hasValidDtftaLineItem = false;
            $hasInvalidDtftaLineItem = false;

            foreach ($payload['line_items'] as $item) {
                $lineItemProperties = $this->normalizeLineItemProperties($item['properties'] ?? []);
                $isDtftaSku = $this->isDtftaSku($item['sku'] ?? null);
                $isDtftaType = strtoupper((string) ($lineItemProperties['dtfta_type'] ?? '')) === 'APPAREL_POD';
                $hasDtftaMarkers = $isDtftaSku || $isDtftaType;

                if ($hasDtftaMarkers) {
                    $validation = $this->validateDtftaLineItem($item, $lineItemProperties);
                    if ($validation['valid']) {
                        $hasValidDtftaLineItem = true;
                    } else {
                        $hasInvalidDtftaLineItem = true;
                    }
                }

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
                        'properties' => $lineItemProperties ?: ($item['properties'] ?? null),
                        'payload' => $item,
                        'status' => $hasDtftaMarkers ? ($validation['valid'] ? 'pending' : 'exception') : 'pending'
                    ]
                );
            }

            if ($hasInvalidDtftaLineItem) {
                Job::updateOrCreate(
                    [
                        'shop_id' => $shopId,
                        'order_id' => $order->id,
                        'job_type' => 'dtfta_apparel_pod'
                    ],
                    [
                        'status' => 'artwork_needed',
                        'payload' => $payload,
                        'error_message' => 'Missing or invalid dtfta_ line-item properties'
                    ]
                );
                $order->update(['fulfillment_status' => 'artwork_needed', 'status' => 'artwork_needed']);
            } elseif ($hasValidDtftaLineItem) {
                Job::updateOrCreate(
                    [
                        'shop_id' => $shopId,
                        'order_id' => $order->id,
                        'job_type' => 'dtfta_apparel_pod'
                    ],
                    [
                        'status' => 'pending',
                        'payload' => $payload,
                        'error_message' => null
                    ]
                );
                if (in_array($order->fulfillment_status, [null, '', 'unfulfilled', 'new', 'pending'], true)) {
                    $order->update(['fulfillment_status' => 'pending', 'status' => 'pending']);
                }
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
     * Handle orders/cancelled webhook
     */
    private function handleOrderCancelled($shopId, $payload)
    {
        if (!isset($payload['id'])) {
            return;
        }

        $order = Order::where('shopify_order_id', $payload['id'])
            ->where('shop_id', $shopId)
            ->first();

        if (!$order) {
            return;
        }

        $order->update([
            'fulfillment_status' => 'cancelled',
            'status' => 'cancelled',
            'cancelled_at' => $payload['cancelled_at'] ?? now(),
            'payload' => $payload
        ]);

        $jobs = Job::where('order_id', $order->id)->get();
        foreach ($jobs as $job) {
            if (in_array($job->status, ['in_production', 'processing'], true)) {
                $job->update([
                    'status' => 'exception',
                    'error_message' => 'Cancellation requested after production start'
                ]);
            } elseif (!in_array($job->status, ['shipped', 'completed'], true)) {
                $job->update([
                    'status' => 'cancelled',
                    'error_message' => 'Cancelled from Shopify'
                ]);
            }
        }
    }

    /**
     * Handle app/uninstalled webhook
     */
    private function handleAppUninstalled($shopId)
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return;
        }

        $shop->update([
            'status' => 'uninstalled',
            'shopify_access_token' => null,
            'shopify_scopes' => null,
            'uninstalled_at' => now(),
        ]);

        Webhook::where('shop_id', $shop->id)->delete();
        FailedWebhook::where('shop_id', $shop->id)->delete();
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

    private function resolveShopDomain(Request $request): ?string
    {
        $candidates = [
            $request->header('X-Shopify-Shop-Domain'),
            $request->header('x-shopify-shop-domain'),
            $request->input('shop_domain'),
            $request->input('shop'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeShopDomain($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeShopDomain($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim(strtolower($value));
        if ($value === '') {
            return null;
        }

        $value = preg_replace('#^https?://#', '', $value);
        $value = preg_replace('#/.*$#', '', $value);

        return $value ?: null;
    }

    private function normalizeLineItemProperties($properties): array
    {
        if (!is_array($properties)) {
            return [];
        }

        $normalized = [];
        foreach ($properties as $property) {
            if (is_array($property) && isset($property['name'])) {
                $normalized[(string) $property['name']] = $property['value'] ?? null;
            } elseif (is_array($property)) {
                foreach ($property as $k => $v) {
                    $normalized[(string) $k] = $v;
                }
            }
        }

        return $normalized;
    }

    private function isDtftaSku(?string $sku): bool
    {
        if (!$sku) {
            return false;
        }

        return str_starts_with(strtoupper($sku), 'DTFTA-APP-');
    }

    private function validateDtftaLineItem(array $lineItem, array $properties): array
    {
        $required = [
            'dtfta_type',
            'dtfta_garment_brand',
            'dtfta_garment_style',
            'dtfta_garment_color',
            'dtfta_garment_size',
            'dtfta_print_plan',
        ];

        $missing = [];
        foreach ($required as $field) {
            if (!isset($properties[$field]) || trim((string) $properties[$field]) === '') {
                $missing[] = $field;
            }
        }

        $hasArtwork = false;
        foreach ($properties as $key => $value) {
            if (str_starts_with((string) $key, 'dtfta_artwork_') && trim((string) $value) !== '') {
                $hasArtwork = true;
                break;
            }
        }
        if (!$hasArtwork) {
            $missing[] = 'dtfta_artwork_*';
        }

        $sku = (string) ($lineItem['sku'] ?? '');
        if (!$this->isDtftaSku($sku)) {
            $missing[] = 'sku_format';
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * POST /fulfillment_order_notification
     * Hosted callback endpoint for fulfillment request/cancellation request.
     * Responds 200 quickly and performs reconciliation after response.
     */
    public function fulfillmentOrderNotification(Request $request)
    {
        $hmac = $request->header('X-Shopify-Hmac-SHA256');
        if (!$this->verifyHmac($request, $hmac)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid HMAC signature'
            ], 401);
        }

        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $callbackEventId = $request->header('X-Shopify-Webhook-Id') ?: sha1($shopDomain . '|' . $request->getContent());

        $shop = Shop::where('shop_domain', $shopDomain)->first();
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found'
            ], 404);
        }

        $existing = Webhook::where('shop_id', $shop->id)
            ->where('topic', 'fulfillment_order_notification')
            ->where(function ($query) use ($callbackEventId) {
                $query->where('webhook_id', $callbackEventId)
                    ->orWhere('shopify_webhook_id', $callbackEventId);
            })
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Duplicate callback ignored'
            ], 200);
        }

        $payload = $request->all();
        $webhook = Webhook::create([
            'shop_id' => $shop->id,
            'event_type' => (string) ($payload['kind'] ?? $payload['request_type'] ?? 'fulfillment_order_notification'),
            'topic' => 'fulfillment_order_notification',
            'webhook_id' => $callbackEventId,
            'shopify_webhook_id' => $callbackEventId,
            'payload' => $payload,
            'created_at_shopify' => now(),
            'processed' => false,
        ]);

        dispatch(function () use ($webhook, $shop, $payload) {
            try {
                $kind = strtoupper((string) ($payload['kind'] ?? $payload['request_type'] ?? ''));
                $fo = $payload['fulfillment_order'] ?? $payload;
                $shopifyOrderId = $fo['order_id'] ?? $payload['order_id'] ?? null;

                if (!$shopifyOrderId) {
                    throw new \RuntimeException('Missing order_id in callback payload');
                }

                $order = Order::firstOrCreate(
                    ['shop_id' => $shop->id, 'shopify_order_id' => (string) $shopifyOrderId],
                    [
                        'status' => 'pending',
                        'fulfillment_status' => 'pending',
                        'payload' => $payload,
                        'raw_data' => $payload,
                    ]
                );

                if (in_array($kind, ['CANCELLATION_REQUEST', 'CANCEL', 'CANCELLED'], true)) {
                    $order->update(['status' => 'cancelled', 'fulfillment_status' => 'cancelled']);
                    Job::where('order_id', $order->id)
                        ->whereNotIn('status', ['shipped', 'completed'])
                        ->update(['status' => 'cancelled', 'error_message' => 'Cancellation requested from fulfillment callback']);
                } else {
                    Job::updateOrCreate(
                        [
                            'shop_id' => $shop->id,
                            'order_id' => $order->id,
                            'job_type' => 'fulfillment_request',
                        ],
                        [
                            'status' => 'pending',
                            'payload' => $payload,
                            'error_message' => null,
                        ]
                    );
                    $order->update(['status' => 'pending', 'fulfillment_status' => 'pending']);
                }

                $webhook->update(['processed' => true, 'processed_at' => now()]);
            } catch (\Throwable $e) {
                FailedWebhook::create([
                    'webhook_id' => $webhook->id,
                    'shop_id' => $shop->id,
                    'event_type' => 'fulfillment_order_notification',
                    'topic' => 'fulfillment_order_notification',
                    'payload' => $payload,
                    'error_message' => $e->getMessage(),
                    'retry_count' => 0,
                    'max_retries' => 5,
                    'next_retry_at' => now()->addMinutes(5),
                ]);
            }
        })->afterResponse();

        return response()->json([
            'success' => true,
            'message' => 'Accepted'
        ], 200);
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
