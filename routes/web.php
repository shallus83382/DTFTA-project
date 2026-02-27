<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\UserManagementController;

Route::get('/', [CrmController::class, 'landing']);

Route::get('/login', [CrmController::class, 'landing'])->name('login');
Route::get('/forgot-password', [CrmController::class, 'forgotPassword'])->name('forgot-password');
Route::get('/reset-password', [CrmController::class, 'resetPassword'])->name('reset-password');

Route::post('/auth/login', [LoginController::class, 'login'])->name('auth.login');
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('forgot-password.submit');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('reset-password.submit');

Route::middleware('auth')->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/auth/me', [LoginController::class, 'me'])->name('auth.me');
    Route::post('/auth/change-password', [PasswordResetController::class, 'changePassword'])->name('auth.change-password');

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
    Route::middleware('admin')->group(function () {
        Route::get('/crm/users', [CrmController::class, 'users'])->name('crm.users');
        Route::get('/crm/admin/users', [UserManagementController::class, 'index'])->name('crm.admin.users.index');
        Route::post('/crm/admin/users', [UserManagementController::class, 'store'])->name('crm.admin.users.store');
        Route::get('/crm/admin/users/{user}', [UserManagementController::class, 'show'])->name('crm.admin.users.show');
        Route::put('/crm/admin/users/{user}', [UserManagementController::class, 'update'])->name('crm.admin.users.update');
        Route::delete('/crm/admin/users/{user}', [UserManagementController::class, 'destroy'])->name('crm.admin.users.destroy');
        Route::get('/crm/admin/activity-logs', [UserManagementController::class, 'activityLogs'])->name('crm.admin.activity-logs');
    });
    Route::get('/crm/order/detail/{orderId}', [CrmController::class, 'orderdetails'])->name('crm.order-detail');
    Route::get('/crm/store/detail/{storeId}', [CrmController::class, 'storedetails'])->name('crm.store-detail');
    Route::post('/crm/store/{storeId}/status', [CrmController::class, 'updateStoreStatus'])->name('crm.store.update-status');
    Route::post('/crm/store/{storeId}/partner-profile', [CrmController::class, 'upsertPartnerProfile'])->name('crm.store.upsert-profile');
});
