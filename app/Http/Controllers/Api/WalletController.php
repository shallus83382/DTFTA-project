<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\AppSignatureVerifier;
use App\Services\SquareWalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private SquareWalletService $squareWalletService,
        private AppSignatureVerifier $appSignatureVerifier,
    ) {
    }

    public function index(Request $request)
    {
        $shop = $this->resolveSignedShop($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found or invalid signature.',
            ], 401);
        }

        $result = $this->squareWalletService->listCardsForShop($shop);

        return response()->json($result, $result['status'] ?? 200);
    }

    public function store(Request $request)
    {
        $shop = $this->resolveSignedShop($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found or invalid signature.',
            ], 401);
        }

        $sourceId = trim((string) ($request->input('sourceId') ?? $request->input('source_id') ?? ''));
        $result = $this->squareWalletService->saveCardFromToken($shop, $sourceId);

        return response()->json($result, $result['status'] ?? 200);
    }

    public function destroy(Request $request, int $card)
    {
        $shop = $this->resolveSignedShop($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found or invalid signature.',
            ], 401);
        }

        $cardId = (int) ($request->input('cardId') ?: $card);
        $result = $this->squareWalletService->deleteCardForShop($shop, $cardId);

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
