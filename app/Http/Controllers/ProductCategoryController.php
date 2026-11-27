<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductCategoryController extends CrmController
{
    public function __construct()
    {
        view()->share($this->prepareViewData());
    }

    public function index(): View
    {
        return view('crm.product-categories', [
            'categories' => ProductCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('product_categories', 'name')],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $imagePath = $request->file('image')->store('product-category', 's3');
        if ($imagePath === false) {
            return back()->withInput()->withErrors([
                'image' => 'The category image could not be uploaded. Please try again.',
            ]);
        }

        ProductCategory::create([
            'name' => $validated['name'],
            'image_path' => $imagePath,
        ]);

        return redirect()->route('crm.product-categories.index')
            ->with('success', 'Category added successfully.');
    }

    public function update(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('product_categories', 'name')->ignore($productCategory->id),
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('product-category', 's3');
            if ($imagePath === false) {
                return back()->withInput()->withErrors([
                    'image' => 'The category image could not be uploaded. Please try again.',
                ]);
            }
        }

        $previousImagePath = $productCategory->image_path;
        $productCategory->name = $validated['name'];
        if ($imagePath !== null) {
            $productCategory->image_path = $imagePath;
        }
        $productCategory->save();

        if ($imagePath !== null && $previousImagePath) {
            Storage::disk($this->imageDisk($previousImagePath))->delete($previousImagePath);
        }

        return redirect()->route('crm.product-categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $imagePath = $productCategory->image_path;
        $productCategory->delete();

        if ($imagePath) {
            Storage::disk($this->imageDisk($imagePath))->delete($imagePath);
        }

        return redirect()->route('crm.product-categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    private function imageDisk(string $imagePath): string
    {
        return str_starts_with($imagePath, 'product-category/') ? 's3' : 'public';
    }
}
