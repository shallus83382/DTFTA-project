<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Webhook;
use App\Models\FailedWebhook;
use App\Models\Shop;
use App\Models\Order;
use App\Models\Job;
use App\Models\Shipment;
use App\Models\FulfillmentService;
use App\Models\CustomProduct;
use App\Services\AppSignatureVerifier;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Log;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Services\ShopifyWebhookVerifier;

class WebhookController extends Controller
{
    public function __construct(
        private AppSignatureVerifier $appSignatureVerifier,
        private ShopifyWebhookVerifier $shopifyWebhookVerifier,
        private ShopifyService $shopifyService
    ) {
    }

    /**
     * POST /webhooks/shopify
     * Handle incoming Shopify webhooks
     */
    public function handle(Request $request)
    {
        $webhook = null;
        $shop = null;
        $topic = null;
        $shopDomain = null;

        try {
            $appTimestamp = (string) $request->header('X-App-Timestamp', '');
            $appSignature = (string) $request->header('X-App-Signature', '');
            $topic = (string) $request->header('X-Shopify-Topic', '');
            $shopDomain = $this->resolveShopDomain($request);
            $webhookId = (string) $request->header('X-Shopify-Webhook-Id', '');
            $payload = $request->json()->all() ?: $request->all();

            if ($topic === '') {
                $topic = (string) $request->input('topic', '');
            }

            $normalizedTopic = $this->normalizeWebhookTopic($topic);




            // Route install events through the existing signed-install flow
            if (in_array($normalizedTopic, ['app/installed', 'app/install', 'install'], true)) {
                Log::info('Install event routed to signed install handler', [
                    'topic' => $topic,
                    'normalized_topic' => $normalizedTopic,
                    'shop_domain' => $shopDomain,
                ]);

                return app(AuthController::class)->install($request);
            }

            Log::info('Shopify webhook received', [
                'topic' => $topic,
                'normalized_topic' => $normalizedTopic,
                'shop_domain' => $shopDomain,
                'app_signature_present' => $appSignature !== '',
                'app_timestamp_present' => $appTimestamp !== '',
                'shopify_hmac_present' => $request->header('X-Shopify-Hmac-Sha256') !== null,
            ]);

            if (!$this->appSignatureVerifier->verify(
                $request,
                $appTimestamp,
                $appSignature,
                $normalizedTopic === 'app/uninstalled'
                    ? AppSignatureVerifier::MODE_TIMESTAMP_ONLY
                    : AppSignatureVerifier::MODE_TIMESTAMP_PLUS_PAYLOAD_VARIANTS
            )) {
                Log::warning('Shopify webhook rejected: invalid Shopify HMAC', [
                    'topic' => $topic,
                    'normalized_topic' => $normalizedTopic,
                    'shop_domain' => $shopDomain,
                ]);
            
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Shopify webhook signature',
                ], 401);
            }

            $shop = Shop::where('shop_domain', $shopDomain)->first();
            if (!$shop) {
                Log::warning('Shopify webhook rejected: shop not found', [
                    'topic' => $topic,
                    'normalized_topic' => $normalizedTopic,
                    'shop_domain' => $shopDomain,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Shop not found',
                    'shop_domain' => $shopDomain
                ], 404);
            }


            if ($webhookId !== '') {
                $existingWebhook = Webhook::where('shop_id', $shop->id)
                    ->where('topic', $normalizedTopic)
                    ->where(function ($q) use ($webhookId) {
                        $q->where('webhook_id', $webhookId)
                          ->orWhere('shopify_webhook_id', $webhookId)
                          ->orWhere('shopify_event_id', $webhookId);
                    })
                    ->first();
            
                if ($existingWebhook) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Duplicate webhook ignored',
                    ], 200);
                }
            }

            $webhook = Webhook::create([
                'shop_id' => $shop->id,
                'event_type' => $normalizedTopic,
                'topic' => $normalizedTopic,
                'webhook_id' => $webhookId ?: null,
                'shopify_webhook_id' => $webhookId ?: null,
                'shopify_event_id' => $webhookId ?: null,
                'payload' => $payload,
                'created_at_shopify' => now(),
                'processed' => false,
            ]);

            $this->processWebhook($webhook, $normalizedTopic, $payload);

            $webhook->update([
                'processed' => true,
                'processed_at' => now()
            ]);

            Log::info('Shopify webhook processed successfully', [
                'topic' => $topic,
                'normalized_topic' => $normalizedTopic,
                'shop_domain' => $shopDomain,
                'webhook_row_id' => $webhook->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Webhook Error: ' . $e->getMessage(), [
                'topic' => $topic,
                'shop_domain' => $shopDomain,
            ]);

            if ($webhook) {
                FailedWebhook::create([
                    'webhook_id' => $webhook->id,
                    'shop_id' => $shop?->id,
                    'event_type' => $topic,
                    'topic' => $topic,
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
     * Process webhook based on normalized topic
     */
    private function processWebhook($webhook, string $topic, array $payload): void
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

            case 'fulfillment_orders/fulfillment_request_submitted':
            case 'fulfillment_orders/cancellation_request_submitted':
            case 'fulfillment_orders/moved':
            case 'fulfillment_orders/split':
            case 'fulfillment_orders/merged':
            case 'fulfillment_orders/cancelled':
            case 'fulfillment_orders/order_routing_complete':
            case 'fulfillment_orders/placed_on_hold':
            case 'fulfillment_orders/hold_released':
                    $this->handleFulfillmentOrderTopic($webhook->shop_id, $topic, $payload);
                    break;

            default:
                Log::warning('Unhandled Shopify webhook topic', [
                    'topic' => $topic,
                    'webhook_id' => $webhook->id ?? null,
                    'payload_keys' => array_keys($payload),
                ]);
                break;
        }
    }


    private function handleFulfillmentOrderTopic(int $shopId, string $topic, array $payload): void
    {
        [$fulfillmentOrderId, $shopifyOrderId] = $this->extractFulfillmentOrderContext($payload);

        if (!$fulfillmentOrderId) {
            throw new \RuntimeException("Missing fulfillment_order id for topic {$topic}");
        }

        $foResult = $this->shopifyService->getFulfillmentOrder($shopId, $fulfillmentOrderId);

        if (
            empty($foResult['success']) ||
            empty($foResult['data']['fulfillment_order'])
        ) {
            throw new \RuntimeException(
                'Unable to fetch fulfillment order from Shopify: ' . ($foResult['message'] ?? 'Unknown error')
            );
        }

        $fo = $foResult['data']['fulfillment_order'];

        $shopifyOrderId = (string) (
            $fo['order_id']
            ?? $shopifyOrderId
            ?? ''
        );

        if ($shopifyOrderId === '') {
            throw new \RuntimeException('Unable to resolve Shopify order id from fulfillment order');
        }

        $order = Order::where('shop_id', $shopId)
            ->where('shopify_order_id', $shopifyOrderId)
            ->first();

        if (!$order) {
            // Better than firstOrCreate with partial data:
            // fetch full order from Shopify, then store it properly.
            $orderSync = $this->shopifyService->getOrderById($shopId, $shopifyOrderId);

            if (empty($orderSync['success']) || empty($orderSync['data']['order'])) {
                throw new \RuntimeException("Order {$shopifyOrderId} not found locally and could not be fetched from Shopify");
            }

            $orderPayload = $orderSync['data']['order'];

            $order = Order::updateOrCreate(
                [
                    'shop_id' => $shopId,
                    'shopify_order_id' => (string) $shopifyOrderId,
                ],
                [
                    'order_number' => $orderPayload['order_number'] ?? null,
                    'customer_email' => $orderPayload['customer']['email'] ?? null,
                    'customer_name' => trim(
                        ($orderPayload['customer']['first_name'] ?? '') . ' ' .
                        ($orderPayload['customer']['last_name'] ?? '')
                    ),
                    'total_price' => $orderPayload['total_price'] ?? 0,
                    'currency' => $orderPayload['currency'] ?? 'USD',
                    'financial_status' => $orderPayload['financial_status'] ?? null,
                    'fulfillment_status' => $orderPayload['fulfillment_status'] ?? 'unfulfilled',
                    'created_at_shopify' => $orderPayload['created_at'] ?? now(),
                    'updated_at_shopify' => $orderPayload['updated_at'] ?? now(),
                    'payload' => $orderPayload,
                ]
            );
        }

        $job = Job::updateOrCreate(
            [
                'shop_id' => $shopId,
                'order_id' => $order->id,
                'job_type' => 'dtfta_apparel_pod',
            ],
            [
                'status' => $this->mapFulfillmentOrderToLocalJobStatus($topic, $fo, $order),
                'payload' => [
                    'topic' => $topic,
                    'webhook_payload' => $payload,
                    'fulfillment_order' => $fo,
                ],
                'error_message' => null,
                'started_at' => in_array(($fo['request_status'] ?? null), ['accepted', 'ACCEPTED'], true) ? now() : null,
            ]
        );

        // Keep order state aligned with actual FO state
        $order->update([
            'status' => $job->status,
            'fulfillment_status' => $this->mapFulfillmentOrderToOrderStatus($fo, $job->status),
            'updated_at_shopify' => now(),
            'payload' => $payload,
        ]);

        if ($topic === 'fulfillment_orders/fulfillment_request_submitted') {
            $this->handleFulfillmentRequestDecision($shopId, $order, $job, $fulfillmentOrderId, $fo);
        }

        if ($topic === 'fulfillment_orders/cancellation_request_submitted') {
            $this->handleCancellationRequestDecision($shopId, $order, $job, $fulfillmentOrderId, $fo);
        }
    }

    private function handleFulfillmentRequestDecision(
        int $shopId,
        Order $order,
        Job $job,
        string $fulfillmentOrderId,
        array $fo
    ): void {
        $requestStatus = strtoupper((string) ($fo['request_status'] ?? ''));
    
        // Ignore if already accepted/rejected
        if (in_array($requestStatus, ['ACCEPTED', 'REJECTED', 'CANCELLATION_REJECTED'], true)) {
            return;
        }
    
        if ($order->status === 'cancelled') {
            $result = $this->shopifyService->rejectFulfillmentRequest(
                $shopId,
                $fulfillmentOrderId,
                'Order is already cancelled in local system'
            );
    
            if (!$result['success']) {
                throw new \RuntimeException(
                    'Reject fulfillment request failed: ' . ($result['message'] ?? 'Unknown error')
                );
            }
    
            $job->update([
                'status' => 'cancelled',
                'error_message' => 'Rejected because order is cancelled',
            ]);
    
            return;
        }
    
        $hasArtworkNeeded = Job::where('order_id', $order->id)
            ->where('status', 'artwork_needed')
            ->exists();
    
        if ($hasArtworkNeeded) {
            // Do not accept yet if your business requires artwork before production.
            $job->update([
                'status' => 'artwork_needed',
                'error_message' => null,
            ]);
    
            $order->update([
                'status' => 'artwork_needed',
                'fulfillment_status' => 'artwork_needed',
            ]);
    
            return;
        }
    
        $result = $this->shopifyService->acceptFulfillmentRequest(
            $shopId,
            $fulfillmentOrderId,
            'Fulfillment request accepted'
        );
    
        if (!$result['success']) {
            throw new \RuntimeException(
                'Accept fulfillment request failed: ' . ($result['message'] ?? 'Unknown error')
            );
        }
    
        $job->update([
            'status' => 'in_production',
            'started_at' => now(),
            'error_message' => null,
        ]);
    
        $order->update([
            'status' => 'in_production',
            'fulfillment_status' => 'accepted',
        ]);
    }


    private function handleCancellationRequestDecision(
        int $shopId,
        Order $order,
        Job $job,
        string $fulfillmentOrderId,
        array $fo
    ): void {
        $isShipped = in_array($job->status, ['shipped', 'completed'], true);
        $inProduction = in_array($job->status, ['in_production', 'processing'], true);
    
        if ($isShipped) {
            $result = $this->shopifyService->rejectCancellationRequest(
                $shopId,
                $fulfillmentOrderId,
                'Cancellation rejected: already shipped'
            );
    
            if (!$result['success']) {
                throw new \RuntimeException(
                    'Reject cancellation request failed: ' . ($result['message'] ?? 'Unknown error')
                );
            }
    
            $order->update([
                'status' => 'exception',
                'fulfillment_status' => 'exception',
            ]);
    
            $job->update([
                'status' => 'exception',
                'error_message' => 'Cancellation requested after shipment completion',
            ]);
    
            return;
        }
    
        $result = $this->shopifyService->acceptCancellationRequest(
            $shopId,
            $fulfillmentOrderId,
            'Cancellation accepted'
        );
    
        if (!$result['success']) {
            throw new \RuntimeException(
                'Accept cancellation request failed: ' . ($result['message'] ?? 'Unknown error')
            );
        }
    
        $cancelStatus = $inProduction ? 'exception' : 'cancelled';
        $cancelReason = $inProduction
            ? 'Cancellation requested after production start'
            : 'Cancelled from Shopify cancellation request';
    
        $order->update([
            'status' => $cancelStatus,
            'fulfillment_status' => $cancelStatus,
        ]);
    
        $job->update([
            'status' => $cancelStatus,
            'error_message' => $cancelReason,
        ]);
    }

    private function mapFulfillmentOrderToLocalJobStatus(string $topic, array $fo, Order $order): string
    {
        $foStatus = strtoupper((string) ($fo['status'] ?? ''));
        $requestStatus = strtoupper((string) ($fo['request_status'] ?? ''));

        if ($topic === 'fulfillment_orders/cancelled' || $foStatus === 'CANCELLED') {
            return 'cancelled';
        }

        if ($topic === 'fulfillment_orders/cancellation_request_submitted') {
            return 'cancellation_requested';
        }

        if ($requestStatus === 'ACCEPTED') {
            return 'in_production';
        }

        if ($requestStatus === 'SUBMITTED') {
            return $order->status === 'artwork_needed' ? 'artwork_needed' : 'pending';
        }

        return $order->status ?: 'pending';
    }

    private function mapFulfillmentOrderToOrderStatus(array $fo, string $jobStatus): string
    {
        $foStatus = strtoupper((string) ($fo['status'] ?? ''));

        if ($foStatus === 'CANCELLED') {
            return 'cancelled';
        }

        return match ($jobStatus) {
            'accepted' => 'in_production',
            'artwork_needed' => 'artwork_needed',
            'cancelled' => 'cancelled',
            'exception' => 'exception',
            default => 'pending',
        };
    }

    /**
     * Normalize Shopify topic.
     * Supports:
     * - Shopify header format: orders/create
     * - local test format: ORDERS_CREATE
     * - lower underscore format: orders_create
     */
    private function normalizeWebhookTopic(?string $topic): string
    {
        $topic = strtolower(trim((string) $topic));

        if ($topic === '') {
            return '';
        }

        if (str_contains($topic, '/')) {
            return $topic;
        }

        $map = [
            'orders_create' => 'orders/create',
            'orders_updated' => 'orders/updated',
            'orders_deleted' => 'orders/deleted',
            'orders_cancelled' => 'orders/cancelled',
            'app_uninstalled' => 'app/uninstalled',
            'app_installed' => 'app/installed',
            'app_install' => 'app/install',
            'fulfillments_create' => 'fulfillments/create',
            'fulfillments_update' => 'fulfillments/update',
            'fulfillment_orders_fulfillment_request_submitted' => 'fulfillment_orders/fulfillment_request_submitted',
            'fulfillment_orders_cancellation_request_submitted' => 'fulfillment_orders/cancellation_request_submitted',
            'fulfillment_orders_moved' => 'fulfillment_orders/moved',
            'fulfillment_orders_split'=>'fulfillment_orders/split',
            'fulfillment_orders_merged'=> 'fulfillment_orders/merged',
            'fulfillment_orders_cancelled'=> 'fulfillment_orders/cancelled',
            'fulfillment_orders_order_routing_complete'=> 'fulfillment_orders/order_routing_complete',
            'fulfillment_orders_placed_on_hold' =>'fulfillment_orders/placed_on_hold',
            'fulfillment_orders/hold_released' => 'fulfillment_orders/hold_released',
        ];

        return $map[$topic] ?? $topic;
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

        if (isset($payload['line_items'])) {
            $hasValidDtftaLineItem = false;
            $hasInvalidDtftaLineItem = false;

            foreach ($payload['line_items'] as $item) {
                $lineItemProperties = $this->normalizeLineItemProperties($item['properties'] ?? []);
                $isDtftaSku = $this->isDtftaSku($item['sku'] ?? null);
                $isDtftaType = strtoupper((string) ($lineItemProperties['dtfta_type'] ?? '')) === 'APPAREL_POD';
                $hasDtftaMarkers = $isDtftaSku || $isDtftaType;

                $validation = ['valid' => true, 'missing' => []];

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

                $order->update([
                    'fulfillment_status' => 'artwork_needed',
                    'status' => 'artwork_needed'
                ]);
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
                    $order->update([
                        'fulfillment_status' => 'pending',
                        'status' => 'pending'
                    ]);
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
                'updated_at_shopify' => isset($payload['updated_at'])
                    ? Carbon::parse($payload['updated_at'])->format('Y-m-d H:i:s')
                    : now(),
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

        $orderIds = Order::where('shop_id', $shop->id)->pluck('id');

        Order::where('shop_id', $shop->id)->update([
            'status' => 'inactive',
            'fulfillment_status' => 'inactive',
        ]);

        Job::where('shop_id', $shop->id)->update(['status' => 'inactive']);
        Shipment::where('shop_id', $shop->id)->update(['status' => 'inactive']);
        FulfillmentService::where('shop_id', $shop->id)->update(['status' => 'inactive']);

        if ($orderIds->isNotEmpty()) {
            OrderItem::whereIn('order_id', $orderIds)->update(['status' => 'inactive']);
        }

        if ($orderIds->isNotEmpty()) {
            OrderItem::whereIn('order_id', $orderIds)->delete();
        }

        Shipment::where('shop_id', $shop->id)->delete();
        Job::where('shop_id', $shop->id)->delete();
        Order::where('shop_id', $shop->id)->delete();
        FulfillmentService::where('shop_id', $shop->id)->delete();
        CustomProduct::where('shop_id', $shop->id)->delete();

        $shop->update([
            'status' => 'inactive',
            'shopify_access_token' => null,
            'shopify_scopes' => null,
            'fulfillment_service_id' => null,
            'location_id' => null,
            'uninstalled_at' => now(),
        ]);

        $shop->delete();

        Webhook::where('shop_id', $shop->id)->delete();
        FailedWebhook::where('shop_id', $shop->id)->delete();
    }

    /**
     * Handle fulfillments/create webhook
     */
    private function handleFulfillmentCreate($shopId, $payload)
    {
        $shopifyFulfillmentId = $payload['id'] ?? null;
        if (!$shopifyFulfillmentId) {
            return;
        }

        $shopifyOrderId = $payload['order_id'] ?? null;
        $order = $shopifyOrderId
            ? Order::where('shop_id', $shopId)->where('shopify_order_id', (string) $shopifyOrderId)->first()
            : null;

        $jobId = null;
        if ($order) {
            $jobId = Job::where('order_id', $order->id)->value('id');
            if (!$jobId) {
                $job = Job::create([
                    'shop_id' => $shopId,
                    'order_id' => $order->id,
                    'job_type' => 'dtfta_apparel_pod',
                    'status' => 'in_production',
                    'payload' => $payload,
                ]);
                $jobId = $job->id;
            }
        }

        if (!$jobId) {
            Log::warning('Fulfillment create webhook skipped: no related job/order found', [
                'shop_id' => $shopId,
                'fulfillment_id' => $shopifyFulfillmentId,
            ]);
            return;
        }

        $trackingNumber = $this->firstNonEmpty(
            $payload['tracking_number'] ?? null,
            $payload['tracking_numbers'] ?? null
        ) ?: ('shopify-' . (string) $shopifyFulfillmentId);

        $trackingUrl = $this->firstNonEmpty(
            $payload['tracking_url'] ?? null,
            $payload['tracking_urls'] ?? null
        );

        $trackingCompany = $payload['tracking_company'] ?? null;

        Shipment::updateOrCreate(
            [
                'shop_id' => $shopId,
                'shipment_id' => (string) $shopifyFulfillmentId,
            ],
            [
                'job_id' => $jobId,
                'order_id' => $order?->id,
                'carrier' => $trackingCompany ?: 'shopify',
                'tracking_company' => $trackingCompany,
                'tracking_number' => (string) $trackingNumber,
                'tracking_url' => $trackingUrl,
                'status' => 'created',
                'line_items' => $payload['line_items'] ?? null,
                'shipped_at' => $payload['created_at'] ?? now(),
                'created_at_shopify' => $payload['created_at'] ?? now(),
                'updated_at_shopify' => $payload['updated_at'] ?? now(),
                'payload' => $payload,
            ]
        );

        if ($order) {
            $order->update([
                'status' => 'shipped',
                'fulfillment_status' => 'fulfilled',
                'updated_at_shopify' => $payload['updated_at'] ?? now(),
            ]);

            Job::where('order_id', $order->id)
                ->whereNotIn('status', ['cancelled', 'failed', 'exception'])
                ->update([
                    'status' => 'shipped',
                    'completed_at' => now(),
                    'error_message' => null,
                ]);
        }
    }

    /**
     * Handle fulfillments/update webhook
     */
    private function handleFulfillmentUpdate($shopId, $payload)
    {
        $shopifyFulfillmentId = $payload['id'] ?? null;
        if (!$shopifyFulfillmentId) {
            return;
        }

        $shipment = Shipment::where('shop_id', $shopId)
            ->where('shipment_id', (string) $shopifyFulfillmentId)
            ->first();

        $statusInput = strtolower((string) ($payload['status'] ?? $payload['shipment_status'] ?? ''));

        $mappedStatus = match ($statusInput) {
            'cancelled', 'canceled' => 'cancelled',
            'failure', 'failed', 'error' => 'exception',
            'in_progress', 'processing' => 'processing',
            'success', 'open' => 'created',
            default => $shipment?->status ?? 'created',
        };

        $trackingNumber = $this->firstNonEmpty(
            $payload['tracking_number'] ?? null,
            $payload['tracking_numbers'] ?? null
        );

        $trackingUrl = $this->firstNonEmpty(
            $payload['tracking_url'] ?? null,
            $payload['tracking_urls'] ?? null
        );

        $trackingCompany = $payload['tracking_company'] ?? null;

        if ($shipment) {
            $shipment->update([
                'status' => $mappedStatus,
                'carrier' => $trackingCompany ?: $shipment->carrier,
                'tracking_company' => $trackingCompany ?: $shipment->tracking_company,
                'tracking_number' => $trackingNumber ?: $shipment->tracking_number,
                'tracking_url' => $trackingUrl ?: $shipment->tracking_url,
                'updated_at_shopify' => $payload['updated_at'] ?? now(),
                'payload' => $payload,
            ]);
        }

        $shopifyOrderId = $payload['order_id'] ?? null;
        $order = null;

        if ($shipment?->order_id) {
            $order = Order::find($shipment->order_id);
        } elseif ($shopifyOrderId) {
            $order = Order::where('shop_id', $shopId)
                ->where('shopify_order_id', (string) $shopifyOrderId)
                ->first();
        }

        if (!$order) {
            return;
        }

        if ($mappedStatus === 'cancelled') {
            $order->update([
                'status' => 'cancelled',
                'fulfillment_status' => 'cancelled',
                'updated_at_shopify' => $payload['updated_at'] ?? now(),
            ]);

            Job::where('order_id', $order->id)
                ->whereNotIn('status', ['shipped', 'completed'])
                ->update([
                    'status' => 'cancelled',
                    'error_message' => 'Fulfillment cancelled from Shopify',
                ]);

            return;
        }

        if ($mappedStatus === 'exception') {
            $order->update([
                'status' => 'exception',
                'updated_at_shopify' => $payload['updated_at'] ?? now(),
            ]);

            Job::where('order_id', $order->id)
                ->whereNotIn('status', ['shipped', 'completed', 'cancelled'])
                ->update([
                    'status' => 'exception',
                    'error_message' => 'Fulfillment update reported failure',
                ]);

            return;
        }

        if ($mappedStatus === 'processing') {
            $order->update([
                'status' => 'in_production',
                'fulfillment_status' => 'in_progress',
                'updated_at_shopify' => $payload['updated_at'] ?? now(),
            ]);

            Job::where('order_id', $order->id)
                ->whereNotIn('status', ['cancelled', 'failed', 'exception', 'shipped', 'completed'])
                ->update([
                    'status' => 'in_production',
                    'error_message' => null,
                ]);

            return;
        }

        if ($mappedStatus === 'created') {
            $order->update([
                'status' => 'accepted',
                'fulfillment_status' => 'accepted',
                'updated_at_shopify' => $payload['updated_at'] ?? now(),
            ]);

            Job::where('order_id', $order->id)
                ->whereNotIn('status', ['cancelled', 'failed', 'exception', 'shipped', 'completed'])
                ->update([
                    'status' => 'accepted',
                    'error_message' => null,
                ]);
        }
    }

    /**
     * Handle fulfillment_orders/fulfillment_request_submitted webhook
     */
    private function handleFulfillmentRequestSubmitted(int $shopId, array $payload): void
    {
        [$fulfillmentOrderId, $shopifyOrderId] = $this->extractFulfillmentOrderContext($payload);
    
        if (!$fulfillmentOrderId) {
            throw new \RuntimeException('Missing fulfillment_order id in fulfillment request payload');
        }
    
        /**
         * STEP 1: Resolve order_id if missing using GraphQL
         */
        if (!$shopifyOrderId) {
            $resolve = $this->shopifyService->getOrderIdFromFulfillmentOrderGid(
                $shopId,
                $fulfillmentOrderId
            );
    
            if ($resolve['success'] && !empty($resolve['data']['order_id'])) {
                $shopifyOrderId = (string) $resolve['data']['order_id'];
    
                Log::info('Resolved order_id from fulfillment_order_id', [
                    'fulfillment_order_id' => $fulfillmentOrderId,
                    'shopify_order_id' => $shopifyOrderId,
                ]);
            } else {
                Log::warning('Unable to resolve order_id from fulfillment_order_id', [
                    'fulfillment_order_id' => $fulfillmentOrderId,
                    'response' => $resolve,
                ]);
            }
        }
    
        /**
         * STEP 2: Create / fetch order if available
         */
        $order = null;
    
        if ($shopifyOrderId) {
            $order = Order::firstOrCreate(
                [
                    'shop_id' => $shopId,
                    'shopify_order_id' => (string) $shopifyOrderId
                ],
                [
                    'status' => 'pending',
                    'fulfillment_status' => 'pending',
                    'payload' => $payload,
                    'raw_data' => $payload,
                ]
            );
    
            Job::updateOrCreate(
                [
                    'shop_id' => $shopId,
                    'order_id' => $order->id,
                    'job_type' => 'dtfta_apparel_pod',
                ],
                [
                    'status' => 'pending',
                    'payload' => $payload,
                    'error_message' => null,
                ]
            );
        } else {
            Log::warning('Proceeding without order_id (fallback mode)', [
                'shop_id' => $shopId,
                'fulfillment_order_id' => $fulfillmentOrderId,
            ]);
        }
    
        /**
         * STEP 3: Reject if already cancelled
         */
        if ($order && in_array($order->status, ['cancelled'], true)) {
            $result = $this->shopifyService->rejectFulfillmentRequest(
                $shopId,
                $fulfillmentOrderId,
                'Order is already cancelled in DTFTA'
            );
    
            if (!$result['success']) {
                throw new \RuntimeException(
                    'Reject fulfillment request failed: ' . ($result['message'] ?? 'Unknown error')
                );
            }
    
            return;
        }
    
        /**
         * STEP 4: Accept fulfillment request
         */
        $result = $this->shopifyService->acceptFulfillmentRequest(
            $shopId,
            $fulfillmentOrderId,
            'Fulfillment request accepted by DTFTA'
        );
    
        if (!$result['success']) {
            throw new \RuntimeException(
                'Accept fulfillment request failed: ' . ($result['message'] ?? 'Unknown error')
            );
        }
    
        /**
         * STEP 5: Update local state
         */
        if ($order) {
            $hasArtworkNeeded = Job::where('order_id', $order->id)
                ->where('status', 'artwork_needed')
                ->exists();
    
            $nextStatus = $hasArtworkNeeded ? 'artwork_needed' : 'accepted';
    
            $order->update([
                'status' => $nextStatus,
                'fulfillment_status' => $nextStatus,
                'payload' => $payload,
            ]);
    
            Job::where('order_id', $order->id)
                ->where('job_type', 'dtfta_apparel_pod')
                ->update([
                    'status' => $nextStatus,
                    'started_at' => $hasArtworkNeeded ? null : now(),
                    'error_message' => null,
                ]);
        }
    }

    /**
     * Handle fulfillment_orders/cancellation_request_submitted webhook
     */
    private function handleCancellationRequestSubmitted(int $shopId, array $payload): void
    {
        [$fulfillmentOrderId, $shopifyOrderId] = $this->extractFulfillmentOrderContext($payload);

        if (!$fulfillmentOrderId) {
            throw new \RuntimeException('Missing fulfillment_order id in cancellation request payload');
        }

        $order = null;
        if ($shopifyOrderId) {
            $order = Order::where('shop_id', $shopId)
                ->where('shopify_order_id', (string) $shopifyOrderId)
                ->first();
        }

        $isShipped = $order
            ? Job::where('order_id', $order->id)->whereIn('status', ['shipped', 'completed'])->exists()
            : false;

        $inProduction = $order
            ? Job::where('order_id', $order->id)->whereIn('status', ['in_production', 'processing'])->exists()
            : false;

        if ($isShipped) {
            $result = $this->shopifyService->rejectCancellationRequest(
                $shopId,
                $fulfillmentOrderId,
                'Cancellation rejected: fulfillment already completed'
            );

            if (!$result['success']) {
                throw new \RuntimeException('Reject cancellation request failed: ' . ($result['message'] ?? 'Unknown error'));
            }

            if ($order) {
                $order->update([
                    'status' => 'exception',
                    'fulfillment_status' => 'exception',
                    'payload' => $payload,
                ]);

                Job::where('order_id', $order->id)
                    ->whereNotIn('status', ['shipped', 'completed', 'cancelled'])
                    ->update([
                        'status' => 'exception',
                        'error_message' => 'Cancellation requested after shipment completion',
                    ]);
            }

            return;
        }

        $result = $this->shopifyService->acceptCancellationRequest(
            $shopId,
            $fulfillmentOrderId,
            'Cancellation accepted by DTFTA'
        );

        if (!$result['success']) {
            throw new \RuntimeException('Accept cancellation request failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        if ($order) {
            $cancelStatus = $inProduction ? 'exception' : 'cancelled';
            $cancelReason = $inProduction
                ? 'Cancellation requested after production start'
                : 'Cancelled from Shopify cancellation request';

            $order->update([
                'status' => $cancelStatus,
                'fulfillment_status' => $cancelStatus,
                'payload' => $payload,
            ]);

            Job::where('order_id', $order->id)
                ->whereNotIn('status', ['shipped', 'completed'])
                ->update([
                    'status' => $cancelStatus,
                    'error_message' => $cancelReason,
                ]);
        }
    }

    /**
     * Extract fulfillment order ID and related Shopify order ID from payload
     */
    private function extractFulfillmentOrderContext(array $payload): array
    {
        $fo = [];

        if (!empty($payload['fulfillment_order']) && is_array($payload['fulfillment_order'])) {
            $fo = $payload['fulfillment_order'];
        } elseif (!empty($payload['submitted_fulfillment_order']) && is_array($payload['submitted_fulfillment_order'])) {
            $fo = $payload['submitted_fulfillment_order'];
        } elseif (!empty($payload['original_fulfillment_order']) && is_array($payload['original_fulfillment_order'])) {
            $fo = $payload['original_fulfillment_order'];
        }

        $foId = $fo['id']
            ?? $payload['fulfillment_order_id']
            ?? ($payload['submitted_fulfillment_order']['id'] ?? null)
            ?? ($payload['original_fulfillment_order']['id'] ?? null);

        $orderId = $fo['order_id']
            ?? $payload['order_id']
            ?? null;

        return [
            $foId ? (string) $foId : null,
            $orderId ? (string) $orderId : null
        ];
    }

    /**
     * Return the first non-empty string from primary and fallback candidates
     */
    private function firstNonEmpty($primary, $fallback): ?string
    {
        $candidates = [$primary];

        if (is_array($fallback)) {
            $candidates = array_merge($candidates, $fallback);
        } else {
            $candidates[] = $fallback;
        }

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate)) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Resolve shop domain from various possible headers and payload fields
     */
    private function resolveShopDomain(Request $request): ?string
    {
        $candidates = [
            $request->header('X-Shopify-Shop-Domain'),
            $request->header('x-shopify-shop-domain'),
            $request->header('X-Shop'),
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

    /**
     * Normalize shop domain by trimming, lowercasing, and removing protocol/path
     */
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

    /**
     * Normalize line item properties into a simple key-value array
     */
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

    /**
     * Determine if a SKU follows the DTFTA apparel POD format
     */
    private function isDtftaSku(?string $sku): bool
    {
        if (!$sku) {
            return false;
        }

        return str_starts_with(strtoupper($sku), 'DTFTA-APP-');
    }

    /**
     * Validate that a line item has all required DTFTA properties
     */
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
     */
    public function fulfillmentOrderNotification(Request $request)
    {
        $appTimestamp = (string) $request->header('X-App-Timestamp', '');
        $appSignature = (string) $request->header('X-App-Signature', '');

        $isValidSignature = $this->appSignatureVerifier->verify(
            $request,
            $appTimestamp,
            $appSignature,
            AppSignatureVerifier::MODE_TIMESTAMP_ONLY
        );

        if (!$isValidSignature) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app signature',
            ], 401);
        }

        $shopDomain = $this->resolveShopDomain($request);
        $callbackEventId = (string) (
            $request->header('X-Shopify-Webhook-Id')
            ?: sha1((string) $shopDomain . '|' . $request->getContent())
        );

        $shop = Shop::where('shop_domain', $shopDomain)->first();
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found',
            ], 404);
        }

        $existing = Webhook::where('shop_id', $shop->id)
            ->where('topic', 'fulfillment_order_notification')
            ->where(function ($query) use ($callbackEventId) {
                $query->where('webhook_id', $callbackEventId)
                    ->orWhere('shopify_webhook_id', $callbackEventId)
                    ->orWhere('shopify_event_id', $callbackEventId);
            })
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Duplicate callback ignored',
            ], 200);
        }

        $payload = $request->json()->all() ?: $request->all();

        $webhook = Webhook::create([
            'shop_id' => $shop->id,
            'event_type' => (string) ($payload['kind'] ?? $payload['request_type'] ?? 'fulfillment_order_notification'),
            'topic' => 'fulfillment_order_notification',
            'webhook_id' => $callbackEventId,
            'shopify_webhook_id' => $callbackEventId,
            'shopify_event_id' => $callbackEventId,
            'payload' => $payload,
            'created_at_shopify' => now(),
            'processed' => false,
        ]);

        dispatch(function () use ($webhook, $shop, $payload) {
            try {
                $kind = strtoupper((string) ($payload['kind'] ?? $payload['request_type'] ?? 'FULFILLMENT_REQUEST'));

                [$fulfillmentOrderId, $shopifyOrderIdFromPayload] = $this->extractFulfillmentOrderContext($payload);

                if (!$fulfillmentOrderId) {
                    throw new \RuntimeException('Missing fulfillment_order id in callback payload');
                }

                // 1) Always fetch the full fulfillment order from Shopify
                $foResult = $this->shopifyService->getFulfillmentOrder($shop->id, (string) $fulfillmentOrderId);

                if (
                    empty($foResult['success']) ||
                    empty($foResult['data']['fulfillment_order'])
                ) {
                    throw new \RuntimeException(
                        'Failed to fetch fulfillment order from Shopify: ' . ($foResult['message'] ?? 'Unknown error')
                    );
                }

                $fo = $foResult['data']['fulfillment_order'];

                $shopifyOrderId = (string) (
                    $fo['order_id']
                    ?? $shopifyOrderIdFromPayload
                    ?? ''
                );

                if ($shopifyOrderId === '') {
                    throw new \RuntimeException('Unable to resolve order_id from fulfillment order');
                }

                // 2) Ensure local order exists, and if not, fetch full order from Shopify
                $order = Order::where('shop_id', $shop->id)
                    ->where('shopify_order_id', $shopifyOrderId)
                    ->first();

                if (!$order) {
                    $orderResult = method_exists($this->shopifyService, 'getOrderById')
                        ? $this->shopifyService->getOrderById($shop->id, $shopifyOrderId)
                        : $this->shopifyService->getOrder($shop->id, $shopifyOrderId);

                    if (empty($orderResult['success']) || empty($orderResult['data'])) {
                        throw new \RuntimeException(
                            "Order {$shopifyOrderId} not found locally and could not be fetched from Shopify"
                        );
                    }

                    $orderPayload = $orderResult['data'];

                    $order = Order::updateOrCreate(
                        [
                            'shop_id' => $shop->id,
                            'shopify_order_id' => (string) $shopifyOrderId,
                        ],
                        [
                            'order_number' => $orderPayload['order_number'] ?? null,
                            'customer_email' => data_get($orderPayload, 'customer.email'),
                            'customer_name' => trim(
                                (string) data_get($orderPayload, 'customer.first_name', '') . ' ' .
                                (string) data_get($orderPayload, 'customer.last_name', '')
                            ),
                            'total_price' => $orderPayload['total_price'] ?? 0,
                            'currency' => $orderPayload['currency'] ?? 'USD',
                            'financial_status' => $orderPayload['financial_status'] ?? null,
                            'fulfillment_status' => $orderPayload['fulfillment_status'] ?? 'unfulfilled',
                            'created_at_shopify' => $orderPayload['created_at'] ?? now(),
                            'updated_at_shopify' => $orderPayload['updated_at'] ?? now(),
                            'payload' => $orderPayload,
                        ]
                    );
                }

                // 3) Create or update local job
                $job = Job::updateOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'order_id' => $order->id,
                        'job_type' => 'dtfta_apparel_pod',
                    ],
                    [
                        'status' => $this->mapCallbackKindToInitialJobStatus($kind, $order),
                        'payload' => [
                            'callback_payload' => $payload,
                            'fulfillment_order' => $fo,
                        ],
                        'error_message' => null,
                    ]
                );

                // 4) Decide action based on callback kind
                if (in_array($kind, ['CANCELLATION_REQUEST', 'CANCEL', 'CANCELLED'], true)) {
                    $this->processCancellationRequestCallback(
                        $shop->id,
                        $order,
                        $job,
                        (string) $fulfillmentOrderId,
                        $fo
                    );
                } else {
                    $this->processFulfillmentRequestCallback(
                        $shop->id,
                        $order,
                        $job,
                        (string) $fulfillmentOrderId,
                        $fo
                    );
                }

                $webhook->update([
                    'processed' => true,
                    'processed_at' => now(),
                ]);
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
            'message' => 'Accepted',
        ], 200);
    }


    private function mapCallbackKindToInitialJobStatus(string $kind, Order $order): string
    {
        if (in_array($kind, ['CANCELLATION_REQUEST', 'CANCEL', 'CANCELLED'], true)) {
            return 'cancellation_requested';
        }

        return $order->status === 'artwork_needed' ? 'artwork_needed' : 'pending';
    }


    private function processFulfillmentRequestCallback(
        int $shopId,
        Order $order,
        Job $job,
        string $fulfillmentOrderId,
        array $fo
    ): void {
        $requestStatus = strtoupper((string) ($fo['request_status'] ?? ''));
        $supportedActions = array_map('strtoupper', $fo['supported_actions'] ?? []);
    
        // Already handled in Shopify
        if (in_array($requestStatus, ['ACCEPTED', 'REJECTED'], true)) {
            $status = $requestStatus === 'ACCEPTED' ? 'accepted' : 'exception';
    
            $job->update([
                'status' => $status,
                'error_message' => $requestStatus === 'REJECTED'
                    ? 'Fulfillment request already rejected in Shopify'
                    : null,
            ]);
    
            $order->update([
                'status' => $status,
                'fulfillment_status' => $status,
            ]);
    
            return;
        }
    
        if ($order->status === 'cancelled') {
            $result = $this->shopifyService->rejectFulfillmentRequest(
                $shopId,
                $fulfillmentOrderId,
                'Order is already cancelled in local system'
            );
    
            if (!$result['success']) {
                throw new \RuntimeException(
                    'Reject fulfillment request failed: ' . ($result['message'] ?? 'Unknown error')
                );
            }
    
            $job->update([
                'status' => 'cancelled',
                'error_message' => 'Rejected because order is cancelled',
            ]);
    
            $order->update([
                'status' => 'cancelled',
                'fulfillment_status' => 'cancelled',
            ]);
    
            return;
        }
    
        $hasArtworkNeeded = Job::where('order_id', $order->id)
            ->where('status', 'artwork_needed')
            ->exists();
    
        if ($hasArtworkNeeded) {
            $job->update([
                'status' => 'artwork_needed',
                'error_message' => null,
            ]);
    
            $order->update([
                'status' => 'artwork_needed',
                'fulfillment_status' => 'artwork_needed',
            ]);
    
            return;
        }
    
        // Optional guard if supported actions are available
        if (!empty($supportedActions) && !in_array('ACCEPT_FULFILLMENT_REQUEST', $supportedActions, true)) {
            $job->update([
                'status' => 'pending',
                'error_message' => 'Fulfillment order is not currently actionable for acceptance',
            ]);
    
            return;
        }
    
        $result = $this->shopifyService->acceptFulfillmentRequest(
            $shopId,
            $fulfillmentOrderId,
            'Fulfillment request accepted by service'
        );
    
        if (!$result['success']) {
            throw new \RuntimeException(
                'Accept fulfillment request failed: ' . ($result['message'] ?? 'Unknown error')
            );
        }
    
        $job->update([
            'status' => 'accepted',
            'started_at' => now(),
            'error_message' => null,
        ]);
    
        $order->update([
            'status' => 'accepted',
            'fulfillment_status' => 'accepted',
        ]);
    }


    private function processCancellationRequestCallback(
        int $shopId,
        Order $order,
        Job $job,
        string $fulfillmentOrderId,
        array $fo
    ): void {
        $requestStatus = strtoupper((string) ($fo['request_status'] ?? ''));
    
        if (in_array($requestStatus, ['CANCELLATION_ACCEPTED', 'CANCELLATION_REJECTED'], true)) {
            return;
        }
    
        $isShipped = in_array($job->status, ['shipped', 'completed'], true);
        $inProduction = in_array($job->status, ['in_production', 'processing'], true);
    
        if ($isShipped) {
            $result = $this->shopifyService->rejectCancellationRequest(
                $shopId,
                $fulfillmentOrderId,
                'Cancellation rejected: fulfillment already completed'
            );
    
            if (!$result['success']) {
                throw new \RuntimeException(
                    'Reject cancellation request failed: ' . ($result['message'] ?? 'Unknown error')
                );
            }
    
            $job->update([
                'status' => 'exception',
                'error_message' => 'Cancellation requested after shipment completion',
            ]);
    
            $order->update([
                'status' => 'exception',
                'fulfillment_status' => 'exception',
            ]);
    
            return;
        }
    
        $result = $this->shopifyService->acceptCancellationRequest(
            $shopId,
            $fulfillmentOrderId,
            'Cancellation accepted by service'
        );
    
        if (!$result['success']) {
            throw new \RuntimeException(
                'Accept cancellation request failed: ' . ($result['message'] ?? 'Unknown error')
            );
        }
    
        $cancelStatus = $inProduction ? 'exception' : 'cancelled';
        $cancelReason = $inProduction
            ? 'Cancellation requested after production start'
            : 'Cancelled from Shopify cancellation request';
    
        $job->update([
            'status' => $cancelStatus,
            'error_message' => $cancelReason,
        ]);
    
        $order->update([
            'status' => $cancelStatus,
            'fulfillment_status' => $cancelStatus,
        ]);
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