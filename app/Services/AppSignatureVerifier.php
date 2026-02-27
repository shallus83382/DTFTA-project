<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppSignatureVerifier
{
    public const MODE_TIMESTAMP_ONLY = 'timestamp_only';
    public const MODE_TIMESTAMP_PLUS_PAYLOAD_VARIANTS = 'timestamp_plus_payload_variants';

    /**
     * Verify app signature using selected mode.
     */
    public function verify(Request $request, string $timestamp, string $signature, string $mode = self::MODE_TIMESTAMP_ONLY): bool
    {
        $timestamp = trim($timestamp);
        $signature = strtolower(trim($signature));

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        if (!ctype_digit($timestamp)) {
            return false;
        }

        $sharedSecret = (string) (config('services.shopify.webhook_secret', config('services.shopify.api_secret', '')));
        if ($sharedSecret === '') {
            Log::error('App signature verification failed: missing shared secret');
            return false;
        }

        if ($mode === self::MODE_TIMESTAMP_PLUS_PAYLOAD_VARIANTS) {
            return $this->verifyWithPayloadVariants($request, $timestamp, $signature, $sharedSecret);
        }

        $expected = hash_hmac('sha256', $timestamp, $sharedSecret);
        return hash_equals($expected, $signature);
    }

    private function verifyWithPayloadVariants(Request $request, string $timestamp, string $signature, string $sharedSecret): bool
    {
        $rawPayload = (string) $request->getContent();
        $payloadVariants = $this->buildPayloadVariants($request, $rawPayload);

        foreach ($payloadVariants as $payloadVariant) {
            $expected = hash_hmac('sha256', $timestamp . $payloadVariant, $sharedSecret);
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function buildPayloadVariants(Request $request, string $rawPayload): array
    {
        $variants = [
            'raw_payload' => $rawPayload,
        ];

        $decoded = json_decode($rawPayload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $frontendStyle = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($frontendStyle)) {
                $variants['frontend_json_stringify'] = $frontendStyle;
            }

            $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($pretty)) {
                $variants['pretty_json'] = $pretty;
            }

            $sorted = $this->sortArrayRecursively($decoded);
            $sortedMinified = json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($sortedMinified)) {
                $variants['sorted_minified_json'] = $sortedMinified;
            }

            $sortedPretty = json_encode($sorted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($sortedPretty)) {
                $variants['sorted_pretty_json'] = $sortedPretty;
            }
        }

        $all = $request->all();
        $encodedAll = json_encode($all, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($encodedAll)) {
            $variants['request_all_json'] = $encodedAll;
        }

        return $variants;
    }

    private function sortArrayRecursively(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sortArrayRecursively($value);
            }
        }

        if ($this->isAssociativeArray($data)) {
            ksort($data);
        }

        return $data;
    }

    private function isAssociativeArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}

