<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    private function apiVersion(): string
    {
        return (string) config('services.shopify.api_version', '2025-10');
    }

    private function baseUrl(string $shopDomain): string
    {
        return "https://{$shopDomain}/admin/api/" . $this->apiVersion();
    }

    private function getShopAndToken(int $shopId): array
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return ['success' => false, 'message' => 'Shop not found'];
        }

        if (empty($shop->shopify_access_token)) {
            return ['success' => false, 'message' => 'Shop access token not available'];
        }

        try {
            $token = decrypt($shop->shopify_access_token);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Failed to decrypt access token'];
        }

        return ['success' => true, 'shop' => $shop, 'token' => $token];
    }

    private function request(int $shopId, string $method, string $path, array $payload = [], array $query = []): array
    {
        $auth = $this->getShopAndToken($shopId);
        if (!$auth['success']) {
            return $auth;
        }

        $shop = $auth['shop'];
        $token = $auth['token'];
        $url = rtrim($this->baseUrl($shop->shop_domain), '/') . '/' . ltrim($path, '/') . '.json';

        try {
            $client = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->retry(2, 250);

            $response = $client->send(strtoupper($method), $url, [
                'query' => $query,
                'json' => $payload,
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
                'message' => $response->successful() ? 'ok' : 'Shopify API request failed',
            ];
        } catch (\Throwable $e) {
            Log::error('Shopify API request error', [
                'shop_id' => $shopId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function getOrder(int $shopId, $shopifyOrderId): array
    {
        $result = $this->request($shopId, 'GET', "orders/{$shopifyOrderId}");
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['order'] ?? null,
            'status' => $result['status'],
            'message' => 'Order fetched successfully',
        ];
    }

    public function getFulfillmentOrdersForOrder(int $shopId, $shopifyOrderId): array
    {
        $result = $this->request($shopId, 'GET', "orders/{$shopifyOrderId}/fulfillment_orders");
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment_orders'] ?? [],
            'status' => $result['status'],
            'message' => 'Fulfillment orders fetched successfully',
        ];
    }

    public function getAssignedFulfillmentOrders(int $shopId, array $filters = []): array
    {
        $result = $this->request($shopId, 'GET', 'assigned_fulfillment_orders', [], $filters);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment_orders'] ?? [],
            'status' => $result['status'],
            'message' => 'Assigned fulfillment orders fetched successfully',
        ];
    }

    public function createFulfillment($shopId, $shopifyOrderId, $data): array
    {
        try {
            $lineItemsByFO = $data['line_items_by_fulfillment_order'] ?? [];

            if (empty($lineItemsByFO)) {
                if (!empty($data['fulfillment_order_id'])) {
                    $lineItemsByFO[] = [
                        'fulfillment_order_id' => $data['fulfillment_order_id'],
                        'fulfillment_order_line_items' => $data['line_items'] ?? [],
                    ];
                } else {
                    $fo = $this->getFulfillmentOrdersForOrder((int) $shopId, $shopifyOrderId);
                    if (!$fo['success'] || empty($fo['data'])) {
                        return [
                            'success' => false,
                            'message' => 'No fulfillment order found for this Shopify order',
                            'data' => $fo['data'] ?? null,
                        ];
                    }

                    foreach ($fo['data'] as $fulfillmentOrder) {
                        $lineItemsByFO[] = [
                            'fulfillment_order_id' => $fulfillmentOrder['id'],
                            'fulfillment_order_line_items' => [],
                        ];
                    }
                }
            }

            $payload = [
                'fulfillment' => [
                    'line_items_by_fulfillment_order' => $lineItemsByFO,
                    'tracking_info' => $data['tracking_info'] ?? null,
                    'notify_customer' => (bool) ($data['notify_customer'] ?? true),
                ]
            ];

            $result = $this->request((int) $shopId, 'POST', 'fulfillments', $payload);
            if (!$result['success']) {
                return $result;
            }

            return [
                'success' => true,
                'data' => $result['data']['fulfillment'] ?? $result['data'],
                'status' => $result['status'],
                'message' => 'Fulfillment created successfully',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateFulfillmentTracking($shopId, $fulfillmentId, $trackingInfo): array
    {
        $payload = [
            'fulfillment' => [
                'tracking_info' => [
                    'number' => $trackingInfo['number'] ?? $trackingInfo['tracking_number'] ?? null,
                    'company' => $trackingInfo['company'] ?? $trackingInfo['tracking_company'] ?? null,
                    'url' => $trackingInfo['url'] ?? $trackingInfo['tracking_url'] ?? null,
                ],
                'notify_customer' => (bool) ($trackingInfo['notify_customer'] ?? true),
            ]
        ];

        $result = $this->request((int) $shopId, 'POST', "fulfillments/{$fulfillmentId}/update_tracking", $payload);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment'] ?? $result['data'],
            'status' => $result['status'],
            'message' => 'Fulfillment tracking updated successfully',
        ];
    }

    public function cancelFulfillment($shopId, $shopifyOrderId, $fulfillmentId): array
    {
        $result = $this->request((int) $shopId, 'POST', "fulfillments/{$fulfillmentId}/cancel");
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment'] ?? $result['data'],
            'status' => $result['status'],
            'message' => 'Fulfillment cancelled successfully',
        ];
    }

    public function getFulfillmentServices($shopId): array
    {
        $result = $this->request((int) $shopId, 'GET', 'fulfillment_services');
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment_services'] ?? [],
            'status' => $result['status'],
            'message' => 'Fulfillment services fetched successfully',
        ];
    }

    public function createFulfillmentService(int $shopId, string $callbackUrl, string $serviceName = 'DTFTA Fulfillment Service'): array
    {
        $payload = [
            'fulfillment_service' => [
                'name' => $serviceName,
                'callback_url' => $callbackUrl,
                'inventory_management' => false,
                'tracking_support' => true,
                'requires_shipping_method' => true,
                'fulfillment_orders_opt_in' => true,
                'format' => 'json',
            ],
        ];

        $result = $this->request($shopId, 'POST', 'fulfillment_services', $payload);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment_service'] ?? $result['data'],
            'status' => $result['status'],
            'message' => 'Fulfillment service created successfully',
        ];
    }

    public function registerWebhook($shopId, $topic, $callbackUrl): array
    {
        $payload = [
            'webhook' => [
                'topic' => $topic,
                'address' => $callbackUrl,
                'format' => 'json',
            ],
        ];

        $result = $this->request((int) $shopId, 'POST', 'webhooks', $payload);
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['webhook'] ?? $result['data'],
            'status' => $result['status'],
            'message' => 'Webhook registered successfully',
        ];
    }

    public function listWebhooks(int $shopId): array
    {
        $result = $this->request($shopId, 'GET', 'webhooks');
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['webhooks'] ?? [],
            'status' => $result['status'],
            'message' => 'Webhooks fetched successfully',
        ];
    }

    public function deleteWebhook(int $shopId, $webhookId): array
    {
        return $this->request($shopId, 'DELETE', "webhooks/{$webhookId}");
    }

    public function ensureRequiredWebhooks(int $shopId, string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $mainWebhookAddress = "{$baseUrl}/api/v1/webhooks/shopify";
        $topics = [
            'app/uninstalled',
            'orders/create',
            'orders/updated',
            'orders/cancelled',
            'fulfillments/create',
            'fulfillments/update',
            'fulfillment_orders/fulfillment_request_submitted',
            'fulfillment_orders/cancellation_request_submitted',
        ];

        $existing = $this->listWebhooks($shopId);
        $existingTopics = [];
        if ($existing['success']) {
            foreach (($existing['data'] ?? []) as $webhook) {
                $existingTopics[] = (string) ($webhook['topic'] ?? '');
            }
        }

        $created = [];
        $errors = [];
        foreach ($topics as $topic) {
            if (in_array($topic, $existingTopics, true)) {
                continue;
            }

            $result = $this->registerWebhook($shopId, $topic, $mainWebhookAddress);
            if ($result['success']) {
                $created[] = $topic;
            } else {
                $errors[] = ['topic' => $topic, 'error' => $result['message'] ?? 'Unknown error'];
            }
        }

        return [
            'success' => empty($errors),
            'data' => [
                'created_topics' => $created,
                'errors' => $errors,
            ],
        ];
    }

    public function provisionShopOnInstall(int $shopId, ?string $baseUrl = null): array
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return ['success' => false, 'message' => 'Shop not found'];
        }

        $baseUrl = $baseUrl ?: (string) config('app.url');
        $baseUrl = rtrim($baseUrl, '/');
        $fulfillmentCallbackUrl = "{$baseUrl}/api/v1/fulfillment_order_notification";

        $serviceResult = null;
        if (empty($shop->fulfillment_service_id)) {
            $serviceResult = $this->createFulfillmentService($shopId, $fulfillmentCallbackUrl, 'DTFTA Fulfillment Service');
            if ($serviceResult['success']) {
                $service = $serviceResult['data'] ?? [];
                $shop->fulfillment_service_id = (string) ($service['id'] ?? '');
                $shop->location_id = isset($service['location_id']) ? (string) $service['location_id'] : ($shop->location_id ?? null);
                $shop->save();
            }
        }

        $webhooks = $this->ensureRequiredWebhooks($shopId, $baseUrl);

        return [
            'success' => ($serviceResult ? (bool) $serviceResult['success'] : true) && (bool) $webhooks['success'],
            'data' => [
                'fulfillment_service' => $serviceResult['data'] ?? null,
                'webhooks' => $webhooks['data'] ?? [],
            ],
            'message' => 'Provisioning completed',
        ];
    }
}
