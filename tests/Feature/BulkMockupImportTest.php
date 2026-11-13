<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PrintArea;
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
            ->assertSee('mockup-zip-review')
            ->assertSee('mockup-zip-occupied-warning')
            ->assertSee('Choose “Replace image” for a slot');

        $product = Product::create([
            'title' => 'Edit Test Tee',
            'status' => 'active',
            'price' => '18.00',
        ]);

        $this->get(route('crm.products.edit', $product->id))
            ->assertOk()
            ->assertSee('Import Mockups ZIP')
            ->assertSee('mockup-zip-import-input')
            ->assertSee('mockup-zip-occupied-warning');

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

    public function test_product_retries_reuse_mockups_and_replace_shared_files_without_duplicate_records(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $this->actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]));

        $printArea = PrintArea::create(['title' => 'Front']);
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=', true);
        $replacement = $pixel . 'replacement';

        $this->post(route('crm.products.store'), [
            'title' => 'Idempotent Tee',
            'status' => 'active',
            'price' => '19.95',
            'images' => [],
            'colors' => ['Test Purple', ' test purple '],
            'sizes' => ['S', 's'],
            'print_area_ids' => [$printArea->id, $printArea->id],
            'color_front' => [
                UploadedFile::fake()->createWithContent('front.png', $pixel),
            ],
            'color_back' => [
                UploadedFile::fake()->createWithContent('back.png', $pixel),
            ],
        ])->assertRedirect(route('crm.products'));

        $product = Product::where('title', 'Idempotent Tee')->firstOrFail();
        $originalFront = $product->color_mockups['test-purple']['front'];
        $oldBack = $product->color_mockups['test-purple']['back'];
        $updateUrl = route('crm.products.update', $product->id);

        $sameImageUpdate = [
            'title' => 'Idempotent Tee',
            'status' => 'active',
            'price' => '19.95',
            'images' => [],
            'colors' => ['Test Purple', ' test purple '],
            'sizes' => ['S', 's'],
            'print_area_ids' => [$printArea->id, $printArea->id],
            'color_front' => [
                UploadedFile::fake()->createWithContent('front-retry.png', $pixel),
            ],
            'color_back_existing' => [$originalFront],
        ];

        $this->post($updateUrl, $sameImageUpdate)->assertRedirect(route('crm.products'));
        $product->refresh();
        $this->assertSame($originalFront, $product->color_mockups['test-purple']['front']);
        $this->assertSame($originalFront, $product->color_mockups['test-purple']['back']);
        $this->assertFalse(Storage::disk('local')->exists($oldBack));
        $this->assertCount(1, Storage::disk('local')->allFiles('assets/customizer/product/' . $product->id));
        $this->assertSame(1, $product->variants()->count());
        $this->assertSame(1, $product->printAreas()->count());
        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('product_print_areas')
                ->where('product_id', $product->id)
                ->where('print_area_id', $printArea->id)
                ->count()
        );

        $this->post($updateUrl, $sameImageUpdate)->assertRedirect(route('crm.products'));
        $product->refresh();
        $this->assertSame($originalFront, $product->color_mockups['test-purple']['front']);
        $this->assertCount(1, Storage::disk('local')->allFiles('assets/customizer/product/' . $product->id));
        $this->assertSame(1, $product->variants()->count());
        $this->assertSame(1, $product->printAreas()->count());

        $this->post($updateUrl, [
            'title' => 'Idempotent Tee',
            'status' => 'active',
            'price' => '19.95',
            'images' => [],
            'colors' => ['Test Purple'],
            'sizes' => ['S'],
            'print_area_ids' => [$printArea->id],
            'color_front' => [
                UploadedFile::fake()->createWithContent('front-replacement.png', $replacement),
            ],
            'color_back_existing' => [$originalFront],
        ])->assertRedirect(route('crm.products'));

        $product->refresh();
        $replacementFront = $product->color_mockups['test-purple']['front'];
        $this->assertNotSame($originalFront, $replacementFront);
        $this->assertSame($originalFront, $product->color_mockups['test-purple']['back']);
        $this->assertTrue(Storage::disk('local')->exists($originalFront));
        $this->assertTrue(Storage::disk('local')->exists($replacementFront));

        $this->post($updateUrl, [
            'title' => 'Idempotent Tee',
            'status' => 'active',
            'price' => '19.95',
            'images' => [],
            'colors' => ['Test Purple'],
            'sizes' => ['S'],
            'print_area_ids' => [$printArea->id],
            'color_front_existing' => [$replacementFront],
            'color_back_remove' => ['1'],
        ])->assertRedirect(route('crm.products'));

        $product->refresh();
        $this->assertFalse(isset($product->color_mockups['test-purple']['back']));
        $this->assertFalse(Storage::disk('local')->exists($originalFront));
        $this->assertTrue(Storage::disk('local')->exists($replacementFront));
        $this->assertCount(1, Storage::disk('local')->allFiles('assets/customizer/product/' . $product->id));
    }
}
