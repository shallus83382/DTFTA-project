<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\PartnerProfileController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\FulfillmentServiceController;
use App\Http\Controllers\Api\FailedWebhookController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\ArtworkController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ShopifyApiController;
use App\Http\Controllers\Api\Auth\UserManagementController;
use App\Http\Controllers\Shopify\WebhookController;
use App\Http\Controllers\CrmController;

// ============================================
// PUBLIC ROUTES (No Authentication Required)
// ============================================
Route::prefix('v1')->group(function () {
    // Public Shopify webhook endpoints
    Route::post('/webhooks/shopify', [WebhookController::class, 'handle']);
    Route::get('/webhooks/status', [WebhookController::class, 'status']);
    Route::post('/fulfillment_order_notification', [WebhookController::class, 'fulfillmentOrderNotification'])
        ->middleware('throttle:120,1');

    // CRM utility endpoints for frontend/Postman without CSRF
    Route::post('/update-status', [CrmController::class, 'updateStatus']);
    Route::post('/get-activity-log', [CrmController::class, 'getActivityLog']);
    Route::get('/get-order-status/{orderId}', [CrmController::class, 'getOrderStatus']);
    Route::post('/retry-billing', [CrmController::class, 'retryBilling']);
    Route::get('/notifications/recent', [CrmController::class, 'getRealtimeNotifications']);

    // Frontend signed endpoint (timestamp + signature HMAC)
    Route::get('/products/get', [ProductController::class, 'signedIndex']);
    Route::get('/products/get/{id}', [ProductController::class, 'signedShow']);
    Route::post('/products/create-in-shopify-signed', [ProductController::class, 'createInShopifySigned']);
    Route::get('/custom-products/{customProduct}', [ProductController::class, 'customProduct']);
    Route::get('/artworks/list', [ArtworkController::class, 'list']);
    Route::post('/artworks/upload', [ArtworkController::class, 'upload']);

    Route::post('/brand-settings', [ShopifyApiController::class, 'CreateStoreBrandSettings']);
    Route::get('/brand-settings', [ShopifyApiController::class, 'GetStoreBrandSettings']);

    Route::get('/dashboard-stats', [ShopifyApiController::class, 'GetStoreDashboardStats']);
    Route::get('/orders-signed', [ShopifyApiController::class, 'GetStoreOrders']);
    Route::get('/fulfillment-status', [ShopifyApiController::class, 'GetStoreFulfillmentStatus']);
    Route::get('/billing/status', [BillingController::class, 'status']);
    Route::post('/billing/approve', [BillingController::class, 'approve']);
    
});

// ============================================
// PROTECTED ROUTES (Authentication Required)
// ============================================
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // User can update own profile
    Route::put('/profile', [UserManagementController::class, 'update']);

    // Shop routes
    Route::post('/shops', [ShopController::class, 'store']);
    Route::get('/shops/{shop_domain}', [ShopController::class, 'show']);
    Route::get('/shops', [ShopController::class, 'index']);
    Route::put('/shops/{shop_domain}', [ShopController::class, 'update']);
    Route::delete('/shops/{shop_domain}', [ShopController::class, 'destroy']);

    // Product routes
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Partner profile routes
    Route::post('/partner-profiles', [PartnerProfileController::class, 'store']);
    Route::get('/partner-profiles/{shop_id}', [PartnerProfileController::class, 'show']);
    Route::delete('/partner-profiles/{shop_id}', [PartnerProfileController::class, 'destroy']);

    // Backward compatibility for older clients
    Route::delete('/profiles/{shop_id}', [PartnerProfileController::class, 'destroy']);
    Route::get('/partner-profiles', [PartnerProfileController::class, 'index']);

    // Order routes
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
    Route::get('/orders/{id}/summary', [OrderController::class, 'summary']);

    // Order Item routes
    Route::post('/orders/{order_id}/items', [OrderItemController::class, 'store']);
    Route::get('/orders/{order_id}/items', [OrderItemController::class, 'index']);
    Route::get('/order-items/{item_id}', [OrderItemController::class, 'show']);
    Route::put('/order-items/{item_id}', [OrderItemController::class, 'update']);
    Route::delete('/order-items/{item_id}', [OrderItemController::class, 'destroy']);

    // Shipment routes
    Route::post('/shipments', [ShipmentController::class, 'store']);
    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::get('/shipments/{id}', [ShipmentController::class, 'show']);
    Route::put('/shipments/{id}', [ShipmentController::class, 'update']);
    Route::delete('/shipments/{id}', [ShipmentController::class, 'destroy']);
    Route::post('/shipments/{id}/cancel', [ShipmentController::class, 'cancel']);

    // Fulfillment Service routes
    Route::post('/fulfillment-services', [FulfillmentServiceController::class, 'store']);
    Route::get('/fulfillment-services', [FulfillmentServiceController::class, 'index']);
    Route::get('/fulfillment-services/{id}', [FulfillmentServiceController::class, 'show']);
    Route::put('/fulfillment-services/{id}', [FulfillmentServiceController::class, 'update']);
    Route::delete('/fulfillment-services/{id}', [FulfillmentServiceController::class, 'destroy']);

    // Failed Webhook routes
    Route::post('/failed-webhooks', [FailedWebhookController::class, 'store']);
    Route::get('/failed-webhooks', [FailedWebhookController::class, 'index']);
    Route::get('/failed-webhooks/{id}', [FailedWebhookController::class, 'show']);
    Route::post('/failed-webhooks/{id}/retry', [FailedWebhookController::class, 'retry']);
    Route::delete('/failed-webhooks/{id}', [FailedWebhookController::class, 'destroy']);
    Route::get('/failed-webhooks/stats/summary', [FailedWebhookController::class, 'stats']);

    // Job routes
    Route::post('/jobs', [JobController::class, 'store']);
    Route::get('/jobs', [JobController::class, 'index']);
    Route::get('/jobs/{id}', [JobController::class, 'show']);
    Route::put('/jobs/{id}', [JobController::class, 'update']);
    Route::delete('/jobs/{id}', [JobController::class, 'destroy']);
    Route::get('/jobs/stats/summary', [JobController::class, 'stats']);
    Route::post('/jobs/retry-failed', [JobController::class, 'retryFailed']);

    // Notifications
    Route::get('/notifications', [CrmController::class, 'getNotifications']);
    Route::post('/notifications/mark-read', [CrmController::class, 'markNotificationsRead']);
});
