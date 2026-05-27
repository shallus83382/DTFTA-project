<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopPaymentCard;
use App\Services\AppSignatureVerifier;
use App\Services\SquareWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_cards_index_returns_saved_cards_for_signed_shop(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'wallet-cards-test.myshopify.com',
            'status' => 'active',
            'square_customer_id' => 'SQ_CUSTOMER_1',
        ]);

        ShopPaymentCard::create([
            'shop_id' => $shop->id,
            'square_customer_id' => 'SQ_CUSTOMER_1',
            'square_card_id' => 'ccof:card_1',
            'card_brand' => 'VISA',
            'last4' => '1111',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
            'status' => 'active',
        ]);

        $verifier = $this->mock(AppSignatureVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(true);

        $response = $this->getJson(
            '/api/v1/wallet/cards?shop=' . urlencode($shop->shop_domain),
            [
                'X-Shop' => $shop->shop_domain,
                'X-App-Timestamp' => (string) now()->valueOf(),
                'X-App-Signature' => 'dummy-signature',
            ],
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cards.0.last4', '1111')
            ->assertJsonPath('data.cards.0.isDefault', true);
    }

    public function test_wallet_cards_store_delegates_to_square_wallet_service(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'wallet-save-test.myshopify.com',
            'status' => 'active',
        ]);

        $verifier = $this->mock(AppSignatureVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(true);

        $wallet = $this->mock(SquareWalletService::class);
        $wallet->shouldReceive('saveCardFromToken')
            ->once()
            ->withArgs(fn (Shop $resolved, string $token) => $resolved->id === $shop->id && $token === 'cnon:test-token')
            ->andReturn([
                'success' => true,
                'status' => 200,
                'message' => 'Card saved successfully.',
                'data' => [
                    'card' => [
                        'id' => '1',
                        'brand' => 'VISA',
                        'last4' => '4242',
                        'expMonth' => 12,
                        'expYear' => 2030,
                        'isDefault' => true,
                    ],
                ],
                'errors' => [],
            ]);

        $response = $this->postJson('/api/v1/wallet/cards', [
            'shop' => $shop->shop_domain,
            'sourceId' => 'cnon:test-token',
        ], [
            'X-Shop' => $shop->shop_domain,
            'X-App-Timestamp' => (string) now()->valueOf(),
            'X-App-Signature' => 'dummy-signature',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.card.last4', '4242');
    }

    public function test_wallet_cards_destroy_deletes_card_via_service(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'wallet-delete-test.myshopify.com',
            'status' => 'active',
            'square_customer_id' => 'SQ_CUSTOMER_1',
        ]);

        $card = ShopPaymentCard::create([
            'shop_id' => $shop->id,
            'square_customer_id' => 'SQ_CUSTOMER_1',
            'square_card_id' => 'ccof:card_delete',
            'card_brand' => 'VISA',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
            'status' => 'active',
        ]);

        $verifier = $this->mock(AppSignatureVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(true);

        $wallet = $this->mock(SquareWalletService::class);
        $wallet->shouldReceive('deleteCardForShop')
            ->once()
            ->withArgs(fn (Shop $resolved, int $cardId) => $resolved->id === $shop->id && $cardId === $card->id)
            ->andReturn([
                'success' => true,
                'status' => 200,
                'message' => 'Saved cards loaded.',
                'data' => ['cards' => []],
                'errors' => [],
            ]);

        $response = $this->deleteJson('/api/v1/wallet/cards/' . $card->id, [
            'shop' => $shop->shop_domain,
            'cardId' => $card->id,
        ], [
            'X-Shop' => $shop->shop_domain,
            'X-App-Timestamp' => (string) now()->valueOf(),
            'X-App-Signature' => 'dummy-signature',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cards', []);
    }
}
