<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CustomerOrderController;
use App\Http\Controllers\Api\V1\EnquiryController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RequestController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Version 1 API Routes
|--------------------------------------------------------------------------
|
| Authority: the frozen Group A contract (docs/api/api-contract.md,
| docs/api/api-resources.md, docs/api/api-conventions.md, docs/api/openapi.yaml).
|
| Conventions:
| - Single version boundary: /api/v1 (apiPrefix 'api' + 'v1' prefix here).
| - The removed /staff/... aliases are NOT registered.
| - Route groups only separate PUBLIC / AUTHENTICATED / OPERATIONAL /
|   ADMINISTRATIVE topology. Authorization itself is decided later by the
|   auth + role/policy layers (AGENTS.md §17-18, Group D); never by path.
| - `auth` (framework) rejects unauthenticated callers today. `operational`|   and `admin` are Group D attachment points (placeholder middleware).
| - Rate limiting attaches later per route/group (approved thresholds live in
|   api-conventions; do not invent thresholds). CSRF applies to cookie-auth
|   mutations later (SpA phase); upload/webhook tokens are Group H/J scoped.
| - Cache policy: PUBLIC catalog vs PRIVATE/OPERATIONAL groups are distinct
|   here so cache middleware can be attached per group in a later phase.
|
*/

Route::prefix('v1')->name('api.')->group(function (): void {

    // Infrastructure endpoint owned by Phase 2.9 (added in Phase 2.1).
    // Not a business resource; minimal availability signal only.
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    })->name('health');

    // ---------------------------------------------------------------------
    // PUBLIC — unauthenticated by contract (SSR/SEO catalog + anonymous flows)
    // ---------------------------------------------------------------------
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/variants', [ProductController::class, 'indexVariants'])->name('products.variants.index');
    Route::get('/products/{product}/variants/{variant}', [ProductController::class, 'showVariant'])->name('products.variants.show');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');

    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/password/forgot', [AuthController::class, 'passwordForgot'])->name('auth.password.forgot');
    Route::post('/auth/password/reset', [AuthController::class, 'passwordReset'])->name('auth.password.reset');

    Route::post('/requests', [RequestController::class, 'store'])->name('requests.store');
    Route::post('/enquiries', [EnquiryController::class, 'store'])->name('enquiries.store');

    // ---------------------------------------------------------------------
    // AUTHENTICATED — auth workflows that required an authenticated principal
    // ---------------------------------------------------------------------
    Route::middleware('auth')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
        Route::post('/email/verify', [AuthController::class, 'verifyEmail'])->name('auth.email.verify');
        Route::post('/email/verify/resend', [AuthController::class, 'resendEmailVerification'])->name('auth.email.resend');
    });

    // ---------------------------------------------------------------------
    // ME — authenticated self (AUTHENTICATED_OWNER). Resource ownership is
    // object-level and decided later; no client user_id is ever accepted.
    // ---------------------------------------------------------------------
    Route::middleware('auth')->prefix('me')->name('me.')->group(function (): void {
        Route::get('/', [MeController::class, 'show'])->name('show');
        Route::patch('/', [MeController::class, 'update'])->name('update');

        Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
        Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.items.store');
        Route::patch('/cart/items/{item}', [CartController::class, 'updateItem'])->name('cart.items.update');
        Route::delete('/cart/items/{item}', [CartController::class, 'removeItem'])->name('cart.items.destroy');
        Route::post('/cart/merge', [CartController::class, 'merge'])->name('cart.merge');

        Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->name('orders.cancel');
        Route::get('/orders/{order}/tracking', [CustomerOrderController::class, 'tracking'])->name('orders.tracking');

        Route::get('/requests', [RequestController::class, 'meIndex'])->name('requests.index');
        Route::get('/requests/{request}', [RequestController::class, 'meShow'])->name('requests.show');
        Route::get('/enquiries', [EnquiryController::class, 'meIndex'])->name('enquiries.index');
        Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'meShow'])->name('enquiries.show');

        Route::get('/notifications', [NotificationController::class, 'meIndex'])->name('notifications.index');
        Route::patch('/notifications/{notification}', [NotificationController::class, 'meUpdate'])->name('notifications.update');
    });

    // ---------------------------------------------------------------------
    // CHECKOUT — authenticated customer only
    // ---------------------------------------------------------------------
    Route::middleware('auth')->post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    // ---------------------------------------------------------------------
    // CATALOG WRITES — ADMINISTRATIVE (Admin; Staff only where approved)
    // Same path family as public reads; authorization is per-operation.
    // ---------------------------------------------------------------------
    Route::middleware('auth', 'admin')->group(function (): void {
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('/products/{product}/images', [ProductController::class, 'storeImage'])->name('products.images.store');
        Route::post('/products/{product}/variants', [ProductController::class, 'storeVariant'])->name('products.variants.store');

        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    });

    // ---------------------------------------------------------------------
    // OPERATIONAL — /orders staff handling (all actions are controlled POST)
    // ---------------------------------------------------------------------
    Route::middleware(['auth', 'operational'])->prefix('orders')->name('orders.')->group(function (): void {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');
        Route::get('/{order}/tracking', [OrderController::class, 'tracking'])->name('tracking');
        Route::post('/{order}/accept', [OrderController::class, 'accept'])->name('accept');
        Route::post('/{order}/process', [OrderController::class, 'process'])->name('process');
        Route::post('/{order}/ready-for-pickup', [OrderController::class, 'readyForPickup'])->name('ready-for-pickup');
        Route::post('/{order}/ship', [OrderController::class, 'ship'])->name('ship');
        Route::post('/{order}/deliver', [OrderController::class, 'deliver'])->name('deliver');
        Route::post('/{order}/complete', [OrderController::class, 'complete'])->name('complete');
        Route::post('/{order}/delivery-fee', [OrderController::class, 'deliveryFee'])->name('delivery-fee');
    });

    // ---------------------------------------------------------------------
    // INVENTORY — operational
    // ---------------------------------------------------------------------
    Route::middleware(['auth', 'operational'])->group(function (): void {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/{inventory}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::post('/inventory/{product}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    });

    // --------------------------------------------------------------------
    // REQUESTS — operational handling (public submission is registered above)
    // --------------------------------------------------------------------
    Route::middleware(['auth', 'operational'])->prefix('requests')->name('requests.')->group(function (): void {
        Route::get('/', [RequestController::class, 'index'])->name('index');
        Route::get('/{request}', [RequestController::class, 'show'])->name('show');
        Route::patch('/{request}', [RequestController::class, 'update'])->name('update');
    });

    // --------------------------------------------------------------------
    // ENQUIRIES — operational handling (public submission registered above)
    // --------------------------------------------------------------------
    Route::middleware(['auth', 'operational'])->prefix('enquiries')->name('enquiries.')->group(function (): void {
        Route::get('/', [EnquiryController::class, 'index'])->name('index');
        Route::get('/{enquiry}', [EnquiryController::class, 'show'])->name('show');
        Route::post('/{enquiry}/close', [EnquiryController::class, 'close'])->name('close');
    });

    // --------------------------------------------------------------------
    // SCOPED ATTACHMENT UPLOADS — bearerAuth OR scoped X-Upload-Token
    // (uploadToken security alternative). Not public and not bearer-mandatory.
    // Token binding/verification is implemented in the attachment phase
    // (Group J / 10.6); routes remain stub-only until then.
    // --------------------------------------------------------------------
    Route::post('/requests/{request}/attachments', [RequestController::class, 'storeAttachment'])->name('requests.attachments.store');
    Route::post('/enquiries/{enquiry}/attachments', [EnquiryController::class, 'storeAttachment'])->name('enquiries.attachments.store');

    // --------------------------------------------------------------------
    // ADMIN — Admin-only staff lifecycle, user visibility, audit logs
    // --------------------------------------------------------------------
    Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/products', [ProductController::class, 'adminIndex'])->name('products.index');
        Route::get('/products/{product}', [ProductController::class, 'adminShow'])->name('products.show');
        Route::get('/staff', [AdminController::class, 'staffIndex'])->name('staff.index');
        Route::post('/staff', [AdminController::class, 'staffStore'])->name('staff.store');
        Route::get('/staff/{user}', [AdminController::class, 'staffShow'])->name('staff.show');
        Route::post('/staff/{user}/approve', [AdminController::class, 'staffApprove'])->name('staff.approve');
        Route::post('/staff/{user}/suspend', [AdminController::class, 'staffSuspend'])->name('staff.suspend');
        Route::post('/staff/{user}/reactivate', [AdminController::class, 'staffReactivate'])->name('staff.reactivate');
        Route::get('/audit-logs', [AdminController::class, 'auditLogIndex'])->name('audit-logs.index');
    });

    // --------------------------------------------------------------------
    // USERS — Admin-only authorized visibility
    // --------------------------------------------------------------------
    Route::middleware(['auth', 'admin'])->group(function (): void {
        Route::get('/users', [AdminController::class, 'userIndex'])->name('users.index');
        Route::get('/users/{user}', [AdminController::class, 'userShow'])->name('users.show');
    });

    // --------------------------------------------------------------------
    // PAYMENTS — Group H placeholders (authenticated customer)
    // --------------------------------------------------------------------
    Route::middleware('auth')->group(function (): void {
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    });

    // --------------------------------------------------------------------
    // WEBHOOKS — provider signature verification (Group H); NOT bearer auth.
    // Route stays stub-only until signature middleware is implemented.
    // --------------------------------------------------------------------
    Route::post('/webhooks/payment/{provider}', [WebhookController::class, 'handlePaymentProvider'])->name('webhooks.payments.handle');
});
