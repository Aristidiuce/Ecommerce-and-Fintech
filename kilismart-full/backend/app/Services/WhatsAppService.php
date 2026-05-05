<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsappNotification;
use Illuminate\Support\Facades\{Http, Log};

// ════════════════════════════════════════════════════════════
//  WHATSAPP SERVICE — 360dialog Business API
//  All customer notifications go through here.
//  Templates must be pre-approved by Meta before use.
// ════════════════════════════════════════════════════════════
class WhatsAppService
{
    protected string $apiKey;
    protected string $baseUrl;

    // ── WhatsApp Message Templates ───────────────────────────
    // These must match EXACTLY what you submitted to Meta for approval.
    // Variables are replaced before sending.
    protected array $templates = [
        'welcome' => [
            'name' => 'kilismart_welcome',
            'body' => "Karibu KiliSmart, {name}! 🎉\n\nAkaunti yako imefunguliwa na umepata bonus ya TZS {bonus} kwenye wallet yako.\n\nTembelea kilismart.co.tz kuanza kuhifadhi.",
        ],
        'deposit_confirmed' => [
            'name' => 'kilismart_deposit',
            'body' => "Habari {name}! 💰\n\nAmana ya TZS {amount} imepokewa.\n{plan_name}: {pct}% imehifadhiwa.\n\nSalio: TZS {balance}\n\nEndelea hivyo! 💪",
        ],
        'plan_100_reached' => [
            'name' => 'kilismart_complete',
            'body' => "HONGERA {name}! 🎉🎊\n\nUmefika 100% kwa {product_name}!\n\nOrder: {order_number}\n\nTutawasiliana nawe ndani ya saa 24 kupanga delivery. Asante kwa kutuamini! 🙏",
        ],
        'weekly_reminder' => [
            'name' => 'kilismart_reminder',
            'body' => "Habari {name}! 👋\n\nUnahifadhi kwa {product_name}.\nImebaki TZS {remaining} ({pct}% tayari).\n\nWeka kidogo leo — hata TZS 2,000 inasaidia!\n\nKilismart.co.tz",
        ],
        'withdrawal_approved' => [
            'name' => 'kilismart_withdrawal_ok',
            'body' => "Habari {name}!\n\nOmbi lako la kutoa pesa limeidhinishwa. ✅\n\nUtapokea: TZS {net_amount}\nAda ya mchakato: TZS {fee}\n\nPesa itafika kwenye M-Pesa yako ndani ya dakika 30.",
        ],
        'withdrawal_rejected' => [
            'name' => 'kilismart_withdrawal_no',
            'body' => "Habari {name},\n\nOmbi lako la kutoa pesa halikuweza kushughulikiwa.\n\nSababu: {reason}\n\nPesa yako imerejesha kwenye mpango wako. Wasiliana nasi kwa msaada zaidi.",
        ],
        'product_dispatched' => [
            'name' => 'kilismart_dispatched',
            'body' => "Habari {name}! 🚚\n\n{product_name} imesafirishwa!\n\nOrder: {order_number}\nDelivery: Leo au kesho\n\nAgent wetu atawasiliana nawe. Asante!",
        ],
        'product_delivered' => [
            'name' => 'kilismart_delivered',
            'body' => "Asante {name}! 🙏✅\n\n{product_name} imepokelewa salama.\n\nTunatumaia kukuona tena KiliSmart. Alika marafiki wako — pata TZS 2,000 bonus!\n\nkilismart.co.tz",
        ],
        'referral_bonus' => [
            'name' => 'kilismart_referral',
            'body' => "Hongera {name}! 🎁\n\nUmepata bonus ya TZS {bonus} kwa kumwelekeza rafiki KiliSmart.\n\nBonus iko tayari kwenye wallet yako sasa hivi!",
        ],
    ];

    public function __construct()
    {
        $this->apiKey  = config('services.whatsapp.api_key', '');
        $this->baseUrl = config('services.whatsapp.base_url', 'https://waba.360dialog.io/v1');
    }

    // ── Send a notification ──────────────────────────────────
    public function send(User $user, string $template, array $variables = []): void
    {
        // Respect user preference
        if (!$user->whatsapp_notifications) return;
        if (!isset($this->templates[$template])) {
            Log::warning("WhatsApp template not found: {$template}");
            return;
        }

        $tpl     = $this->templates[$template];
        $phone   = $this->formatPhone($user->phone);
        $body    = $this->interpolate($tpl['body'], $variables);

        // Log in DB first (so we have a record even if sending fails)
        $record = WhatsappNotification::create([
            'user_id'   => $user->id,
            'phone'     => $phone,
            'template'  => $template,
            'variables' => $variables,
            'status'    => 'queued',
        ]);

        // Send via 360dialog
        try {
            $response = Http::withHeaders([
                'D360-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $phone,
                'type'              => 'text',
                'text'              => ['body' => $body],
            ]);

            if ($response->successful()) {
                $record->update([
                    'status'      => 'sent',
                    'external_id' => $response->json('messages.0.id'),
                    'sent_at'     => now(),
                ]);
                Log::info("WhatsApp sent: {$template} → {$phone}");
            } else {
                $record->update(['status' => 'failed']);
                Log::error("WhatsApp failed: {$template}", $response->json());
            }

        } catch (\Exception $e) {
            $record->update(['status' => 'failed']);
            Log::error("WhatsApp exception: {$e->getMessage()}");
        }
    }

    // ── Bulk send to multiple users ──────────────────────────
    public function sendBulk(array $users, string $template, array $variables = []): int
    {
        $sent = 0;
        foreach ($users as $user) {
            $this->send($user, $template, $variables);
            $sent++;
            usleep(200000); // 200ms delay between messages to avoid rate limiting
        }
        return $sent;
    }

    // ── Helpers ──────────────────────────────────────────────
    protected function interpolate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }

    protected function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', ltrim($phone, '+'));
        if (str_starts_with($phone, '0')) $phone = '255' . substr($phone, 1);
        if (!str_starts_with($phone, '255')) $phone = '255' . $phone;
        return $phone;
    }
}

// ════════════════════════════════════════════════════════════
//  SMS SERVICE — Africa's Talking
//  Used for OTP delivery only.
//  WhatsApp is used for all other notifications.
// ════════════════════════════════════════════════════════════
class SmsService
{
    protected string $username;
    protected string $apiKey;

    public function __construct()
    {
        $this->username = config('services.africastalking.username', 'sandbox');
        $this->apiKey   = config('services.africastalking.api_key', '');
    }

    public function send(string $phone, string $message): bool
    {
        $baseUrl = $this->username === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        try {
            $response = Http::withHeaders([
                'apiKey'       => $this->apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ])->asForm()->post($baseUrl, [
                'username' => $this->username,
                'to'       => $phone,
                'message'  => $message,
                'from'     => config('services.africastalking.sender_id', 'KiliSmart'),
            ]);

            if ($response->successful()) {
                Log::info("SMS sent to {$phone}");
                return true;
            }

            Log::error("SMS failed to {$phone}", $response->json());
            return false;

        } catch (\Exception $e) {
            Log::error("SMS exception: {$e->getMessage()}");
            return false;
        }
    }
}
