<?php

namespace App\Console\Commands;

use App\Models\SavingPlan;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * KiliSmart Scheduled Commands
 * Run via: php artisan schedule:run (triggered by cron every minute)
 *
 * All commands registered in app/Console/Kernel.php:
 *   $schedule->command('kilismart:weekly-reminders')->weekdays()->at('09:00');
 *   $schedule->command('kilismart:check-price-expiry')->daily()->at('00:01');
 *   $schedule->command('kilismart:inactive-followup')->weekly()->mondays()->at('08:00');
 */

// ── WEEKLY REMINDER COMMAND ──────────────────────────────────
class SendWeeklyReminders extends Command
{
    protected $signature   = 'kilismart:weekly-reminders';
    protected $description = 'Send weekly WhatsApp reminders to active savers';

    public function handle(WhatsAppService $whatsApp): void
    {
        $this->info('Sending weekly reminders...');

        $plans = SavingPlan::where('status', 'active')
            ->with(['user', 'product'])
            ->get();

        $sent = 0;
        foreach ($plans as $plan) {
            if (!$plan->user || !$plan->user->whatsapp_notifications) continue;

            $whatsApp->send($plan->user, 'weekly_reminder', [
                'name'         => $plan->user->full_name,
                'product_name' => $plan->product->name_sw ?? $plan->product->name,
                'remaining'    => 'TZS ' . number_format($plan->remaining),
                'pct'          => $plan->progress_pct,
            ]);

            $sent++;
            usleep(200000); // 200ms between messages
        }

        $this->info("✅ Sent {$sent} reminders.");
        Log::info("Weekly reminders sent: {$sent}");
    }
}

// ── PRICE EXPIRY CHECKER ─────────────────────────────────────
class CheckPriceExpiry extends Command
{
    protected $signature   = 'kilismart:check-price-expiry';
    protected $description = 'Flag saving plans where price lock has expired';

    public function handle(): void
    {
        $expired = SavingPlan::where('status', 'active')
            ->where('price_locked_until', '<', now())
            ->get();

        foreach ($expired as $plan) {
            // Check if product price has changed
            $currentPrice = $plan->product->retail_price;
            if ($currentPrice !== $plan->locked_price) {
                $plan->update([
                    'status'         => 'price_expired',
                    'target_amount'  => $currentPrice, // update to new price
                ]);
                Log::info("Price expired for plan {$plan->id}", [
                    'old_price' => $plan->locked_price,
                    'new_price' => $currentPrice,
                ]);
            } else {
                // Price unchanged — extend lock
                $plan->update([
                    'price_locked_until' => now()->addDays($plan->product->price_lock_days)
                ]);
            }
        }

        $this->info("Checked {$expired->count()} expired price locks.");
    }
}

// ── INACTIVE CUSTOMER FOLLOWUP ───────────────────────────────
class FollowupInactiveCustomers extends Command
{
    protected $signature   = 'kilismart:inactive-followup';
    protected $description = 'Send WhatsApp to customers inactive for 14+ days';

    public function handle(WhatsAppService $whatsApp): void
    {
        $plans = SavingPlan::where('status', 'active')
            ->whereDoesntHave('transactions', fn($q) =>
                $q->where('type', 'deposit')
                  ->where('created_at', '>=', now()->subDays(14))
            )
            ->with(['user', 'product'])
            ->get();

        $sent = 0;
        foreach ($plans as $plan) {
            if (!$plan->user?->whatsapp_notifications) continue;
            $whatsApp->send($plan->user, 'weekly_reminder', [
                'name'         => $plan->user->full_name,
                'product_name' => $plan->product->name_sw ?? $plan->product->name,
                'remaining'    => 'TZS ' . number_format($plan->remaining),
                'pct'          => $plan->progress_pct,
            ]);
            $sent++;
            usleep(300000);
        }

        $this->info("Inactive follow-up sent to {$sent} customers.");
        Log::info("Inactive followup: {$sent}");
    }
}
