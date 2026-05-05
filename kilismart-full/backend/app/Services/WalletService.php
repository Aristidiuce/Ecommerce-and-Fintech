<?php

namespace App\Services;

use App\Models\{SavingPlan, Transaction, WithdrawalRequest, FulfillmentOrder, User};
use Illuminate\Support\Facades\{DB, Log};

/**
 * KiliSmart — Wallet Service
 * All money movements go through here.
 * Deposits via MpesaService (STK Push / C2B).
 * Withdrawals via MpesaService B2C.
 */
class WalletService
{
    public function __construct(protected MpesaService $mpesa) {}

    // ══ CREDIT WALLET FROM M-PESA PAYMENT ══════════════════════
    // Called by MpesaService after STK or C2B callback confirms.
    public function creditFromMpesa(string $phone, int $amount, string $mpesaCode, ?int $planId = null): void
    {
        DB::transaction(function () use ($phone, $amount, $mpesaCode, $planId) {
            $user   = User::where('phone', $phone)->firstOrFail();
            $wallet = $user->wallet ?? $user->wallet()->create(['balance' => 0]);
            $wallet->increment('balance', $amount);

            $txn = Transaction::create([
                'user_id'      => $user->id,
                'type'         => 'deposit',
                'amount'       => $amount,
                'channel'      => 'mpesa',
                'external_ref' => $mpesaCode,
                'status'       => 'completed',
                'description'  => 'Amana kupitia M-Pesa',
                'meta'         => ['mpesa_code' => $mpesaCode],
            ]);

            if ($planId) {
                $plan = SavingPlan::where('id', $planId)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->first();
                if ($plan) {
                    $plan->increment('amount_saved', $amount);
                    $plan->update(['last_deposit_at' => now()]);
                    $txn->update(['saving_plan_id' => $plan->id]);
                    if ($plan->fresh()->amount_saved >= $plan->target_amount) {
                        $this->triggerFulfillment($plan);
                    }
                }
            }
            Log::info('Wallet credited via M-Pesa', compact('phone', 'amount', 'mpesaCode'));
        });
    }

    // ══ CREATE SAVING PLAN (max 3 active) ══════════════════════
    public function createPlan(User $user, int $productId, int $suggestedWeekly): SavingPlan
    {
        if (SavingPlan::where('user_id', $user->id)->where('status', 'active')->count() >= 3) {
            throw new \Exception('Unaweza kuwa na mipango 3 tu inayofanya kazi kwa wakati mmoja.');
        }
        $product = \App\Models\Product::findOrFail($productId);
        return SavingPlan::create([
            'user_id'            => $user->id,
            'product_id'         => $productId,
            'target_amount'      => $product->retail_price,
            'locked_price'       => $product->retail_price,
            'amount_saved'       => 0,
            'suggested_weekly'   => $suggestedWeekly,
            'status'             => 'active',
            'price_locked_until' => now()->addDays(60),
        ]);
    }

    // ══ REQUEST WITHDRAWAL ══════════════════════════════════════
    // Min withdrawal: TZS 5,000 (to cover M-Pesa B2C charge ~TZS 50)
    public function requestWithdrawal(User $user, int $planId, int $amount, string $payoutPhone): WithdrawalRequest
    {
        $minWd = (int) config('mpesa.min_withdrawal', 5000);

        if ($amount < $minWd) {
            throw new \Exception("Kiwango cha chini cha kutoa ni TZS " . number_format($minWd) . ".");
        }
        $plan = SavingPlan::where('id', $planId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        // 7-day cooling-off period
        if ($plan->last_deposit_at && $plan->last_deposit_at->diffInDays(now()) < 7) {
            $left = 7 - $plan->last_deposit_at->diffInDays(now());
            throw new \Exception("Subiri siku {$left} zaidi baada ya amana yako ya mwisho.");
        }

        if ($amount > $plan->amount_saved) {
            throw new \Exception("Unajaribu kutoa zaidi ya ulichohifadhi (TZS " . number_format($plan->amount_saved) . ").");
        }

        // KiliSmart fee: 5% on withdrawn amount
        $fee = (int) round($amount * 0.05);
        $net = $amount - $fee;

        return DB::transaction(function () use ($user, $plan, $amount, $payoutPhone, $fee, $net) {
            $plan->decrement('amount_saved', $amount);
            $user->wallet->decrement('balance', $amount);
            Transaction::create([
                'user_id'        => $user->id,
                'saving_plan_id' => $plan->id,
                'type'           => 'withdrawal_request',
                'amount'         => $amount,
                'channel'        => 'withdrawal',
                'status'         => 'pending',
                'description'    => 'Ombi la kutoa pesa — inasubiri idhini',
            ]);
            return WithdrawalRequest::create([
                'user_id'          => $user->id,
                'saving_plan_id'   => $plan->id,
                'amount_requested' => $amount,
                'kilismart_fee'    => $fee,
                'net_to_customer'  => $net,
                'payout_phone'     => $payoutPhone,
                'status'           => 'pending',
            ]);
        });
    }

    // ══ APPROVE WITHDRAWAL — sends M-Pesa B2C ══════════════════
    public function approveWithdrawal(WithdrawalRequest $wr): array
    {
        if ($wr->status !== 'pending') throw new \Exception("Ombi hili si katika hali ya 'pending'.");
        $wr->update(['status' => 'approved', 'approved_at' => now()]);

        try {
            $result = $this->mpesa->b2cPayout(
                phone:        $wr->payout_phone,
                amount:       $wr->net_to_customer,
                withdrawalId: $wr->id,
            );
            $wr->update(['status' => 'processing', 'b2c_conversation_id' => $result['ConversationID'] ?? null]);
            return ['success' => true, 'message' => "TZS " . number_format($wr->net_to_customer) . " inatumwa kwa " . $wr->payout_phone];
        } catch (\Exception $e) {
            $wr->update(['status' => 'pending']);
            throw new \Exception('Malipo yalishindwa: ' . $e->getMessage());
        }
    }

    // ══ TRIGGER FULFILLMENT when plan hits 100% ═════════════════
    protected function triggerFulfillment(SavingPlan $plan): void
    {
        $plan->update(['status' => 'completed', 'completed_at' => now()]);
        FulfillmentOrder::firstOrCreate(['saving_plan_id' => $plan->id], [
            'user_id'    => $plan->user_id,
            'product_id' => $plan->product_id,
            'amount_paid'=> $plan->target_amount,
            'status'     => 'queued',
        ]);
        app(WhatsAppService::class)->send($plan->user, 'plan_complete', [
            'name'         => $plan->user->full_name,
            'product_name' => $plan->product->name_sw ?? $plan->product->name,
            'amount'       => number_format($plan->target_amount),
        ]);
        Log::info('Plan completed, fulfillment triggered', ['plan_id' => $plan->id]);
    }

    // ══ BONUSES ════════════════════════════════════════════════
    public function giveWelcomeBonus(User $user): void
    {
        $user->wallet->increment('balance', 2000);
        Transaction::create(['user_id' => $user->id, 'type' => 'bonus', 'amount' => 2000, 'channel' => 'internal', 'status' => 'completed', 'description' => 'Bonasi ya Kuanza — Karibu KiliSmart!']);
    }

    public function giveReferralBonus(User $referrer, User $referred): void
    {
        foreach ([$referrer, $referred] as $u) {
            $u->wallet->increment('balance', 2000);
            Transaction::create(['user_id' => $u->id, 'type' => 'bonus', 'amount' => 2000, 'channel' => 'referral', 'status' => 'completed', 'description' => 'Bonasi ya Kuwalika Rafiki']);
        }
    }
}
