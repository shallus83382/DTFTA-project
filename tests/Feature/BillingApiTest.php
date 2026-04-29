<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Services\AppSignatureVerifier;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_status_endpoint_returns_status_for_signed_shop(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'billing-status-test.myshopify.com',
            'status' => 'active',
        ]);

        $verifier = $this->mock(AppSignatureVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(true);

        $billing = $this->mock(BillingService::class);
        $billing->shouldReceive('getBillingStatusForShop')
            ->once()
            ->withArgs(fn (Shop $resolved) => $resolved->id === $shop->id)
            ->andReturn([
                'success' => true,
                'status' => 200,
                'message' => 'ok',
                'data' => [
                    'billing_status' => 'active',
                    'is_billing_required' => true,
                    'line_item_id' => 'gid://shopify/AppSubscriptionLineItem/1',
                ],
                'errors' => [],
            ]);

        $response = $this->getJson('/api/v1/billing/status?shop=' . urlencode($shop->shop_domain), [
            'X-Shop' => $shop->shop_domain,
            'X-App-Timestamp' => (string) now()->valueOf(),
            'X-App-Signature' => 'dummy-signature',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.billing_status', 'active');
    }

    public function test_billing_approve_endpoint_returns_confirmation_url(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'billing-approve-test.myshopify.com',
            'status' => 'active',
        ]);

        $verifier = $this->mock(AppSignatureVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(true);

        $billing = $this->mock(BillingService::class);
        $billing->shouldReceive('ensureApprovalUrl')
            ->once()
            ->withArgs(fn (Shop $resolved) => $resolved->id === $shop->id)
            ->andReturn([
                'success' => true,
                'status' => 200,
                'message' => 'ok',
                'data' => [
                    'confirmation_url' => 'https://shopify.com/confirm-billing',
                ],
                'errors' => [],
            ]);

        $response = $this->postJson('/api/v1/billing/approve', [
            'shop' => $shop->shop_domain,
        ], [
            'X-Shop' => $shop->shop_domain,
            'X-App-Timestamp' => (string) now()->valueOf(),
            'X-App-Signature' => 'dummy-signature',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.confirmation_url', 'https://shopify.com/confirm-billing');
    }
}

