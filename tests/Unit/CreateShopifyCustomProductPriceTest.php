<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CreateShopifyCustomProductService;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class CreateShopifyCustomProductPriceTest extends TestCase
{
    public function test_retail_price_only_overrides_the_selected_color_and_size(): void
    {
        $variants = collect([
            $this->variant(1, 'Black', 'S', '12.00'),
            $this->variant(2, 'Black', 'M', '13.00'),
            $this->variant(3, 'White', 'M', '14.00'),
        ]);
        $context = $this->buildPayloadContext([
            'selectedColor' => 'black',
            'selectedSize' => 'M',
            'retailPrice' => '24.99',
        ], $variants);

        $pricesByVariant = collect($context['variants'])
            ->mapWithKeys(fn (array $variant) => [$variant['db_variant_id'] => $variant['price']]);

        $this->assertSame('12.00', (string) $pricesByVariant[1]);
        $this->assertSame('24.99', $pricesByVariant[2]);
        $this->assertSame('14.00', (string) $pricesByVariant[3]);
    }

    public function test_retail_price_rejects_a_color_and_size_not_in_the_product(): void
    {
        $this->expectException(ValidationException::class);

        $this->buildPayloadContext([
            'selectedColor' => 'black',
            'selectedSize' => 'XL',
            'retailPrice' => '24.99',
        ], collect([$this->variant(1, 'Black', 'M', '13.00')]));
    }

    private function buildPayloadContext(array $validated, Collection $variants): array
    {
        $product = new Product;
        $product->id = 10;
        $product->title = 'Test shirt';
        $product->price = '12.00';
        $product->setRelation('variants', $variants);
        $product->setRelation('printAreas', collect());

        $method = new ReflectionMethod(
            CreateShopifyCustomProductService::class,
            'buildShopifyPayloadContext'
        );
        $method->setAccessible(true);

        return $method->invoke(
            new CreateShopifyCustomProductService(new ShopifyService),
            Request::create('/', 'POST', ['artworkUrls' => []]),
            $validated,
            $product,
            1,
            $variants
        );
    }

    private function variant(int $id, string $color, string $size, string $price): ProductVariant
    {
        $variant = new ProductVariant([
            'color' => $color,
            'size' => $size,
            'price' => $price,
            'sku' => "TEST-{$color}-{$size}",
        ]);
        $variant->id = $id;

        return $variant;
    }
}
