<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OtpCode;
use App\Models\Referral;
use App\Services\WalletService;
use App\Services\WhatsAppService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        protected WalletService  $walletService,
        protected WhatsAppService $whatsApp,
        protected SmsService     $sms,
    ) {}

    // ──────────────────────────────────────────
    //  STEP 1 — Send OTP
    //  POST /api/v1/auth/send-otp
    //  Body: { phone: "+255712000001" }
    // ──────────────────────────────────────────
    public function sendOtp(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'phone'   => 'required|string|min:10|max:20',
            'purpose' => 'in:registration,login,withdrawal',
        ]);
        if ($v->fails()) return $this->error($v->errors()->first(), 422);

        $phone   = $this->normalizePhone($request->phone);
        $purpose = $request->purpose ?? 'registration';
        $code    = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Invalidate old OTPs for this phone
        OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->update(['used' => true]);

        OtpCode::create([
            'phone'      => $phone,
            'code'       => $code,
            'purpose'    => $purpose,
            'used'       => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send via Africa's Talking SMS
        $this->sms->send(
            phone:   $phone,
            message: "KiliSmart: Nambari yako ya kuthibitisha ni {$code}. Inaisha baada ya dakika 10. Usishirikishe na mtu yeyote."
        );

        return $this->success([
            'phone'      => $phone,
            'expires_in' => 600, // seconds
        ], 'OTP imetumwa kwa ' . $phone);
    }

    // ──────────────────────────────────────────
    //  STEP 2 — Verify OTP
    //  POST /api/v1/auth/verify-otp
    //  Body: { phone, code, purpose }
    // ──────────────────────────────────────────
    public function verifyOtp(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'phone'   => 'required|string',
            'code'    => 'required|string|size:6',
            'purpose' => 'in:registration,login,withdrawal',
        ]);
        if ($v->fails()) return $this->error($v->errors()->first(), 422);

        $phone   = $this->normalizePhone($request->phone);
        $purpose = $request->purpose ?? 'registration';

        $otp = OtpCode::where('phone', $phone)
            ->where('code', $request->code)
            ->where('purpose', $purpose)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return $this->error('OTP si sahihi au imekwisha muda. Tuma tena.', 422);
        }

        $otp->update(['used' => true]);

        return $this->success([
            'verified' => true,
            'phone'    => $phone,
            'token'    => 'verified_' . Str::random(32), // short-lived token to allow registration step
        ], 'OTP imethibitishwa');
    }

    // ──────────────────────────────────────────
    //  STEP 3 — Register
    //  POST /api/v1/auth/register
    //  Full registration form (all 6 steps)
    // ──────────────────────────────────────────
    public function register(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'phone'         => 'required|string|unique:users,phone',
            'full_name'     => 'required|string|min:3|max:100',
            'id_type'       => 'required|in:nida,driving_license',
            'id_number'     => 'required|string|unique:users,id_number',
            'date_of_birth' => 'required|date|before:today',
            'gender'        => 'required|in:male,female,prefer_not_to_say',
            'district'      => 'required|string',
            'ward'          => 'required|string',
            'street'        => 'nullable|string',
            'house_description' => 'nullable|string',
            'delivery_phone'=> 'nullable|string',
            'job_type'      => 'required|string',
            'income_range'  => 'required|string',
            'payday_cycle'  => 'nullable|string',
            'password'      => 'required|string|min:8|confirmed',
            'referral_code' => 'nullable|string|exists:users,referral_code',
            'whatsapp_notifications' => 'boolean',
        ]);

        if ($v->fails()) {
            return $this->error($v->errors()->first(), 422);
        }

        $phone = $this->normalizePhone($request->phone);

        return DB::transaction(function () use ($request, $phone) {

            // Find referrer if code provided
            $referrer = null;
            if ($request->referral_code) {
                $referrer = User::where('referral_code', $request->referral_code)->first();
            }

            // Create user
            $user = User::create([
                'phone'                 => $phone,
                'phone_verified_at'     => now(),
                'delivery_phone'        => $request->delivery_phone
                                           ? $this->normalizePhone($request->delivery_phone)
                                           : null,
                'full_name'             => $request->full_name,
                'id_type'               => $request->id_type,
                'id_number'             => $request->id_number,
                'date_of_birth'         => $request->date_of_birth,
                'gender'                => $request->gender,
                'region'                => 'Kilimanjaro',
                'district'              => $request->district,
                'ward'                  => $request->ward,
                'street'                => $request->street,
                'house_description'     => $request->house_description,
                'job_type'              => $request->job_type,
                'job_other'             => $request->job_other,
                'income_range'          => $request->income_range,
                'payday_cycle'          => $request->payday_cycle,
                'password'              => Hash::make($request->password),
                'referred_by'           => $referrer?->id,
                'status'                => 'active',
                'whatsapp_notifications'=> $request->whatsapp_notifications ?? true,
            ]);

            // Wallet is auto-created in User boot()
            // Give welcome bonus
            $this->walletService->giveWelcomeBonus($user);

            // Link referral
            if ($referrer) {
                Referral::create([
                    'referrer_id' => $referrer->id,
                    'referred_id' => $user->id,
                ]);
            }

            // Issue API token
            $token = $user->createToken('mobile')->plainTextToken;

            return $this->success([
                'user'  => $this->userResource($user),
                'token' => $token,
                'wallet_balance' => $user->wallet->balance,
            ], 'Akaunti imefunguliwa! Karibu KiliSmart.');
        });
    }

    // ──────────────────────────────────────────
    //  LOGIN
    //  POST /api/v1/auth/login
    //  Body: { phone, password }
    // ──────────────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'phone'    => 'required|string',
            'password' => 'required|string',
        ]);
        if ($v->fails()) return $this->error($v->errors()->first(), 422);

        $phone = $this->normalizePhone($request->phone);
        $user  = User::where('phone', $phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Nambari ya simu au nenosiri si sahihi.', 401);
        }

        if ($user->status === 'suspended') {
            return $this->error('Akaunti yako imefungwa. Wasiliana nasi.', 403);
        }

        // Revoke old tokens and issue new one
        $user->tokens()->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        return $this->success([
            'user'           => $this->userResource($user),
            'token'          => $token,
            'wallet_balance' => $user->wallet?->balance ?? 0,
            'active_plans'   => $user->activePlans()->count(),
        ], 'Umeingia. Karibu!');
    }

    // ──────────────────────────────────────────
    //  LOGOUT
    //  POST /api/v1/auth/logout
    // ──────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Umetoka salama.');
    }

    // ──────────────────────────────────────────
    //  ME — get current user profile
    //  GET /api/v1/auth/me
    // ──────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('wallet');
        return $this->success($this->userResource($user));
    }

    // ──────────────────────────────────────────
    //  UPDATE PROFILE
    //  PUT /api/v1/profile
    // ──────────────────────────────────────────
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $v = Validator::make($request->all(), [
            'full_name'       => 'sometimes|string|min:3',
            'ward'            => 'sometimes|string',
            'street'          => 'sometimes|nullable|string',
            'house_description' => 'sometimes|nullable|string',
            'delivery_phone'  => 'sometimes|nullable|string',
            'job_type'        => 'sometimes|string',
            'income_range'    => 'sometimes|string',
            'whatsapp_notifications' => 'sometimes|boolean',
        ]);
        if ($v->fails()) return $this->error($v->errors()->first(), 422);

        $user->update($request->only([
            'full_name','ward','street','house_description',
            'delivery_phone','job_type','income_range',
            'whatsapp_notifications',
        ]));

        return $this->success($this->userResource($user), 'Wasifu umesasishwa.');
    }

    // ──────────────────────────────────────────
    //  FORGOT / RESET PASSWORD
    // ──────────────────────────────────────────
    public function forgotPassword(Request $request): JsonResponse
    {
        $phone = $this->normalizePhone($request->phone ?? '');
        $user  = User::where('phone', $phone)->first();

        // Always return success to avoid user enumeration
        if ($user) {
            $this->sendOtp(new Request(['phone' => $phone, 'purpose' => 'login']));
        }

        return $this->success(null, 'OTP ya kurekebisha nenosiri imetumwa.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'phone'    => 'required|string',
            'code'     => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);
        if ($v->fails()) return $this->error($v->errors()->first(), 422);

        $phone = $this->normalizePhone($request->phone);

        $otp = OtpCode::where('phone', $phone)
            ->where('code', $request->code)
            ->where('purpose', 'login')
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) return $this->error('OTP si sahihi au imekwisha muda.', 422);

        $user = User::where('phone', $phone)->firstOrFail();
        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete();
        $otp->update(['used' => true]);

        return $this->success(null, 'Nenosiri limebadilishwa. Ingia sasa.');
    }

    // ──────────────────────────────────────────
    //  REFERRALS
    //  GET /api/v1/referrals
    // ──────────────────────────────────────────
    public function referrals(Request $request): JsonResponse
    {
        $user = $request->user();
        $referrals = $user->referrals()
            ->with('referred:id,full_name,created_at')
            ->get()
            ->map(fn($r) => [
                'name'               => $r->referred->full_name,
                'joined_at'          => $r->referred->created_at->format('d M Y'),
                'bonus_paid'         => $r->bonus_paid_referrer,
                'first_deposit_at'   => $r->first_deposit_at?->format('d M Y'),
            ]);

        return $this->success([
            'referral_code'  => $user->referral_code,
            'total_referrals'=> $referrals->count(),
            'bonuses_earned' => $referrals->where('bonus_paid', true)->count() * 2000,
            'referrals'      => $referrals,
        ]);
    }

    // ──────────────────────────────────────────
    //  HELPERS
    // ──────────────────────────────────────────
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (str_starts_with($phone, '0')) $phone = '+255' . substr($phone, 1);
        if (!str_starts_with($phone, '+')) $phone = '+255' . $phone;
        return $phone;
    }

    private function userResource(User $user): array
    {
        return [
            'id'             => $user->id,
            'full_name'      => $user->full_name,
            'phone'          => $user->phone,
            'district'       => $user->district,
            'ward'           => $user->ward,
            'job_type'       => $user->job_type,
            'referral_code'  => $user->referral_code,
            'status'         => $user->status,
            'wallet_balance' => $user->wallet?->balance ?? 0,
        ];
    }

    private function success($data, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    private function error(string $message, int $code = 400): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null], $code);
    }
}
