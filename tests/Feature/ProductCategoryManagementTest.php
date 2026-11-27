<?php

namespace Tests\Feature;

use App\Helpers\AssetHelper;
use App\Helpers\PrintAreaPreviewData;
use App\Http\Resources\DtftaProductResource;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\PrintArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_categories_and_upload_images(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('crm.product-categories.index'))
            ->assertOk()
            ->assertSee('Add a category')
            ->assertSeeInOrder(['ID', 'Category name', 'Category image', 'Actions'])
            ->assertSee('open-add-category');

        $this->post(route('crm.product-categories.store'), [
            'name' => 'Bottles',
            'image' => $this->pngUpload('bottles.png'),
        ])->assertRedirect(route('crm.product-categories.index'));

        $category = ProductCategory::query()->where('name', 'Bottles')->firstOrFail();
        $oldImagePath = $category->image_path;
        $this->assertStringStartsWith('product-category/', $oldImagePath);
        Storage::disk('s3')->assertExists($oldImagePath);
        $dynamicAssetKey = 'product-category-image-'.$category->id;
        $this->get(route('crm.products.create'))
            ->assertOk()
            ->assertSee('product-category-'.$category->id)
            ->assertSee('Bottles');
        $this->get(url('/asset-categories/product-category-'.$category->id.'/assets'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assets.'.$dynamicAssetKey.'.name', 'Bottles');

        $product = Product::create([
            'title' => 'Bottle Product',
            'status' => 'active',
            'price' => '10.00',
        ]);
        $printArea = PrintArea::create([
            'title' => 'Front Chest',
            'images' => [
                'category' => 'product-category-'.$category->id,
                'asset_key' => $dynamicAssetKey,
            ],
        ]);
        $product->printAreas()->attach($printArea->id);

        $serializedProduct = (new DtftaProductResource($product->load('printAreas')))->resolve(request());
        $this->assertSame(
            Storage::disk('s3')->url($oldImagePath),
            $serializedProduct['print_areas'][0]['image_url']
        );
        $this->assertSame(
            Storage::disk('s3')->url($oldImagePath),
            PrintAreaPreviewData::assets()[$dynamicAssetKey]['primary']
        );

        $this->get(route('crm.product-categories.index'))
            ->assertOk()
            ->assertSee('Bottles')
            ->assertSee('Edit Category')
            ->assertSee('Delete Category');

        $this->put(route('crm.product-categories.update', $category), [
            'name' => 'Sippers',
            'image' => $this->pngUpload('sippers.png'),
        ])->assertRedirect(route('crm.product-categories.index'));

        $category->refresh();
        $this->assertSame('Sippers', $category->name);
        Storage::disk('s3')->assertMissing($oldImagePath);
        Storage::disk('s3')->assertExists($category->image_path);

        $this->get(route('crm.products.create'))
            ->assertOk()
            ->assertSee('Sippers');

        $this->delete(route('crm.product-categories.destroy', $category))
            ->assertRedirect(route('crm.product-categories.index'));

        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
        Storage::disk('s3')->assertMissing($category->image_path);
    }

    public function test_category_image_must_be_an_allowed_image(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $this->actingAs($user)
            ->from(route('crm.product-categories.index'))
            ->post(route('crm.product-categories.store'), [
                'name' => 'Bags',
                'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('crm.product-categories.index'))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('product_categories', ['name' => 'Bags']);
    }

    private function pngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/r5sAAAAASUVORK5CYII=')
        );
    }
}
