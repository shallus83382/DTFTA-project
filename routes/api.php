<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\PartnerProfileController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::prefix('v1')->group(function () {
    // Shop routes
    Route::post('/shops', [ShopController::class, 'store']);
    Route::get('/shops/{shop_domain}', [ShopController::class, 'show']);
    Route::get('/shops', [ShopController::class, 'index']);
    Route::delete('/shops/{shop_domain}', [ShopController::class, 'destroy']);

    // Partner profile routes
    Route::post('/partner-profiles', [PartnerProfileController::class, 'store']);
    Route::get('/partner-profiles/{shop_id}', [PartnerProfileController::class, 'show']);
    Route::delete('/profiles/{shop_id}', [PartnerProfileController::class, 'destroy']);
    Route::get('/partner-profiles', [PartnerProfileController::class, 'index']);
});
