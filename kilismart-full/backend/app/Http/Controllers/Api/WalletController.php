<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\{Request, JsonResponse};

class WalletController extends Controller
{
    public function __construct(protected WalletService $wallet) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('wallet', 'savingPlans.product');
        return response()->json(['success' => true, 'data' => ['wallet' => $user->wallet, 'plans' => $user->savingPlans]]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $txns = $request->user()->transactions()->orderByDesc('created_at')->paginate(20);
        return response()->json(['success' => true, 'data' => $txns]);
    }

    public function withdrawalEstimate(Request $request): JsonResponse
    {
        $amount = (int) $request->get('amount', 0);
        $min = (int) config('mpesa.min_withdrawal', 5000);
        if ($amount < $min) {
            return response()->json(['success' => false, 'message' => "Kiwango cha chini ni TZS ".number_format($min)], 422);
        }
        $fee = (int) round($amount * 0.05);
        return response()->json(['success' => true, 'data' => [
            'amount_requested' => $amount,
            'kilismart_fee'    => $fee,
            'net_to_customer'  => $amount - $fee,
            'fee_pct'          => 5,
        ]]);
    }
}
