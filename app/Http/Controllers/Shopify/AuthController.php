<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Shop;
use App\Services\ShopifyService;

class AuthController extends Controller
{
    public function __construct(private ShopifyService $shopifyService)
    {
    }

    /**
     * GET /shopify/install?shop={shop}.myshopify.com
     */
    public function install(Request $request)
    {
        $shop = strtolower(trim((string) $request->query('shop')));
        if (!$this->isValidShopDomain($shop)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid shop domain.'
            ], 422);
        }

        $clientId = config('services.shopify.api_key');
        if (!$clientId) {
            return response()->json([
                'success' => false,
                'message' => 'Shopify API key is not configured.'
            ], 500);
        }

        $state = bin2hex(random_bytes(16));
        Cache::put('shopify_oauth_state_' . $shop, $state, now()->addMinutes(10));

        $redirectUrl = "https://{$shop}/admin/oauth/authorize?" . http_build_query([
            'client_id' => $clientId,
            'scope' => config('services.shopify.scopes', ''),
            'redirect_uri' => route('shopify.callback'),
            'state' => $state,
        ]);

        return redirect($redirectUrl);
    }

    /**
     * GET /shopify/callback
     */
    public function callback(Request $request)
    {
        $shop = strtolower(trim((string) $request->query('shop')));
        $code = (string) $request->query('code');
        $state = (string) $request->query('state');
        $hmac = (string) $request->query('hmac');

        if (!$this->isValidShopDomain($shop) || $code === '' || $state === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing required OAuth parameters.'
            ], 422);
        }

        $expectedState = Cache::pull('shopify_oauth_state_' . $shop);
        if (!$expectedState || !hash_equals($expectedState, $state)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OAuth state.'
            ], 401);
        }

        if (!$this->verifyOAuthHmac($request, $hmac)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OAuth signature.'
            ], 401);
        }

        // Exchange the authorization code for a permanent access token
        $response = Http::post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('services.shopify.api_key'),
            'client_secret' => config('services.shopify.api_secret'),
            'code' => $code,
        ]);

        if (!$response->successful() || empty($response->json('access_token'))) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to exchange OAuth code.',
                'error' => $response->json()
            ], 400);
        }

        $accessToken = (string) $response->json('access_token');
        $grantedScopes = (string) $response->json('scope', '');

        $shop = Shop::updateOrCreate(
            ['shop_domain' => $shop],
            [
                'shopify_access_token' => encrypt($accessToken),
                'shopify_scopes' => $grantedScopes,
                'shopify_api_version' => config('services.shopify.api_version', '2025-10'),
                'status' => 'active',
                'installed_at' => now(),
                'uninstalled_at' => null,
            ]
        );

        try {
            $provision = $this->shopifyService->provisionShopOnInstall((int) $shop->id, config('app.url'));
            if (!$provision['success']) {
                Log::warning('Shop provisioning partially failed', [
                    'shop_id' => $shop->id,
                    'result' => $provision
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Shop provisioning failed', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect('/crm/stores')->with('success', 'Shopify app installed and provisioned successfully.');
    }
    /**
     * Update user (admin or self)
     */
    private function isValidShopDomain(string $shop): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9\\-]*\\.myshopify\\.com$/i', $shop);
    }
    /**
     * Show user details (admin only, or own profile) 
     * This method is not used in the current flow but can be useful for future extensions where we want to display shop details after installation or in a settings page.
     */
    private function verifyOAuthHmac(Request $request, string $hmac): bool
    {
        $secret = (string) config('services.shopify.api_secret', '');
        if ($secret === '' || $hmac === '') {
            return false;
        }

        $query = $request->query();
        unset($query['hmac'], $query['signature']);
        ksort($query);

        $message = collect($query)
            ->map(function ($value, $key) {
                return $key . '=' . (is_array($value) ? implode(',', $value) : $value);
            })
            ->implode('&');

        $calculated = hash_hmac('sha256', $message, $secret);
        return hash_equals($calculated, $hmac);
    }
}
