<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Shop;
use App\Services\AppSignatureVerifier;
use App\Services\ShopifyService;

class AuthController extends Controller
{
    public function __construct(
        private ShopifyService $shopifyService,
        private AppSignatureVerifier $appSignatureVerifier
    )
    {
    }

    /**
     * POST /shopify/install
     * Custom signed install payload flow (non-OAuth).
     */
    public function install(Request $request)
    {
        $shop = strtolower(trim((string) (
            $request->header('X-Shop')
            ?? $request->input('shop_domain')
            ?? ''
        )));
        $appTimestamp = (string) $request->header('X-App-Timestamp', '');
        $appSignature = (string) $request->header('X-App-Signature', '');

        if (!$this->isValidShopDomain($shop)) {
            Log::warning('Install rejected: invalid shop domain', [
                'shop' => $shop,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.'
            ], 422);
        }

        $isValidSignature = $this->appSignatureVerifier->verify(
            $request,
            $appTimestamp,
            $appSignature,
            AppSignatureVerifier::MODE_TIMESTAMP_PLUS_PAYLOAD_VARIANTS
        );

        if (!$isValidSignature) {
            Log::warning('Install rejected: invalid app signature', [
                'shop' => $shop,
                'timestamp_present' => $appTimestamp !== '',
                'signature_present' => $appSignature !== '',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid install signature.'
            ], 401);
        }

        Log::info('Install signature verified', [
            'shop' => $shop,
        ]);

        return $this->handleSignedInstallPayload($request, $shop);
    }

    private function handleSignedInstallPayload(Request $request, string $shop)
    {
        $payload = $request->all();
        $token = trim((string) ($payload['shopify_access_token'] ?? ''));
        if ($token === '') {
            Log::warning('Signed install rejected: missing shopify_access_token', [
                'shop' => $shop,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'shopify_access_token is required for signed payload install.'
            ], 422);
        }

        try {
            $versionsToTry = array_values(array_unique(array_filter([
                (string) config('services.shopify.api_version', '2025-10'),
                (string) config('services.shopify.webhook_api_version', '2026-04'),
                '2026-04',
            ])));

            $tokenValid = false;
            $validationErrors = [];
            foreach ($versionsToTry as $version) {
                $tokenValidation = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])
                    ->acceptJson()
                    ->asJson()
                    ->timeout(20)
                    ->post("https://{$shop}/admin/api/{$version}/graphql.json", [
                        'query' => '{ shop { id myshopifyDomain } }',
                    ]);

                if ($tokenValidation->successful()) {
                    $hasShopData = (bool) data_get($tokenValidation->json(), 'data.shop.id');
                    $hasErrors = !empty(data_get($tokenValidation->json(), 'errors', []));
                    if ($hasShopData && !$hasErrors) {
                        $tokenValid = true;
                        break;
                    }
                }

                $validationErrors[] = [
                    'version' => $version,
                    'status' => $tokenValidation->status(),
                    'response' => $tokenValidation->body(),
                ];
            }

            if (!$tokenValid) {
                Log::warning('Signed install rejected: invalid Shopify access token', [
                    'shop' => $shop,
                    'attempts' => $validationErrors,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid shopify_access_token for this shop.',
                    'shop' => $shop,
                ], 422);
            }
        } catch (\Throwable $e) {
            Log::error('Signed install token validation failed with exception', [
                'shop' => $shop,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to validate shopify_access_token at this time.',
                'shop' => $shop,
            ], 502);
        }

        $status = strtolower(trim((string) ($payload['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive', 'suspended', 'uninstalled'], true)) {
            $status = 'active';
        }

        $installedAtInput = trim((string) ($payload['installed_at'] ?? ''));
        $installedAt = now();
        if ($installedAtInput !== '') {
            try {
                $installedAt = \Carbon\Carbon::parse($installedAtInput);
            } catch (\Throwable $e) {
                $installedAt = now();
            }
        }

        $shopData = [
            'store_id' => isset($payload['store_id']) ? (string) $payload['store_id'] : null,
            'name' => $payload['shop_name'] ?? ($payload['name'] ?? null),
            'email' => $payload['shop_email'] ?? ($payload['email'] ?? null),
            'domain' => $payload['domain'] ?? null,
            'shop_owner' => $payload['shop_owner'] ?? null,
            'shopify_access_token' => encrypt($token),
            'shopify_scopes' => (string) ($payload['shopify_scopes'] ?? config('services.shopify.scopes', '')),
            'shopify_api_version' => config('services.shopify.api_version', '2025-10'),
            'shopify_webhook_api_version' => config('services.shopify.webhook_api_version', '2026-04'),
            'status' => $status,
            'installed_at' => $installedAt,
            'uninstalled_at' => null,
        ];

        $shopRecord = null;
        $existingShop = Shop::withTrashed()
            ->where('shop_domain', $shop)
            ->orderByDesc('id')
            ->first();

        if ($existingShop) {
            if ($existingShop->trashed()) {
                $restoreCutoff = now()->subDays(30);
                $deletedAt = $existingShop->deleted_at;
                if ($deletedAt && $deletedAt->gte($restoreCutoff)) {
                    $existingShop->restore();
                    $existingShop->fill($shopData);
                    $existingShop->save();
                    $shopRecord = $existingShop;
                } else {
                    $shopRecord = Shop::create(array_merge(['shop_domain' => $shop], $shopData));
                }
            } else {
                $existingShop->fill($shopData);
                $existingShop->save();
                $shopRecord = $existingShop;
            }
        } else {
            $shopRecord = Shop::create(array_merge(['shop_domain' => $shop], $shopData));
        }

        Log::info('Signed install payload saved shop record', [
            'shop' => $shopRecord->shop_domain,
            'shop_id' => $shopRecord->id,
        ]);

        $fulfillmentProvision = null;
        if (
            $shopRecord->status === 'active'
            && !empty($shopRecord->shopify_access_token)
            && empty($shopRecord->fulfillment_service_id)
        ) {
            $fulfillmentProvision = $this->shopifyService->ensureFulfillmentServiceAndLocation((int) $shopRecord->id, config('app.url'));
            if (!($fulfillmentProvision['success'] ?? false)) {
                Log::warning('Fulfillment provisioning failed after signed payload install', [
                    'shop_id' => $shopRecord->id,
                    'shop_domain' => $shopRecord->shop_domain,
                    'result' => $fulfillmentProvision,
                ]);
            } else {
                $shopRecord = $shopRecord->fresh();
            }
        }

        $webhookProvision = $this->shopifyService->ensureRequiredWebhooks((int) $shopRecord->id, config('app.url'));
        if (!($webhookProvision['success'] ?? false)) {
            Log::warning('Webhook provisioning partially failed after signed payload install', [
                'shop_id' => $shopRecord->id,
                'result' => $webhookProvision,
            ]);
        }

        Log::info('Signed install flow completed', [
            'shop' => $shopRecord->shop_domain,
            'shop_id' => $shopRecord->id,
            'fulfillment_provisioned' => (bool) ($fulfillmentProvision['success'] ?? false),
            'webhooks_provisioned' => (bool) ($webhookProvision['success'] ?? false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Signed install payload processed successfully.',
            'data' => $shopRecord,
            'fulfillment_provisioning' => $fulfillmentProvision,
            'webhook_provisioning' => $webhookProvision,
        ]);
    }
    /**
     * Update user (admin or self)
     */
    private function isValidShopDomain(string $shop): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9\\-]*\\.myshopify\\.com$/i', $shop);
    }
}
