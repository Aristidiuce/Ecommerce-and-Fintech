# KiliSmart API Reference
# Base URL: https://test.kilismart.co.tz/api/v1
# ============================================================
# All responses: { "success": true/false, "message": "...", "data": {...} }
# Auth: Bearer token in Authorization header
# Content-Type: application/json
# ============================================================


## AUTHENTICATION

  All public endpoints need no authentication.
  All customer endpoints need: Authorization: Bearer {token}
  All admin endpoints need: Authorization: Bearer {token} + admin phone


──────────────────────────────────────────────────────────────
  PUBLIC ENDPOINTS (no auth required)
──────────────────────────────────────────────────────────────

POST /auth/send-otp
  Body: { "phone": "+255712000001", "purpose": "registration" }
  Returns: { "phone": "+255712000001", "expires_in": 600 }
  Purpose values: registration | login | withdrawal

POST /auth/verify-otp
  Body: { "phone": "+255712000001", "code": "123456", "purpose": "registration" }
  Returns: { "verified": true, "token": "verified_xxxxx" }

POST /auth/register
  Body: {
    "phone": "+255712000001",
    "full_name": "Amina Juma Moshi",
    "id_type": "nida",                         # nida | driving_license
    "id_number": "19900101234567890001",
    "date_of_birth": "1990-01-15",
    "gender": "female",                         # male | female | prefer_not_to_say
    "district": "Moshi Mjini",
    "ward": "Mji Mpya",
    "street": "Karibu na KKKT",
    "job_type": "Biashara ndogo",
    "income_range": "TZS 200,000 – 500,000",
    "password": "StrongPass123!",
    "password_confirmation": "StrongPass123!",
    "referral_code": "JOHN24",                  # optional
    "whatsapp_notifications": true
  }
  Returns: { "user": {...}, "token": "xxx", "wallet_balance": 2000 }

POST /auth/login
  Body: { "phone": "+255712000001", "password": "StrongPass123!" }
  Returns: { "user": {...}, "token": "xxx", "wallet_balance": 50000, "active_plans": 2 }

POST /auth/forgot-password
  Body: { "phone": "+255712000001" }

POST /auth/reset-password
  Body: { "phone": "...", "code": "123456", "password": "...", "password_confirmation": "..." }

GET  /products                          # All active products (with ?search=samsung &category=simu)
GET  /products/featured                 # Hot + sale products
GET  /products/category/{slug}          # Products by category slug
GET  /products/{id}                     # Single product full detail
GET  /categories                        # All categories


──────────────────────────────────────────────────────────────
  M-PESA WEBHOOKS (called by Safaricom — no auth, IP-secured)
──────────────────────────────────────────────────────────────

POST /mpesa/stk-callback               # STK Push payment result
POST /mpesa/c2b-validate               # C2B payment validation
POST /mpesa/c2b-confirm                # C2B payment confirmation
POST /mpesa/b2c-result                 # B2C payout result (withdrawal)
POST /mpesa/b2c-timeout                # B2C timeout notification

POST /ussd                             # Africa's Talking USSD handler


──────────────────────────────────────────────────────────────
  CUSTOMER ENDPOINTS (Bearer token required)
──────────────────────────────────────────────────────────────

POST /auth/logout
GET  /auth/me                          # Current user profile

PUT  /profile                          # Update profile (name, ward, delivery_phone...)
PUT  /profile/password                 # Change password

GET  /wallet                           # Wallet balance + stats
GET  /wallet/transactions              # Transaction history (paginated, 20/page)
POST /wallet/deposit/stk               # Trigger M-Pesa STK Push
  Body: { "amount": 10000, "plan_id": 1, "phone": "+255712000001" }
  Returns: { "checkout_request_id": "xxx", "message": "Angalia simu yako" }

GET  /plans                            # All my saving plans
POST /plans                            # Create new saving plan
  Body: { "product_id": 1, "suggested_weekly": 5000 }
  Returns: { saving plan with product }
GET  /plans/{id}                       # Single plan + recent transactions
DELETE /plans/{id}                     # Cancel a plan

GET  /withdrawals                      # My withdrawal history
POST /withdrawals                      # Request a withdrawal
  Body: {
    "plan_id": 1,
    "amount": 20000,
    "payout_phone": "+255712000001",
    "payout_channel": "mpesa"          # mpesa | tigo | airtel
  }
  Returns: {
    "id": 5,
    "requested": "TZS 20,000",
    "fee": "TZS 1,000",
    "you_receive": "TZS 19,000",
    "cooling_ok": true,
    "status": "pending"
  }
GET  /withdrawals/{id}                 # Single withdrawal status

GET  /referrals                        # My referral stats + list


──────────────────────────────────────────────────────────────
  ADMIN ENDPOINTS (Bearer token + admin phone required)
──────────────────────────────────────────────────────────────

GET  /admin/overview                   # Dashboard KPIs
GET  /admin/customers                  # All customers (paginated, ?search= ?district=)
GET  /admin/customers/{id}             # Customer detail + plans + transactions
PUT  /admin/customers/{id}/status      # Body: { "status": "active|suspended" }

GET  /admin/plans                      # All saving plans
GET  /admin/plans/inactive             # Plans with no deposit 14+ days

GET  /admin/fulfillment                # Orders at 100% waiting for fulfillment
PUT  /admin/fulfillment/{id}/status    # Body: { "status": "sourcing|quality_check|dispatched|delivered" }

GET  /admin/withdrawals                # All withdrawal requests
POST /admin/withdrawals/{id}/approve   # Approve — triggers M-Pesa B2C payout
POST /admin/withdrawals/{id}/reject    # Body: { "reason": "..." }

GET  /admin/products                   # All products with savers count
POST /admin/products                   # Create product
PUT  /admin/products/{id}              # Update product
DELETE /admin/products/{id}            # Hide product (soft delete)

GET  /admin/suppliers                  # All suppliers
POST /admin/suppliers                  # Add supplier

GET  /admin/reports/financial          # Financial summary
GET  /admin/reports/monthly            # Monthly breakdown

POST /admin/whatsapp/bulk              # Send bulk WhatsApp to inactive customers


──────────────────────────────────────────────────────────────
  RESPONSE FORMAT EXAMPLES
──────────────────────────────────────────────────────────────

SUCCESS:
{
  "success": true,
  "message": "Akaunti imefunguliwa! Karibu KiliSmart.",
  "data": {
    "user": {
      "id": 1,
      "full_name": "Amina Juma Moshi",
      "phone": "+255712000001",
      "district": "Moshi Mjini",
      "referral_code": "AMINA24",
      "status": "active",
      "wallet_balance": 2000
    },
    "token": "1|xxxxxxxxxxxxxxxxxxxxx"
  }
}

ERROR:
{
  "success": false,
  "message": "Nambari ya simu au nenosiri si sahihi.",
  "data": null
}

VALIDATION ERROR:
{
  "success": false,
  "message": "The phone field is required.",
  "data": null
}

PAGINATED:
{
  "success": true,
  "message": "OK",
  "data": {
    "current_page": 1,
    "data": [...],
    "per_page": 20,
    "total": 487,
    "last_page": 25
  }
}


──────────────────────────────────────────────────────────────
  TESTING WITH CURL
──────────────────────────────────────────────────────────────

# Get all products:
curl https://test.kilismart.co.tz/api/v1/products | python3 -m json.tool

# Login:
TOKEN=$(curl -s -X POST https://test.kilismart.co.tz/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone":"+255700000002","password":"Test@1234!"}' | python3 -c "import sys,json;print(json.load(sys.stdin)['data']['token'])")

echo "Token: $TOKEN"

# Get wallet (with auth):
curl https://test.kilismart.co.tz/api/v1/wallet \
  -H "Authorization: Bearer $TOKEN"

# Create saving plan:
curl -X POST https://test.kilismart.co.tz/api/v1/plans \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"product_id": 1, "suggested_weekly": 5000}'

# Request withdrawal:
curl -X POST https://test.kilismart.co.tz/api/v1/withdrawals \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"plan_id":1,"amount":5000,"payout_phone":"+255700000002","payout_channel":"mpesa"}'
