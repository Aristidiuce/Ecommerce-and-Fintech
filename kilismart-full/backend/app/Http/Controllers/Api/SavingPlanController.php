<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\WalletService;
use App\Models\{SavingPlan, FulfillmentOrder};
use Illuminate\Http\{Request, JsonResponse};

class SavingPlanController extends Controller
{
    public function __construct(protected WalletService $wallet) {}

    public function index(Request $request): JsonResponse
    {
        $plans = $request->user()->savingPlans()->with('product')->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'product_id'       => 'required|integer|exists:products,id',
            'suggested_weekly' => 'required|integer|min:2000',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);

        try {
            $plan = $this->wallet->createPlan($request->user(), $request->product_id, $request->suggested_weekly);
            return response()->json(['success' => true, 'data' => $plan->load('product'), 'message' => 'Mpango wako umeanza! Weka amana yako ya kwanza.'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $plan = $request->user()->savingPlans()->with('product', 'transactions')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $plan]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $plan = $request->user()->savingPlans()->where('status', 'active')->findOrFail($id);
        $plan->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Mpango umefutwa. Omba kurejesha pesa ukitaka.']);
    }

    public function orderHistory(Request $request): JsonResponse
    {
        $orders = FulfillmentOrder::with('product', 'savingPlan')->where('user_id', $request->user()->id)->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function trackOrder(Request $request, int $id): JsonResponse
    {
        $order = FulfillmentOrder::with('product')->where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json(['success' => true, 'data' => $order]);
    }
}
