<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Services\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyServiceBatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Shop::create([
            'shop_domain' => 'batching-test.myshopify.com',
            'shopify_access_token' => encrypt('test-token'),
        ]);
    }

    public function test_variant_media_is_attached_in_batches(): void
    {
        Http::fake(fn () => Http::response([
            'data' => [
                'productVariantAppendMedia' => [
                    'product' => ['id' => 'gid://shopify/Product/123'],
                    'userErrors' => [],
                ],
            ],
        ], 200));

        $variantMedia = [];
        for ($index = 0; $index < 132; $index++) {
            $variantMedia[] = [
                'variantId' => (string) (1000 + $index),
                'mediaIds' => [(string) (2000 + $index)],
            ];
        }

        $result = (new ShopifyService())->attachMediaToProductVariants(
            1,
            '123',
            $variantMedia
        );

        $this->assertTrue($result['success']);
        $this->assertSame(132, $result['data']['variant_media_count']);
        Http::assertSentCount(2);
        Http::assertSent(fn (ClientRequest $request): bool =>
            count(data_get($request->data(), 'variables.variantMedia', [])) === 100
        );
        Http::assertSent(fn (ClientRequest $request): bool =>
            count(data_get($request->data(), 'variables.variantMedia', [])) === 32
        );
    }

    public function test_inventory_assignment_batches_activation_and_other_location_deactivation(): void
    {
        $variantToInventoryItem = [];
        $variants = [];
        for ($index = 0; $index < 132; $index++) {
            $variantId = 'gid://shopify/ProductVariant/' . (1000 + $index);
            $inventoryItemId = 'gid://shopify/InventoryItem/' . (2000 + $index);
            $variantToInventoryItem[$variantId] = $inventoryItemId;
            $variants[] = [
                'id' => $variantId,
                'inventoryItem' => ['id' => $inventoryItemId],
            ];
        }

        Http::fake(function (ClientRequest $request) use ($variantToInventoryItem) {
            $body = $request->data();
            $query = $body['query'] ?? '';
            $variables = $body['variables'] ?? [];

            if (str_contains($query, 'GetInventoryLevelsForVariants')) {
                $data = [];
                foreach ($variables as $name => $variantId) {
                    if (!preg_match('/^variantId(\d+)$/', $name, $matches)) {
                        continue;
                    }

                    $index = (int) $matches[1];
                    $inventoryItemId = $variantToInventoryItem[$variantId];
                    $data["variant{$index}"] = [
                        'inventoryItem' => [
                            'id' => $inventoryItemId,
                            'inventoryLevels' => [
                                'edges' => [[
                                    'node' => [
                                        'id' => 'gid://shopify/InventoryLevel/'
                                            . substr($inventoryItemId, strrpos($inventoryItemId, '/') + 1),
                                        'location' => ['id' => 'gid://shopify/Location/999'],
                                    ],
                                ]],
                            ],
                        ],
                    ];
                }

                return Http::response(['data' => $data], 200);
            }

            $data = [];
            if (str_contains($query, 'ActivateVariantsAtLocation')) {
                foreach ($variables as $name => $_value) {
                    if (preg_match('/^inventoryItemId(\d+)$/', $name, $matches)) {
                        $data['activation' . $matches[1]] = ['userErrors' => []];
                    }
                }
            } else {
                foreach ($variables as $name => $_value) {
                    if (preg_match('/^inventoryLevelId(\d+)$/', $name, $matches)) {
                        $data['deactivation' . $matches[1]] = ['userErrors' => []];
                    }
                }
            }

            return Http::response(['data' => $data], 200);
        });

        $result = (new ShopifyService())->assignVariantsToInventoryLocation(
            1,
            $variants,
            '123'
        );

        $this->assertTrue($result['success']);
        $this->assertCount(132, $result['data']['assignments']);
        $this->assertSame(
            ['gid://shopify/Location/999'],
            $result['data']['assignments'][0]['deactivated_locations']
        );
        Http::assertSentCount(20);
        Http::assertSent(fn (ClientRequest $request): bool =>
            str_contains($request->data()['query'] ?? '', 'inventoryActivate')
            && $request->data()['variables']['locationId'] === 'gid://shopify/Location/123'
        );
        Http::assertSent(fn (ClientRequest $request): bool =>
            str_contains($request->data()['query'] ?? '', 'inventoryDeactivate')
            && $request->data()['variables']['inventoryLevelId0'] === 'gid://shopify/InventoryLevel/2000'
        );
    }
}
