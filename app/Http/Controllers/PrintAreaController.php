<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrintArea;
use App\Helpers\PrintAreaPreviewData;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\CrmController;
use Illuminate\Validation\Rule;

class PrintAreaController extends CrmController
{
    public function __construct()
    {
        view()->share($this->prepareViewData());
    }

    public function index(Request $request)
    {
        $query = PrintArea::query();

        // ================= SEARCH =================
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('tshirt_size', 'like', "%{$search}%");
            });
        }

        // ================= STATUS FILTER =================
        if ($request->status !== null && $request->status !== 'all') {
            $query->where('is_active', $request->status);
        }

        // ================= SORTING =================
        $sortBy  = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');

        $allowedSorts = ['created_at', 'title', 'display_order'];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $query->orderBy($sortBy, $sortDir);

        // ================= PAGINATION =================
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        $printAreas = $query->paginate($perPage)->withQueryString();

        return view('print_areas.index', [
            'printAreas' => $printAreas,
            'filters' => $request->all(),
        ]);
    }

    // Create Form
    public function create()
    {
        return view('print_areas.create', [
            'previewData' => PrintAreaPreviewData::payload(),
        ]);
    }

    // Store
    public function store(Request $request)
    {
        $minWidth = (float) config('crm.features.print_areas.constraints.width.min', 1);
        $maxWidth = (float) config('crm.features.print_areas.constraints.width.max', 1000);
        $minHeight = (float) config('crm.features.print_areas.constraints.height.min', 1);
        $maxHeight = (float) config('crm.features.print_areas.constraints.height.max', 1000);
        $allowedUnits = config('crm.features.print_areas.constraints.allowed_units', ['mm', 'cm', 'in', 'px']);

        $request->validate([
            'print_areas' => 'nullable|array',
            'print_areas.*.title' => 'nullable|string|max:255',
            'print_areas.*.area_width' => "required|numeric|between:{$minWidth},{$maxWidth}",
            'print_areas.*.area_height' => "required|numeric|between:{$minHeight},{$maxHeight}",
            'print_areas.*.unit' => ['required', Rule::in($allowedUnits)],
            'print_areas.*.position_x' => 'nullable|numeric',
            'print_areas.*.position_y' => 'nullable|numeric',
            'print_areas.*.design_position_x' => 'nullable|numeric|min:0|max:' . PrintArea::DESIGN_LOGICAL_SIZE,
            'print_areas.*.design_position_y' => 'nullable|numeric|min:0|max:' . PrintArea::DESIGN_LOGICAL_SIZE,
            'print_areas.*.design_area_width' => 'nullable|numeric|min:' . PrintArea::DESIGN_MIN_EDGE . '|max:' . PrintArea::DESIGN_LOGICAL_SIZE,
            'print_areas.*.design_area_height' => 'nullable|numeric|min:' . PrintArea::DESIGN_MIN_EDGE . '|max:' . PrintArea::DESIGN_LOGICAL_SIZE,
            'print_areas.*.tshirt_size' => 'nullable|string|max:50',
            'print_areas.*.display_order' => 'nullable|integer|min:0',
            'print_areas.*.price' => 'nullable|numeric|min:0',
            'print_areas.*.is_active' => 'nullable|boolean',
            'print_area.*.images' => 'nullable|array',
            'print_area.*.images.*' => 'nullable|image|max:5120',
        ]);

        if ($request->has('print_areas')) {

            foreach ($request->print_areas as $index => $area) {
                // if ($request->hasFile("print_area_images.$index")) {
                //     $image = $request->file("print_area_images.$index")
                //         ->store('print_areas', 'public');
                //     $area['images'] = $image; 
                // }


                PrintArea::create(array_merge($area, PrintArea::normalizeDesignRegion($area)));
            }
        }

        return redirect()->route('crm.print-areas.index')
            ->with('success', 'Print Area Created Successfully');
    }

    // Show
    public function show($id)
    {
        $area = PrintArea::findOrFail($id);
       
        return view('print_areas.show', compact('area'));
    }

    // Edit
    public function edit($id)
    {
        $printArea = PrintArea::withCount('products')->findOrFail($id);

        return view('print_areas.edit', [
            'printArea' => $printArea,
            'previewData' => PrintAreaPreviewData::payload(),
        ]);
    }

    // Update
public function update(Request $request, $id)
{
    $printArea = PrintArea::findOrFail($id);
    $minWidth = (float) config('crm.features.print_areas.constraints.width.min', 1);
    $maxWidth = (float) config('crm.features.print_areas.constraints.width.max', 1000);
    $minHeight = (float) config('crm.features.print_areas.constraints.height.min', 1);
    $maxHeight = (float) config('crm.features.print_areas.constraints.height.max', 1000);
    $allowedUnits = config('crm.features.print_areas.constraints.allowed_units', ['mm', 'cm', 'in', 'px']);

    $request->validate([
        'print_areas.0.title' => 'nullable|string|max:255',
        'print_areas.0.area_width' => "required|numeric|between:{$minWidth},{$maxWidth}",
        'print_areas.0.area_height' => "required|numeric|between:{$minHeight},{$maxHeight}",
        'print_areas.0.unit' => ['required', Rule::in($allowedUnits)],
        'print_areas.0.position_x' => 'nullable|numeric',
        'print_areas.0.position_y' => 'nullable|numeric',
        'print_areas.0.design_position_x' => 'nullable|numeric|min:0|max:' . PrintArea::DESIGN_LOGICAL_SIZE,
        'print_areas.0.design_position_y' => 'nullable|numeric|min:0|max:' . PrintArea::DESIGN_LOGICAL_SIZE,
        'print_areas.0.design_area_width' => 'nullable|numeric|min:' . PrintArea::DESIGN_MIN_EDGE . '|max:' . PrintArea::DESIGN_LOGICAL_SIZE,
        'print_areas.0.design_area_height' => 'nullable|numeric|min:' . PrintArea::DESIGN_MIN_EDGE . '|max:' . PrintArea::DESIGN_LOGICAL_SIZE,
        'print_areas.0.tshirt_size' => 'nullable|string|max:50',
        'print_areas.0.display_order' => 'nullable|integer|min:0',
        'print_areas.0.price' => 'nullable|numeric|min:0',
        'print_areas.0.is_active' => 'nullable|in:0,1',
        'print_area.0.images.0' => 'nullable|image|max:5120',
    ]);

    $areaData = $request->print_areas[0];
    // Only touch the canvas region when the form actually sent it (keeps a saved region intact otherwise).
    if (array_intersect_key($areaData, array_flip(PrintArea::DESIGN_REGION_FIELDS))) {
        $areaData = array_merge($areaData, PrintArea::normalizeDesignRegion($areaData));
    } else {
        $areaData = array_diff_key($areaData, array_flip(PrintArea::DESIGN_REGION_FIELDS));
    }

    // ✅ Handle New Image Upload (ONLY THIS)
    // if ($request->hasFile('print_area_images.0')) {

    //     // Delete old image if exists
    //     if ($printArea->images && Storage::disk('public')->exists($printArea->images)) {
    //         Storage::disk('public')->delete($printArea->images);
    //     }

    //     // Store new image
    //     $areaData['images'] = $request->file('print_area_images.0')
    //         ->store('print_areas', 'public');
    // }

    $printArea->update($areaData);

    return redirect()->route('crm.print-areas.index')
        ->with('success', 'Updated Successfully');
}

    /**
     * Product → placement preview: pick a product, switch between its print areas
     * and adjust each canvas region over the product image before saving.
     */
    public function designer(Request $request)
    {
        $previewData = PrintAreaPreviewData::payload();

        return view('print_areas.designer', [
            'previewData' => $previewData,
            'initialProductId' => $request->integer('product') ?: null,
            'initialPrintAreaId' => $request->integer('area') ?: null,
            'initialColor' => (string) $request->query('color', ''),
        ]);
    }

    /**
     * Save only the customizer canvas region of one print area (JSON, used by the designer page).
     * Sending all four fields empty clears the region so the app derives it again.
     */
    public function updateRegion(Request $request, $id)
    {
        $printArea = PrintArea::withCount('products')->findOrFail($id);
        $size = PrintArea::DESIGN_LOGICAL_SIZE;
        $minEdge = PrintArea::DESIGN_MIN_EDGE;

        $validated = $request->validate([
            'design_position_x' => "nullable|numeric|min:0|max:{$size}",
            'design_position_y' => "nullable|numeric|min:0|max:{$size}",
            'design_area_width' => "nullable|numeric|min:{$minEdge}|max:{$size}",
            'design_area_height' => "nullable|numeric|min:{$minEdge}|max:{$size}",
        ]);

        $region = PrintArea::normalizeDesignRegion($validated);
        $printArea->update($region);
        $printArea->refresh()->loadCount('products');

        return response()->json([
            'success' => true,
            'message' => $printArea->hasDesignRegion()
                ? 'Print area saved.'
                : 'Custom region cleared. The customizer will calculate it from size and position.',
            'print_area' => PrintAreaPreviewData::printArea($printArea),
        ]);
    }

    // Delete
   public function destroy($id)
{
    $printArea = PrintArea::findOrFail($id);

    // // Delete image from storage
    // if ($printArea->images && Storage::disk('public')->exists($printArea->images)) {
    //     Storage::disk('public')->delete($printArea->images);
    // }

    $printArea->delete();

    return redirect()->route('crm.print-areas.index')
        ->with('success', 'Deleted Successfully');
}
}
