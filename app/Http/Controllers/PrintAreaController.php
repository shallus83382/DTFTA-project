<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrintArea;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\CrmController;

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
        return view('print_areas.create');
    }

    // Store
    public function store(Request $request)
    {

        $request->validate([
            'print_areas' => 'nullable|array',
            'print_areas.*.title' => 'nullable|string|max:255',
            'print_areas.*.area_width' => 'nullable|numeric|min:0',
            'print_areas.*.area_height' => 'nullable|numeric|min:0',
            'print_areas.*.unit' => 'nullable|in:mm,cm,in,px',
            'print_areas.*.position_x' => 'nullable|numeric',
            'print_areas.*.position_y' => 'nullable|numeric',
            'print_areas.*.tshirt_size' => 'nullable|string|max:50',
            'print_areas.*.display_order' => 'nullable|integer|min:0',
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


                PrintArea::create($area);
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
        $printArea = PrintArea::findOrFail($id);
        return view('print_areas.edit', compact('printArea'));
    }

    // Update
public function update(Request $request, $id)
{
    $printArea = PrintArea::findOrFail($id);

    $request->validate([
        'print_areas.0.title' => 'nullable|string|max:255',
        'print_areas.0.area_width' => 'nullable|numeric|min:0',
        'print_areas.0.area_height' => 'nullable|numeric|min:0',
        'print_areas.0.unit' => 'nullable|in:mm,cm,in,px',
        'print_areas.0.position_x' => 'nullable|numeric',
        'print_areas.0.position_y' => 'nullable|numeric',
        'print_areas.0.tshirt_size' => 'nullable|string|max:50',
        'print_areas.0.display_order' => 'nullable|integer|min:0',
        'print_areas.0.is_active' => 'nullable|in:0,1',
        'print_area.0.images.0' => 'nullable|image|max:5120',
    ]);

    $areaData = $request->print_areas[0];

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
