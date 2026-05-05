# KiliSmart — Savings-First E-Commerce Platform
# Kilimanjaro Region, Tanzania
# ============================================================

## WHAT IS KILISMART?

KiliSmart is a savings-first e-commerce platform built specifically
for the Kilimanjaro Region of Tanzania. Customers browse products
freely, then save small amounts via M-Pesa until they reach 100%
of the product price — at which point KiliSmart arranges delivery.

  No credit. No interest. No risk for the buyer.
  No inventory risk for KiliSmart. 100% pre-funded before purchase.


## PROJECT STRUCTURE

  kilismart-full/
  ├── frontend/                    ← All HTML pages (6 files)
  │   ├── index.html               ← Public homepage + product catalogue
  │   ├── login.html               ← Login (customer/admin/supplier)
  │   ├── register.html            ← 6-step registration form
  │   ├── dashboard.html           ← Customer wallet + saving plans
  │   ├── admin.html               ← Admin dashboard (full operations)
  │   └── supplier.html            ← Supplier portal
  │
  ├── backend/                     ← Laravel 11 API
  │   ├── app/
  │   │   ├── Models/              ← All 12 Eloquent models
  │   │   ├── Http/Controllers/    ← API controllers (Auth, Products, Wallet...)
  │   │   ├── Services/            ← MpesaService, WalletService, WhatsAppService
  │   │   └── Console/Commands/    ← Scheduled tasks (reminders, expiry checks)
  │   ├── database/
  │   │   ├── migrations/          ← All 15 database tables
  │   │   └── seeders/             ← 9 categories, 6 suppliers, 15 products
  │   ├── routes/api.php           ← All 40+ API endpoints
  │   ├── .env.example             ← Environment template
  │   └── composer.json            ← PHP dependencies
  │
  └── docs/
      ├── install/
      │   ├── INSTALL_MAC.md       ← macOS setup guide
      │   ├── INSTALL_WINDOWS.md   ← Windows (WSL2 + XAMPP) guide
      │   └── INSTALL_LINUX.md     ← Ubuntu server guide
      └── api/
          └── API_REFERENCE.md     ← All API endpoints documented


## QUICK START

  macOS:    See docs/install/INSTALL_MAC.md
  Windows:  See docs/install/INSTALL_WINDOWS.md
  Linux:    See docs/install/INSTALL_LINUX.md

  Minimum 3 commands to get running (after prerequisites):
    composer install
    cp .env.example .env && php artisan key:generate
    php artisan migrate --seed && php artisan serve


## DEFAULT CREDENTIALS (after seeding)

  Admin:    phone=+255700000001  password=Admin@KiliSmart2024!
  Customer: phone=+255700000002  password=Test@1234!

  ⚠️  CHANGE THESE IMMEDIATELY after first login in production!


## TECHNOLOGY STACK

  Backend:    PHP 8.3 + Laravel 11
  Database:   MySQL 8.0
  Cache/Queue: Redis 7
  Frontend:   Pure HTML/CSS/JS (no framework — fast on slow connections)
  Payments:   M-Pesa Daraja API (Safaricom)
  USSD:       Africa's Talking
  WhatsApp:   360dialog Business API
  Storage:    DigitalOcean Spaces (or local for testing)
  SSL:        Let's Encrypt (free, auto-renewed)
  Monitoring: Sentry (free tier)


## API BASE URL

  Test:  https://test.kilismart.co.tz/api/v1
  Local: http://localhost:8000/api/v1


## KEY BUSINESS RULES (enforced in code)

  Minimum deposit:       TZS 2,000
  Minimum withdrawal:    TZS 5,000
  Withdrawal fee:        5% of amount withdrawn
  Cooling-off period:    7 days since last deposit (before withdrawal)
  Max active plans:      3 per customer
  Price lock:            60 days from plan creation
  Welcome bonus:         TZS 2,000 on registration
  Referral bonus:        TZS 2,000 per referral (both sides)


## SAMPLE DATA (after db:seed)

  Categories: 9 (Smart Phones, Home Appliances, BodaBoda, Kilimo,
               Solar, Furniture, Computers, School, Beauty)

  Suppliers: 6 (TechMoshi, Moshi Furniture, HomeGoods, Solar Africa,
               SleepWell, AgriTools)

  Products: 15 with full specs, descriptions in Swahili, delivery info,
            warranty details, what's in the box


## HOMEPAGE FEATURES

  ✓ Hero slider (4 slides, auto-advance, swipe, pause on hover)
  ✓ Flash sale countdown timer
  ✓ Category bar with filter
  ✓ Featured products strip
  ✓ Category sections (each category own row)
  ✓ AliExpress-style product detail modal
    - Left thumbnail strip
    - Large zoomable product image
    - Right panel: rating, price, progress, colors, trust strip
    - Tabs: Description, Specs, Savings Plans, Reviews, Delivery
    - Star rating breakdown bars
    - Verified customer reviews
    - Sticky CTA bar
  ✓ Recently viewed products strip
  ✓ WhatsApp floating button
  ✓ Mobile-first responsive (works on any phone)


## WHAT'S READY TO BUILD NEXT (Phase 2B+)

  The following are stubbed in the codebase and ready to implement:

  [ ] Tigo Pesa + Airtel Money integration (same pattern as M-Pesa)
  [ ] Product image upload to DigitalOcean Spaces (admin dashboard)
  [ ] Supplier login and supplier API (separate auth token)
  [ ] Product reviews — customers submit after delivery
  [ ] Wishlist — save products without starting a plan
  [ ] Push notifications — browser/PWA notifications
  [ ] Promotions + discount codes
  [ ] Delivery tracking with real-time boda boda location
  [ ] Analytics dashboard — conversion funnel, deposit heatmap
  [ ] Mobile app API (React Native or Flutter)
  [ ] Referral leaderboard
  [ ] USSD — fully built, needs Africa's Talking shortcode to activate
  [ ] Monthly bank reconciliation tool
  [ ] Multi-language admin (English + Swahili toggle)


## MONTHLY COSTS (LIVE PRODUCTION)

  Your own server:    TZS 0/month (hardware paid once)
  Domain:             ~TZS 50,000/year (~TZS 4,200/month)
  SSL:                TZS 0 (Let's Encrypt, free forever)
  M-Pesa API:         TZS 0 (Safaricom does not charge per transaction)
  Africa's Talking:   ~TZS 15–25 per SMS OTP
  360dialog WhatsApp: ~TZS 5 per message sent
  Sentry monitoring:  TZS 0 (free tier, 5K errors/month)
  ─────────────────────────────────────────────────
  Total:              ~TZS 4,200/month + usage-based SMS/WhatsApp

  Revenue at 200 customers, avg product TZS 250,000, 20% margin:
  Per fulfillment: TZS 50,000 margin
  30 fulfillments/month = TZS 1,500,000/month gross margin
  Break-even: Month 8–10 conservatively


## SUPPORT & NEXT STEPS

  Documentation:  docs/ folder
  API reference:  docs/api/API_REFERENCE.md
  Deployment:     docs/install/INSTALL_LINUX.md
