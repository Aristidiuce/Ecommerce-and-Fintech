<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    public function __construct(protected MpesaService $mpesa) {}

    /** STK Push callback — called by Safaricom after customer pays */
    public function stkCallback(Request $request): \Illuminate\Http\Response
    {
        Log::info('STK callback received', $request->all());
        try {
            $this->mpesa->handleStkCallback($request->all());
        } catch (\Exception $e) {
            Log::error('STK callback error', ['error' => $e->getMessage()]);
        }
        return response('OK', 200);
    }

    /** C2B Validate — Safaricom asks if we accept this payment */
    public function c2bValidate(Request $request)
    {
        Log::info('C2B validate', $request->all());
        try {
            $result = $this->mpesa->validateC2b($request->all());
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
        }
    }

    /** C2B Confirm — Safaricom confirms payment completed */
    public function c2bConfirm(Request $request): \Illuminate\Http\Response
    {
        Log::info('C2B confirm', $request->all());
        try {
            $this->mpesa->confirmC2b($request->all());
        } catch (\Exception $e) {
            Log::error('C2B confirm error', ['error' => $e->getMessage()]);
        }
        return response('OK', 200);
    }

    /** B2C Result — Safaricom notifies us payout succeeded/failed */
    public function b2cResult(Request $request): \Illuminate\Http\Response
    {
        Log::info('B2C result', $request->all());
        try {
            $this->mpesa->handleB2cResult($request->all());
        } catch (\Exception $e) {
            Log::error('B2C result error', ['error' => $e->getMessage()]);
        }
        return response('OK', 200);
    }

    /** B2C Timeout — payment timed out, needs manual review */
    public function b2cTimeout(Request $request): \Illuminate\Http\Response
    {
        Log::warning('B2C timeout', $request->all());
        $this->mpesa->handleB2cTimeout($request->all());
        return response('OK', 200);
    }

    /** Initiate STK Push for a deposit */
    public function initiateStk(Request $request)
    {
        $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'phone'   => 'required|string',
            'amount'  => 'required|integer|min:2000',
            'plan_id' => 'required|integer|exists:saving_plans,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);

        try {
            $result = $this->mpesa->stkPush($request->phone, $request->amount, $request->plan_id, 'KiliSmart Deposit');
            // Store plan mapping so callback can find it
            $this->mpesa->storePlanCheckoutMapping($result['CheckoutRequestID'] ?? '', $request->plan_id);
            return response()->json(['success' => true, 'message' => 'Angalia simu yako — ingiza PIN yako ya M-Pesa.', 'checkout_id' => $result['CheckoutRequestID'] ?? null]);
        } catch (\Exception $e) {
            Log::error('STK Push initiation error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'M-Pesa haikujibu. Jaribu tena.'], 500);
        }
    }
}
