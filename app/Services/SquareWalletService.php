<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopPaymentCard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Square\Cards\Requests\CreateCardRequest;
use Square\Cards\Requests\DisableCardsRequest;
use Square\Customers\Requests\CreateCustomerRequest;
use Square\Environments;
use Square\Exceptions\SquareApiException;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\SquareClient;
use Square\Types\Card;
use Square\Types\Money;

class SquareWalletService
{
    private ?SquareClient $client = null;

    public function isConfigured(): bool
    {
        return trim((string) config('services.square.access_token', '')) !== '';
    }

    public function isWalletBillingEnabled(): bool
    {
        return (bool) config('services.square.wallet_billing_enabled', true);
    }

    /**
     * Shop is linked to Square (customer id on shops table).
     */
    public function shopHasSquareCustomer(Shop $shop): bool
    {
        return trim((string) $shop->square_customer_id) !== '';
    }

    /**
     * Billing "active" for UI/status: shop has square_customer_id AND at least one
     * active card in shop_payment_cards for that customer.
     */
    public function shopHasActiveWalletBilling(Shop $shop): bool
    {
        if (!$this->isWalletBillingEnabled()) {
            return false;
        }

        $squareCustomerId = trim((string) $shop->square_customer_id);
        if ($squareCustomerId === '') {
            return false;
        }

        return ShopPaymentCard::query()
            ->where('shop_id', $shop->id)
            ->where('square_customer_id', $squareCustomerId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Ready to charge orders: square customer on shop + default active card on file.
     */
    public function shopHasChargeableCard(Shop $shop): bool
    {
        if (!$this->isWalletBillingEnabled() || !$this->isConfigured()) {
            return false;
        }

        $squareCustomerId = trim((string) $shop->square_customer_id);
        if ($squareCustomerId === '') {
            return false;
        }

        return ShopPaymentCard::query()
            ->where('shop_id', $shop->id)
            ->where('square_customer_id', $squareCustomerId)
            ->where('status', 'active')
            ->where('is_default', true)
            ->exists();
    }

    /**
     * @return array{success: bool, status: int, message: string, data: array<string, mixed>, errors: array<int, mixed>}
     */
    public function listCardsForShop(Shop $shop): array
    {
        $cards = ShopPaymentCard::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ShopPaymentCard $card) => $card->toWalletArray())
            ->values()
            ->all();

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Saved cards loaded.',
            'data' => ['cards' => $cards],
            'errors' => [],
        ];
    }

    /**
     * @return array{success: bool, status: int, message: string, data: array<string, mixed>, errors: array<int, mixed>}
     */
    public function saveCardFromToken(Shop $shop, string $sourceId): array
    {
        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            return $this->errorResponse('Missing card token from Square.', 400);
        }

        if (!$this->isConfigured()) {
            return $this->errorResponse('Square is not configured on the server.', 503);
        }

        try {
            $customerId = $this->getOrCreateSquareCustomerId($shop);
            $idempotencyKey = Str::uuid()->toString();

            $response = $this->client()->cards->create(
                new CreateCardRequest([
                    'idempotencyKey' => $idempotencyKey,
                    'sourceId' => $sourceId,
                    'card' => new Card(['customerId' => $customerId]),
                ]),
            );

            $errors = $response->getErrors();
            if (!empty($errors)) {
                return $this->errorResponse(
                    $this->formatSquareErrors($errors),
                    422,
                    ['square_errors' => $errors],
                );
            }

            $squareCard = $response->getCard();
            if (!$squareCard || !$squareCard->getId()) {
                return $this->errorResponse('Square did not return a saved card.', 422);
            }

            $isFirstCard = !ShopPaymentCard::query()
                ->where('shop_id', $shop->id)
                ->where('status', 'active')
                ->exists();

            if ($isFirstCard) {
                ShopPaymentCard::query()
                    ->where('shop_id', $shop->id)
                    ->update(['is_default' => false]);
            }

            $record = ShopPaymentCard::create([
                'shop_id' => $shop->id,
                'square_customer_id' => $customerId,
                'square_card_id' => (string) $squareCard->getId(),
                'card_brand' => $squareCard->getCardBrand() ?: 'Card',
                'last4' => $squareCard->getLast4() ?: '0000',
                'exp_month' => $squareCard->getExpMonth(),
                'exp_year' => $squareCard->getExpYear(),
                'is_default' => $isFirstCard,
                'status' => 'active',
                'meta' => [
                    'fingerprint' => $squareCard->getFingerprint(),
                ],
            ]);

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Card saved successfully.',
                'data' => ['card' => $record->toWalletArray()],
                'errors' => [],
            ];
        } catch (SquareApiException $e) {
            Log::warning('Square save card failed', [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
                'body' => $e->getBody(),
            ]);

            return $this->errorResponse($e->getMessage() ?: 'Failed to save card with Square.', 422);
        } catch (\Throwable $e) {
            Log::error('Square save card unexpected error', [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse('Unexpected error while saving card.', 500);
        }
    }

    /**
     * @return array{success: bool, status: int, message: string, data: array<string, mixed>, errors: array<int, mixed>}
     */
    public function chargeCard(
        Shop $shop,
        ShopPaymentCard $card,
        float $amount,
        string $currency,
        string $idempotencyKey,
        string $note,
    ): array {
        if (!$this->isConfigured()) {
            return $this->errorResponse('Square is not configured on the server.', 503);
        }

        $amountCents = (int) round($amount * 100);
        if ($amountCents <= 0) {
            return $this->errorResponse('Charge amount must be greater than zero.', 422);
        }

        $locationId = trim((string) config('services.square.location_id', ''));
        if ($locationId === '') {
            return $this->errorResponse('Square location id is not configured.', 503);
        }

        try {
            $requestData = [
                'sourceId' => $card->square_card_id,
                'idempotencyKey' => $idempotencyKey,
                'amountMoney' => new Money([
                    'amount' => $amountCents,
                    'currency' => strtoupper($currency),
                ]),
                'locationId' => $locationId,
                'customerId' => $card->square_customer_id,
                'note' => $note,
            ];

            $response = $this->client()->payments->create(
                new CreatePaymentRequest($requestData),
            );

            $errors = $response->getErrors();
            if (!empty($errors)) {
                return $this->errorResponse(
                    $this->formatSquareErrors($errors),
                    422,
                    ['square_errors' => $errors],
                );
            }

            $payment = $response->getPayment();
            if (!$payment || !$payment->getId()) {
                return $this->errorResponse('Square did not return a payment id.', 422);
            }

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Payment accepted.',
                'data' => [
                    'square_payment_id' => (string) $payment->getId(),
                    'payment_status' => $payment->getStatus(),
                ],
                'errors' => [],
            ];
        } catch (SquareApiException $e) {
            Log::warning('Square wallet charge failed', [
                'shop_id' => $shop->id,
                'card_id' => $card->id,
                'message' => $e->getMessage(),
                'body' => $e->getBody(),
            ]);

            return $this->errorResponse($e->getMessage() ?: 'Failed to charge card with Square.', 422);
        } catch (\Throwable $e) {
            Log::error('Square wallet charge unexpected error', [
                'shop_id' => $shop->id,
                'card_id' => $card->id,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse('Unexpected error while charging card.', 500);
        }
    }

    /**
     * Disable the card in Square (by square_card_id), then delete the local row.
     *
     * @return array{success: bool, status: int, message: string, data: array<string, mixed>, errors: array<int, mixed>}
     */
    public function deleteCardForShop(Shop $shop, int $cardId): array
    {
        $squareCustomerId = trim((string) $shop->square_customer_id);

        $card = ShopPaymentCard::query()
            ->where('shop_id', $shop->id)
            ->where('id', $cardId)
            ->where('status', 'active')
            ->when(
                $squareCustomerId !== '',
                fn ($query) => $query->where('square_customer_id', $squareCustomerId)
            )
            ->first();

        if (!$card) {
            return $this->errorResponse('Card not found.', 404);
        }

        $squareCardId = trim((string) $card->square_card_id);
        if ($squareCardId === '') {
            return $this->errorResponse('Card is missing a Square card id.', 422);
        }

        if ($this->isConfigured()) {
            try {
                $response = $this->client()->cards->disable(
                    new DisableCardsRequest(['cardId' => $squareCardId]),
                );

                $errors = $response->getErrors();
                if (!empty($errors)) {
                    return $this->errorResponse(
                        $this->formatSquareErrors($errors),
                        422,
                        ['square_errors' => $errors],
                    );
                }
            } catch (SquareApiException $e) {
                Log::warning('Square disable card failed', [
                    'shop_id' => $shop->id,
                    'card_id' => $card->id,
                    'square_card_id' => $squareCardId,
                    'message' => $e->getMessage(),
                    'body' => $e->getBody(),
                ]);

                return $this->errorResponse($e->getMessage() ?: 'Failed to remove card with Square.', 422);
            } catch (\Throwable $e) {
                Log::error('Square disable card unexpected error', [
                    'shop_id' => $shop->id,
                    'card_id' => $card->id,
                    'square_card_id' => $squareCardId,
                    'message' => $e->getMessage(),
                ]);

                return $this->errorResponse('Unexpected error while removing card.', 500);
            }
        }

        $wasDefault = (bool) $card->is_default;
        $localCardId = (int) $card->id;

        ShopPaymentCard::query()
            ->where('shop_id', $shop->id)
            ->where('id', $localCardId)
            ->where('square_card_id', $squareCardId)
            ->delete();

        if ($wasDefault) {
            $nextDefault = ShopPaymentCard::query()
                ->where('shop_id', $shop->id)
                ->where('status', 'active')
                ->when(
                    $squareCustomerId !== '',
                    fn ($query) => $query->where('square_customer_id', $squareCustomerId)
                )
                ->latest('id')
                ->first();

            if ($nextDefault) {
                ShopPaymentCard::query()
                    ->where('shop_id', $shop->id)
                    ->where('status', 'active')
                    ->update(['is_default' => false]);

                $nextDefault->update(['is_default' => true]);
            }
        }

        return $this->listCardsForShop($shop);
    }

    public function getDefaultCard(Shop $shop): ?ShopPaymentCard
    {
        return ShopPaymentCard::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'active')
            ->where('is_default', true)
            ->latest('id')
            ->first();
    }

    private function getOrCreateSquareCustomerId(Shop $shop): string
    {
        $existing = trim((string) $shop->square_customer_id);
        if ($existing !== '') {
            return $existing;
        }

        $referenceId = 'shop_' . $shop->id;
        $response = $this->client()->customers->create(
            new CreateCustomerRequest([
                'idempotencyKey' => Str::uuid()->toString(),
                'referenceId' => $referenceId,
                'companyName' => $shop->name ?: $shop->shop_domain,
                'emailAddress' => $shop->email,
            ]),
        );

        $errors = $response->getErrors();
        if (!empty($errors)) {
            throw new \RuntimeException($this->formatSquareErrors($errors));
        }

        $customer = $response->getCustomer();
        if (!$customer || !$customer->getId()) {
            throw new \RuntimeException('Square did not return a customer id.');
        }

        $customerId = (string) $customer->getId();
        $shop->update(['square_customer_id' => $customerId]);

        return $customerId;
    }

    private function client(): SquareClient
    {
        if ($this->client) {
            return $this->client;
        }

        $token = (string) config('services.square.access_token', '');
        $environment = strtolower((string) config('services.square.environment', 'sandbox'));
        $baseUrl = $environment === 'production'
            ? Environments::Production->value
            : Environments::Sandbox->value;

        $this->client = new SquareClient($token, null, ['baseUrl' => $baseUrl]);

        return $this->client;
    }

    /**
     * @param array<int, mixed> $errors
     */
    private function formatSquareErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $error) {
            if (is_object($error) && method_exists($error, 'getDetail') && $error->getDetail()) {
                $messages[] = (string) $error->getDetail();
            } elseif (is_object($error) && method_exists($error, 'getCode') && $error->getCode()) {
                $messages[] = (string) $error->getCode();
            }
        }

        return $messages !== [] ? implode(' ', $messages) : 'Square request failed.';
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, status: int, message: string, data: array<string, mixed>, errors: array<int, mixed>}
     */
    private function errorResponse(string $message, int $status, array $data = []): array
    {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'errors' => [],
        ];
    }
}
