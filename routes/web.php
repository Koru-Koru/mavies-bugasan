<?php

use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\Admin\DamageController as AdminDamageController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\StaffController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\Customer\OrderController as CustomerOrderController;
use App\Services\ReportService;
use Illuminate\Support\Facades\Route;

// Session-based API. Everything here is reached with the SPA's session cookie
// and X-CSRF-TOKEN; nothing has a token/sanctum guard. Default-deny: only the
// explicitly public routes below sit outside an auth group.
Route::prefix('api')->group(function () {
    // --- Public (explicitly opted in) ---
    Route::post('admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:login');
    Route::post('admin/password/forgot', [AdminAuthController::class, 'forgotPassword'])->middleware('throttle:otp');
    Route::post('admin/password/reset', [AdminAuthController::class, 'resetPassword'])->middleware('throttle:otp');
    Route::post('customer/login', [CustomerAuthController::class, 'login'])->middleware('throttle:login');
    Route::post('customer/register', [CustomerAuthController::class, 'register'])->middleware('throttle:register');
    Route::post('customer/register/otp', [CustomerAuthController::class, 'sendOtp'])->middleware('throttle:otp');
    Route::post('customer/otp/verify', [CustomerAuthController::class, 'verifyOtp'])->middleware('throttle:otp');
    Route::post('customer/password/forgot', [CustomerAuthController::class, 'forgotPassword'])->middleware('throttle:otp');
    Route::post('customer/password/reset', [CustomerAuthController::class, 'resetPassword'])->middleware('throttle:otp');
    Route::get('catalog', [CatalogController::class, 'index']);
    Route::get('shop', [ShopController::class, 'info']);
    Route::get('csrf-token', [TokenController::class, 'index']);

    // --- Customer session ---
    Route::middleware('auth:customer')->group(function () {
        Route::get('customer/me', [CustomerAuthController::class, 'me']);
        Route::post('customer/logout', [CustomerAuthController::class, 'logout']);
        Route::patch('customer/profile', [CustomerAuthController::class, 'profile']);
        Route::post('customer/change-password', [CustomerAuthController::class, 'changePassword']);

        Route::get('customer/orders', [CustomerOrderController::class, 'index']);
        Route::post('customer/orders', [CustomerOrderController::class, 'store'])->middleware('throttle:place-order');
        Route::get('customer/orders/{orderId}', [CustomerOrderController::class, 'show'])->whereNumber('orderId');
        Route::post('customer/orders/{orderId}/cancel', [CustomerOrderController::class, 'cancel'])->whereNumber('orderId');
    });

    // --- Admin session ---
    Route::prefix('admin')->middleware('auth:admin')->group(function () {
        Route::get('me', [AdminAuthController::class, 'me']);
        Route::post('logout', [AdminAuthController::class, 'logout']);
        Route::post('change-password', [AdminAuthController::class, 'changePassword']);

        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{orderId}', [AdminOrderController::class, 'show'])->whereNumber('orderId');
        Route::patch('orders/{orderId}/status', [AdminOrderController::class, 'updateStatus'])->whereNumber('orderId');
        Route::patch('orders/{orderId}/price', [AdminOrderController::class, 'price'])->whereNumber('orderId');
        Route::patch('orders/{orderId}/note', [AdminOrderController::class, 'note'])->whereNumber('orderId');
        Route::patch('orders/{orderId}/courier', [AdminOrderController::class, 'courier'])->whereNumber('orderId');

        Route::get('inventory', [InventoryController::class, 'index']);
        Route::post('inventory', [InventoryController::class, 'store']);
        Route::patch('inventory/{inventoryId}', [InventoryController::class, 'update'])->whereNumber('inventoryId');
        Route::patch('inventory/{inventoryId}/stock', [InventoryController::class, 'adjustStock'])->whereNumber('inventoryId');
        Route::post('inventory/{inventoryId}/archive', [InventoryController::class, 'archive'])->whereNumber('inventoryId');
        Route::post('inventory/{inventoryId}/restore', [InventoryController::class, 'restore'])->whereNumber('inventoryId');
        Route::get('inventory/{inventoryId}/movements', [InventoryController::class, 'movements'])->whereNumber('inventoryId');

        Route::get('customers', [AdminCustomerController::class, 'index']);
        Route::post('customers', [AdminCustomerController::class, 'store']);
        Route::get('customers/{customerId}', [AdminCustomerController::class, 'show'])->whereNumber('customerId');
        Route::patch('customers/{customerId}', [AdminCustomerController::class, 'update'])->whereNumber('customerId');
        Route::post('customers/{customerId}/blacklist', [AdminCustomerController::class, 'blacklist'])->whereNumber('customerId');
        Route::post('customers/{customerId}/whitelist', [AdminCustomerController::class, 'whitelist'])->whereNumber('customerId');
        Route::post('customers/{customerId}/reset-password', [AdminCustomerController::class, 'resetPassword'])->whereNumber('customerId')->middleware('throttle:otp');

        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/suggest', [PaymentController::class, 'suggest']);
        Route::post('payments', [PaymentController::class, 'store']);
        Route::post('payments/{paymentId}/void', [PaymentController::class, 'void'])->whereNumber('paymentId');

        Route::get('damages', [AdminDamageController::class, 'index']);
        Route::post('damages', [AdminDamageController::class, 'store']);

        Route::get('settings', [SettingsController::class, 'index']);
        Route::patch('settings', [SettingsController::class, 'update']);

        Route::get('staff', [StaffController::class, 'index']);
        Route::post('staff', [StaffController::class, 'store']);
        Route::patch('staff/{adminId}', [StaffController::class, 'update'])->whereNumber('adminId');
        Route::post('staff/{adminId}/reset-password', [StaffController::class, 'resetPassword'])->whereNumber('adminId')->middleware('throttle:otp');

        Route::get('reports/export', [ReportController::class, 'export']);
        Route::get('reports/{report}', [ReportController::class, 'show'])->whereIn('report', ReportService::REPORTS);

        Route::get('activity-log', [ActivityLogController::class, 'index']);
    });
});

// SPA shell. Every path that is not an asset or the API renders the same Blade
// shell; Vue Router (history mode) takes over client-side.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|storage|build).*$');
