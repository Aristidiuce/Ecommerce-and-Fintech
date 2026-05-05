<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController, ProductController, WalletController,
    SavingPlanController, WithdrawalController,
    MpesaController, AdminController, UssdController, AIController
};

// ============================================================
//  KiliSmart API Routes v1
//  Base URL: https://test.kilismart.co.tz/api/v1
// ============================================================

Route::prefix('v1')->group(function () {

    // ──────────────────────────────────────────────────────────
    //  PUBLIC — No auth required
    // ──────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('send-otp',        [AuthController::class, 'sendOtp']);
        Route::post('verify-otp',      [AuthController::class, 'verifyOtp']);
        Route::post('register',        [AuthController::class, 'register']);
        Route::post('login',           [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    });

    // Products — public browsing
    Route::prefix('products')->group(function () {
        Route::get('/',                [ProductController::class, 'index']);
        Route::get('/featured',        [ProductController::class, 'featured']);
        Route::get('/category/{slug}', [ProductController::class, 'byCategory']);
        Route::get('/{id}/related',    [ProductController::class, 'related']);
        Route::get('/{id}',            [ProductController::class, 'show']);
    });
    Route::get('categories',           [ProductController::class, 'categories']);

    // AI — public chat and search (no login needed)
    Route::post('ai/chat/public',      [AIController::class, 'chatPublic']);
    Route::get('ai/search',            [AIController::class, 'nlpSearchEndpoint']);

    // Withdrawal estimate (public calculator)
    Route::get('withdrawal-estimate',  [WalletController::class, 'withdrawalEstimate']);

    // ── M-PESA WEBHOOKS ────────────────────────────────────────
    // Safaricom calls these URLs — no auth, signature verified in controller
    // Register in Daraja portal: developer.safaricom.co.ke
    Route::prefix('mpesa')->group(function () {
        Route::post('stk-callback',    [MpesaController::class, 'stkCallback']);
        Route::post('c2b-validate',    [MpesaController::class, 'c2bValidate']);
        Route::post('c2b-confirm',     [MpesaController::class, 'c2bConfirm']);
        Route::post('b2c-result',      [MpesaController::class, 'b2cResult']);
        Route::post('b2c-timeout',     [MpesaController::class, 'b2cTimeout']);
    });

    // USSD — Africa's Talking
    Route::post('ussd',                [UssdController::class, 'handle']);

    // ──────────────────────────────────────────────────────────
    //  PROTECTED — Bearer token required
    // ──────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('auth/logout',     [AuthController::class, 'logout']);
        Route::get('auth/me',          [AuthController::class, 'me']);
        Route::put('profile',          [AuthController::class, 'updateProfile']);
        Route::put('profile/password', [AuthController::class, 'changePassword']);
        Route::get('referrals',        [AuthController::class, 'referrals']);

        // Wallet & Deposits
        Route::prefix('wallet')->group(function () {
            Route::get('/',            [WalletController::class, 'show']);
            Route::get('transactions', [WalletController::class, 'transactions']);
            Route::post('deposit/stk', [WalletController::class, 'initiateStk']); // M-Pesa STK Push
        });

        // Saving Plans
        Route::prefix('plans')->group(function () {
            Route::get('/',            [SavingPlanController::class, 'index']);
            Route::post('/',           [SavingPlanController::class, 'store']);
            Route::get('/{id}',        [SavingPlanController::class, 'show']);
            Route::delete('/{id}',     [SavingPlanController::class, 'cancel']);
        });

        // Withdrawals
        Route::prefix('withdrawals')->group(function () {
            Route::get('/',            [WithdrawalController::class, 'index']);
            Route::post('/',           [WithdrawalController::class, 'request']);
            Route::get('/{id}',        [WithdrawalController::class, 'show']);
        });

        // Order tracking
        Route::get('orders',           [SavingPlanController::class, 'orderHistory']);
        Route::get('orders/{id}/track',[SavingPlanController::class, 'trackOrder']);

        // AI — personalized (auth required)
        Route::prefix('ai')->group(function () {
            Route::post('savings-advice',  [AIController::class, 'savingsAdvice']);
            Route::get('recommendations',  [AIController::class, 'recommendations']);
            Route::get('price-trend/{id}', [AIController::class, 'priceTrend']);
            Route::post('chat',            [AIController::class, 'chat']);
        });

        // ── ADMIN ───────────────────────────────────────────────
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('overview',                  [AdminController::class, 'overview']);
            Route::get('customers',                 [AdminController::class, 'customers']);
            Route::get('customers/{id}',            [AdminController::class, 'customerDetail']);
            Route::put('customers/{id}/status',     [AdminController::class, 'updateCustomerStatus']);
            Route::get('plans',                     [AdminController::class, 'plans']);
            Route::get('plans/inactive',            [AdminController::class, 'inactivePlans']);
            Route::get('fulfillment',               [AdminController::class, 'fulfillmentQueue']);
            Route::put('fulfillment/{id}/status',   [AdminController::class, 'updateFulfillmentStatus']);
            Route::post('fulfillment/{id}/photo',   [AdminController::class, 'uploadQualityPhoto']);
            Route::get('withdrawals',               [AdminController::class, 'withdrawals']);
            Route::post('withdrawals/{id}/approve', [AdminController::class, 'approveWithdrawal']);
            Route::post('withdrawals/{id}/reject',  [AdminController::class, 'rejectWithdrawal']);
            Route::get('products',                  [AdminController::class, 'products']);
            Route::post('products',                 [AdminController::class, 'createProduct']);
            Route::put('products/{id}',             [AdminController::class, 'updateProduct']);
            Route::delete('products/{id}',          [AdminController::class, 'hideProduct']);
            Route::post('products/{id}/images',     [AdminController::class, 'uploadProductImages']);
            Route::get('categories',                [AdminController::class, 'categories']);
            Route::post('categories',               [AdminController::class, 'createCategory']);
            Route::put('categories/{id}',           [AdminController::class, 'updateCategory']);
            Route::get('suppliers',                 [AdminController::class, 'suppliers']);
            Route::post('suppliers',                [AdminController::class, 'createSupplier']);
            Route::put('suppliers/{id}',            [AdminController::class, 'updateSupplier']);
            Route::get('deliveries',                [AdminController::class, 'deliveries']);
            Route::post('whatsapp/bulk',            [AdminController::class, 'sendBulkWhatsapp']);
            Route::get('reports/financial',         [AdminController::class, 'financialReport']);
            Route::get('reports/monthly',           [AdminController::class, 'monthlyReport']);
        });
    });

    // ══════════════════════════════════════════════════════════════
    //  FUTURE API ROOM — Uncomment block when building each phase
    // ══════════════════════════════════════════════════════════════

    // PHASE 2B — Airtel Money direct integration
    // Route::post('airtel/callback',    [AirtelController::class, 'callback']);

    // PHASE 2B — Mixx by Yas (Tigo Pesa) direct integration
    // Route::post('mixx/callback',      [MixxController::class, 'callback']);

    // PHASE 2B — Halopesa direct integration
    // Route::post('halopesa/callback',  [HalopesaController::class, 'callback']);

    // PHASE 3 — Product Reviews
    // Route::middleware('auth:sanctum')->group(function () {
    //     Route::get('products/{id}/reviews',  [ReviewController::class, 'index']);
    //     Route::post('products/{id}/reviews', [ReviewController::class, 'store']);
    // });

    // PHASE 3 — Wishlist
    // Route::middleware('auth:sanctum')->group(function () {
    //     Route::get('wishlist',             [WishlistController::class, 'index']);
    //     Route::post('wishlist/{productId}',[WishlistController::class, 'toggle']);
    // });

    // PHASE 3 — Promotions & Coupons
    // Route::get('promotions',              [PromotionController::class, 'index']);
    // Route::post('promotions/apply',       [PromotionController::class, 'apply']);

    // PHASE 3 — Push Notifications (PWA/Mobile)
    // Route::middleware('auth:sanctum')->group(function () {
    //     Route::post('notifications/subscribe', [PushController::class, 'subscribe']);
    //     Route::get('notifications',            [PushController::class, 'index']);
    // });

    // PHASE 3 — Delivery GPS Tracking
    // Route::get('orders/{id}/gps',          [DeliveryController::class, 'gpsTrack']);

    // PHASE 3 — Supplier Portal API
    // Route::middleware(['auth:sanctum','supplier'])->prefix('supplier')->group(function () {
    //     Route::get('orders',              [SupplierController::class, 'orders']);
    //     Route::put('orders/{id}/confirm', [SupplierController::class, 'confirmOrder']);
    //     Route::get('products',            [SupplierController::class, 'myProducts']);
    // });

    // PHASE 3 — Referral Leaderboard
    // Route::get('referrals/leaderboard',    [ReferralController::class, 'leaderboard']);

    // PHASE 4 — Analytics
    // Route::middleware(['auth:sanctum','admin'])->prefix('admin/analytics')->group(function () {
    //     Route::get('funnel',               [AnalyticsController::class, 'conversionFunnel']);
    //     Route::get('cohorts',              [AnalyticsController::class, 'cohortAnalysis']);
    //     Route::get('heatmap',              [AnalyticsController::class, 'depositHeatmap']);
    // });

    // PHASE 4 — Mobile App (React Native / Flutter)
    // Route::prefix('mobile')->group(function () {
    //     Route::post('auth/biometric',      [MobileAuthController::class, 'biometric']);
    //     Route::post('auth/device-token',   [MobileAuthController::class, 'registerDevice']);
    //     Route::get('home-feed',            [MobileController::class, 'homeFeed']);
    // });

    // PHASE 4 — Advanced AI
    // Route::prefix('ai')->middleware('auth:sanctum')->group(function () {
    //     Route::post('semantic-search',     [AIController::class, 'semanticSearch']);
    //     Route::get('spending-analysis',    [AIController::class, 'spendingAnalysis']);
    // });

    // PHASE 5 — KiliSmart Pay (Financial Services)
    // Route::middleware('auth:sanctum')->prefix('pay')->group(function () {
    //     Route::post('transfer',            [PayController::class, 'transfer']);
    //     Route::get('history',              [PayController::class, 'history']);
    // });
});
