<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Shopify\WebhookController;
use App\Http\Controllers\CrmController;

// Public landing page / login
Route::get('/', [CrmController::class, 'landing']);

// Login page
Route::get('/login', [CrmController::class, 'landing'])->name('login');

// CRM routes (client-side authentication via localStorage token)
Route::get('/crm/dashboard', [CrmController::class, 'dashboard'])->name('crm.dashboard');
Route::get('/crm/orders', [CrmController::class, 'orders'])->name('crm.orders');
Route::get('/crm/orders/{jobId}', [CrmController::class, 'jobDetail'])->name('crm.job-detail');
Route::get('/crm/shipping', [CrmController::class, 'shipping'])->name('crm.shipping');
Route::get('/crm/shipping/detail/{shipmentId}', [CrmController::class, 'shippingDetail'])->name('crm.shipping-detail');
Route::get('/crm/stores', [CrmController::class, 'stores'])->name('crm.stores');
Route::get('/crm/reports', [CrmController::class, 'reports'])->name('crm.reports');
Route::get('/crm/settings', [CrmController::class, 'settings'])->name('crm.settings');
Route::get('/crm/notifications', [CrmController::class, 'notifications'])->name('crm.notifications');
Route::get('/crm/order/detail/{orderId}', [CrmController::class, 'orderdetails'])->name('crm.order-detail');
Route::get('/crm/store/detail/{storeId}', [CrmController::class, 'storedetails'])->name('crm.store-detail');

// Status update endpoint (handled in CrmController)
Route::post('/api/update-status', [CrmController::class, 'updateStatus']);

// Get activity log endpoint (handled in CrmController)
Route::post('/api/get-activity-log', [CrmController::class, 'getActivityLog']);

// Return current order status (used by order-detail page)
Route::get('/api/get-order-status/{orderId}', [CrmController::class, 'getOrderStatus']);

// Webhook routes moved to API routes to avoid CSRF (POST was causing 419)
