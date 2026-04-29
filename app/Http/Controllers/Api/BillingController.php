<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\AppSignatureVerifier;
use App\Services\BillingService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private BillingService $billingService,
        private AppSignatureVerifier $appSignatureVerifier
    ) {
    }

    public function status(Request $request)
    {
        $shop = $this->resolveSignedShop($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found or invalid signature.',
            ], 401);
        }

        $result = $this->billingService->getBillingStatusForShop($shop);
        return response()->json($result, $result['status'] ?? 200);
    }

    public function approve(Request $request)
    {
        $shop = $this->resolveSignedShop($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found or invalid signature.',
            ], 401);
        }

        $returnUrl = $request->input('returnUrl');
        $result = $this->billingService->ensureApprovalUrl($shop, is_string($returnUrl) ? $returnUrl : null);

        return response()->json($result, $result['status'] ?? 200);
    }

    private function resolveSignedShop(Request $request): ?Shop
    {
        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');
        $shopDomain = strtolower(trim((string) (
            $request->header('X-Shop')
            ?? $request->query('shop')
            ?? $request->input('shop')
            ?? ''
        )));

        if ($shopDomain === '') {
            return null;
        }

        if (
            !$this->appSignatureVerifier->verify(
                $request,
                $timestamp,
                $signature,
                AppSignatureVerifier::MODE_TIMESTAMP_PLUS_PAYLOAD_VARIANTS
            )
        ) {
            return null;
        }

        return Shop::where('shop_domain', $shopDomain)->first();
    }
}

