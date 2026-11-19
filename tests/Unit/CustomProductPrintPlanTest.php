<?php

namespace Tests\Unit;

use App\Models\CustomProduct;
use App\Services\CreateShopifyCustomProductService;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class CustomProductPrintPlanTest extends TestCase
{
    public function test_multi_placement_print_plan_is_stored_as_json(): void
    {
        $plan = 'UPPER CHEST:10x12|FRONT:12x16|BACK:12x16|LEFT SLEEVE:3x3|RIGHT SLEEVE:3x3';

        $product = new CustomProduct;
        $product->print_plan = $plan;

        $stored = $product->getAttributes()['print_plan'];

        $this->assertIsString($stored);
        $this->assertSame($plan, json_decode($stored, true));
        $this->assertSame($plan, $product->print_plan);
    }

    public function test_empty_print_plan_is_not_written_as_invalid_json_text(): void
    {
        $product = new CustomProduct;
        $product->print_plan = null;

        $this->assertNull($product->print_plan);
    }

    public function test_wrapped_base64_artwork_from_several_placements_decodes(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $wrapped = substr($png, 0, 24)."\n".substr($png, 24);
        $dataUrl = 'data:image/png;base64,'.$wrapped;

        $service = new CreateShopifyCustomProductService(new ShopifyService);
        $method = new ReflectionMethod($service, 'storeBase64Artwork');

        $url = $method->invoke($service, $dataUrl, 5, 1, 'upper chest', 0);

        $this->assertIsString($url);
        $this->assertNotSame('', $url);
        Storage::disk('local')->assertExists(
            collect(Storage::disk('local')->allFiles())->first()
        );
    }
}
