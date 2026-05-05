<?php
// ============================================================
//  KiliSmart — All Models
//  Each model in its own class block.
//  In real Laravel, each goes in its own file.
//  For deployment: split into individual files in app/Models/
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ── WALLET ──────────────────────────────────────────────────
class Wallet extends Model
{
    protected $fillable = [
        'user_id','balance','bonus_balance',
        'total_deposited','total_withdrawn',
    ];

    public function user() { return $this->belongsTo(User::class); }

    // Balance in TZS (stored as integer, no decimals)
    public function getFormattedBalanceAttribute(): string
    {
        return 'TZS ' . number_format($this->balance);
    }
}

// ── CATEGORY ────────────────────────────────────────────────
class Category extends Model
{
    protected $fillable = ['slug','name','name_sw','icon','sort_order','is_active'];

    public function products() { return $this->hasMany(Product::class); }
}

// ── SUPPLIER ────────────────────────────────────────────────
class Supplier extends Model
{
    protected $fillable = [
        'name','contact_person','phone','location',
        'lead_days','status','notes',
    ];

    public function products() { return $this->hasMany(Product::class); }
}

// ── PRODUCT ─────────────────────────────────────────────────
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id','supplier_id','name','name_sw','slug',
        'description','description_sw','emoji',
        'retail_price','wholesale_price','delivery_fee',
        'price_lock_days','image_paths','specs',
        'status','badge','sort_order',
    ];

    protected $casts = [
        'image_paths' => 'array',
        'specs'       => 'array',
    ];

    public function category()  { return $this->belongsTo(Category::class); }
    public function supplier()  { return $this->belongsTo(Supplier::class); }
    public function plans()     { return $this->hasMany(SavingPlan::class); }

    public function getMarginAttribute(): int
    {
        return $this->retail_price - $this->wholesale_price;
    }

    public function getMarginPctAttribute(): float
    {
        return round(($this->margin / $this->retail_price) * 100, 1);
    }

    public function getActiveSaversCountAttribute(): int
    {
        return $this->plans()->where('status', 'active')->count();
    }
}

// ── SAVING PLAN ─────────────────────────────────────────────
class SavingPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id','product_id','locked_price','price_locked_until',
        'target_amount','amount_saved','suggested_weekly',
        'status','completed_at','fulfilled_at','cancelled_at',
    ];

    protected $casts = [
        'price_locked_until' => 'datetime',
        'completed_at'       => 'datetime',
        'fulfilled_at'       => 'datetime',
        'cancelled_at'       => 'datetime',
    ];

    public function user()        { return $this->belongsTo(User::class); }
    public function product()     { return $this->belongsTo(Product::class); }
    public function transactions(){ return $this->hasMany(Transaction::class); }
    public function fulfillment() { return $this->hasOne(FulfillmentOrder::class); }
    public function withdrawals() { return $this->hasMany(WithdrawalRequest::class); }

    public function getProgressPctAttribute(): float
    {
        if ($this->target_amount == 0) return 0;
        return round(($this->amount_saved / $this->target_amount) * 100, 1);
    }

    public function getRemainingAttribute(): int
    {
        return max(0, $this->target_amount - $this->amount_saved);
    }

    public function isComplete(): bool
    {
        return $this->amount_saved >= $this->target_amount;
    }

    public function isPriceLocked(): bool
    {
        return $this->price_locked_until && $this->price_locked_until->isFuture();
    }
}

// ── TRANSACTION ─────────────────────────────────────────────
class Transaction extends Model
{
    protected $fillable = [
        'user_id','saving_plan_id','type','direction',
        'amount','fee','net_amount','balance_after',
        'channel','mobile_number','external_ref','internal_ref',
        'status','notes',
    ];

    public function user()       { return $this->belongsTo(User::class); }
    public function savingPlan() { return $this->belongsTo(SavingPlan::class); }
}

// ── WITHDRAWAL REQUEST ───────────────────────────────────────
class WithdrawalRequest extends Model
{
    protected $fillable = [
        'user_id','saving_plan_id','transaction_id',
        'amount_requested','fee_amount','net_payout',
        'payout_phone','payout_channel',
        'last_deposit_at','cooling_off_ok',
        'status','mpesa_receipt','approved_by',
        'approved_at','rejection_reason',
    ];

    protected $casts = [
        'cooling_off_ok' => 'boolean',
        'approved_at'    => 'datetime',
        'last_deposit_at'=> 'datetime',
    ];

    public function user()       { return $this->belongsTo(User::class); }
    public function savingPlan() { return $this->belongsTo(SavingPlan::class); }
    public function approver()   { return $this->belongsTo(User::class, 'approved_by'); }
}

// ── FULFILLMENT ORDER ────────────────────────────────────────
class FulfillmentOrder extends Model
{
    protected $fillable = [
        'order_number','saving_plan_id','user_id','product_id','supplier_id',
        'amount_paid','supplier_cost','margin',
        'delivery_address','delivery_district','delivery_phone',
        'delivery_agent','delivery_agent_phone',
        'quality_checked','quality_photo_path','quality_checked_at',
        'status','queued_at','sourcing_at','dispatched_at','delivered_at',
        'customer_signature_path','notes',
    ];

    protected $casts = [
        'quality_checked'    => 'boolean',
        'quality_checked_at' => 'datetime',
        'queued_at'          => 'datetime',
        'sourcing_at'        => 'datetime',
        'dispatched_at'      => 'datetime',
        'delivered_at'       => 'datetime',
    ];

    public function user()       { return $this->belongsTo(User::class); }
    public function product()    { return $this->belongsTo(Product::class); }
    public function supplier()   { return $this->belongsTo(Supplier::class); }
    public function savingPlan() { return $this->belongsTo(SavingPlan::class); }
}

// ── MPESA CALLBACK ───────────────────────────────────────────
class MpesaCallback extends Model
{
    protected $fillable = [
        'type','merchant_request_id','checkout_request_id',
        'mpesa_receipt_number','amount','phone_number',
        'result_code','result_desc','raw_payload','status','transaction_id',
    ];

    protected $casts = ['raw_payload' => 'array'];
}

// ── WHATSAPP NOTIFICATION ────────────────────────────────────
class WhatsappNotification extends Model
{
    protected $fillable = [
        'user_id','phone','template','variables',
        'status','external_id','sent_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'sent_at'   => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
}

// ── REFERRAL ─────────────────────────────────────────────────
class Referral extends Model
{
    protected $fillable = [
        'referrer_id','referred_id',
        'bonus_paid_referrer','bonus_paid_referred','first_deposit_at',
    ];

    protected $casts = [
        'bonus_paid_referrer' => 'boolean',
        'bonus_paid_referred' => 'boolean',
        'first_deposit_at'    => 'datetime',
    ];

    public function referrer() { return $this->belongsTo(User::class, 'referrer_id'); }
    public function referred() { return $this->belongsTo(User::class, 'referred_id'); }
}

// ── OTP CODE ─────────────────────────────────────────────────
class OtpCode extends Model
{
    protected $fillable = ['phone','code','purpose','used','expires_at'];

    protected $casts = [
        'used'       => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        return !$this->used && $this->expires_at->isFuture();
    }
}
