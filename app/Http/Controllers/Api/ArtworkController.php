<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artwork;
use App\Models\Shop;
use App\Services\AppSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArtworkController extends Controller
{
    public function __construct(private AppSignatureVerifier $appSignatureVerifier)
    {
    }

    public function list(Request $request)
    {
        $authResult = $this->resolveAndAuthorizeShop($request);
        if ($authResult['response']) {
            return $authResult['response'];
        }

        /** @var Shop $shop */
        $shop = $authResult['shop'];

        $validated = $request->validate([
            'productKey' => ['nullable', 'string', 'max:120'],
            'placement' => ['nullable', 'string', 'max:80'],
            'colorCode' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['all', 'svg', 'raster'])],
            'sort' => ['nullable', Rule::in(['recent', 'name_asc', 'name_desc'])],
            'cursor' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 60);
        $sort = (string) ($validated['sort'] ?? 'recent');
        $type = (string) ($validated['type'] ?? 'all');

        $query = Artwork::query()->where('shop_id', $shop->id);

        if (!empty($validated['productKey'])) {
            $query->where('product_key', $validated['productKey']);
        }
        if (!empty($validated['placement'])) {
            $query->where('placement', $validated['placement']);
        }
        if (!empty($validated['colorCode'])) {
            $query->where('color_code', $validated['colorCode']);
        }
        if (!empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('url', 'like', '%' . $search . '%')
                    ->orWhere('path', 'like', '%' . $search . '%');
            });
        }

        if ($type === 'svg') {
            $query->where(function ($q) {
                $q->where('mime_type', 'image/svg+xml')
                    ->orWhere('extension', 'svg')
                    ->orWhere('url', 'like', '%.svg');
            });
        } elseif ($type === 'raster') {
            $query->where(function ($q) {
                $q->whereNull('mime_type')
                    ->orWhere('mime_type', '!=', 'image/svg+xml');
            });
        }

        if (!empty($validated['cursor']) && ctype_digit((string) $validated['cursor'])) {
            $query->where('id', '<', (int) $validated['cursor']);
        }

        if ($sort === 'name_asc') {
            $query->orderBy('name', 'asc')->orderByDesc('id');
        } elseif ($sort === 'name_desc') {
            $query->orderBy('name', 'desc')->orderByDesc('id');
        } else {
            $query->orderByDesc('uploaded_at')->orderByDesc('created_at')->orderByDesc('id');
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $items = $rows->take($limit)->values();
        $nextCursor = $hasMore ? (string) optional($items->last())->id : '';

        return response()->json([
            'data' => $items->map(fn (Artwork $artwork) => [
                'id' => (string) $artwork->id,
                'name' => $artwork->name,
                'url' => config('app.cloudfront_url') . '/' . $artwork->path,
                'mimeType' => $artwork->mime_type,
                'source' => $artwork->source,
                'createdAt' => optional($artwork->created_at)?->toISOString(),
            ]),
            'nextCursor' => $nextCursor,
        ]);
    }

    public function upload(Request $request)
    {
        $authResult = $this->resolveAndAuthorizeShop($request);
        if ($authResult['response']) {
            return $authResult['response'];
        }

        /** @var Shop $shop */
        $shop = $authResult['shop'];

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimetypes:image/svg+xml,image/png,image/jpeg,image/webp,image/gif,image/avif,image/bmp,image/tiff'],
            'productKey' => ['nullable', 'string', 'max:120'],
            'placement' => ['nullable', 'string', 'max:80'],
            'colorCode' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', Rule::in(['upload', 'listing', 'library'])],
            'tags' => ['nullable'],
        ]);

        $file = $validated['file'];
        $disk = config('filesystems.default', 'public');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'artwork';
        $safeName = Str::slug($name) ?: 'artwork';

        $path = $file->storeAs(
            'artworks/shop_' . $shop->id,
            now()->format('YmdHis') . '_' . Str::random(8) . '_' . $safeName . ($ext ? ('.' . $ext) : ''),
            $disk
        );

        $url = Storage::disk($disk)->url($path);
        [$width, $height] = $this->tryImageDimensions($file->getPathname());

        $artwork = Artwork::create([
            'shop_id' => $shop->id,
            'product_key' => $validated['productKey'] ?? null,
            'placement' => $validated['placement'] ?? null,
            'color_code' => $validated['colorCode'] ?? null,
            'name' => $file->getClientOriginalName(),
            'source' => $validated['source'] ?? 'upload',
            'mime_type' => $file->getMimeType(),
            'extension' => $ext ?: null,
            'disk' => $disk,
            'path' => $path,
            'url' => $url,
            'file_size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'meta' => [
                'tags' => $validated['tags'] ?? null,
            ],
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => (string) $artwork->id,
                'name' => $artwork->name,
                'url' => config('app.cloudfront_url') . '/' . $artwork->path,
                'mimeType' => $artwork->mime_type,
                'source' => $artwork->source,
                'createdAt' => optional($artwork->created_at)?->toISOString(),
            ],
        ], 201);
    }

    private function resolveAndAuthorizeShop(Request $request): array
    {
        $timestamp = (string) $request->header('X-App-Timestamp', '');
        $signature = (string) $request->header('X-App-Signature', '');

        $isValidSignature = $this->appSignatureVerifier->verify(
            $request,
            $timestamp,
            $signature,
            AppSignatureVerifier::MODE_TIMESTAMP_ONLY
        );

        if (!$isValidSignature) {
            return [
                'shop' => null,
                'response' => response()->json([
                    'ok' => false,
                    'error' => 'Invalid app signature',
                ], 401),
            ];
        }

        $shopDomain = strtolower(trim((string) (
            $request->header('X-Shop')
            ?? $request->query('shop_id')
            ?? $request->query('store')
            ?? $request->input('shop_id')
            ?? $request->input('store')
            ?? ''
        )));

        if ($shopDomain === '') {
            return [
                'shop' => null,
                'response' => response()->json([
                    'ok' => false,
                    'error' => 'Missing shop context',
                ], 422),
            ];
        }

        $shop = Shop::where('shop_domain', $shopDomain)->orderByDesc('id')->first();

        if (!$shop) {
            return [
                'shop' => null,
                'response' => response()->json([
                    'ok' => false,
                    'error' => 'Store does not exist',
                ], 404),
            ];
        }

        return [
            'shop' => $shop,
            'response' => null,
        ];
    }

    private function tryImageDimensions(string $realPath): array
    {
        $width = null;
        $height = null;

        try {
            $size = @getimagesize($realPath);
            if (is_array($size) && isset($size[0], $size[1])) {
                $width = (int) $size[0];
                $height = (int) $size[1];
            }
        } catch (\Throwable) {
            // Keep nullable dimensions for unsupported parsers (e.g. some SVG variants).
        }

        return [$width, $height];
    }
}
