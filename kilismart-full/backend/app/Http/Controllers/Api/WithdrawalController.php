<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\WalletService;
use App\Models\WithdrawalRequest;
use Illuminate\Http\{Request, JsonResponse};

class WithdrawalController extends Controller
{
    public function __construct(protected WalletService $wallet) {}

    public function index(Request $request): JsonResponse
    {
        $withdrawals = WithdrawalRequest::where('user_id', $request->user()->id)->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $withdrawals]);
    }

    public function request(Request $request): JsonResponse
    {
        $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'plan_id'      => 'required|integer',
            'amount'       => 'required|integer|min:5000',
            'payout_phone' => 'required|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);

        try {
            $wr = $this->wallet->requestWithdrawal($request->user(), $request->plan_id, $request->amount, $request->payout_phone);
            return response()->json(['success' => true, 'data' => $wr, 'message' => 'Ombi lako limepokelewa. Likataaliwa ndani ya saa 24.'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $wr = WithdrawalRequest::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json(['success' => true, 'data' => $wr]);
    }
}
