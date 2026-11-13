<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkMockupImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_and_edit_product_forms_offer_zip_import_inside_color_mockups(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]));

        $this->get(route('crm.products.create'))
            ->assertOk()
            ->assertSee('Import Mockups ZIP')
            ->assertSee('mockup-zip-import-input')
            ->assertSee('mockup-zip-review');

        $product = Product::create([
            'title' => 'Edit Test Tee',
            'status' => 'active',
            'price' => '18.00',
        ]);

        $this->get(route('crm.products.edit', $product->id))
            ->assertOk()
            ->assertSee('Import Mockups ZIP')
            ->assertSee('mockup-zip-import-input');

        $this->get(route('crm.products'))
            ->assertOk()
            ->assertDontSee('Import Mockups ZIP');
        $this->assertSame(404, $this->get('/crm/products/mockup-import')->status());
    }

    public function test_color_name_swatch_and_zip_images_save_with_normal_product_form(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $this->actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]));

        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=', true);
        $response = $this->post(route('crm.products.store'), [
            'title' => 'ZIP Imported Tee',
            'brand' => 'Example',
            'model_code' => 'T123',
            'category' => 'T-Shirt',
            'status' => 'active',
            'price' => '19.95',
            'images' => [],
            'colors' => ['Test Purple'],
            'color_hex' => ['#663399'],
            'sizes' => ['S', 'M'],
            'color_front' => [
                UploadedFile::fake()->createWithContent('Test_Purple_Front.png', $pixel),
            ],
            'color_back' => [
                UploadedFile::fake()->createWithContent('Test_Purple_Back.png', $pixel),
            ],
        ]);

        $response->assertRedirect(route('crm.products'));
        $product = Product::where('title', 'ZIP Imported Tee')->firstOrFail();
        $mockup = $product->color_mockups['test-purple'];

        $this->assertSame('Test Purple', $mockup['name']);
        $this->assertSame('#663399', $mockup['hex']);
        $this->assertSame($pixel, Storage::disk('local')->get($mockup['front']));
        $this->assertSame($pixel, Storage::disk('local')->get($mockup['back']));
        $this->assertSame(2, $product->variants()->count());

        $editResponse = $this->post(route('crm.products.update', $product->id), [
            'title' => 'ZIP Imported Tee',
            'brand' => 'Example',
            'model_code' => 'T123',
            'category' => 'T-Shirt',
            'status' => 'active',
            'price' => '19.95',
            'images' => [],
            'colors' => ['Test Purple'],
            'color_hex' => ['#663399'],
            'sizes' => ['S', 'M'],
            'color_right_sleeve' => [
                UploadedFile::fake()->createWithContent('Test_Purple_Right_Sleeve.png', $pixel),
            ],
        ]);

        $editResponse->assertRedirect(route('crm.products'));
        $product->refresh();
        $this->assertSame('#663399', $product->color_mockups['test-purple']['hex']);
        $this->assertSame(
            $pixel,
            Storage::disk('local')->get($product->color_mockups['test-purple']['right_sleeve'])
        );
    }
}
