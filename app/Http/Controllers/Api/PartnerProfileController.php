<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PartnerProfile;

class PartnerProfileController extends Controller
{
    /**
     * POST /api/v1/partner-profiles
     * Creates or updates a partner profile record based on the provided shop_id.
     * Expects JSON body with shop_id, brand_name, return_address_street, return_address_city, return_address_state, return_address_zip, return_address_country, support_email, support_phone.
     */
    public function store(Request $request)
    {
        $profile = PartnerProfile::updateOrCreate(
            ['shop_id' => $request->shop_id],
            $request->only([
                'brand_name',
                'return_address_street',
                'return_address_city',
                'return_address_state',
                'return_address_zip',
                'return_address_country',
                'support_email',
                'support_phone'
            ])
        );
           return response()->json([
            'success' => true,
            'message' => 'Partner profile record created successfully.',
            'data' => $profile
        ], 201);
       
    }
    /**
     * GET /api/v1/partner-profiles/{shop_id}
     * Retrieves a partner profile record by its shop_id.
     */
    public function show($shop_id)
    {
       $data = PartnerProfile::with('shop')->where('shop_id', $shop_id)->first();
       return response()->json([
           'success' => true,
           'message' => 'Partner profile record retrieved successfully.',
           'data' => $data
       ]);
     
    }

    /**
     * DELETE /api/v1/partner-profiles/{shop_id}
     * Deletes a partner profile record by its shop_id.
     */
    public function destroy($shop_id)
    {
        $profile = PartnerProfile::where('shop_id', $shop_id)->first();
        if ($profile) {
            $profile->delete();
            return response()->json([
                'success' => true,
                'message' => 'Partner profile record deleted successfully.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile record not found.'
            ], 404);    
        }
    }

    /** Get all partner profiles */
    public function index()
    {
        $profiles = PartnerProfile::with('shop')->get();
        return response()->json([
            'success' => true,
            'message' => 'Partner profiles retrieved successfully.',
            'data' => $profiles
        ]);
    }
}
