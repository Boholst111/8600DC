<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;

// Public API endpoints
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/filters', [ProductController::class, 'filters']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ReturnController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/checkout', [CheckoutController::class, 'checkout']);

    // ── Admin Only ──────────────────────────────────────────────
    Route::middleware(['role:ADMIN'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Orders
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::put('/orders/{id}/status', [AdminController::class, 'updateOrderStatus']);
        Route::put('/orders/{id}', [AdminController::class, 'updateOrder']);
        Route::get('/orders/{id}/invoice', [AdminController::class, 'generateInvoice']);

        // Products
        Route::post('/products', [AdminController::class, 'storeProduct']);
        Route::put('/products/{id}', [AdminController::class, 'updateProduct']);
        Route::delete('/products/{id}', [AdminController::class, 'destroyProduct']);

        // Users
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'storeUser']);
        Route::put('/users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::patch('/users/{id}/block', [AdminController::class, 'toggleUserBlock']);

        // Exports
        Route::get('/export/orders/csv', [AdminController::class, 'exportOrdersCsv']);

        // Delivery management (admin)
        Route::get('/deliveries', [DeliveryController::class, 'allDeliveries']);
        Route::put('/deliveries/{id}/assign', [DeliveryController::class, 'assignRider']);
        Route::get('/delivery/riders', [DeliveryController::class, 'availableRiders']);

        // Returns / RMA Management
        Route::get('/returns', [ReturnController::class, 'index']);
        Route::get('/returns/{id}', [ReturnController::class, 'show']);
        Route::put('/returns/{id}/process', [ReturnController::class, 'process']);
    });

    // ── Delivery Rider ───────────────────────────────────────────
    Route::middleware(['role:DELIVERY|ADMIN'])->prefix('delivery')->group(function () {
        Route::get('/my-deliveries', [DeliveryController::class, 'myDeliveries']);
        Route::put('/{id}/status', [DeliveryController::class, 'updateStatus']);
    });

    // ── Client ───────────────────────────────────────────────────
    Route::middleware(['role:CLIENT|ADMIN'])->prefix('client')->group(function () {
        Route::get('/orders', [ClientController::class, 'myOrders']);
        Route::get('/orders/{id}', [ClientController::class, 'orderDetails']);
        Route::get('/orders/{id}/invoice', [ClientController::class, 'downloadInvoice']);
        Route::get('/loyalty-history', [ClientController::class, 'loyaltyHistory']);
        
        // Address CRUD
        Route::get('/addresses', [ClientController::class, 'getAddresses']);
        Route::post('/addresses', [ClientController::class, 'storeAddress']);
        Route::put('/addresses/{id}', [ClientController::class, 'updateAddress']);
        Route::delete('/addresses/{id}', [ClientController::class, 'deleteAddress']);
        Route::post('/orders/{id}/settle', [ClientController::class, 'settleBalance']);

        // Returns / RMA
        Route::post('/returns', [ReturnController::class, 'store']);
        Route::get('/returns', [ReturnController::class, 'myReturns']);
    });
});
