<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\Api\Auth\PasswordResetController;

// Public landing page / login
Route::get('/', [CrmController::class, 'landing']);

// Login page
Route::get('/login', [CrmController::class, 'landing'])->name('login');

// Forgot password page
Route::get('/forgot-password', [CrmController::class, 'forgotPassword'])->name('forgot-password');
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('forgot-password.submit');

// Reset password page
Route::get('/reset-password', [CrmController::class, 'resetPassword'])->name('reset-password');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('reset-password.submit');

// CRM routes protected using same sanctum auth as API routes.

    Route::get('/crm/dashboard', [CrmController::class, 'dashboard'])->name('crm.dashboard');
    Route::get('/crm/orders', [CrmController::class, 'orders'])->name('crm.orders');
    Route::get('/crm/orders/{jobId}', [CrmController::class, 'jobDetail'])->name('crm.job-detail');
    Route::get('/crm/shipping', [CrmController::class, 'shipping'])->name('crm.shipping');
    Route::get('/crm/shipping/detail/{shipmentId}', [CrmController::class, 'shippingDetail'])->name('crm.shipping-detail');
    Route::get('/crm/stores', [CrmController::class, 'stores'])->name('crm.stores');
    Route::get('/crm/products', [CrmController::class, 'products'])->name('crm.products');
    Route::get('/crm/products/add', [CrmController::class, 'createProduct'])->name('crm.products.create');
    Route::get('/crm/products/{productId}', [CrmController::class, 'viewProduct'])->name('crm.products.view');
    Route::get('/crm/products/{productId}/edit', [CrmController::class, 'editProduct'])->name('crm.products.edit');
    Route::post('/crm/products', [CrmController::class, 'storeProduct'])->name('crm.products.store');
    Route::post('/crm/products/{productId}', [CrmController::class, 'updateProduct'])->name('crm.products.update');
    Route::delete('/crm/products/{productId}', [CrmController::class, 'destroyProduct'])->name('crm.products.destroy');
    Route::get('/crm/reports', [CrmController::class, 'reports'])->name('crm.reports');
    Route::get('/crm/reports/export-csv', [CrmController::class, 'exportReportsCsv'])->name('crm.reports.export-csv');
    Route::get('/crm/settings', [CrmController::class, 'settings'])->name('crm.settings');
    Route::get('/crm/notifications', [CrmController::class, 'notifications'])->name('crm.notifications');
    Route::get('/crm/users', [CrmController::class, 'users'])->name('crm.users');
    Route::get('/crm/order/detail/{orderId}', [CrmController::class, 'orderdetails'])->name('crm.order-detail');
    Route::get('/crm/store/detail/{storeId}', [CrmController::class, 'storedetails'])->name('crm.store-detail');
    Route::post('/crm/store/{storeId}/status', [CrmController::class, 'updateStoreStatus'])->name('crm.store.update-status');
    Route::post('/crm/store/{storeId}/partner-profile', [CrmController::class, 'upsertPartnerProfile'])->name('crm.store.upsert-profile');
