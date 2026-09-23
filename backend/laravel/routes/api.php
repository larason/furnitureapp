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

// constant
$products = '/products';
$productPath = '/{product}';
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
|   ADMINISTRATIVE topology. `clerk.auth` authenticates the Clerk bearer
|   credential; `operational` and `admin` apply coarse local RBAC role gates.
|   Detailed permission, ownership, and state policy remains in Phase 4.10;
|   authorization is never inferred from the path.
| - Rate limiting attaches later per route/group (approved thresholds live in
|   api-conventions; do not invent thresholds). CSRF applies to cookie-auth
|   mutations later (SpA phase); upload/webhook tokens are Group H/J scoped.
| - Cache policy: PUBLIC catalog vs PRIVATE/OPERATIONAL groups are distinct
|   here so cache middleware can be attached per group in a later phase.
|
*/

Route::prefix('v1')->name('api.')->group(function () use ($products, $productPath): void {

    // ---------------------------------------------------------------------
    // PUBLIC — unauthenticated by contract (SSR/SEO catalog + anonymous flows)
    // ---------------------------------------------------------------------
    Route::middleware('throttle:public-read')->group(function () use ($products, $productPath): void {
        Route::get($products, [ProductController::class, 'index'])->name('products.index');
        Route::get($products.$productPath, [ProductController::class, 'show'])->name('products.show');
        Route::get($products.'/{product}/variants', [ProductController::class, 'indexVariants'])->name('products.variants.index');
        Route::get($products.'/{product}/variants/{variant}', [ProductController::class, 'showVariant'])->name('products.variants.show');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
    });

    // RETIRED — Clerk owns credential, session, recovery, and verification
    // flows. These always return `410 GONE` regardless of authentication
    // state; never behind auth middleware.
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/password/forgot', [AuthController::class, 'passwordForgot'])->name('auth.password.forgot');
    Route::post('/auth/password/reset', [AuthController::class, 'passwordReset'])->name('auth.password.reset');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
    Route::post('/email/verify', [AuthController::class, 'verifyEmail'])->name('auth.email.verify');
    Route::post('/email/verify/resend', [AuthController::class, 'resendEmailVerification'])->name('auth.email.resend');

    Route::middleware(['clerk.optional', 'throttle:anonymous-submit'])->post('/requests', [RequestController::class, 'store'])->name('requests.store');
    Route::middleware(['clerk.optional', 'throttle:anonymous-submit'])->post('/enquiries', [EnquiryController::class, 'store'])->name('enquiries.store');

    // ---------------------------------------------------------------------
    // ME — authenticated self (AUTHENTICATED_OWNER). Resource ownership is
    // object-level and decided later; no client user_id is ever accepted.
    // ---------------------------------------------------------------------
    Route::middleware('clerk.auth')->prefix('me')->name('me.')->group(function (): void {
        Route::middleware('throttle:authenticated-read')->group(function (): void {
            Route::get('/', [MeController::class, 'show'])->name('show');
            Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->name('orders.show');
            Route::get('/orders/{order}/tracking', [CustomerOrderController::class, 'tracking'])->name('orders.tracking');
            Route::get('/requests', [RequestController::class, 'meIndex'])->name('requests.index');
            Route::get('/requests/{request}', [RequestController::class, 'meShow'])->name('requests.show');
            Route::get('/enquiries', [EnquiryController::class, 'meIndex'])->name('enquiries.index');
            Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'meShow'])->name('enquiries.show');
            Route::get('/notifications', [NotificationController::class, 'meIndex'])->name('notifications.index');
        });

        Route::patch('/', [MeController::class, 'update'])->middleware('throttle:authenticated-write')->name('update');

        Route::post('/cart/items', [CartController::class, 'addItem'])->middleware('throttle:cart-add')->name('cart.items.store');
        Route::patch('/cart/items/{item}', [CartController::class, 'updateItem'])->middleware('throttle:authenticated-write')->name('cart.items.update');
        Route::delete('/cart/items/{item}', [CartController::class, 'removeItem'])->middleware('throttle:authenticated-write')->name('cart.items.destroy');
        Route::post('/cart/merge', [CartController::class, 'merge'])->middleware('throttle:authenticated-write')->name('cart.merge');

        Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->middleware('throttle:order-cancel')->name('orders.cancel');
        Route::patch('/notifications/{notification}', [NotificationController::class, 'meUpdate'])->middleware('throttle:authenticated-write')->name('notifications.update');
    });

    // ---------------------------------------------------------------------
    // CART — holder-scoped create/get. Optional Clerk auth so anonymous
    // guests can resolve/create their own guest cart via the guest credential.
    // ---------------------------------------------------------------------
    Route::middleware(['clerk.optional', 'throttle:authenticated-read'])
        ->get('/me/cart', [CartController::class, 'show'])
        ->name('me.cart.show');

    // ---------------------------------------------------------------------
    // CHECKOUT — authenticated customer only
    // ---------------------------------------------------------------------
    Route::middleware(['clerk.auth', 'throttle:checkout'])->post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    // ---------------------------------------------------------------------
    // CATALOG WRITES — STAFF/ADMIN (Staff only where products.manage allows)
    // Same path family as public reads; authorization is per-operation.
    // ---------------------------------------------------------------------
    Route::middleware(['clerk.auth', 'staff-or-admin'])->group(function () use ($products, $productPath): void {
        Route::middleware('permission:products.manage')->group(function () use ($products, $productPath): void {
            Route::post($products, [ProductController::class, 'store'])->middleware('throttle:operational-write')->name('products.store');
            Route::patch($products.$productPath, [ProductController::class, 'update'])->middleware('throttle:operational-write')->name('products.update');
            Route::post($products.'/{product}/images', [ProductController::class, 'storeImage'])->middleware('throttle:operational-write')->name('products.images.store');
            Route::post($products.'/{product}/variants', [ProductController::class, 'storeVariant'])->middleware('throttle:operational-write')->name('products.variants.store');
            Route::post('/categories', [CategoryController::class, 'store'])->middleware('throttle:operational-write')->name('categories.store');
            Route::patch('/categories/{category}', [CategoryController::class, 'update'])->middleware('throttle:operational-write')->name('categories.update');
        });
    });

    // ---------------------------------------------------------------------
    // OPERATIONAL — /orders staff handling (all actions are controlled POST)
    // ---------------------------------------------------------------------
    Route::middleware(['clerk.auth', 'operational'])->prefix('orders')->name('orders.')->group(function (): void {
        Route::middleware('permission:orders.view_operational')->group(function (): void {
            Route::get('/', [OrderController::class, 'index'])->middleware('throttle:authenticated-read')->name('index');
            Route::get('/{order}', [OrderController::class, 'show'])->middleware('throttle:authenticated-read')->name('show');
            Route::get('/{order}/tracking', [OrderController::class, 'tracking'])->middleware('throttle:authenticated-read')->name('tracking');
        });
        Route::middleware(['permission:orders.accept', 'throttle:operational-write'])->post('/{order}/accept', [OrderController::class, 'accept'])->name('accept');
        Route::middleware(['permission:orders.process', 'throttle:operational-write'])->post('/{order}/process', [OrderController::class, 'process'])->name('process');
        Route::middleware(['permission:orders.ready_for_pickup', 'throttle:operational-write'])->post('/{order}/ready-for-pickup', [OrderController::class, 'readyForPickup'])->name('ready-for-pickup');
        Route::middleware(['permission:orders.ship', 'throttle:operational-write'])->post('/{order}/ship', [OrderController::class, 'ship'])->name('ship');
        Route::middleware(['permission:orders.deliver', 'throttle:operational-write'])->post('/{order}/deliver', [OrderController::class, 'deliver'])->name('deliver');
        Route::middleware(['permission:orders.complete', 'throttle:operational-write'])->post('/{order}/complete', [OrderController::class, 'complete'])->name('complete');
        Route::middleware(['permission:orders.set_delivery_fee', 'throttle:operational-write'])->post('/{order}/delivery-fee', [OrderController::class, 'deliveryFee'])->name('delivery-fee');
    });

    // ---------------------------------------------------------------------
    // INVENTORY — operational
    // ---------------------------------------------------------------------
    Route::middleware(['clerk.auth', 'operational'])->group(function (): void {
        Route::middleware('permission:inventory.view')->group(function (): void {
            Route::get('/inventory', [InventoryController::class, 'index'])->middleware('throttle:authenticated-read')->name('inventory.index');
            Route::get('/inventory/{inventory}', [InventoryController::class, 'show'])->middleware('throttle:authenticated-read')->name('inventory.show');
        });
        Route::middleware(['permission:inventory.manage', 'throttle:inventory-adjust'])->post('/inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    });

    // --------------------------------------------------------------------
    // REQUESTS — operational handling (public submission is registered above)
    // --------------------------------------------------------------------
    Route::middleware(['clerk.auth', 'operational'])->prefix('requests')->name('requests.')->group(function (): void {
        Route::middleware('permission:requests.view')->group(function (): void {
            Route::get('/', [RequestController::class, 'index'])->middleware('throttle:authenticated-read')->name('index');
            Route::get('/{request}', [RequestController::class, 'show'])->middleware('throttle:authenticated-read')->name('show');
        });
        Route::middleware(['permission:requests.manage', 'throttle:operational-write'])->patch('/{request}', [RequestController::class, 'update'])->name('update');
    });

    // --------------------------------------------------------------------
    // ENQUIRIES — operational handling (public submission registered above)
    // --------------------------------------------------------------------
    Route::middleware(['clerk.auth', 'operational'])->prefix('enquiries')->name('enquiries.')->group(function (): void {
        Route::middleware('permission:enquiries.view')->group(function (): void {
            Route::get('/', [EnquiryController::class, 'index'])->middleware('throttle:authenticated-read')->name('index');
            Route::get('/{enquiry}', [EnquiryController::class, 'show'])->middleware('throttle:authenticated-read')->name('show');
        });
        Route::middleware(['permission:enquiries.manage', 'throttle:operational-write'])->post('/{enquiry}/close', [EnquiryController::class, 'close'])->name('close');
    });

    // --------------------------------------------------------------------
    // SCOPED ATTACHMENT UPLOADS — bearerAuth OR scoped X-Upload-Token
    // (uploadToken security alternative). Not public and not bearer-mandatory.
    // Token binding/verification is implemented in the attachment phase
    // (Group J / 10.6); routes remain stub-only until then.
    // --------------------------------------------------------------------
    Route::middleware('clerk.auth')->group(function (): void {
        Route::post('/requests/{request}/attachments', [RequestController::class, 'storeAttachment'])->name('requests.attachments.store');
        Route::post('/enquiries/{enquiry}/attachments', [EnquiryController::class, 'storeAttachment'])->name('enquiries.attachments.store');
    });

    // --------------------------------------------------------------------
    // ADMIN — Admin-only staff lifecycle, user visibility, audit logs
    // --------------------------------------------------------------------
    Route::middleware(['clerk.auth', 'admin'])->prefix('admin')->name('admin.')->group(function () use ($products, $productPath): void {
        Route::middleware('permission:products.manage')->group(function () use ($products, $productPath): void {
            Route::get($products, [ProductController::class, 'adminIndex'])->name('products.index');
            Route::get($products.$productPath, [ProductController::class, 'adminShow'])->name('products.show');
        });
        Route::middleware('permission:staff.manage')->group(function (): void {
            Route::get('/staff', [AdminController::class, 'staffIndex'])->name('staff.index');
            Route::post('/staff', [AdminController::class, 'staffStore'])->middleware('throttle:admin-staff')->name('staff.store');
            Route::get('/staff/{user}', [AdminController::class, 'staffShow'])->name('staff.show');
        });
        Route::middleware(['permission:staff.approve', 'throttle:admin-staff'])->post('/staff/{user}/approve', [AdminController::class, 'staffApprove'])->name('staff.approve');
        Route::middleware(['permission:staff.manage', 'throttle:admin-staff'])->post('/staff/{user}/suspend', [AdminController::class, 'staffSuspend'])->name('staff.suspend');
        Route::middleware(['permission:staff.manage', 'throttle:admin-staff'])->post('/staff/{user}/reactivate', [AdminController::class, 'staffReactivate'])->name('staff.reactivate');
        Route::get('/audit-logs', [AdminController::class, 'auditLogIndex'])->name('audit-logs.index');
    });

    // --------------------------------------------------------------------
    // USERS — Admin-only authorized visibility
    // --------------------------------------------------------------------
    Route::middleware(['clerk.auth', 'admin'])->group(function (): void {
        Route::middleware('permission:users.manage_authorized')->group(function (): void {
            Route::get('/users', [AdminController::class, 'userIndex'])->name('users.index');
            Route::get('/users/{user}', [AdminController::class, 'userShow'])->name('users.show');
        });
    });

    // --------------------------------------------------------------------
    // PAYMENTS — Group H placeholders (authenticated customer)
    // --------------------------------------------------------------------
    Route::middleware('clerk.auth')->group(function (): void {
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    });

    // --------------------------------------------------------------------
    // WEBHOOKS — provider signature verification (Group H); NOT bearer auth.
    // Route stays stub-only until signature middleware is implemented.
    // --------------------------------------------------------------------
    Route::post('/webhooks/payment/{provider}', [WebhookController::class, 'handlePaymentProvider'])->name('webhooks.payments.handle');
});
