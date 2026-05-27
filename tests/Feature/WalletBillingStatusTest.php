<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopPaymentCard;
use App\Services\BillingService;
use App\Services\SquareWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletBillingStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_wallet_billing_requires_square_customer_id_and_active_card(): void
    {
        config(['services.square.wallet_billing_enabled' => true]);

        $service = app(SquareWalletService::class);

        $shop = Shop::create([
            'shop_domain' => 'wallet-billing-active.myshopify.com',
            'status' => 'active',
            'square_customer_id' => 'SQ_CUSTOMER_ACTIVE',
        ]);

        $this->assertFalse($service->shopHasActiveWalletBilling($shop));

        ShopPaymentCard::create([
            'shop_id' => $shop->id,
            'square_customer_id' => 'SQ_CUSTOMER_ACTIVE',
            'square_card_id' => 'ccof:active_card',
            'card_brand' => 'VISA',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
            'status' => 'active',
        ]);

        $this->assertTrue($service->shopHasActiveWalletBilling($shop));
    }

    public function test_active_wallet_billing_false_when_only_square_customer_id_on_shop(): void
    {
        config(['services.square.wallet_billing_enabled' => true]);

        $service = app(SquareWalletService::class);

        $shop = Shop::create([
            'shop_domain' => 'wallet-billing-no-card.myshopify.com',
            'status' => 'active',
            'square_customer_id' => 'SQ_CUSTOMER_ONLY',
        ]);

        $this->assertFalse($service->shopHasActiveWalletBilling($shop));
    }

    public function test_delete_card_for_shop_removes_database_row(): void
    {
        config([
            'services.square.wallet_billing_enabled' => true,
            'services.square.access_token' => '',
        ]);

        $service = app(SquareWalletService::class);

        $shop = Shop::create([
            'shop_domain' => 'wallet-delete-row.myshopify.com',
            'status' => 'active',
            'square_customer_id' => 'SQ_CUSTOMER_DELETE',
        ]);

        $card = ShopPaymentCard::create([
            'shop_id' => $shop->id,
            'square_customer_id' => 'SQ_CUSTOMER_DELETE',
            'square_card_id' => 'ccof:to_delete',
            'card_brand' => 'VISA',
            'last4' => '1111',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
            'status' => 'active',
        ]);

        $result = $service->deleteCardForShop($shop, $card->id);

        $this->assertTrue($result['success'] ?? false);
        $this->assertDatabaseMissing('shop_payment_cards', [
            'id' => $card->id,
            'square_card_id' => 'ccof:to_delete',
        ]);
        $this->assertFalse($service->shopHasActiveWalletBilling($shop->fresh()));
    }

    public function test_active_wallet_billing_false_without_square_customer_on_shop(): void
    {
        config(['services.square.wallet_billing_enabled' => true]);

        $service = app(SquareWalletService::class);

        $shop = Shop::create([
            'shop_domain' => 'wallet-billing-no-customer.myshopify.com',
            'status' => 'active',
        ]);

        ShopPaymentCard::create([
            'shop_id' => $shop->id,
            'square_customer_id' => 'SQ_ORPHAN',
            'square_card_id' => 'ccof:orphan',
            'card_brand' => 'VISA',
            'last4' => '9999',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
            'status' => 'active',
        ]);

        $this->assertFalse($service->shopHasActiveWalletBilling($shop));
    }

    public function test_billing_service_returns_active_only_with_customer_and_active_card(): void
    {
        config(['services.square.wallet_billing_enabled' => true]);

        $billing = app(BillingService::class);

        $shop = Shop::create([
            'shop_domain' => 'billing-service-wallet.myshopify.com',
            'status' => 'active',
            'square_customer_id' => 'SQ_BILLING_SVC',
        ]);

        $inactive = $billing->getBillingStatusForShop($shop);
        $this->assertSame('inactive', data_get($inactive, 'data.billing_status'));

        ShopPaymentCard::create([
            'shop_id' => $shop->id,
            'square_customer_id' => 'SQ_BILLING_SVC',
            'square_card_id' => 'ccof:billing_svc',
            'card_brand' => 'VISA',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
            'status' => 'active',
        ]);

        $active = $billing->getBillingStatusForShop($shop->fresh());
        $this->assertSame('active', data_get($active, 'data.billing_status'));
        $this->assertFalse(data_get($active, 'data.is_billing_required'));
    }
}
