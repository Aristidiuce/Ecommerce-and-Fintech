<?php
// ============================================================
//  KiliSmart — Admin Middleware
//  Protects all /api/v1/admin/* routes
// ============================================================

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Haujaingia.'], 401);
        }

        // Check admin phone — in production, use a roles table
        $adminPhones = ['+255700000001']; // Add admin phones here
        if (!in_array($user->phone, $adminPhones)) {
            return response()->json(['success' => false, 'message' => 'Huna ruhusa.'], 403);
        }

        return $next($request);
    }
}

// ============================================================
//  FUTURE API ROUTES — Stubbed and ready to build
//  Add these to routes/api.php when needed
// ============================================================
//
//  PROMOTIONS & COUPONS (Phase 3)
//  Route::get('promotions',          [PromotionController::class, 'index']);
//  Route::post('promotions/apply',   [PromotionController::class, 'apply']);
//
//  PUSH NOTIFICATIONS (Phase 3)
//  Route::post('notifications/subscribe', [NotificationController::class, 'subscribe']);
//  Route::get('notifications',            [NotificationController::class, 'index']);
//
//  PRODUCT REVIEWS (Phase 3)
//  Route::get('products/{id}/reviews',    [ReviewController::class, 'index']);
//  Route::post('products/{id}/reviews',   [ReviewController::class, 'store']);
//
//  WISHLIST (Phase 3)
//  Route::get('wishlist',                 [WishlistController::class, 'index']);
//  Route::post('wishlist/{productId}',    [WishlistController::class, 'toggle']);
//
//  MOBILE APP SPECIFIC (Phase 4)
//  Route::post('auth/biometric',          [AuthController::class, 'biometric']);
//  Route::get('products/{id}/similar',    [ProductController::class, 'similar']);
//  Route::get('search/suggestions',       [ProductController::class, 'suggestions']);
//
//  ANALYTICS (Phase 4)
//  Route::get('admin/analytics/funnel',   [AnalyticsController::class, 'conversionFunnel']);
//  Route::get('admin/analytics/cohorts',  [AnalyticsController::class, 'cohorts']);
//  Route::get('admin/analytics/heatmap',  [AnalyticsController::class, 'depositHeatmap']);
//
//  SUPPLIER API (Phase 3) — suppliers get their own token
//  Route::middleware('auth:sanctum','supplier')->prefix('supplier')->group(function(){
//      Route::get('orders',           [SupplierController::class, 'orders']);
//      Route::put('orders/{id}',      [SupplierController::class, 'updateOrder']);
//      Route::get('products',         [SupplierController::class, 'myProducts']);
//  });
//
//  TIGO PESA + AIRTEL (Phase 2B)
//  Route::post('tigo/callback',      [TigoController::class, 'callback']);
//  Route::post('airtel/callback',    [AirtelController::class, 'callback']);
//
//  PRODUCT IMAGES UPLOAD (Phase 2B)
//  Route::post('admin/products/{id}/images', [ProductController::class, 'uploadImages']);
//
//  DELIVERY TRACKING (Phase 3)
//  Route::get('orders/{id}/tracking',        [FulfillmentController::class, 'track']);
//  Route::post('admin/fulfillment/{id}/photo',[FulfillmentController::class, 'qualityPhoto']);
//
//  REFERRAL LEADERBOARD (Phase 3)
//  Route::get('referrals/leaderboard',        [ReferralController::class, 'leaderboard']);
//
//  MPESA STATEMENT RECONCILIATION (Phase 3)
//  Route::post('admin/reconcile',             [ReconciliationController::class, 'run']);
