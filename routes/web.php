<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Shopify\AuthController as ShopifyAuthController;
use App\Http\Controllers\CrmController;

// Public landing page / login
Route::get('/', [CrmController::class, 'landing']);

// Login page
Route::get('/login', [CrmController::class, 'landing'])->name('login');

// Shopify custom signed install
Route::post('/shopify/install', [ShopifyAuthController::class, 'install'])->name('shopify.install');

// CRM routes (client-side authentication via localStorage token)
Route::get('/crm/dashboard', [CrmController::class, 'dashboard'])->name('crm.dashboard');
Route::get('/crm/orders', [CrmController::class, 'orders'])->name('crm.orders');
Route::get('/crm/orders/{jobId}', [CrmController::class, 'jobDetail'])->name('crm.job-detail');
Route::get('/crm/shipping', [CrmController::class, 'shipping'])->name('crm.shipping');
Route::get('/crm/shipping/detail/{shipmentId}', [CrmController::class, 'shippingDetail'])->name('crm.shipping-detail');
Route::get('/crm/stores', [CrmController::class, 'stores'])->name('crm.stores');
Route::get('/crm/reports', [CrmController::class, 'reports'])->name('crm.reports');
Route::get('/crm/reports/export-csv', [CrmController::class, 'exportReportsCsv'])->name('crm.reports.export-csv');
Route::get('/crm/settings', [CrmController::class, 'settings'])->name('crm.settings');
Route::get('/crm/notifications', [CrmController::class, 'notifications'])->name('crm.notifications');
Route::get('/crm/users', [CrmController::class, 'users'])->name('crm.users');
Route::get('/crm/order/detail/{orderId}', [CrmController::class, 'orderdetails'])->name('crm.order-detail');
Route::get('/crm/store/detail/{storeId}', [CrmController::class, 'storedetails'])->name('crm.store-detail');
Route::post('/crm/store/{storeId}/status', [CrmController::class, 'updateStoreStatus'])->name('crm.store.update-status');
Route::post('/crm/store/{storeId}/partner-profile', [CrmController::class, 'upsertPartnerProfile'])->name('crm.store.upsert-profile');
