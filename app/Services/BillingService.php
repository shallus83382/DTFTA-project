<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\BillingCharge;
use App\Models\Order;
use App\Models\Job;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class BillingService
{
    public function __construct(private ShopifyService $shopifyService)
    {
    }

    public function isEnforced(): bool
    {
        return (bool) config('services.shopify.billing.enabled', true);
    }

    public function getBillingStatusForShop(Shop $shop): array
    {
        $activeSubscription = $this->shopifyService->getActiveManagedSubscription((int) $shop->id);
        if (!($activeSubscription['success'] ?? false)) {
            return [
                'success' => false,
                'status' => $activeSubscription['status'] ?? 500,
                'message' => $activeSubscription['message'] ?? 'Failed to load managed billing status.',
                'data' => [
                    'billing_status' => $shop->billing_status ?: 'inactive',
                    'active_subscription' => null,
                    'line_item_id' => null,
                ],
                'errors' => $activeSubscription['errors'] ?? [],
            ];
        }

        $subscription = data_get($activeSubscription, 'data.subscription');
        $lineItemId = data_get($activeSubscription, 'data.line_item_id');

        if ($subscription && $lineItemId) {
            if ($shop->billing_status !== 'active' || empty($shop->shopify_billing_line_item_gid)) {
                $shop->update([
                    'billing_status' => 'active',
                    'shopify_billing_subscription_gid' => (string) data_get($subscription, 'id'),
                    'shopify_billing_line_item_gid' => (string) $lineItemId,
                    'billing_approved_at' => $shop->billing_approved_at ?: now(),
                    'billing_blocked_reason' => null,
                ]);
            }
        } elseif ($shop->billing_status === 'active') {
            $shop->update([
                'billing_status' => 'inactive',
                'shopify_billing_subscription_gid' => null,
                'shopify_billing_line_item_gid' => null,
                'billing_blocked_reason' => 'No active managed subscription',
            ]);
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Billing status resolved.',
            'data' => [
                'billing_status' => $shop->fresh()->billing_status ?? 'inactive',
                'active_subscription' => $subscription,
                'line_item_id' => $lineItemId,
                'is_billing_required' => $this->isEnforced(),
            ],
            'errors' => [],
        ];
    }

    public function ensureApprovalUrl(Shop $shop, ?string $returnUrl = null): array
    {
        $approval = $this->shopifyService->createManagedSubscriptionApproval((int) $shop->id, $returnUrl);
        if (!($approval['success'] ?? false)) {
            return $approval;
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Billing approval URL generated.',
            'data' => [
                'confirmation_url' => data_get($approval, 'data.confirmation_url'),
            ],
            'errors' => [],
        ];
    }

    public function assertMerchantBillingReady(Shop $shop): array
    {
        if (!$this->isEnforced()) {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Billing enforcement is disabled.',
                'data' => ['billing_status' => $shop->billing_status ?: 'inactive'],
                'errors' => [],
            ];
        }

        $status = $this->getBillingStatusForShop($shop);
        if (!($status['success'] ?? false)) {
            return $status;
        }

        if (data_get($status, 'data.billing_status') === 'active') {
            return $status;
        }

        $approval = $this->ensureApprovalUrl($shop);

        return [
            'success' => false,
            'status' => 402,
            'message' => 'Billing approval is required before fulfillment can start.',
            'data' => [
                'code' => 'BILLING_REQUIRED',
                'billing_status' => data_get($status, 'data.billing_status', 'inactive'),
                'billing_confirmation_url' => data_get($approval, 'data.confirmation_url'),
            ],
            'errors' => $approval['errors'] ?? [],
        ];
    }

    public function chargePreFulfillment(Shop $shop, Order $order, ?Shipment $shipment = null): array
    {
        $acceptedExisting = BillingCharge::query()
            ->where('shop_id', $shop->id)
            ->where('order_id', $order->id)
            ->where('charge_type', 'pre_fulfillment')
            ->where('status', 'accepted')
            ->latest('id')
            ->first();

        if ($acceptedExisting) {
            $this->syncStatusesAfterBillingPaid($order);
            $this->logPrepaymentAcceptedActivity($order, (string) ($acceptedExisting->shopify_usage_record_gid ?? ''));
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Pre-fulfillment charge already accepted for order.',
                'data' => [
                    'billing_charge' => $acceptedExisting,
                    'already_charged' => true,
                ],
                'errors' => [],
            ];
        }

        $amountBreakdown = $this->calculateFulfillmentChargeBreakdown($order);
        $amount = $amountBreakdown['total'];
        $currency = trim((string) ($order->currency ?: config('services.shopify.billing.currency_code', 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }
        $shipmentToken = $shipment?->id ? 'shipment_' . $shipment->id : 'shipment_pending';
        $idempotencyKey = sprintf(
            'pre_fulfillment:shop_%d:order_%d:%s',
            (int) $shop->id,
            (int) $order->id,
            $shipmentToken
        );

        Log::info('Pre-fulfillment billing amount calculated', [
            'shop_id' => (int) $shop->id,
            'order_id' => (int) $order->id,
            'shipment_id' => $shipment?->id,
            'currency' => $currency,
            'subtotal' => $amountBreakdown['subtotal'],
            'shipping' => $amountBreakdown['shipping'],
            'tax' => $amountBreakdown['tax'],
            'total' => $amountBreakdown['total'],
            'idempotency_key' => $idempotencyKey,
        ]);

        $existing = BillingCharge::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing && $existing->status === 'accepted') {
            $this->syncStatusesAfterBillingPaid($order);
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Pre-fulfillment charge already accepted.',
                'data' => [
                    'billing_charge' => $existing,
                    'already_charged' => true,
                ],
                'errors' => [],
            ];
        }

        $billingReady = $this->assertMerchantBillingReady($shop);
        if (!($billingReady['success'] ?? false)) {
            $failed = $existing ?: BillingCharge::create([
                'shop_id' => $shop->id,
                'order_id' => $order->id,
                'shipment_id' => $shipment?->id,
                'charge_type' => 'pre_fulfillment',
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'failed',
                'idempotency_key' => $idempotencyKey,
                'error_message' => $billingReady['message'] ?? 'Billing required',
            ]);

            AdminActivityLog::logSystemActivity(
                'Billing pending for order (approval required before fulfillment)',
                'Order',
                $order->id
            );

            return [
                'success' => false,
                'status' => 402,
                'message' => $billingReady['message'] ?? 'Billing required',
                'data' => [
                    'code' => 'BILLING_REQUIRED',
                    'billing_charge' => $failed,
                    'billing_confirmation_url' => data_get($billingReady, 'data.billing_confirmation_url'),
                ],
                'errors' => $billingReady['errors'] ?? [],
            ];
        }

        $lineItemId = (string) data_get($billingReady, 'data.line_item_id', $shop->shopify_billing_line_item_gid);
        if ($lineItemId === '') {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Missing Shopify billing line item id.',
                'data' => ['code' => 'BILLING_LINE_ITEM_MISSING'],
                'errors' => [],
            ];
        }

        $charge = $existing ?: BillingCharge::create([
            'shop_id' => $shop->id,
            'order_id' => $order->id,
            'shipment_id' => $shipment?->id,
            'charge_type' => 'pre_fulfillment',
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
            'meta' => [
                'shopify_order_id' => $order->shopify_order_id,
            ],
        ]);

        $usage = $this->shopifyService->createUsageRecord(
            (int) $shop->id,
            $lineItemId,
            $amount,
            sprintf(
                'Pre-fulfillment charge for order %s (product + shipping + tax)',
                (string) $order->order_number
            ),
            $idempotencyKey,
            $currency
        );

        if (!($usage['success'] ?? false)) {
            $charge->update([
                'status' => 'failed',
                'error_message' => $usage['message'] ?? 'Failed to create usage record.',
                'meta' => array_merge((array) $charge->meta, [
                    'errors' => $usage['errors'] ?? [],
                ]),
            ]);

            Log::warning('Pre-fulfillment billing charge failed', [
                'shop_id' => $shop->id,
                'order_id' => $order->id,
                'shipment_id' => $shipment?->id,
                'idempotency_key' => $idempotencyKey,
                'message' => $usage['message'] ?? null,
                'errors' => $usage['errors'] ?? [],
            ]);

            AdminActivityLog::logSystemActivity(
                'Billing failed for order (usage charge creation failed)',
                'Order',
                $order->id
            );

            return [
                'success' => false,
                'status' => 422,
                'message' => 'Unable to create pre-fulfillment billing charge.',
                'data' => [
                    'code' => 'BILLING_CHARGE_FAILED',
                    'billing_charge' => $charge->fresh(),
                ],
                'errors' => $usage['errors'] ?? [],
            ];
        }

        $usageRecordId = (string) data_get($usage, 'data.usage_record.id', '');
        $charge->update([
            'status' => 'accepted',
            'shopify_usage_record_gid' => $usageRecordId ?: null,
            'error_message' => null,
        ]);
        $this->syncStatusesAfterBillingPaid($order);
        $this->logPrepaymentAcceptedActivity($order, $usageRecordId);

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Pre-fulfillment billing charge accepted.',
            'data' => [
                'billing_charge' => $charge->fresh(),
                'usage_record_id' => $usageRecordId,
            ],
            'errors' => [],
        ];
    }

    public function ensurePreFulfillmentPaid(Shop $shop, Order $order): array
    {
        $acceptedCharge = BillingCharge::query()
            ->where('shop_id', $shop->id)
            ->where('order_id', $order->id)
            ->where('charge_type', 'pre_fulfillment')
            ->where('status', 'accepted')
            ->latest('id')
            ->first();

        if ($acceptedCharge) {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Pre-fulfillment billing already paid for this order.',
                'data' => [
                    'billing_charge' => $acceptedCharge,
                    'is_paid' => true,
                ],
                'errors' => [],
            ];
        }

        $billingReady = $this->assertMerchantBillingReady($shop);
        $billingConfirmationUrl = data_get($billingReady, 'data.billing_confirmation_url');

        return [
            'success' => false,
            'status' => 402,
            'message' => 'Pre-fulfillment billing is not paid for this order.',
            'data' => [
                'code' => 'BILLING_NOT_PAID',
                'is_paid' => false,
                'billing_confirmation_url' => $billingConfirmationUrl,
            ],
            'errors' => $billingReady['errors'] ?? [],
        ];
    }

    private function calculateFulfillmentChargeBreakdown(Order $order): array
    {
        $order->loadMissing('orderItems');

        $subTotal = 0.0;
        foreach ($order->orderItems as $item) {
            $quantity = (int) ($item->quantity ?? 1);
            if ($quantity < 1) {
                $quantity = 1;
            }

            $sku = trim((string) ($item->sku ?? ''));
            $variantPrice = null;
            if ($sku !== '') {
                $variantPrice = ProductVariant::query()
                    ->where('sku', $sku)
                    ->value('price');
            }

            $unitPrice = is_numeric($variantPrice)
                ? (float) $variantPrice
                : (float) ($item->price ?? 0);

            $subTotal += $unitPrice * $quantity;
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        $shipping = $this->extractShippingAmount($payload);
        $tax = $this->extractTaxAmount($payload);

        $total = $subTotal + $shipping + $tax;
        return [
            'subtotal' => round(max($subTotal, 0), 2),
            'shipping' => round(max($shipping, 0), 2),
            'tax' => round(max($tax, 0), 2),
            'total' => round(max($total, 0), 2),
        ];
    }

    private function extractShippingAmount(array $payload): float
    {
        $shippingLines = data_get($payload, 'shipping_lines', []);
        if (is_array($shippingLines) && !empty($shippingLines)) {
            $sum = collect($shippingLines)->sum(function ($line) {
                return (float) (data_get($line, 'price', data_get($line, 'price_set.shop_money.amount', 0)) ?? 0);
            });
            if ($sum > 0) {
                return (float) $sum;
            }
        }

        return (float) (data_get($payload, 'total_shipping_price_set.shop_money.amount', 0) ?? 0);
    }

    private function extractTaxAmount(array $payload): float
    {
        $totalTax = data_get($payload, 'total_tax');
        if (is_numeric($totalTax)) {
            return (float) $totalTax;
        }

        $taxLines = data_get($payload, 'tax_lines', []);
        if (is_array($taxLines) && !empty($taxLines)) {
            return (float) collect($taxLines)->sum(function ($line) {
                return (float) (data_get($line, 'price', 0) ?? 0);
            });
        }

        return 0.0;
    }

    private function syncStatusesAfterBillingPaid(Order $order): void
    {
        $orderStatus = strtolower((string) ($order->status ?? ''));
        $fulfillmentStatus = strtolower((string) ($order->fulfillment_status ?? ''));
        $blockedOrderStatuses = [
            'billing_pending',
            'billing_required',
            'billing_issue',
            'payment_pending',
            'payment_required',
            'failed',
            'exception',
        ];

        if (in_array($orderStatus, $blockedOrderStatuses, true) || in_array($fulfillmentStatus, $blockedOrderStatuses, true)) {
            $order->update([
                'status' => 'pending',
                'fulfillment_status' => 'pending',
            ]);

            AdminActivityLog::logSystemActivity(
                'Updated Order Status to pending after billing success',
                'Order',
                $order->id
            );
        }

        $jobIdsToReset = Job::query()
            ->where('order_id', $order->id)
            ->whereIn('status', $blockedOrderStatuses)
            ->pluck('id');

        Job::query()
            ->whereIn('id', $jobIdsToReset)
            ->update([
                'status' => 'pending',
                'error_message' => null,
                'failed_at' => null,
            ]);

        foreach ($jobIdsToReset as $jobId) {
            AdminActivityLog::logSystemActivity(
                'Updated Job Status to pending after billing success',
                'Job',
                (int) $jobId
            );
        }
    }

    private function logPrepaymentAcceptedActivity(Order $order, string $usageRecordId = ''): void
    {
        AdminActivityLog::logSystemActivity(
            'Billing prepayment charge accepted for order ' . ($order->order_number ?: $order->id)
                . ' (Usage Billing ID: ' . ($usageRecordId !== '' ? $usageRecordId : 'N/A') . ')',
            'Order',
            $order->id
        );
    }
}

