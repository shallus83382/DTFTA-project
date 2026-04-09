<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\FulfillmentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    /**
     * Get the API version to use for Shopify requests
     */
    private function apiVersion(): string
    {
        return (string) config('services.shopify.api_version', '2025-10');
    }

    /**
     * Get webhook API version used for webhook registration/list/delete calls
     */
    private function webhookApiVersion(?Shop $shop = null): string
    {
        $shopValue = $shop?->shopify_webhook_api_version;
        if (is_string($shopValue) && trim($shopValue) !== '') {
            return trim($shopValue);
        }

        return (string) config('services.shopify.webhook_api_version', '2026-04');
    }

    /**
     * Construct the base URL for Shopify API requests based on shop domain and API version
     */
    private function baseUrl(string $shopDomain, ?string $apiVersion = null): string
    {
        return "https://{$shopDomain}/admin/api/" . ($apiVersion ?: $this->apiVersion());
    }

    /**
     * Retrieve shop information and decrypted access token for a given shop ID
     */
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
    
            Log::info('Shopify auth resolved', [
                'shop_id' => $shopId,
                'shop_domain' => $shop->shop_domain,
                'token_present' => $token !== '',
                'token_prefix' => substr($token, 0, 6),
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Failed to decrypt access token'];
        }
    
        return ['success' => true, 'shop' => $shop, 'token' => $token];
    }

    /**
     * Make a REST API request to Shopify for a specific shop
     */
    private function request(
        int $shopId,
        string $method,
        string $path,
        array $payload = [],
        array $query = [],
        ?string $apiVersion = null
    ): array
    {
        $auth = $this->getShopAndToken($shopId);
        if (!$auth['success']) {
            return $auth;
        }

        $shop = $auth['shop'];
        $token = $auth['token'];
        $url = rtrim($this->baseUrl($shop->shop_domain, $apiVersion), '/') . '/' . ltrim($path, '/') . '.json';

        Log::error('Shopify API request', [
            'shop_id' => $shopId,
            'path' => $url,
            'query' => $query,
            'payload' => $payload,
        ]);


        try {
            $client = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])
                ->acceptJson()
                ->asJson()
                ->withoutVerifying()
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

    /**
     * Make a GraphQL API request to Shopify for a specific shop
     */
    private function graphqlRequest(int $shopId, string $query, array $variables = []): array
    {
        $auth = $this->getShopAndToken($shopId);
        if (!$auth['success']) {
            return $auth;
        }

        $shop = $auth['shop'];
        $token = $auth['token'];
        $url = rtrim($this->baseUrl($shop->shop_domain), '/') . '/graphql.json';

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])
                ->acceptJson()
                ->withoutVerifying()
                ->asJson()
                ->timeout(20)
                ->retry(2, 250)
                ->post($url, [
                    'query' => $query,
                    'variables' => $variables,
                ]);

            $data = $response->json() ?? [];
            $hasErrors = !empty($data['errors']);

            return [
                'success' => $response->successful() && !$hasErrors,
                'status' => $response->status(),
                'data' => $data['data'] ?? null,
                'errors' => $data['errors'] ?? [],
                'message' => $response->successful() && !$hasErrors ? 'ok' : 'Shopify GraphQL request failed',
            ];
        } catch (\Throwable $e) {
            Log::error('Shopify GraphQL request error', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => [],
            ];
        }
    }


    /**
     * Convert a fulfillment order ID to a GraphQL global ID format if it's not already
     */
    private function toFulfillmentOrderGid($fulfillmentOrderId): string
    {
        $value = trim((string) $fulfillmentOrderId);
        if (str_starts_with($value, 'gid://shopify/FulfillmentOrder/')) {
            return $value;
        }

        return 'gid://shopify/FulfillmentOrder/' . $value;
    }

    private function runFulfillmentOrderMutation(
        int $shopId,
        string $mutationName,
        $fulfillmentOrderId,
        ?string $message = null
    ): array {
                $gid = $this->toFulfillmentOrderGid($fulfillmentOrderId);
                $selection = $mutationName;

                $query = <<<GQL
        mutation {$mutationName}(\$id: ID!, \$message: String) {
        {$selection}(id: \$id, message: \$message) {
            fulfillmentOrder {
            id
            status
            requestStatus
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;

                $result = $this->graphqlRequest($shopId, $query, [
                    'id' => $gid,
                    'message' => $message,
                ]);
                if (!$result['success']) {
                    return $result;
                }

                $payload = $result['data'][$selection] ?? [];
                $userErrors = $payload['userErrors'] ?? [];
                if (!empty($userErrors)) {
                    return [
                        'success' => false,
                        'status' => 422,
                        'message' => 'Shopify mutation returned user errors',
                        'data' => $payload,
                        'errors' => $userErrors,
                    ];
                }

                return [
                    'success' => true,
                    'status' => $result['status'],
                    'message' => 'Mutation completed successfully',
                    'data' => $payload['fulfillmentOrder'] ?? $payload,
                    'errors' => [],
                ];
    }

    /**
     * getOrder - Retrieve a specific order from Shopify using the REST API. This is used in the fulfillment order flow to get order details needed for creating fulfillments.
     */
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

    /**
     * getFulfillmentOrdersForOrder - Retrieve fulfillment orders for a specific Shopify order. This is used to determine which fulfillment orders are associated with an order when creating fulfillments.
     */
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

    /**
     * getAssignedFulfillmentOrders - Retrieve fulfillment orders that are currently assigned to the app's fulfillment service. This is used to identify which fulfillment orders the app is responsible for fulfilling.
     */
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

    /**
     * acceptFulfillmentRequest - Accept a fulfillment request for a specific fulfillment order. This is used when the app wants to accept responsibility for fulfilling a fulfillment order that has requested fulfillment.
     */
    public function acceptFulfillmentRequest(int $shopId, $fulfillmentOrderId, ?string $message = null): array
    {
        return $this->runFulfillmentOrderMutation(
            $shopId,
            'fulfillmentOrderAcceptFulfillmentRequest',
            $fulfillmentOrderId,
            $message
        );
    }

    /**
     * rejectFulfillmentRequest - Reject a fulfillment request for a specific fulfillment order. This is used when the app wants to decline responsibility for fulfilling a fulfillment order that has requested fulfillment, often with a message explaining why.
     */
    public function rejectFulfillmentRequest(int $shopId, $fulfillmentOrderId, string $message): array
    {
        return $this->runFulfillmentOrderMutation(
            $shopId,
            'fulfillmentOrderRejectFulfillmentRequest',
            $fulfillmentOrderId,
            $message
        );
    }

    /**
     * acceptCancellationRequest - Accept a cancellation request for a specific fulfillment order. This is used when the app wants to approve a cancellation request that has been submitted for a fulfillment order.
     */
    public function acceptCancellationRequest(int $shopId, $fulfillmentOrderId, ?string $message = null): array
    {
        return $this->runFulfillmentOrderMutation(
            $shopId,
            'fulfillmentOrderAcceptCancellationRequest',
            $fulfillmentOrderId,
            $message
        );
    }

    /**
     * rejectCancellationRequest - Reject a cancellation request for a specific fulfillment order. This is used when the app wants to deny a cancellation request that has been submitted for a fulfillment order, often with a message explaining why.
     */
    public function rejectCancellationRequest(int $shopId, $fulfillmentOrderId, string $message): array
    {
        return $this->runFulfillmentOrderMutation(
            $shopId,
            'fulfillmentOrderRejectCancellationRequest',
            $fulfillmentOrderId,
            $message
        );
    }

    /**
     * createFulfillment - Create a fulfillment for a specific order and fulfillment order. This is used to fulfill items in a fulfillment order that the app has accepted responsibility for.
     */
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

            Log::info('Shopify Create ORDER Fullfillment response', [
                'shop_id' => $shopId,
                'response' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);

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

    /**
     * updateFulfillmentTracking - Update the tracking information for a specific fulfillment. This is used to add or change tracking details for a fulfillment that has already been created.
     */
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

    /**
     * cancelFulfillment - Cancel a specific fulfillment. This is used to cancel a fulfillment that has been created but not yet fulfilled, often when an order is cancelled or a fulfillment needs to be voided.
     */
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

    /**
     * getFulfillment - Retrieve a specific fulfillment from Shopify. This is used to get details about a fulfillment, including its status and tracking information.
     */
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


     /**
     * getFulfillment - Retrieve a specific fulfillment from Shopify.
     * This is used to get details about a fulfillment, including its status and tracking information.
     */
    public function getFulfillment($shopId, $fulfillmentId): array
    {
        $result = $this->request((int) $shopId, 'GET', "fulfillments/{$fulfillmentId}");

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data']['fulfillment'] ?? null,
            'status' => $result['status'],
            'message' => 'Fulfillment fetched successfully',
        ];
    }


    public function getOrderIdFromFulfillmentOrderGid(int $shopId, string $fulfillmentOrderGid): array
    {
            $query = <<<'GRAPHQL'
        query GetFulfillmentOrderOrderId($id: ID!) {
        fulfillmentOrder(id: $id) {
            id
            order {
            id
            legacyResourceId
            name
            }
        }
        }
        GRAPHQL;
        
            $response = $this->graphqlRequest($shopId, $query, [
                'id' => $fulfillmentOrderGid,
            ]);
        
            if (!$response['success']) {
                return $response;
            }
        
            $order = $response['data']['fulfillmentOrder']['order'] ?? null;
        
            return [
                'success' => true,
                'status' => $response['status'],
                'message' => 'Order resolved successfully',
                'data' => [
                    'order_gid' => $order['id'] ?? null,
                    'order_id' => $order['legacyResourceId'] ?? null,
                ],
                'errors' => [],
            ];
    }

    /**
     * createFulfillmentService - Create a fulfillment service in Shopify. This is used to register a fulfillment service with Shopify that can handle fulfillment orders.
     */
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


    public function createDeliveryProfile(int $shopId, string $locationId, string $profileName = 'DTFTA Shipping'): array
    {
            try {
                $locationGid = str_starts_with($locationId, 'gid://')
                    ? $locationId
                    : "gid://shopify/Location/{$locationId}";
        
                $query = <<<'GRAPHQL'
        mutation CreateDeliveryProfile($profile: DeliveryProfileInput!) {
        deliveryProfileCreate(profile: $profile) {
            profile {
            id
            name
            profileLocationGroups {
                locationGroup {
                id
                locations(first: 10) {
                    nodes {
                    id
                    name
                    }
                }
                }
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GRAPHQL;
        
            $variables = [
                'profile' => [
                    'name' => $profileName,
                    'locationGroupsToCreate' => [
                        [
                            'locationsToAdd' => [$locationGid],
                            'zonesToCreate' => [
                                [
                                    'name' => 'United States',
                                    'countries' => [
                                        [
                                            'code' => 'US',
                                            'provinces' => [
                                                ['code' => 'AL'],
                                                ['code' => 'AK'],
                                                ['code' => 'AZ'],
                                                ['code' => 'AR'],
                                                ['code' => 'CA'],
                                                ['code' => 'CO'],
                                                ['code' => 'CT'],
                                                ['code' => 'DE'],
                                                ['code' => 'FL'],
                                                ['code' => 'GA'],
                                                ['code' => 'HI'],
                                                ['code' => 'ID'],
                                                ['code' => 'IL'],
                                                ['code' => 'IN'],
                                                ['code' => 'IA'],
                                                ['code' => 'KS'],
                                                ['code' => 'KY'],
                                                ['code' => 'LA'],
                                                ['code' => 'ME'],
                                                ['code' => 'MD'],
                                                ['code' => 'MA'],
                                                ['code' => 'MI'],
                                                ['code' => 'MN'],
                                                ['code' => 'MS'],
                                                ['code' => 'MO'],
                                                ['code' => 'MT'],
                                                ['code' => 'NE'],
                                                ['code' => 'NV'],
                                                ['code' => 'NH'],
                                                ['code' => 'NJ'],
                                                ['code' => 'NM'],
                                                ['code' => 'NY'],
                                                ['code' => 'NC'],
                                                ['code' => 'ND'],
                                                ['code' => 'OH'],
                                                ['code' => 'OK'],
                                                ['code' => 'OR'],
                                                ['code' => 'PA'],
                                                ['code' => 'RI'],
                                                ['code' => 'SC'],
                                                ['code' => 'SD'],
                                                ['code' => 'TN'],
                                                ['code' => 'TX'],
                                                ['code' => 'UT'],
                                                ['code' => 'VT'],
                                                ['code' => 'VA'],
                                                ['code' => 'WA'],
                                                ['code' => 'WV'],
                                                ['code' => 'WI'],
                                                ['code' => 'WY'],
                                            ],
                                        ],
                                    ],
                                    'methodDefinitionsToCreate' => [
                                        [
                                            'name' => 'Standard',
                                            'rateDefinition' => [
                                                'price' => [
                                                    'amount' => 4.99,
                                                    'currencyCode' => 'USD',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        
                Log::info('Shopify deliveryProfileCreate request', [
                    'shop_id' => $shopId,
                    'location_gid' => $locationGid,
                    'variables' => $variables,
                ]);
        
                $result = $this->graphqlRequest($shopId, $query, $variables);
        
                Log::info('Shopify deliveryProfileCreate response', [
                    'shop_id' => $shopId,
                    'result' => $result,
                ]);
        
                if (!($result['success'] ?? false)) {
                    return [
                        'success' => false,
                        'message' => $result['message'] ?? 'GraphQL request failed',
                        'errors' => $result['errors'] ?? [],
                        'data' => $result['data'] ?? null,
                    ];
                }
        
                if (!empty($result['errors'])) {
                    return [
                        'success' => false,
                        'message' => $result['errors'][0]['message'] ?? 'Shopify GraphQL returned errors',
                        'errors' => $result['errors'],
                        'data' => $result['data'] ?? null,
                    ];
                }
        
                $payload = data_get($result, 'data.deliveryProfileCreate');
        
                if (!$payload) {
                    return [
                        'success' => false,
                        'message' => 'Missing deliveryProfileCreate payload in Shopify response',
                        'data' => $result['data'] ?? null,
                        'errors' => $result['errors'] ?? [],
                    ];
                }
        
                if (!empty($payload['userErrors'])) {
                    return [
                        'success' => false,
                        'message' => $payload['userErrors'][0]['message'] ?? 'Failed to create delivery profile',
                        'errors' => $payload['userErrors'],
                        'data' => $payload,
                    ];
                }
        
                return [
                    'success' => true,
                    'message' => 'Delivery profile created successfully',
                    'data' => $payload['profile'] ?? null,
                ];
            } catch (\Throwable $e) {
                Log::error('createDeliveryProfile exception', [
                    'shop_id' => $shopId,
                    'location_id' => $locationId,
                    'error' => $e->getMessage(),
                ]);
        
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
    }

    public function getDeliveryProfileDetails(int $shopId, string $deliveryProfileId): array
    {
            try {
                $profileGid = str_starts_with($deliveryProfileId, 'gid://')
                    ? $deliveryProfileId
                    : "gid://shopify/DeliveryProfile/{$deliveryProfileId}";

                $query = <<<'GRAPHQL'
        query GetDeliveryProfile($id: ID!) {
        node(id: $id) {
            ... on DeliveryProfile {
            id
            name
            profileLocationGroups {
                locationGroup {
                id
                locations(first: 20) {
                    nodes {
                    id
                    name
                    }
                }
                }
                locationGroupZones(first: 20) {
                nodes {
                    zone {
                    id
                    name
                    countries {
                        code {
                        countryCode
                        }
                        provinces {
                        code
                        }
                    }
                    }
                    methodDefinitions(first: 20) {
                    nodes {
                        id
                        name
                        active
                        rateProvider {
                        ... on DeliveryRateDefinition {
                            id
                            price {
                            amount
                            currencyCode
                            }
                        }
                        }
                    }
                    }
                }
                }
            }
            }
        }
        }
        GRAPHQL;

                $result = $this->graphqlRequest($shopId, $query, [
                    'id' => $profileGid,
                ]);

                if (!($result['success'] ?? false)) {
                    return [
                        'success' => false,
                        'message' => $result['message'] ?? 'GraphQL request failed',
                        'errors' => $result['errors'] ?? [],
                        'data' => $result['data'] ?? null,
                    ];
                }

                if (!empty($result['errors'])) {
                    return [
                        'success' => false,
                        'message' => $result['errors'][0]['message'] ?? 'Shopify GraphQL returned errors',
                        'errors' => $result['errors'],
                        'data' => $result['data'] ?? null,
                    ];
                }

                $profile = data_get($result, 'data.node');

                if (!$profile) {
                    return [
                        'success' => false,
                        'message' => 'Delivery profile not found',
                        'data' => $result['data'] ?? null,
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Delivery profile fetched successfully',
                    'data' => $profile,
                ];
            } catch (\Throwable $e) {
                Log::error('getDeliveryProfileDetails exception', [
                    'shop_id' => $shopId,
                    'delivery_profile_id' => $deliveryProfileId,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
    }

    private function getUsProvinceCodes(): array
    {
        return [
            'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA',
            'HI','ID','IL','IN','IA','KS','KY','LA','ME','MD',
            'MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
            'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC',
            'SD','TN','TX','UT','VT','VA','WA','WV','WI','WY',
        ];
    }

    private function buildStandardUsZoneInput(float $amount = 4.99, string $currencyCode = 'USD'): array
    {
        return [
            'name' => 'United States',
            'countries' => [
                [
                    'code' => 'US',
                    'provinces' => array_map(
                        fn (string $code) => ['code' => $code],
                        $this->getUsProvinceCodes()
                    ),
                ],
            ],
            'methodDefinitionsToCreate' => [
                [
                    'name' => 'Standard',
                    'rateDefinition' => [
                        'price' => [
                            'amount' => $amount,
                            'currencyCode' => $currencyCode,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function syncDeliveryProfileConfiguration(
        int $shopId,
        string $deliveryProfileId,
        string $locationId,
        string $profileName = 'DTFTA Shipping',
        float $standardRate = 4.99,
        string $currencyCode = 'USD'
    ): array {
                try {
                    $profileGid = str_starts_with($deliveryProfileId, 'gid://')
                        ? $deliveryProfileId
                        : "gid://shopify/DeliveryProfile/{$deliveryProfileId}";
            
                    $locationGid = str_starts_with($locationId, 'gid://')
                        ? $locationId
                        : "gid://shopify/Location/{$locationId}";
            
                    $details = $this->getDeliveryProfileDetails($shopId, $profileGid);
                    if (!($details['success'] ?? false)) {
                        return $details;
                    }
            
                    $profile = $details['data'] ?? [];
                    $profileLocationGroups = $profile['profileLocationGroups'] ?? [];
            
                    $existingGroup = $profileLocationGroups[0] ?? null;
                    $locationGroup = $existingGroup['locationGroup'] ?? null;
                    $existingZones = data_get($existingGroup, 'locationGroupZones.nodes', []);
            
                    $groupId = $locationGroup['id'] ?? null;
                    $existingLocationIds = collect(data_get($locationGroup, 'locations.nodes', []))
                        ->pluck('id')
                        ->filter()
                        ->values()
                        ->all();
            
                    $hasNewLocation = in_array($locationGid, $existingLocationIds, true);
            
                    $usZoneNode = null;
                    foreach ($existingZones as $zoneNode) {
                        $zoneName = data_get($zoneNode, 'zone.name');
                        $countryCode = data_get($zoneNode, 'zone.countries.0.code.countryCode');
                        if ($zoneName === 'United States' || $countryCode === 'US') {
                            $usZoneNode = $zoneNode;
                            break;
                        }
                    }
            
                    $profileInput = [
                        'name' => $profileName,
                    ];
            
                    // Case A: no location group exists at all -> create full nested structure
                    if (!$groupId) {
                        $profileInput['locationGroupsToCreate'] = [
                            [
                                'locationsToAdd' => [$locationGid],
                                'zonesToCreate' => [
                                    $this->buildStandardUsZoneInput($standardRate, $currencyCode),
                                ],
                            ],
                        ];
                    } else {
                        $groupUpdate = [
                            'id' => $groupId,
                        ];
            
                        if (!$hasNewLocation) {
                            $groupUpdate['locationsToAdd'] = [$locationGid];
                        }
            
                        // Optional: remove orphaned old locations if you want strict single-location ownership
                        $locationsToRemove = array_values(array_filter(
                            $existingLocationIds,
                            fn (string $id) => $id !== $locationGid
                        ));
                        if (!empty($locationsToRemove)) {
                            $groupUpdate['locationsToRemove'] = $locationsToRemove;
                        }
            
                        // Case B1: location group exists but US zone is missing -> create zone
                        if (!$usZoneNode) {
                            $groupUpdate['zonesToCreate'] = [
                                $this->buildStandardUsZoneInput($standardRate, $currencyCode),
                            ];
                        } else {
                            // Case B2: zone exists -> update method(s) if present, otherwise recreate method(s)
                            $zoneId = data_get($usZoneNode, 'zone.id');
                            $methodNodes = data_get($usZoneNode, 'methodDefinitions.nodes', []);
            
                            $zoneUpdate = [
                                'id' => $zoneId,
                                'name' => 'United States',
                            ];
            
                            if (!empty($methodNodes)) {
                                $zoneUpdate['methodDefinitionsToUpdate'] = array_map(
                                    function (array $method) use ($standardRate, $currencyCode) {
                                        return [
                                            'id' => $method['id'],
                                            'name' => $method['name'] ?? 'Standard',
                                            'active' => true,
                                            'rateDefinition' => [
                                                'price' => [
                                                    'amount' => $standardRate,
                                                    'currencyCode' => $currencyCode,
                                                ],
                                            ],
                                        ];
                                    },
                                    $methodNodes
                                );
                            } else {
                                $zoneUpdate['methodDefinitionsToCreate'] = [
                                    [
                                        'name' => 'Standard',
                                        'rateDefinition' => [
                                            'price' => [
                                                'amount' => $standardRate,
                                                'currencyCode' => $currencyCode,
                                            ],
                                        ],
                                    ],
                                ];
                            }
            
                            $groupUpdate['zonesToUpdate'] = [$zoneUpdate];
                        }
            
                        $profileInput['locationGroupsToUpdate'] = [$groupUpdate];
                    }
            
                    $mutation = <<<'GRAPHQL'
            mutation SyncDeliveryProfile($id: ID!, $profile: DeliveryProfileInput!) {
            deliveryProfileUpdate(id: $id, profile: $profile) {
                profile {
                id
                name
                profileLocationGroups {
                    locationGroup {
                    id
                    locations(first: 20) {
                        nodes {
                        id
                        name
                        }
                    }
                    }
                    locationGroupZones(first: 20) {
                    nodes {
                        zone {
                        id
                        name
                        }
                        methodDefinitions(first: 20) {
                        nodes {
                            id
                            name
                            active
                        }
                        }
                    }
                    }
                }
                }
                userErrors {
                field
                message
                }
            }
            }
            GRAPHQL;
            
                    $variables = [
                        'id' => $profileGid,
                        'profile' => $profileInput,
                    ];
            
                    Log::info('Shopify deliveryProfileUpdate sync request', [
                        'shop_id' => $shopId,
                        'delivery_profile_id' => $profileGid,
                        'location_id' => $locationGid,
                        'variables' => $variables,
                    ]);
            
                    $result = $this->graphqlRequest($shopId, $mutation, $variables);
            
                    Log::info('Shopify deliveryProfileUpdate sync response', [
                        'shop_id' => $shopId,
                        'result' => $result,
                    ]);
            
                    if (!($result['success'] ?? false)) {
                        return [
                            'success' => false,
                            'message' => $result['message'] ?? 'GraphQL request failed',
                            'errors' => $result['errors'] ?? [],
                            'data' => $result['data'] ?? null,
                        ];
                    }
            
                    if (!empty($result['errors'])) {
                        return [
                            'success' => false,
                            'message' => $result['errors'][0]['message'] ?? 'Shopify GraphQL returned errors',
                            'errors' => $result['errors'],
                            'data' => $result['data'] ?? null,
                        ];
                    }
            
                    $payload = data_get($result, 'data.deliveryProfileUpdate');
            
                    if (!$payload) {
                        return [
                            'success' => false,
                            'message' => 'Missing deliveryProfileUpdate payload in Shopify response',
                            'data' => $result['data'] ?? null,
                            'errors' => $result['errors'] ?? [],
                        ];
                    }
            
                    if (!empty($payload['userErrors'])) {
                        return [
                            'success' => false,
                            'message' => $payload['userErrors'][0]['message'] ?? 'Failed to sync delivery profile',
                            'errors' => $payload['userErrors'],
                            'data' => $payload,
                        ];
                    }
            
                    return [
                        'success' => true,
                        'message' => 'Delivery profile synchronized successfully',
                        'data' => $payload['profile'] ?? null,
                    ];
                } catch (\Throwable $e) {
                    Log::error('syncDeliveryProfileConfiguration exception', [
                        'shop_id' => $shopId,
                        'delivery_profile_id' => $deliveryProfileId,
                        'location_id' => $locationId,
                        'error' => $e->getMessage(),
                    ]);
            
                    return [
                        'success' => false,
                        'message' => $e->getMessage(),
                    ];
                }
    }

    public function removeDeliveryProfile(int $shopId, string $profileId): array
    {
                $mutation = <<<'GQL'
        mutation RemoveDeliveryProfile($id: ID!) {
        deliveryProfileRemove(id: $id) {
            job {
            id
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;

            $result = $this->graphqlRequest($shopId, $mutation, [
                'id' => $profileId,
            ]);

            if (!($result['success'] ?? false)) {
                return $result;
            }

            $payload = data_get($result, 'data.deliveryProfileRemove');

            if (!empty($payload['userErrors'])) {
                return [
                    'success' => false,
                    'message' => $payload['userErrors'][0]['message'] ?? 'Failed to remove delivery profile',
                    'errors' => $payload['userErrors'],
                    'data' => $payload,
                ];
            }

            return [
                'success' => true,
                'message' => 'Delivery profile removal queued',
                'data' => $payload,
            ];
    }   

    /**
     * Ensure the shop has a fulfillment service and a US-configured location.
     * Uses Shopify GraphQL as requested by frontend integration.
     */
    public function ensureFulfillmentServiceAndLocation(int $shopId, ?string $baseUrl = null): array
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return ['success' => false, 'message' => 'Shop not found'];
        }

        if (!empty($shop->fulfillment_service_id) && !empty($shop->location_id)) {
            return [
                'success' => true,
                'message' => 'Fulfillment service already configured',
                'data' => [
                    'fulfillment_service_id' => (string) $shop->fulfillment_service_id,
                    'location_id' => (string) $shop->location_id,
                ],
            ];
        }

        $baseUrl = rtrim((string) ($baseUrl ?: config('app.url')), '/');
        $callbackUrl = $baseUrl . '/api/v1';

                $createMutation = <<<'GQL'
        mutation fulfillmentServiceCreate(
        $name: String!,
        $callbackUrl: URL!,
        $inventoryManagement: Boolean!,
        $trackingSupport: Boolean!
        ) {
        fulfillmentServiceCreate(
            name: $name,
            callbackUrl: $callbackUrl,
            inventoryManagement: $inventoryManagement,
            trackingSupport: $trackingSupport
        ) {
            fulfillmentService {
            id
            location {
                id
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;

        $createResult = $this->graphqlRequest($shopId, $createMutation, [
            'name' => 'DTFTA',
            'callbackUrl' => $callbackUrl,
            'inventoryManagement' => true,
            'trackingSupport' => true,
        ]);

        if (!$createResult['success']) {
            return $createResult;
        }

        $serviceData = $createResult['data']['fulfillmentServiceCreate'] ?? [];
        $userErrors = $serviceData['userErrors'] ?? [];
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Fulfillment service create returned user errors',
                'errors' => $userErrors,
                'data' => $serviceData,
            ];
        }

        $fulfillmentServiceId = (string) ($serviceData['fulfillmentService']['id'] ?? '');
        $createdLocationId = (string) ($serviceData['fulfillmentService']['location']['id'] ?? '');

        if ($fulfillmentServiceId === '') {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Fulfillment service ID missing in Shopify response',
                'data' => $serviceData,
            ];
        }

        $locationUpdateErrors = [];
        if ($createdLocationId !== '') {
                        $locationMutation = <<<'GQL'
            mutation updateLocation($id: ID!, $input: LocationEditInput!) {
            locationEdit(id: $id, input: $input) {
                location {
                id
                name
                }
                userErrors {
                field
                message
                }
            }
            }
            GQL;

            $locationResult = $this->graphqlRequest($shopId, $locationMutation, [
                'id' => $createdLocationId,
                'input' => [
                    'address' => [
                        'countryCode' => 'US',
                    ],
                ],
            ]);

            if (!$locationResult['success']) {
                $locationUpdateErrors = $locationResult['errors'] ?? [];
            } else {
                $locationEditData = $locationResult['data']['locationEdit'] ?? [];
                if (!empty($locationEditData['userErrors'])) {
                    $locationUpdateErrors = $locationEditData['userErrors'];
                }
            }
        }

        $shop->fulfillment_service_id = $fulfillmentServiceId;
        if ($createdLocationId !== '') {
            $shop->location_id = $createdLocationId;
        }
        $shop->save();

        return [
            'success' => true,
            'message' => 'Fulfillment service configured successfully',
            'data' => [
                'fulfillment_service_id' => $fulfillmentServiceId,
                'location_id' => $createdLocationId !== '' ? $createdLocationId : null,
                'location_update_errors' => $locationUpdateErrors,
            ],
        ];
    }

    /**
     * registerWebhook - Register a webhook in Shopify for a specific topic and callback URL. This is used to set up webhooks that notify the app of relevant events in Shopify.
     */
    public function registerWebhook($shopId, $topic, $callbackUrl): array
    {
        $shop = Shop::find((int) $shopId);
        $webhookApiVersion = $this->webhookApiVersion($shop);
        $payload = [
            'webhook' => [
                'topic' => $topic,
                'address' => $callbackUrl,
                'format' => 'json',
            ],
        ];

        $result = $this->request((int) $shopId, 'POST', 'webhooks', $payload, [], $webhookApiVersion);
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

    /**
     * listWebhooks - Retrieve a list of registered webhooks in Shopify. This is used to check which webhooks are currently set up for the shop.
     */
    public function listWebhooks(int $shopId): array
    {
        $shop = Shop::find($shopId);
        $webhookApiVersion = $this->webhookApiVersion($shop);
        $result = $this->request($shopId, 'GET', 'webhooks', [], [], $webhookApiVersion);
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

    /**
     * deleteWebhook - Delete a specific webhook in Shopify by its ID. This is used to remove webhooks that are no longer needed or to clean up during uninstallation.
     */
    public function deleteWebhook(int $shopId, $webhookId): array
    {
        $shop = Shop::find($shopId);
        $webhookApiVersion = $this->webhookApiVersion($shop);
        return $this->request($shopId, 'DELETE', "webhooks/{$webhookId}", [], [], $webhookApiVersion);
    }

    /**
     * ensureRequiredWebhooks - Ensure that all required webhooks for the app are registered in Shopify. This is used during installation or setup to make sure the app is subscribed to the necessary events.
     */
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

    /**
     * provisionShopOnInstall - Perform necessary setup for a shop when the app is installed, including creating a fulfillment service and registering required webhooks. This is used to prepare the shop for using the app's features.
     */
    public function provisionShopOnInstall(int $shopId, ?string $baseUrl = null): array
    {
        $shop = Shop::find($shopId);
    
        if (!$shop) {
            return ['success' => false, 'message' => 'Shop not found'];
        }
    
        $baseUrl = rtrim($baseUrl ?: (string) config('app.url'), '/');
        $fulfillmentCallbackUrl = "{$baseUrl}/api/v1";

        $serviceResult = null;
        $deliveryProfileResult = null;
    
        if (empty($shop->fulfillment_service_id) || empty($shop->location_id)) {
            $serviceResult = $this->createFulfillmentService(
                $shopId,
                $fulfillmentCallbackUrl,
                'DTFTA Fulfillment Service'
            );
    
            if (!$serviceResult['success']) {
                return [
                    'success' => false,
                    'message' => $serviceResult['message'] ?? 'Failed to create fulfillment service',
                    'data' => [
                        'fulfillment_service' => $serviceResult['data'] ?? null,
                    ],
                ];
            }
    
            $service = $serviceResult['data'] ?? [];
    
            $shop->fulfillment_service_id = !empty($service['id']) ? "gid://shopify/FulfillmentService/{$service['id']}" : $shop->fulfillment_service_id;
            $shop->location_id = !empty($service['location_id']) ? "gid://shopify/Location/{$service['location_id']}" : $shop->location_id;
    
            $shop->save();
        }
    
        if (!empty($shop->location_id)) {

            if (empty($shop->shipping_profile_id)) {
                $create = $this->createDeliveryProfile(
                    (int) $shop->id,
                    (string) $shop->location_id,
                    'DTFTA Shipping'
                );

                if ($create['success']) {
                    $profile = $create['data'] ?? [];
                    $shop->shipping_profile_id = (string) ($profile['id'] ?? null);

                    $groupId = data_get($profile, 'profileLocationGroups.0.locationGroup.id');
                    if ($groupId) {
                        $shop->delivery_location_group_id = (string) $groupId;
                    }

                    $shop->save();
                }
            } else {
                $sync = $this->syncDeliveryProfileConfiguration(
                    (int) $shop->id,
                    (string) $shop->shipping_profile_id,
                    (string) $shop->location_id,
                    'DTFTA Shipping',
                    4.99,
                    'USD'
                );

                if ($sync['success']) {
                    $profile = $sync['data'] ?? [];
                    $groupId = data_get($profile, 'profileLocationGroups.0.locationGroup.id');
                    if ($groupId) {
                        $shop->delivery_location_group_id = (string) $groupId;
                        $shop->save();
                    }
                }
            }
        }
    
        $webhooks = $this->ensureRequiredWebhooks($shopId, $baseUrl);
    
        return [
            'success' =>
                ($serviceResult ? (bool) $serviceResult['success'] : true) &&
                ($deliveryProfileResult ? (bool) $deliveryProfileResult['success'] : true) &&
                (bool) $webhooks['success'],
            'data' => [
                'fulfillment_service' => $serviceResult['data'] ?? null,
                'delivery_profile' => $deliveryProfileResult['data'] ?? null,
                'webhooks' => $webhooks['data'] ?? [],
            ],
            'message' => 'Provisioning completed',
        ];
    }


    /**
     * Create custom product with:
     * - product
     * - variants (with SKU)
     * - print area metafield
     * - artwork metafield
     * - print plan metafield
     * - artwork attached as product media
     *
     * Expected payload:
     * [
     *   'title' => 'T-Shirt Custom',
     *   'descriptionHtml' => '<p>Custom t-shirt</p>',
     *   'vendor' => 'DTFTA',
     *   'productType' => 'T-Shirt',
     *   'status' => 'DRAFT',
     *   'tags' => ['custom', 'print-on-demand'],
     *   'options' => [
     *      ['name' => 'Color', 'values' => ['Black', 'White']],
     *      ['name' => 'Size', 'values' => ['S', 'M']],
     *   ],
     *   'variants' => [
     *      [
     *          'price' => '19.99',
     *          'compareAtPrice' => '24.99',
     *          'sku' => 'TS-BLK-S',
     *          'barcode' => '123456',
     *          'optionValues' => [
     *              ['optionName' => 'Color', 'name' => 'Black'],
     *              ['optionName' => 'Size', 'name' => 'S'],
     *          ],
     *      ],
     *   ],
     *   'print_areas' => [
     *      [
     *          'placement' => 'front',
     *          'width' => 12,
     *          'height' => 16,
     *          'unit' => 'in',
     *          'position_x' => 125,
     *          'position_y' => 125,
     *      ],
     *   ],
     *   'artwork' => [
     *      'front' => 'data:image/png;base64,...',
     *      'back' => 'data:image/png;base64,...',
     *   ],
     *   'print_plan' => [
     *      'front' => ['width' => 12, 'height' => 16],
     *   ],
     * ]
     */
    public function createCustomProductWithPrintAreas(int $shopId, array $payload): array
    {
        try {

            $productCreateResult = $this->createShopifyProduct($shopId, $payload);
            if (!$productCreateResult['success']) {
                return $productCreateResult;
            }
    
            $product = $productCreateResult['data']['product'] ?? null;
            $productId = $product['id'] ?? null;
    
            if (!$productId) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => 'Product ID missing after product creation',
                    'data' => $productCreateResult['data'] ?? null,
                    'errors' => [],
                ];
            }
    
            $createdVariants = [];
    
            if (!empty($payload['variants'])) {
                $variantResult = $this->createShopifyVariants(
                    $shopId,
                    $productId,
                    $product,
                    $payload['variants']
                );
    
                if (!$variantResult['success']) {
                    return $variantResult;
                }
    
                $createdVariants = $variantResult['data']['variants'] ?? [];
                $product = $variantResult['data']['product'] ?? $product;
            }
    
            $artworkUrls = [];
            $mediaResultData = [];
    
            if (!empty($payload['artwork']) && is_array($payload['artwork'])) {
                $normalized = $this->normalizeArtworkBatchForShopify($payload['artwork']);
    
                if (!$normalized['success']) {
                    return $normalized;
                }
    
                $resolved = $this->resolveArtworkSourcesForShopify(
                    $shopId,
                    $normalized['data']['artwork']
                );
    
                if (!$resolved['success']) {
                    return $resolved;
                }
    
                $artworkUrls = $resolved['data']['artwork'] ?? [];
    
                $attachResult = $this->attachArtworkAsProductMedia(
                    $shopId,
                    $productId,
                    $resolved['data']['media'] ?? []
                );
    
                if (!$attachResult['success']) {
                    return $attachResult;
                }
    
                $mediaResultData = $attachResult['data']['media'] ?? [];
                $product = $attachResult['data']['product'] ?? $product;
            }
    
            // $metafieldResult = $this->setCustomProductMetafields($shopId, $productId, [
            //     'print_areas' => $payload['print_areas'] ?? [],
            //     'artwork' => $artworkUrls,
            //     'print_plan' => $payload['print_plan'] ?? null,
            //     'line_item_meta' => $payload['line_item_meta'] ?? [],
            // ]);

            $metafieldResult = $this->setCustomProductMetafields($shopId, $productId, [
                'print_areas' => [],
                'artwork' => [],
                'print_plan' => [],
                'line_item_meta' => $payload['line_item_meta'] ?? [],
            ]);
    
            if (!$metafieldResult['success']) {
                return $metafieldResult;
            }
    
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Custom product created successfully',
                'data' => [
                    'product' => $product,
                    'variants' => $createdVariants,
                    'artwork' => $artworkUrls,
                    'media' => $mediaResultData,
                    'metafields' => $metafieldResult['data']['metafields'] ?? [],
                ],
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('Shopify createCustomProductWithPrintAreas error', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);
    
            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => [],
            ];
        }
    }
    
    private function createShopifyProduct(int $shopId, array $payload): array
    {
            $mutation = <<<'GQL'
        mutation productCreate($product: ProductCreateInput!) {
        productCreate(product: $product) {
            product {
            id
            title
            handle
            status
            options {
                id
                name
                optionValues {
                id
                name
                }
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;
    
        $productInput = [
            'title' => (string) ($payload['title'] ?? ''),
            'descriptionHtml' => $payload['descriptionHtml'] ?? null,
            'vendor' => $payload['vendor'] ?? null,
            'productType' => $payload['productType'] ?? null,
            'status' => $payload['status'] ?? 'DRAFT',
            'tags' => array_values($payload['tags'] ?? []),
            'productOptions' => collect($payload['options'] ?? [])
                ->map(function ($option) {
                    return [
                        'name' => (string) ($option['name'] ?? ''),
                        'values' => collect($option['values'] ?? [])
                            ->map(fn ($value) => ['name' => (string) $value])
                            ->values()
                            ->all(),
                    ];
                })
                ->filter(fn ($option) => !empty($option['name']) && !empty($option['values']))
                ->values()
                ->all(),
        ];
    
        if ($productInput['title'] === '') {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Product title is required',
                'data' => null,
                'errors' => [],
            ];
        }
    
        $result = $this->graphqlRequest($shopId, $mutation, [
            'product' => $productInput,
        ]);
    
        Log::info('Shopify productCreate response', [
            'shop_id' => $shopId,
            'response' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        if (!$result['success']) {
            return $result;
        }
    
        $payloadData = $result['data']['productCreate'] ?? [];
        $userErrors = $payloadData['userErrors'] ?? [];
    
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify productCreate returned user errors',
                'data' => $payloadData,
                'errors' => $userErrors,
            ];
        }
    
        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Product created successfully',
            'data' => [
                'product' => $payloadData['product'] ?? null,
            ],
            'errors' => [],
        ];
    }
    
    private function createShopifyVariants(int $shopId, string $productId, array $product, array $variants): array
    {
        $optionIdMap = [];
    
        foreach (($product['options'] ?? []) as $option) {
            $optionName = (string) ($option['name'] ?? '');
            $optionId = (string) ($option['id'] ?? '');
    
            if ($optionName === '' || $optionId === '') {
                continue;
            }
    
            foreach (($option['optionValues'] ?? []) as $value) {
                $valueName = (string) ($value['name'] ?? '');
                if ($valueName !== '') {
                    $optionIdMap[$optionName][$valueName] = $optionId;
                }
            }
        }
    
        $variantInputs = collect($variants)->map(function ($variant) use ($optionIdMap) {
            $trackInventory = array_key_exists('trackInventory', $variant)
                ? (bool) $variant['trackInventory']
                : false; // default: do not track inventory
    
            $allowBackorder = array_key_exists('allowBackorder', $variant)
                ? (bool) $variant['allowBackorder']
                : true; // default: allow selling when out of stock
    
            $inventoryItem = array_filter([
                'sku' => $variant['sku'] ?? null,
                'tracked' => $trackInventory,
            ], fn ($value) => $value !== null && $value !== '');
    
            return array_filter([
                'price' => isset($variant['price']) ? (string) $variant['price'] : null,
                'compareAtPrice' => isset($variant['compareAtPrice']) ? (string) $variant['compareAtPrice'] : null,
                'barcode' => $variant['barcode'] ?? null,
                'inventoryItem' => !empty($inventoryItem) ? $inventoryItem : null,
                'inventoryPolicy' => $allowBackorder ? 'CONTINUE' : 'DENY',
                'optionValues' => collect($variant['optionValues'] ?? [])
                    ->map(function ($optionValue) use ($optionIdMap) {
                        $optionName = (string) ($optionValue['optionName'] ?? '');
                        $valueName = (string) ($optionValue['name'] ?? '');
                        $optionId = $optionIdMap[$optionName][$valueName] ?? null;
    
                        if (!$optionId || $valueName === '') {
                            return null;
                        }
    
                        return [
                            'optionId' => $optionId,
                            'name' => $valueName,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all(),
            ], fn ($value) => $value !== null);
        })->values()->all();
    
        Log::info('Shopify productVariantsBulkCreate request', [
            'shop_id' => $shopId,
            'product_id' => $productId,
            'variants' => json_encode($variantInputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
            $mutation = <<<'GQL'
        mutation productVariantsBulkCreate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
        productVariantsBulkCreate(
            productId: $productId,
            variants: $variants,
            strategy: REMOVE_STANDALONE_VARIANT
        ) {
            product {
            id
            title
            handle
            }
            productVariants {
            id
            title
            price
            barcode
            inventoryPolicy
            inventoryItem {
                id
                sku
                tracked
            }
            selectedOptions {
                name
                value
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;
    
        $result = $this->graphqlRequest($shopId, $mutation, [
            'productId' => $productId,
            'variants' => $variantInputs,
        ]);
    
        Log::info('Shopify productVariantsBulkCreate response', [
            'shop_id' => $shopId,
            'response' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        if (!$result['success']) {
            return $result;
        }
    
        $payloadData = $result['data']['productVariantsBulkCreate'] ?? [];
        $userErrors = $payloadData['userErrors'] ?? [];
    
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify productVariantsBulkCreate returned user errors',
                'data' => $payloadData,
                'errors' => $userErrors,
            ];
        }
    
        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Variants created successfully',
            'data' => [
                'product' => $payloadData['product'] ?? null,
                'variants' => $payloadData['productVariants'] ?? [],
            ],
            'errors' => [],
        ];
    }
    
    private function normalizeArtworkBatchForShopify(array $artwork): array
    {
        $normalized = [];
    
        foreach ($artwork as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
    
            $placement = strtolower(trim((string) ($item['placement'] ?? 'artwork_' . ($index + 1))));
            $url = trim((string) ($item['url'] ?? ''));
            $sourceCode = trim((string) ($item['source_code'] ?? ''));
            $title = trim((string) ($item['title'] ?? ucfirst(str_replace('_', ' ', $placement)) . ' artwork'));
    
            if ($url === '' && $sourceCode === '') {
                continue;
            }
    
            $normalized[] = [
                'placement' => $placement,
                'url' => $url,
                'source_code' => $sourceCode,
                'title' => $title,
            ];
        }
    
        if (empty($normalized)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'No artwork URL or source_code was provided',
                'data' => null,
                'errors' => [],
            ];
        }
    
        Log::info('Normalized artwork batch for Shopify', [
            'artwork' => json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        return [
            'success' => true,
            'status' => 200,
            'message' => 'Artwork batch normalized successfully',
            'data' => [
                'artwork' => $normalized,
            ],
            'errors' => [],
        ];
    }
    
    private function uploadArtworkToShopify(
            int $shopId,
            string $base64Image,
            string $filename = 'artwork.png',
            string $alt = 'Artwork'
        ): array {
        if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $base64Image, $matches)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Invalid base64 image format',
                'data' => null,
                'errors' => [],
            ];
        }
    
        $mimeType = $matches[1];
        $binary = base64_decode(substr($base64Image, strpos($base64Image, ',') + 1), true);
    
        if ($binary === false) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Failed to decode base64 image',
                'data' => null,
                'errors' => [],
            ];
        }
    
        $fileSize = strlen($binary);
    
            $stagedMutation = <<<'GQL'
        mutation stagedUploadsCreate($input: [StagedUploadInput!]!) {
        stagedUploadsCreate(input: $input) {
            stagedTargets {
            url
            resourceUrl
            parameters {
                name
                value
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;
    
        $stagedResult = $this->graphqlRequest($shopId, $stagedMutation, [
            'input' => [
                [
                    'resource' => 'IMAGE',
                    'filename' => $filename,
                    'mimeType' => $mimeType,
                    'httpMethod' => 'POST',
                    'fileSize' => (string) $fileSize,
                ],
            ],
        ]);
    
        if (!$stagedResult['success']) {
            return $stagedResult;
        }
    
        $stagedPayload = $stagedResult['data']['stagedUploadsCreate'] ?? [];
        $stagedErrors = $stagedPayload['userErrors'] ?? [];
    
        if (!empty($stagedErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify stagedUploadsCreate returned user errors',
                'data' => $stagedPayload,
                'errors' => $stagedErrors,
            ];
        }
    
        $target = $stagedPayload['stagedTargets'][0] ?? null;
    
        if (!$target || empty($target['url']) || empty($target['resourceUrl'])) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Missing staged upload target',
                'data' => $stagedPayload,
                'errors' => [],
            ];
        }
    
        $formFields = [];
        foreach (($target['parameters'] ?? []) as $param) {
            $formFields[$param['name']] = $param['value'];
        }
    
        $uploadResponse = Http::timeout(120)
            ->withoutVerifying()
            ->attach('file', $binary, $filename, [
                'Content-Type' => $mimeType,
            ])
            ->post($target['url'], $formFields);
    
        if (!$uploadResponse->successful()) {
            Log::error('Shopify staged upload failed', [
                'shop_id' => $shopId,
                'status' => $uploadResponse->status(),
                'body' => $uploadResponse->body(),
                'target_url' => $target['url'],
                'resource_url' => $target['resourceUrl'],
                'form_fields' => $formFields,
            ]);
    
            return [
                'success' => false,
                'status' => $uploadResponse->status(),
                'message' => 'Failed to upload image to Shopify staged target',
                'data' => [
                    'body' => $uploadResponse->body(),
                    'target' => $target,
                ],
                'errors' => [],
            ];
        }
    
        return [
            'success' => true,
            'status' => 200,
            'message' => 'Artwork uploaded successfully',
            'data' => [
                'resourceUrl' => $target['resourceUrl'],
                'filename' => $filename,
                'alt' => $alt,
            ],
            'errors' => [],
        ];
    }
    
    private function resolveArtworkSourcesForShopify(int $shopId, array $artwork): array
    {
        $resolvedArtwork = [];
        $media = [];
    
        foreach ($artwork as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
    
            $placement = strtolower(trim((string) ($item['placement'] ?? 'artwork_' . ($index + 1))));
            $title = trim((string) ($item['title'] ?? ucfirst(str_replace('_', ' ', $placement)) . ' artwork'));
            $url = trim((string) ($item['url'] ?? ''));
            $sourceCode = trim((string) ($item['source_code'] ?? ''));
    
            $finalUrl = '';
    
            // Prefer source_code upload first, so Shopify-hosted originalSource is used
            if ($sourceCode !== '') {
                $filename = $placement . '.png';
    
                $uploadResult = $this->uploadArtworkToShopify($shopId, $sourceCode, $filename, $title);
    
                if (!$uploadResult['success']) {
                    return $uploadResult;
                }
    
                $finalUrl = (string) ($uploadResult['data']['resourceUrl'] ?? '');
            }
    
            // Fallback to direct URL only if source_code was not available or upload failed to resolve
            if ($finalUrl === '' && $url !== '') {
                $finalUrl = $url;
            }
    
            if ($finalUrl === '') {
                continue;
            }
    
            $resolvedArtwork[] = [
                'placement' => $placement,
                'url' => $finalUrl,
                'title' => $title,
            ];
    
            $media[] = [
                'originalSource' => $finalUrl,
                'mediaContentType' => 'IMAGE',
                'alt' => $title,
            ];
        }
    
        if (empty($resolvedArtwork)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'No artwork could be resolved from URL or source_code',
                'data' => null,
                'errors' => [],
            ];
        }
    
        return [
            'success' => true,
            'status' => 200,
            'message' => 'Artwork sources resolved successfully',
            'data' => [
                'artwork' => $resolvedArtwork,
                'media' => $media,
            ],
            'errors' => [],
        ];
    }
    
    private function attachArtworkAsProductMedia(int $shopId, string $productId, array $media): array
    {
        if (empty($media)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'No valid artwork media provided',
                'data' => null,
                'errors' => [],
            ];
        }
    
        Log::info('Shopify product media attach request', [
            'shop_id' => $shopId,
            'product_id' => $productId,
            'media' => json_encode($media, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
            $mutation = <<<'GQL'
        mutation productUpdate($product: ProductUpdateInput!, $media: [CreateMediaInput!]) {
        productUpdate(product: $product, media: $media) {
            product {
            id
            title
            media(first: 20) {
                nodes {
                id
                alt
                mediaContentType
                status
                ... on MediaImage {
                    image {
                    url
                    }
                }
                }
            }
            }
            userErrors {
            field
            message
            }
        }
        }
        GQL;
    
        $result = $this->graphqlRequest($shopId, $mutation, [
            'product' => [
                'id' => $productId,
            ],
            'media' => $media,
        ]);
    
        Log::info('Shopify product media attach response', [
            'shop_id' => $shopId,
            'response' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        if (!$result['success']) {
            return $result;
        }
    
        $payloadData = $result['data']['productUpdate'] ?? [];
        $userErrors = $payloadData['userErrors'] ?? [];
    
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify productUpdate returned user errors',
                'data' => $payloadData,
                'errors' => $userErrors,
            ];
        }
    
        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Artwork attached as product media successfully',
            'data' => [
                'product' => $payloadData['product'] ?? null,
                'media' => $payloadData['product']['media']['nodes'] ?? [],
            ],
            'errors' => [],
        ];
    }
    
    private function setCustomProductMetafields(int $shopId, string $productId, array $data): array
    {
        $metafields = [];
    
        if (!empty($data['print_areas'])) {
            $metafields[] = [
                'ownerId' => $productId,
                'namespace' => 'dtfta',
                'key' => 'print_areas',
                'type' => 'json',
                'value' => json_encode(array_values($data['print_areas']), JSON_UNESCAPED_SLASHES),
            ];
        }
    
        if (!empty($data['artwork'])) {
            $metafields[] = [
                'ownerId' => $productId,
                'namespace' => 'dtfta',
                'key' => 'artwork',
                'type' => 'json',
                'value' => json_encode(array_values($data['artwork']), JSON_UNESCAPED_SLASHES),
            ];
        }
    
        if (!empty($data['print_plan'])) {
            $metafields[] = [
                'ownerId' => $productId,
                'namespace' => 'dtfta',
                'key' => 'print_plan',
                'type' => 'json',
                'value' => json_encode($data['print_plan'], JSON_UNESCAPED_SLASHES),
            ];
        }

        if(!empty($data['line_item_meta'])){
            $lineItemMetas = $data['line_item_meta'] ?? [];
            foreach($lineItemMetas as $lineItemMeta ){
                $metafields[] = [
                    'ownerId' => $productId,
                    'namespace' => 'dtfta',
                    'key' => 'template_id',
                    'type' => 'single_line_text_field',
                    'value' => (string) $lineItemMeta['template_id'],
                ];
            }
        }
    
        if (empty($metafields)) {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'No metafields to save',
                'data' => [
                    'metafields' => [],
                ],
                'errors' => [],
            ];
        }
    
        Log::info('Shopify metafieldsSet request', [
            'shop_id' => $shopId,
            'product_id' => $productId,
            'metafields' => json_encode($metafields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
            $mutation = <<<'GQL'
        mutation MetafieldsSet($metafields: [MetafieldsSetInput!]!) {
        metafieldsSet(metafields: $metafields) {
            metafields {
            id
            namespace
            key
            type
            value
            }
            userErrors {
            field
            message
            code
            }
        }
        }
        GQL;
    
        $result = $this->graphqlRequest($shopId, $mutation, [
            'metafields' => $metafields,
        ]);
    
        Log::info('Shopify metafieldsSet response', [
            'shop_id' => $shopId,
            'response' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    
        if (!$result['success']) {
            return $result;
        }
    
        $payloadData = $result['data']['metafieldsSet'] ?? [];
        $userErrors = $payloadData['userErrors'] ?? [];
    
        if (!empty($userErrors)) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Shopify metafieldsSet returned user errors',
                'data' => $payloadData,
                'errors' => $userErrors,
            ];
        }
    
        return [
            'success' => true,
            'status' => $result['status'],
            'message' => 'Metafields saved successfully',
            'data' => [
                'metafields' => $payloadData['metafields'] ?? [],
            ],
            'errors' => [],
        ];
    }


    /**
     * Assign one or more product variants to an existing Shopify delivery profile.
     *
     * $deliveryProfileId can be either:
     * - gid://shopify/DeliveryProfile/123
     * - 123
     *
     * $variantIds can be:
     * - ["gid://shopify/ProductVariant/111", "gid://shopify/ProductVariant/222"]
     * - [111, 222]
     */
    public function assignVariantsToDeliveryProfile(
        int $shopId,
        string $deliveryProfileId,
        array $variantIds
    ): array {
        try {
            $profileGid = str_starts_with($deliveryProfileId, 'gid://')
                ? $deliveryProfileId
                : "gid://shopify/DeliveryProfile/{$deliveryProfileId}";

            $variantGids = array_values(array_filter(array_map(function ($id) {
                $value = trim((string) $id);
                if ($value === '') {
                    return null;
                }

                return str_starts_with($value, 'gid://')
                    ? $value
                    : "gid://shopify/ProductVariant/{$value}";
            }, $variantIds)));

            if (empty($variantGids)) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => 'At least one valid variant ID is required',
                    'data' => null,
                    'errors' => [],
                ];
            }

                    $mutation = <<<'GRAPHQL'
            mutation AssignVariantsToDeliveryProfile($id: ID!, $profile: DeliveryProfileInput!) {
            deliveryProfileUpdate(id: $id, profile: $profile) {
                profile {
                id
                name
                productVariantsCount {
                    count
                }
                profileItems(first: 20) {
                    edges {
                    node {
                        id
                        product {
                        id
                        title
                        }
                        variants(first: 20) {
                        edges {
                            node {
                            id
                            title
                            sku
                            }
                        }
                        }
                    }
                    }
                }
                }
                userErrors {
                field
                message
                }
            }
            }
            GRAPHQL;

            $variables = [
                'id' => $profileGid,
                'profile' => [
                    'variantsToAssociate' => $variantGids,
                ],
            ];

            Log::info('Shopify assignVariantsToDeliveryProfile request', [
                'shop_id' => $shopId,
                'delivery_profile_id' => $profileGid,
                'variant_ids' => $variantGids,
            ]);

            $result = $this->graphqlRequest($shopId, $mutation, $variables);

            Log::info('Shopify assignVariantsToDeliveryProfile response', [
                'shop_id' => $shopId,
                'result' => $result,
            ]);

            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'status' => $result['status'] ?? 500,
                    'message' => $result['message'] ?? 'GraphQL request failed',
                    'data' => $result['data'] ?? null,
                    'errors' => $result['errors'] ?? [],
                ];
            }

            $payload = data_get($result, 'data.deliveryProfileUpdate');

            if (!$payload) {
                return [
                    'success' => false,
                    'status' => 500,
                    'message' => 'Missing deliveryProfileUpdate payload in Shopify response',
                    'data' => $result['data'] ?? null,
                    'errors' => $result['errors'] ?? [],
                ];
            }

            if (!empty($payload['userErrors'])) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => $payload['userErrors'][0]['message'] ?? 'Failed to assign variants to delivery profile',
                    'data' => $payload,
                    'errors' => $payload['userErrors'],
                ];
            }

            return [
                'success' => true,
                'status' => $result['status'],
                'message' => 'Variants assigned to delivery profile successfully',
                'data' => $payload['profile'] ?? null,
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('assignVariantsToDeliveryProfile exception', [
                'shop_id' => $shopId,
                'delivery_profile_id' => $deliveryProfileId,
                'variant_ids' => $variantIds,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => [],
            ];
        }
    }

    /**
     * Assign a product variant to a specific Shopify inventory/fulfillment location.
     *
     * This works by:
     * 1) Resolving the variant's inventoryItem ID
     * 2) Activating that inventory item at the target location
     *
     * $variantId can be:
     * - gid://shopify/ProductVariant/123
     * - 123
     *
     * $locationId can be:
     * - gid://shopify/Location/456
     * - 456
     */
    public function assignVariantToInventoryLocation(
        int $shopId,
        string $variantId,
        string $locationId,
        ?int $available = null,
        ?int $onHand = null
    ): array {
        try {
            $variantGid = str_starts_with($variantId, 'gid://')
                ? $variantId
                : "gid://shopify/ProductVariant/{$variantId}";

            $locationGid = str_starts_with($locationId, 'gid://')
                ? $locationId
                : "gid://shopify/Location/{$locationId}";

                    // Step 1: Resolve inventory item from variant
                    $variantQuery = <<<'GRAPHQL'
            query GetVariantInventoryItem($id: ID!) {
            productVariant(id: $id) {
                id
                title
                inventoryItem {
                id
                sku
                tracked
                }
            }
            }
            GRAPHQL;

            $variantResult = $this->graphqlRequest($shopId, $variantQuery, [
                'id' => $variantGid,
            ]);

            if (!($variantResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'status' => $variantResult['status'] ?? 500,
                    'message' => $variantResult['message'] ?? 'Failed to resolve variant inventory item',
                    'data' => $variantResult['data'] ?? null,
                    'errors' => $variantResult['errors'] ?? [],
                ];
            }

            $variantData = data_get($variantResult, 'data.productVariant');
            $inventoryItemId = data_get($variantData, 'inventoryItem.id');

            if (!$inventoryItemId) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => 'Inventory item not found for variant',
                    'data' => $variantData,
                    'errors' => [],
                ];
            }

                // Step 2: Activate inventory item at location
                        $mutation = <<<'GRAPHQL'
                mutation ActivateInventoryAtLocation(
                $inventoryItemId: ID!,
                $locationId: ID!,
                $available: Int,
                $onHand: Int
                ) {
                inventoryActivate(
                    inventoryItemId: $inventoryItemId,
                    locationId: $locationId,
                    available: $available,
                    onHand: $onHand
                ) {
                    inventoryLevel {
                    id
                    item {
                        id
                    }
                    location {
                        id
                        name
                    }
                    quantities(names: ["available", "on_hand"]) {
                        name
                        quantity
                    }
                    }
                    userErrors {
                    field
                    message
                    }
                }
                }
                GRAPHQL;

            $variables = [
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationGid,
                'available' => $available,
                'onHand' => $onHand,
            ];

            Log::info('Shopify assignVariantToInventoryLocation request', [
                'shop_id' => $shopId,
                'variant_id' => $variantGid,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationGid,
                'available' => $available,
                'on_hand' => $onHand,
            ]);

            $result = $this->graphqlRequest($shopId, $mutation, $variables);

            Log::info('Shopify assignVariantToInventoryLocation response', [
                'shop_id' => $shopId,
                'result' => $result,
            ]);

            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'status' => $result['status'] ?? 500,
                    'message' => $result['message'] ?? 'GraphQL request failed',
                    'data' => $result['data'] ?? null,
                    'errors' => $result['errors'] ?? [],
                ];
            }

            $payload = data_get($result, 'data.inventoryActivate');

            if (!$payload) {
                return [
                    'success' => false,
                    'status' => 500,
                    'message' => 'Missing inventoryActivate payload in Shopify response',
                    'data' => $result['data'] ?? null,
                    'errors' => $result['errors'] ?? [],
                ];
            }

            if (!empty($payload['userErrors'])) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => $payload['userErrors'][0]['message'] ?? 'Failed to assign variant to inventory location',
                    'data' => $payload,
                    'errors' => $payload['userErrors'],
                ];
            }

            return [
                'success' => true,
                'status' => $result['status'],
                'message' => 'Variant assigned to inventory location successfully',
                'data' => [
                    'variant' => $variantData,
                    'inventory_level' => $payload['inventoryLevel'] ?? null,
                ],
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('assignVariantToInventoryLocation exception', [
                'shop_id' => $shopId,
                'variant_id' => $variantId,
                'location_id' => $locationId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => [],
            ];
        }
    }

}
