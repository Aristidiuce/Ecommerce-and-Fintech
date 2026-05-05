<?php
// ============================================================
//  KiliSmart — Complete Database Schema
//  Run: php artisan migrate
//  All tables in dependency order
// ============================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────
        // 1. USERS  (customers)
        // ──────────────────────────────────────────
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Contact
            $table->string('phone', 20)->unique();          // +255712000001
            $table->string('phone_verified_at')->nullable();
            $table->string('delivery_phone', 20)->nullable();

            // Identity
            $table->string('full_name');
            $table->enum('id_type', ['nida', 'driving_license']);
            $table->string('id_number')->unique();           // NIDA or license number
            $table->string('id_photo_path')->nullable();     // stored in Spaces
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'prefer_not_to_say'])->nullable();

            // Location
            $table->string('region')->default('Kilimanjaro');
            $table->string('district');                      // Moshi Mjini, Hai, Rombo …
            $table->string('ward');
            $table->string('street')->nullable();
            $table->string('house_description')->nullable(); // landmark for delivery

            // Employment
            $table->string('job_type');                      // Mkulima, Biashara, etc.
            $table->string('job_other')->nullable();
            $table->string('income_range')->nullable();      // TZS 200K–500K
            $table->string('payday_cycle')->nullable();

            // Auth
            $table->string('password');
            $table->string('remember_token', 100)->nullable();

            // Referral
            $table->string('referral_code', 10)->unique();   // AMINA24
            $table->foreignId('referred_by')->nullable()->constrained('users');

            // Status
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->boolean('whatsapp_notifications')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('district');
            $table->index('status');
        });

        // ──────────────────────────────────────────
        // 2. OTP CODES
        // ──────────────────────────────────────────
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('code', 6);
            $table->enum('purpose', ['registration', 'login', 'withdrawal']);
            $table->boolean('used')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['phone', 'code']);
        });

        // ──────────────────────────────────────────
        // 3. PERSONAL ACCESS TOKENS  (Sanctum)
        // ──────────────────────────────────────────
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // ──────────────────────────────────────────
        // 4. WALLETS
        // ──────────────────────────────────────────
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);  // in TZS cents (×100)
            $table->unsignedBigInteger('bonus_balance')->default(0); // welcome bonus
            $table->unsignedBigInteger('total_deposited')->default(0);
            $table->unsignedBigInteger('total_withdrawn')->default(0);
            $table->timestamps();
        });

        // ──────────────────────────────────────────
        // 5. CATEGORIES
        // ──────────────────────────────────────────
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();               // smart-phones
            $table->string('name');                         // Smart Phones
            $table->string('name_sw');                      // Simu za Kisasa
            $table->string('icon', 10)->nullable();         // 📱
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ──────────────────────────────────────────
        // 6. SUPPLIERS
        // ──────────────────────────────────────────
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('location');                     // Moshi Mjini
            $table->integer('lead_days')->default(2);       // avg delivery to us
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ──────────────────────────────────────────
        // 7. PRODUCTS
        // ──────────────────────────────────────────
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();

            $table->string('name');
            $table->string('name_sw');                      // Swahili name
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('description_sw')->nullable();
            $table->string('emoji', 10)->nullable();

            // Pricing
            $table->unsignedBigInteger('retail_price');     // TZS (e.g. 280000)
            $table->unsignedBigInteger('wholesale_price');  // what we pay supplier
            $table->unsignedBigInteger('delivery_fee')->default(5000);

            // Price lock
            $table->integer('price_lock_days')->default(60);

            // Images (stored in DigitalOcean Spaces)
            $table->json('image_paths')->nullable();

            // Specs shown on product page
            $table->json('specs')->nullable();              // [{label, value}]

            // Status
            $table->enum('status', ['active', 'hidden', 'out_of_stock'])->default('active');
            $table->enum('badge', ['hot', 'new', 'sale', ''])->default('');
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('status');
        });

        // ──────────────────────────────────────────
        // 8. SAVING PLANS
        // ──────────────────────────────────────────
        Schema::create('saving_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();

            // Locked price at time of plan creation
            $table->unsignedBigInteger('locked_price');     // retail_price snapshot
            $table->timestamp('price_locked_until');        // locked_at + 60 days

            // Progress
            $table->unsignedBigInteger('amount_saved')->default(0); // running total
            $table->unsignedBigInteger('target_amount');    // = locked_price

            // Suggested deposit
            $table->unsignedBigInteger('suggested_weekly')->nullable();

            // Status
            $table->enum('status', [
                'active',       // saving in progress
                'completed',    // 100% reached, awaiting fulfillment
                'fulfilled',    // product delivered
                'cancelled',    // user cancelled / withdrew all
                'price_expired' // price lock expired before 100%
            ])->default('active');

            $table->timestamp('completed_at')->nullable();  // hit 100%
            $table->timestamp('fulfilled_at')->nullable();  // delivered
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('product_id');

            // Max 3 active plans per user — enforced in service layer
        });

        // ──────────────────────────────────────────
        // 9. TRANSACTIONS
        // ──────────────────────────────────────────
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('saving_plan_id')->nullable()->constrained();

            $table->enum('type', [
                'deposit',          // M-Pesa deposit in
                'withdrawal',       // customer withdrew money out
                'withdrawal_fee',   // the 5% fee kept by KiliSmart
                'bonus',            // welcome bonus, referral bonus
                'fulfillment',      // money moved to pay supplier
                'refund',           // admin-initiated refund
            ]);

            $table->enum('direction', ['credit', 'debit']); // credit=money in, debit=money out
            $table->unsignedBigInteger('amount');            // TZS
            $table->unsignedBigInteger('fee')->default(0);   // withdrawal fee amount
            $table->unsignedBigInteger('net_amount');        // amount after fee

            // Running balance after this transaction
            $table->unsignedBigInteger('balance_after');

            // Payment channel
            $table->enum('channel', ['mpesa', 'tigo', 'airtel', 'system'])->default('system');
            $table->string('mobile_number', 20)->nullable(); // which number was used

            // M-Pesa / telco reference
            $table->string('external_ref')->nullable()->unique(); // M-Pesa transaction ID
            $table->string('internal_ref')->unique();             // KS-TXN-000001

            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('pending');
            $table->text('notes')->nullable();                    // admin notes

            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('external_ref');
            $table->index('status');
        });

        // ──────────────────────────────────────────
        // 10. WITHDRAWAL REQUESTS
        // ──────────────────────────────────────────
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('saving_plan_id')->constrained();
            $table->foreignId('transaction_id')->nullable()->constrained();

            $table->unsignedBigInteger('amount_requested');  // gross amount
            $table->unsignedBigInteger('fee_amount');        // 5%
            $table->unsignedBigInteger('net_payout');        // what customer receives

            $table->string('payout_phone', 20);              // M-Pesa number to pay
            $table->enum('payout_channel', ['mpesa', 'tigo', 'airtel'])->default('mpesa');

            // Cooling-off: last deposit must be 7+ days ago
            $table->timestamp('last_deposit_at')->nullable();
            $table->boolean('cooling_off_ok')->default(false);

            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->string('mpesa_receipt')->nullable();     // B2C confirmation code
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // ──────────────────────────────────────────
        // 11. FULFILLMENT ORDERS
        // ──────────────────────────────────────────
        Schema::create('fulfillment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();         // KS-0001
            $table->foreignId('saving_plan_id')->unique()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();

            $table->unsignedBigInteger('amount_paid');       // from wallet
            $table->unsignedBigInteger('supplier_cost');     // wholesale price
            $table->unsignedBigInteger('margin');            // profit

            // Delivery
            $table->string('delivery_address');
            $table->string('delivery_district');
            $table->string('delivery_phone', 20);
            $table->string('delivery_agent')->nullable();    // boda boda name
            $table->string('delivery_agent_phone', 20)->nullable();

            // Quality check
            $table->boolean('quality_checked')->default(false);
            $table->string('quality_photo_path')->nullable();
            $table->timestamp('quality_checked_at')->nullable();

            // Status flow
            $table->enum('status', [
                'queued',       // plan hit 100%, waiting for processing
                'sourcing',     // contacting supplier
                'quality_check',// product received, checking quality
                'dispatched',   // sent to customer
                'delivered',    // customer confirmed receipt
                'failed',       // could not fulfill
            ])->default('queued');

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sourcing_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->string('customer_signature_path')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['status']);
            $table->index('user_id');
        });

        // ──────────────────────────────────────────
        // 12. MPESA CALLBACKS  (audit log)
        // ──────────────────────────────────────────
        Schema::create('mpesa_callbacks', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['stk_push', 'c2b', 'b2c']);
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->integer('result_code')->nullable();
            $table->string('result_desc')->nullable();
            $table->json('raw_payload');                     // full M-Pesa JSON
            $table->enum('status', ['received', 'processed', 'failed'])->default('received');
            $table->foreignId('transaction_id')->nullable()->constrained();
            $table->timestamps();

            $table->index('mpesa_receipt_number');
            $table->index('checkout_request_id');
        });

        // ──────────────────────────────────────────
        // 13. WHATSAPP NOTIFICATIONS LOG
        // ──────────────────────────────────────────
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('phone', 20);
            $table->enum('template', [
                'deposit_confirmed',
                'plan_100_reached',
                'weekly_reminder',
                'withdrawal_approved',
                'withdrawal_rejected',
                'product_dispatched',
                'product_delivered',
                'referral_bonus',
                'welcome',
            ]);
            $table->json('variables')->nullable();           // template placeholders
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->string('external_id')->nullable();      // 360dialog message ID
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'template']);
        });

        // ──────────────────────────────────────────
        // 14. REFERRALS
        // ──────────────────────────────────────────
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users'); // who invited
            $table->foreignId('referred_id')->constrained('users'); // who joined
            $table->boolean('bonus_paid_referrer')->default(false);
            $table->boolean('bonus_paid_referred')->default(false);
            $table->timestamp('first_deposit_at')->nullable();      // trigger for bonus
            $table->timestamps();
        });

        // ──────────────────────────────────────────
        // 15. ADMIN USERS
        // ──────────────────────────────────────────
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['owner', 'manager', 'support'])->default('support');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('whatsapp_notifications');
        Schema::dropIfExists('mpesa_callbacks');
        Schema::dropIfExists('fulfillment_orders');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('saving_plans');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('users');
    }
};
