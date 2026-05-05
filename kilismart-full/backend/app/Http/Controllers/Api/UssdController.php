<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SavingPlan;
use Illuminate\Http\Request;

/**
 * KiliSmart — USSD Controller
 *
 * Handles sessions from Africa's Talking USSD gateway.
 * Customer dials *384*XXX# on ANY phone — no internet needed.
 *
 * Session flow:
 *   *384*XXX# → Main menu
 *     1. Angalia Salio        (Check balance)
 *     2. Mipango Yangu        (My saving plans)
 *     3. Weka Pesa            (Deposit instructions)
 *     4. Toa Pesa             (Withdrawal request)
 *     5. Msaada               (Help / WhatsApp)
 */
class UssdController extends Controller
{
    public function handle(Request $request)
    {
        $sessionId   = $request->input('sessionId');
        $serviceCode = $request->input('serviceCode');
        $phoneNumber = $request->input('phoneNumber');
        $text        = $request->input('text', '');

        // Normalize phone
        $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (str_starts_with($phone, '0')) $phone = '255' . substr($phone, 1);

        $user = User::where('phone', '+' . $phone)
            ->orWhere('phone', $phone)
            ->first();

        // Split the input text to track menu navigation
        $levels = $text === '' ? [] : explode('*', $text);
        $level  = count($levels);

        $response = $this->route($levels, $level, $user, $phone);

        return response($response)->header('Content-Type', 'text/plain');
    }

    protected function route(array $levels, int $level, ?User $user, string $phone): string
    {
        // ── Level 0: Main menu ──
        if ($level === 0) {
            return $this->mainMenu($user);
        }

        $choice = $levels[0];

        // ── Level 1: Main menu choices ──
        if ($level === 1) {
            return match ($choice) {
                '1' => $this->checkBalance($user),
                '2' => $this->myPlans($user),
                '3' => $this->depositInstructions($user, $phone),
                '4' => $this->withdrawMenu($user),
                '5' => $this->helpMenu(),
                default => "END Chaguo si sahihi. Jaribu tena."
            };
        }

        // ── Level 2: Sub-menu choices ──
        if ($level === 2) {
            return match ($choice) {
                '4' => $this->handleWithdrawChoice($levels[1], $user),
                default => "END Asante kwa kutumia KiliSmart!"
            };
        }

        return "END Asante kwa kutumia KiliSmart!";
    }

    // ──────────────────────────────────────────
    //  MAIN MENU
    // ──────────────────────────────────────────
    protected function mainMenu(?User $user): string
    {
        if (!$user) {
            return "CON Karibu KiliSmart!\n" .
                   "Nambari yako haijasajiliwa.\n" .
                   "Tembelea kilismart.co.tz\n" .
                   "au piga: 0800 XXX XXX\n\n" .
                   "99. Badilisha lugha\n" .
                   "0.  Toka";
        }

        $wallet  = $user->wallet;
        $balance = number_format($wallet->balance ?? 0);

        return "CON Karibu {$user->full_name}!\n" .
               "Salio: TZS {$balance}\n\n" .
               "1. Angalia Salio\n" .
               "2. Mipango Yangu\n" .
               "3. Weka Pesa\n" .
               "4. Toa Pesa\n" .
               "5. Msaada";
    }

    // ──────────────────────────────────────────
    //  1. CHECK BALANCE
    // ──────────────────────────────────────────
    protected function checkBalance(?User $user): string
    {
        if (!$user) return "END Nambari yako haijasajiliwa. Tembelea kilismart.co.tz";

        $wallet  = $user->wallet;
        $balance = number_format($wallet->balance ?? 0);
        $plans   = SavingPlan::where('user_id', $user->id)->where('status', 'active')->get();

        $planLines = $plans->map(function ($p) {
            $pct = round($p->amount_saved / $p->target_amount * 100);
            $name = \Str::limit($p->product->name_sw ?? $p->product->name, 18);
            return "{$name}: {$pct}%";
        })->implode("\n");

        return "END ══ KiliSmart ══\n" .
               "Salio: TZS {$balance}\n\n" .
               ($planLines ? "Mipango:\n{$planLines}\n\n" : "Huna mipango inayoendelea.\n\n") .
               "Weka pesa: Tuma kwenye\n" .
               "M-Pesa Paybill: XXXXXXX\n" .
               "Akaunti: " . ($user->phone);
    }

    // ──────────────────────────────────────────
    //  2. MY PLANS
    // ──────────────────────────────────────────
    protected function myPlans(?User $user): string
    {
        if (!$user) return "END Nambari yako haijasajiliwa.";

        $plans = SavingPlan::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('product')
            ->get();

        if ($plans->isEmpty()) {
            return "END Huna mipango inayoendelea.\n" .
                   "Tembelea kilismart.co.tz\n" .
                   "kuchagua bidhaa yako.";
        }

        $lines = $plans->map(function ($p, $i) {
            $pct      = round($p->amount_saved / $p->target_amount * 100);
            $saved    = number_format($p->amount_saved);
            $target   = number_format($p->target_amount);
            $name     = \Str::limit($p->product->name_sw ?? $p->product->name, 20);
            return ($i + 1) . ". {$name}\n" .
                   "   TZS {$saved} / {$target} ({$pct}%)";
        })->implode("\n\n");

        return "END ══ Mipango Yangu ══\n\n{$lines}";
    }

    // ──────────────────────────────────────────
    //  3. DEPOSIT INSTRUCTIONS
    // ──────────────────────────────────────────
    protected function depositInstructions(?User $user, string $phone): string
    {
        if (!$user) return "END Jisajili kwanza: kilismart.co.tz";

        return "END ══ Jinsi ya Kuweka Pesa ══\n\n" .
               "M-Pesa:\n" .
               "Lipa Paybill: XXXXXXX\n" .
               "Akaunti: {$user->phone}\n\n" .
               "Tigo Pesa:\n" .
               "Paybill: XXXXXXX\n" .
               "Akaunti: {$user->phone}\n\n" .
               "Pesa itaingia\nmoja kwa moja kwenye\nmpango wako.";
    }

    // ──────────────────────────────────────────
    //  4. WITHDRAWAL MENU
    // ──────────────────────────────────────────
    protected function withdrawMenu(?User $user): string
    {
        if (!$user) return "END Nambari yako haijasajiliwa.";

        $plans = SavingPlan::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('product')
            ->get();

        if ($plans->isEmpty()) {
            return "END Huna mipango inayoendelea.";
        }

        $lines = $plans->map(function ($p, $i) {
            $saved = number_format($p->amount_saved);
            $name  = \Str::limit($p->product->name_sw ?? $p->product->name, 18);
            return ($i + 1) . ". {$name}\n   TZS {$saved}";
        })->implode("\n");

        return "CON Chagua mpango wa kutoa:\n\n{$lines}\n\n0. Rudi";
    }

    protected function handleWithdrawChoice(string $choice, ?User $user): string
    {
        if (!$user) return "END Nambari yako haijasajiliwa.";

        $plans = SavingPlan::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $idx = (int)$choice - 1;
        if (!isset($plans[$idx])) return "END Chaguo si sahihi.";

        $plan = $plans[$idx];

        return "END Kutoa pesa:\n" .
               "Mpango: " . \Str::limit($plan->product->name_sw ?? '', 18) . "\n" .
               "Imehifadhiwa: TZS " . number_format($plan->amount_saved) . "\n\n" .
               "Kutoa, tembelea:\n" .
               "kilismart.co.tz/dashboard\n" .
               "au piga WhatsApp:\n" .
               "0800 XXX XXX\n\n" .
               "Ada ya 5% itakatwa.";
    }

    // ──────────────────────────────────────────
    //  5. HELP
    // ──────────────────────────────────────────
    protected function helpMenu(): string
    {
        return "END ══ Msaada ══\n\n" .
               "WhatsApp: +255 XXX XXX XXX\n" .
               "Tovuti: kilismart.co.tz\n" .
               "Saa za kazi:\n" .
               "Jumatatu–Ijumaa\n" .
               "8:00 – 18:00\n\n" .
               "Asante kwa kutumia\nKiliSmart!";
    }
}
