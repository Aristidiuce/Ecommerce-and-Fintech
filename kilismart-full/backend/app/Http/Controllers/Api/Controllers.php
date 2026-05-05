<?php
// ============================================================
//  KiliSmart — API Controllers
//  ProductController, WalletController, SavingPlanController,
//  WithdrawalController, MpesaController
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Product, Category, SavingPlan, Wallet, Transaction, WithdrawalRequest, MpesaCallback, FulfillmentOrder};
use App\Services\{WalletService, MpesaService, WhatsAppService};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Validator, DB, Log};

// ════════════════════════════════════════════════════════════
//  PRODUCT CONTROLLER
//  Public — no auth required
// ════════════════════════════════════════════════════════════
class ProductController extends Controller
{
    // GET /api/v1/products
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category')
            ->where('status', 'active')
            ->orderBy('sort_order');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('name_sw', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->category) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }

        $products = $query->get()->map(fn($p) => $this->productResource($p));

        return response()->json(['success' => true, 'data' => $products]);
    }

    // GET /api/v1/products/featured
    public function featured(): JsonResponse
    {
        $products = Product::with('category')
            ->where('status', 'active')
            ->whereIn('badge', ['hot', 'sale'])
            ->orderBy('sort_order')
            ->limit(8)
            ->get()
            ->map(fn($p) => $this->productResource($p));

        return response()->json(['success' => true, 'data' => $products]);
    }

    // GET /api/v1/products/category/{slug}
    public function byCategory(string $slug): JsonResponse
    {
        $products = Product::with('category')
            ->whereHas('category', fn($q) => $q->where('slug', $slug))
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($p) => $this->productResource($p));

        return response()->json(['success' => true, 'data' => $products]);
    }

    // GET /api/v1/products/{id}
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'supplier'])->findOrFail($id);
        if ($product->status !== 'active') abort(404);

        return response()->json(['success' => true, 'data' => $this->productResource($product, full: true)]);
    }

    // GET /api/v1/categories
    public function categories(): JsonResponse
    {
        $cats = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name', 'name_sw', 'icon']);

        return response()->json(['success' => true, 'data' => $cats]);
    }

    private function productResource(Product $p, bool $full = false): array
    {
        $activeSavers = $p->plans()->where('status', 'active')->count();
        $avgPct = $p->plans()->where('status', 'active')
            ->selectRaw('AVG(amount_saved / target_amount * 100) as avg_pct')
            ->value('avg_pct') ?? 0;

        $base = [
            'id'           => $p->id,
            'name'         => $p->name,
            'name_sw'      => $p->name_sw,
            'slug'         => $p->slug,
            'emoji'        => $p->emoji,
            'category'     => $p->category?->name,
            'category_slug'=> $p->category?->slug,
            'retail_price' => $p->retail_price,
            'delivery_fee' => $p->delivery_fee,
            'badge'        => $p->badge,
            'image_paths'  => $p->image_paths ?? [],
            'specs'        => $p->specs ?? [],
            'active_savers'=> $activeSavers,
            'avg_pct'      => round($avgPct, 1),
            'weekly_4'     => (int) ceil($p->retail_price / 4),
            'weekly_8'     => (int) ceil($p->retail_price / 8),
            'weekly_12'    => (int) ceil($p->retail_price / 12),
            'weekly_16'    => (int) ceil($p->retail_price / 16),
        ];

        if ($full) {
            $base['description']    = $p->description;
            $base['description_sw'] = $p->description_sw;
            $base['supplier']       = $p->supplier?->name;
            $base['lead_days']      = $p->supplier?->lead_days;
            $base['price_lock_days']= $p->price_lock_days;
        }

        return $base;
    }
}

// ════════════════════════════════════════════════════════════
//  WALLET CONTROLLER
// ════════════════════════════════════════════════════════════
class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected MpesaService  $mpesaService,
    ) {}

    // GET /api/v1/wallet
    public function show(Request $request): JsonResponse
    {
        $user   = $request->user()->load('wallet');
        $wallet = $user->wallet;

        return response()->json([
            'success' => true,
            'data' => [
                'balance'         => $wallet->balance,
                'bonus_balance'   => $wallet->bonus_balance,
                'total_deposited' => $wallet->total_deposited,
                'total_withdrawn' => $wallet->total_withdrawn,
                'formatted'       => 'TZS ' . number_format($wallet->balance),
            ],
        ]);
    }

    // GET /api/v1/wallet/transactions
    public function transactions(Request $request): JsonResponse
    {
        $txns = Transaction::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $txns]);
    }

    // POST /api/v1/wallet/deposit/stk
    // Triggers M-Pesa STK Push on customer phone
    public function initiateStk(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'amount'  => 'required|integer|min:2000',
            'plan_id' => 'required|integer|exists:saving_plans,id',
            'phone'   => 'nullable|string', // if different from account phone
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);
        }

        $user  = $request->user();
        $phone = $request->phone ?? $user->phone;
        $phone = preg_replace('/[^0-9]/', '', ltrim($phone, '+'));

        // Verify the plan belongs to this user
        $plan = SavingPlan::where('id', $request->plan_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        try {
            $result = $this->mpesaService->stkPush(
                phone:       $phone,
                amount:      (int) $request->amount,
                planId:      $plan->id,
                description: 'KiliSmart - ' . ($plan->product->name_sw ?? 'Deposit'),
            );

            if (data_get($result, 'ResponseCode') === '0') {
                return response()->json([
                    'success'             => true,
                    'message'             => 'Angalia simu yako — ingiza PIN ya M-Pesa.',
                    'checkout_request_id' => $result['CheckoutRequestID'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'M-Pesa haikujibu vizuri. Jaribu tena.',
            ], 500);

        } catch (\Exception $e) {
            Log::error('STK Push failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Hitilafu ya mtandao. Jaribu tena.'], 500);
        }
    }
}

// ════════════════════════════════════════════════════════════
//  SAVING PLAN CONTROLLER
// ════════════════════════════════════════════════════════════
class SavingPlanController extends Controller
{
    public function __construct(protected WalletService $walletService) {}

    // GET /api/v1/plans
    public function index(Request $request): JsonResponse
    {
        $plans = SavingPlan::where('user_id', $request->user()->id)
            ->with('product:id,name,name_sw,emoji,retail_price,image_paths')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'product'        => $p->product,
                'locked_price'   => $p->locked_price,
                'target_amount'  => $p->target_amount,
                'amount_saved'   => $p->amount_saved,
                'remaining'      => $p->remaining,
                'progress_pct'   => $p->progress_pct,
                'suggested_weekly'=> $p->suggested_weekly,
                'status'         => $p->status,
                'price_locked_until' => $p->price_locked_until?->toDateString(),
                'completed_at'   => $p->completed_at?->toDateString(),
                'created_at'     => $p->created_at->format('d M Y'),
            ]);

        return response()->json(['success' => true, 'data' => $plans]);
    }

    // POST /api/v1/plans
    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'product_id'       => 'required|integer|exists:products,id',
            'suggested_weekly' => 'nullable|integer|min:2000',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);
        }

        try {
            $plan = $this->walletService->createPlan(
                user:            $request->user(),
                productId:       $request->product_id,
                suggestedWeekly: $request->suggested_weekly,
            );

            return response()->json([
                'success' => true,
                'message' => 'Mpango wa kuhifadhi umeanzishwa! Anza kuweka pesa sasa.',
                'data'    => $plan->load('product'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/plans/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $plan = SavingPlan::where('user_id', $request->user()->id)
            ->with(['product', 'transactions' => fn($q) => $q->orderByDesc('created_at')->limit(10)])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $plan]);
    }

    // DELETE /api/v1/plans/{id}
    public function cancel(Request $request, int $id): JsonResponse
    {
        $plan = SavingPlan::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($id);

        $plan->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Mpango umefutwa. Pesa iliyohifadhiwa bado iko kwenye wallet yako.',
        ]);
    }
}

// ════════════════════════════════════════════════════════════
//  WITHDRAWAL CONTROLLER
// ════════════════════════════════════════════════════════════
class WithdrawalController extends Controller
{
    public function __construct(protected WalletService $walletService) {}

    // GET /api/v1/withdrawals
    public function index(Request $request): JsonResponse
    {
        $withdrawals = WithdrawalRequest::where('user_id', $request->user()->id)
            ->with('savingPlan.product:id,name_sw')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $withdrawals]);
    }

    // POST /api/v1/withdrawals
    public function request(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'plan_id'        => 'required|integer|exists:saving_plans,id',
            'amount'         => 'required|integer|min:5000',
            'payout_phone'   => 'required|string',
            'payout_channel' => 'in:mpesa,tigo,airtel',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);
        }

        try {
            $wr = $this->walletService->requestWithdrawal(
                user:          $request->user(),
                planId:        $request->plan_id,
                amount:        $request->amount,
                payoutPhone:   $request->payout_phone,
                payoutChannel: $request->payout_channel ?? 'mpesa',
            );

            return response()->json([
                'success' => true,
                'message' => 'Ombi la kutoa pesa limepokelewa. Litashughulikiwa ndani ya saa 24.',
                'data'    => [
                    'id'          => $wr->id,
                    'requested'   => 'TZS ' . number_format($wr->amount_requested),
                    'fee'         => 'TZS ' . number_format($wr->fee_amount),
                    'you_receive' => 'TZS ' . number_format($wr->net_payout),
                    'cooling_ok'  => $wr->cooling_off_ok,
                    'status'      => $wr->status,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/withdrawals/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $wr = WithdrawalRequest::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json(['success' => true, 'data' => $wr]);
    }
}

// ════════════════════════════════════════════════════════════
//  MPESA CONTROLLER
//  Handles all callbacks from Safaricom
// ════════════════════════════════════════════════════════════
class MpesaController extends Controller
{
    public function __construct(protected MpesaService $mpesaService) {}

    // POST /api/v1/mpesa/stk-callback
    public function stkCallback(Request $request): \Illuminate\Http\Response
    {
        Log::info('M-Pesa STK Callback received', $request->all());

        try {
            $this->mpesaService->handleStkCallback($request->all());
        } catch (\Exception $e) {
            Log::error('STK Callback error', ['error' => $e->getMessage()]);
        }

        // Always return 200 to Safaricom — never return error or they keep retrying
        return response('OK', 200);
    }

    // POST /api/v1/mpesa/c2b-validate
    public function c2bValidate(Request $request): JsonResponse
    {
        // Accept all C2B payments
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    // POST /api/v1/mpesa/c2b-confirm
    public function c2bConfirm(Request $request): \Illuminate\Http\Response
    {
        Log::info('M-Pesa C2B Confirm', $request->all());

        try {
            $this->mpesaService->handleC2bConfirm($request->all());
        } catch (\Exception $e) {
            Log::error('C2B Confirm error', ['error' => $e->getMessage()]);
        }

        return response('OK', 200);
    }

    // POST /api/v1/mpesa/b2c-result
    public function b2cResult(Request $request): \Illuminate\Http\Response
    {
        Log::info('M-Pesa B2C Result', $request->all());

        try {
            $this->mpesaService->handleB2cResult($request->all());
        } catch (\Exception $e) {
            Log::error('B2C Result error', ['error' => $e->getMessage()]);
        }

        return response('OK', 200);
    }

    // POST /api/v1/mpesa/b2c-timeout
    public function b2cTimeout(Request $request): \Illuminate\Http\Response
    {
        Log::warning('M-Pesa B2C Timeout', $request->all());
        return response('OK', 200);
    }
}

// ════════════════════════════════════════════════════════════
//  ADMIN CONTROLLER
// ════════════════════════════════════════════════════════════
class AdminController extends Controller
{
    public function __construct(protected WalletService $walletService) {}

    // GET /api/v1/admin/overview
    public function overview(): JsonResponse
    {
        $data = [
            'total_customers'   => \App\Models\User::where('status','active')->count(),
            'total_float'       => Wallet::sum('balance'),
            'active_plans'      => SavingPlan::where('status','active')->count(),
            'fulfillment_queue' => FulfillmentOrder::where('status','queued')->count(),
            'pending_withdrawals'=> WithdrawalRequest::where('status','pending')->count(),
            'inactive_customers'=> SavingPlan::where('status','active')
                ->whereDoesntHave('transactions', fn($q) =>
                    $q->where('type','deposit')->where('created_at','>=',now()->subDays(14))
                )->distinct('user_id')->count('user_id'),
            'month_deposits'    => Transaction::where('type','deposit')
                ->whereMonth('created_at', now()->month)->sum('amount'),
            'month_fulfillments'=> FulfillmentOrder::where('status','delivered')
                ->whereMonth('delivered_at', now()->month)->count(),
            'month_revenue'     => FulfillmentOrder::where('status','delivered')
                ->whereMonth('delivered_at', now()->month)->sum('margin'),
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /api/v1/admin/customers
    public function customers(Request $request): JsonResponse
    {
        $customers = \App\Models\User::with('wallet')
            ->withCount(['savingPlans as active_plans' => fn($q) => $q->where('status','active')])
            ->when($request->search, fn($q) =>
                $q->where('full_name','like','%'.$request->search.'%')
                  ->orWhere('phone','like','%'.$request->search.'%')
            )
            ->when($request->district, fn($q) => $q->where('district', $request->district))
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json(['success' => true, 'data' => $customers]);
    }

    // GET /api/v1/admin/fulfillment
    public function fulfillmentQueue(): JsonResponse
    {
        $orders = FulfillmentOrder::with(['user:id,full_name,phone,district','product:id,name_sw,emoji'])
            ->whereIn('status',['queued','sourcing','quality_check'])
            ->orderBy('queued_at')
            ->get();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    // PUT /api/v1/admin/fulfillment/{id}/status
    public function updateFulfillmentStatus(Request $request, int $id): JsonResponse
    {
        $order = FulfillmentOrder::findOrFail($id);
        $valid = ['sourcing','quality_check','dispatched','delivered','failed'];

        if (!in_array($request->status, $valid)) {
            return response()->json(['success' => false, 'message' => 'Status si sahihi'], 422);
        }

        $timestamps = [
            'sourcing'      => ['sourcing_at' => now()],
            'dispatched'    => ['dispatched_at' => now()],
            'delivered'     => ['delivered_at' => now()],
        ];

        $order->update(array_merge(
            ['status' => $request->status],
            $timestamps[$request->status] ?? []
        ));

        // Mark saving plan as fulfilled
        if ($request->status === 'delivered') {
            $order->savingPlan?->update(['status' => 'fulfilled', 'fulfilled_at' => now()]);
            // Send WhatsApp to customer
            app(WhatsAppService::class)->send($order->user, 'product_delivered', [
                'name'         => $order->user->full_name,
                'product_name' => $order->product->name_sw,
                'order_number' => $order->order_number,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Status imesasishwa.']);
    }

    // GET /api/v1/admin/withdrawals
    public function withdrawals(): JsonResponse
    {
        $wrs = WithdrawalRequest::with('user:id,full_name,phone')
            ->with('savingPlan.product:id,name_sw')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $wrs]);
    }

    // POST /api/v1/admin/withdrawals/{id}/approve
    public function approveWithdrawal(Request $request, int $id): JsonResponse
    {
        $wr = WithdrawalRequest::findOrFail($id);

        try {
            $this->walletService->approveWithdrawal($wr, $request->user()->id);
            return response()->json(['success' => true, 'message' => 'Kutoa pesa kumeidhinishwa. M-Pesa inatumwa.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // POST /api/v1/admin/withdrawals/{id}/reject
    public function rejectWithdrawal(Request $request, int $id): JsonResponse
    {
        $wr = WithdrawalRequest::where('status', 'pending')->findOrFail($id);
        $wr->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason ?? 'Imekataliwa na admin.',
        ]);

        // Refund back to plan
        $wr->savingPlan?->increment('amount_saved', $wr->amount_requested);
        $wr->user->wallet?->increment('balance', $wr->amount_requested);

        app(WhatsAppService::class)->send($wr->user, 'withdrawal_rejected', [
            'name'   => $wr->user->full_name,
            'reason' => $wr->rejection_reason,
        ]);

        return response()->json(['success' => true, 'message' => 'Ombi limekataliwa. Pesa imerejesha.']);
    }

    // GET /api/v1/admin/reports/financial
    public function financialReport(): JsonResponse
    {
        $data = [
            'total_float'            => Wallet::sum('balance'),
            'total_deposited_ever'   => Wallet::sum('total_deposited'),
            'total_withdrawn_ever'   => Wallet::sum('total_withdrawn'),
            'this_month_deposits'    => Transaction::where('type','deposit')->whereMonth('created_at',now()->month)->sum('amount'),
            'this_month_withdrawals' => Transaction::where('type','withdrawal')->whereMonth('created_at',now()->month)->sum('amount'),
            'this_month_fees'        => Transaction::where('type','withdrawal_fee')->whereMonth('created_at',now()->month)->sum('amount'),
            'this_month_margin'      => FulfillmentOrder::where('status','delivered')->whereMonth('delivered_at',now()->month)->sum('margin'),
            'total_orders_fulfilled' => FulfillmentOrder::where('status','delivered')->count(),
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    // POST /api/v1/admin/whatsapp/bulk
    public function sendBulkWhatsapp(Request $request): JsonResponse
    {
        // Inactive customers — no deposit in 14+ days
        $plans = SavingPlan::where('status', 'active')
            ->whereDoesntHave('transactions', fn($q) =>
                $q->where('type','deposit')->where('created_at','>=',now()->subDays(14))
            )
            ->with('user','product')
            ->get();

        $count = 0;
        foreach ($plans as $plan) {
            app(WhatsAppService::class)->send($plan->user, 'weekly_reminder', [
                'name'         => $plan->user->full_name,
                'product_name' => $plan->product->name_sw,
                'remaining'    => 'TZS ' . number_format($plan->remaining),
                'pct'          => $plan->progress_pct,
            ]);
            $count++;
        }

        return response()->json(['success' => true, 'message' => "Ujumbe wa WhatsApp umetumwa kwa wateja {$count}."]);
    }

    // Products management
    public function products(): JsonResponse
    {
        $products = \App\Models\Product::with('category','supplier')
            ->withCount(['plans as active_savers' => fn($q) => $q->where('status','active')])
            ->orderBy('sort_order')->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function createProduct(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'category_id'    => 'required|exists:categories,id',
            'supplier_id'    => 'nullable|exists:suppliers,id',
            'name'           => 'required|string',
            'name_sw'        => 'required|string',
            'retail_price'   => 'required|integer|min:1000',
            'wholesale_price'=> 'required|integer|min:1000',
        ]);
        if ($v->fails()) return response()->json(['success'=>false,'message'=>$v->errors()->first()],422);

        $product = \App\Models\Product::create(array_merge(
            $request->all(),
            ['slug' => \Str::slug($request->name . '-' . time())]
        ));

        return response()->json(['success' => true, 'data' => $product], 201);
    }

    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $product = \App\Models\Product::findOrFail($id);
        $product->update($request->all());
        return response()->json(['success' => true, 'data' => $product]);
    }

    public function hideProduct(int $id): JsonResponse
    {
        \App\Models\Product::findOrFail($id)->update(['status' => 'hidden']);
        return response()->json(['success' => true, 'message' => 'Bidhaa imefichiwa.']);
    }

    public function suppliers(): JsonResponse
    {
        return response()->json(['success'=>true,'data'=>\App\Models\Supplier::all()]);
    }

    public function createSupplier(Request $request): JsonResponse
    {
        $supplier = \App\Models\Supplier::create($request->all());
        return response()->json(['success'=>true,'data'=>$supplier],201);
    }

    // Stubs for remaining endpoints
    public function plans()          { return response()->json(['success'=>true,'data'=>SavingPlan::with('user','product')->paginate(30)]); }
    public function inactivePlans()  { return $this->sendBulkWhatsapp(request()); }
    public function customerDetail(int $id) { return response()->json(['success'=>true,'data'=>\App\Models\User::with('wallet','savingPlans.product','transactions')->findOrFail($id)]); }
    public function updateCustomerStatus(Request $request, int $id) {
        \App\Models\User::findOrFail($id)->update(['status'=>$request->status]);
        return response()->json(['success'=>true,'message'=>'Status imesasishwa.']);
    }
    public function monthlyReport() { return $this->financialReport(); }
}
