<?php

namespace App\Services;

use Illuminate\Support\Facades\{Http, Cache, Log};
use App\Models\{Transaction, WithdrawalRequest};

/**
 * KiliSmart — M-Pesa Daraja Service
 *
 * Covers: STK Push (deposits), C2B (Paybill), B2C (withdrawals)
 *
 * Get credentials from: developer.safaricom.co.ke
 * Sandbox: sandbox.safaricom.co.ke | Live: api.safaricom.co.ke
 *
 * NOTE: For Airtel/Tigo/Halopesa — use their separate APIs.
 * Room is left in routes/api.php for those integrations.
 */
class MpesaService
{
    protected string $baseUrl;
    protected string $shortcode;
    protected string $passkey;

    public function __construct()
    {
        $this->baseUrl  = config('mpesa.env') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
        $this->shortcode = config('mpesa.shortcode');
        $this->passkey   = config('mpesa.passkey');
    }

    // ── Access Token (cached 55 min) ─────────────────────────
    protected function getAccessToken(): string
    {
        return Cache::remember('mpesa_token', 3300, function () {
            $r = Http::withBasicAuth(config('mpesa.consumer_key'), config('mpesa.consumer_secret'))
                ->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");
            if ($r->failed()) throw new \Exception('M-Pesa token failed');
            return $r->json('access_token');
        });
    }

    // ── STK PUSH (Lipa na M-Pesa) ────────────────────────────
    // Sends USSD popup to customer's phone. Customer enters PIN to pay.
    public function stkPush(string $phone, int $amount, int $planId, string $desc = 'KiliSmart'): array
    {
        $token = $this->getAccessToken();
        $ts    = now()->format('YmdHis');
        $pwd   = base64_encode($this->shortcode . $this->passkey . $ts);
        $phone = $this->normalizePhone($phone);

        $r = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                'BusinessShortCode' => $this->shortcode,
                'Password'          => $pwd,
                'Timestamp'         => $ts,
                'TransactionType'   => 'CustomerPayBillOnline',
                'Amount'            => $amount,
                'PartyA'            => $phone,
                'PartyB'            => $this->shortcode,
                'PhoneNumber'       => $phone,
                'CallBackURL'       => config('mpesa.callback_base') . '/api/v1/mpesa/stk-callback',
                'AccountReference'  => 'KS-PLAN-' . $planId,
                'TransactionDesc'   => substr($desc, 0, 20),
            ]);

        if ($r->failed()) throw new \Exception('STK Push failed: ' . $r->json('errorMessage', 'Unknown error'));
        Log::info('STK Push sent', ['phone' => $phone, 'amount' => $amount, 'plan' => $planId]);
        return $r->json();
    }

    // ── STK CALLBACK HANDLER ─────────────────────────────────
    public function handleStkCallback(array $payload): void
    {
        $resultCode = data_get($payload, 'Body.stkCallback.ResultCode');
        if ((int) $resultCode !== 0) { Log::info('STK cancelled', ['code' => $resultCode]); return; }

        $meta      = collect(data_get($payload, 'Body.stkCallback.CallbackMetadata.Item', []))->pluck('Value', 'Name');
        $amount    = (int) $meta->get('Amount');
        $mpesaCode = $meta->get('MpesaReceiptNumber');
        $phone     = (string) $meta->get('PhoneNumber');
        $checkoutId= data_get($payload, 'Body.stkCallback.CheckoutRequestID');
        $planId    = Cache::get('mpesa_checkout_plan_' . $checkoutId);

        if (Transaction::where('external_ref', $mpesaCode)->exists()) { Log::warning('Duplicate ignored', ['code' => $mpesaCode]); return; }

        app(WalletService::class)->creditFromMpesa('+' . ltrim($phone, '+'), $amount, $mpesaCode, $planId);
        Log::info('STK payment credited', compact('phone','amount','mpesaCode'));
    }

    // ── C2B REGISTER URLs ────────────────────────────────────
    public function registerC2bUrls(): array
    {
        $r = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/mpesa/c2b/v1/registerurl", [
                'ShortCode'       => $this->shortcode,
                'ResponseType'    => 'Completed',
                'ConfirmationURL' => config('mpesa.callback_base') . '/api/v1/mpesa/c2b-confirm',
                'ValidationURL'   => config('mpesa.callback_base') . '/api/v1/mpesa/c2b-validate',
            ]);
        return $r->json();
    }

    // ── C2B VALIDATE ─────────────────────────────────────────
    public function validateC2b(array $payload): array
    {
        Log::info('C2B validation', $payload);
        return ['ResultCode' => '0', 'ResultDesc' => 'Accepted'];
    }

    // ── C2B CONFIRM ──────────────────────────────────────────
    public function confirmC2b(array $payload): void
    {
        $amount    = (int) data_get($payload, 'TransAmount');
        $mpesaCode = data_get($payload, 'TransID');
        $phone     = data_get($payload, 'MSISDN');
        $billRef   = data_get($payload, 'BillRefNumber');
        if (Transaction::where('external_ref', $mpesaCode)->exists()) return;
        preg_match('/(\d+)/', $billRef ?? '', $m);
        $planId = !empty($m[1]) ? (int) $m[1] : null;
        app(WalletService::class)->creditFromMpesa('+255' . ltrim($phone, '0'), $amount, $mpesaCode, $planId);
    }

    // ── B2C PAYOUT (withdrawal) ──────────────────────────────
    // Requires separate B2C approval from Safaricom.
    // Apply at: developer.safaricom.co.ke → B2C
    public function b2cPayout(string $phone, int $amount, int $withdrawalId): array
    {
        $r = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/mpesa/b2c/v1/paymentrequest", [
                'InitiatorName'      => config('mpesa.b2c_initiator'),
                'SecurityCredential' => config('mpesa.b2c_security_credential'),
                'CommandID'          => 'BusinessPayment',
                'Amount'             => $amount,
                'PartyA'             => $this->shortcode,
                'PartyB'             => $this->normalizePhone($phone),
                'Remarks'            => 'KiliSmart Withdrawal',
                'QueueTimeOutURL'    => config('mpesa.callback_base') . '/api/v1/mpesa/b2c-timeout',
                'ResultURL'          => config('mpesa.callback_base') . '/api/v1/mpesa/b2c-result',
                'Occasion'           => 'KS-WD-' . $withdrawalId,
            ]);
        if ($r->failed()) throw new \Exception('B2C failed: ' . $r->json('errorMessage', 'Unknown'));
        Log::info('B2C sent', ['phone' => $phone, 'amount' => $amount, 'wd' => $withdrawalId]);
        return $r->json();
    }

    // ── B2C RESULT ───────────────────────────────────────────
    public function handleB2cResult(array $payload): void
    {
        $resultCode = data_get($payload, 'Result.ResultCode');
        $occasion   = data_get($payload, 'Result.ReferenceData.ReferenceItem.Value', '');
        preg_match('/KS-WD-(\d+)/', $occasion, $m);
        $wr = WithdrawalRequest::find($m[1] ?? null);
        if (!$wr) return;

        if ((int) $resultCode === 0) {
            $params  = collect(data_get($payload, 'Result.ResultParameters.ResultParameter'))->pluck('Value', 'Key');
            $wr->update(['status' => 'paid', 'mpesa_receipt' => $params->get('TransactionID'), 'paid_at' => now()]);
        } else {
            $wr->update(['status' => 'failed']);
            $wr->savingPlan?->increment('amount_saved', $wr->amount_requested);
            $wr->user->wallet?->increment('balance', $wr->amount_requested);
            Log::error('B2C failed — refunded', ['wd' => $wr->id, 'code' => $resultCode]);
        }
    }

    public function handleB2cTimeout(array $payload): void { Log::warning('B2C timeout', $payload); }

    // Store checkout→plan mapping so callback can find the plan
    public function storePlanCheckoutMapping(string $checkoutId, int $planId): void
    {
        Cache::put('mpesa_checkout_plan_' . $checkoutId, $planId, 3600);
    }

    // Normalize to Safaricom format: 2557XXXXXXXX
    public function normalizePhone(string $phone): string
    {
        $p = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($p, '0'))   return '255' . substr($p, 1);
        if (str_starts_with($p, '255')) return $p;
        return '255' . $p;
    }
}
