<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes;

    protected $fillable = [
        'phone','phone_verified_at','delivery_phone',
        'full_name','id_type','id_number','id_photo_path',
        'date_of_birth','gender',
        'region','district','ward','street','house_description',
        'job_type','job_other','income_range','payday_cycle',
        'password','referral_code','referred_by',
        'status','whatsapp_notifications',
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts = [
        'date_of_birth'       => 'date',
        'whatsapp_notifications' => 'boolean',
        'phone_verified_at'   => 'datetime',
    ];

    // ── Relationships ──
    public function wallet()       { return $this->hasOne(Wallet::class); }
    public function savingPlans()  { return $this->hasMany(SavingPlan::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }
    public function withdrawals()  { return $this->hasMany(WithdrawalRequest::class); }
    public function referrer()     { return $this->belongsTo(User::class, 'referred_by'); }
    public function referrals()    { return $this->hasMany(Referral::class, 'referrer_id'); }

    // ── Helpers ──
    public function activePlans()
    {
        return $this->savingPlans()->where('status', 'active');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->role === 'owner';
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($user) {
            if (empty($user->referral_code)) {
                $user->referral_code = strtoupper(substr(preg_replace('/[^A-Z]/', '',
                    strtoupper($user->full_name ?? 'USER')), 0, 5)
                    . rand(10, 99));
            }
        });
        // Auto-create wallet on user creation
        static::created(function ($user) {
            Wallet::create(['user_id' => $user->id, 'balance' => 0]);
        });
    }
}
