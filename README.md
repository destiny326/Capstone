# 🍔 TAMCC Foodie – Setup & Deployment Guide

## File Structure
```
tamcc_foodie/
├── assets/
│   └── logo.png              ← Copy the TAMCC Foodie logo here
├── vendor/                   ← Created by Composer (Stripe SDK)
├── .htaccess                 ← Apache security rules
├── config.php                ← App config (Azure AD + Stripe keys)
├── db.php                    ← PDO database connection
├── auth.php                  ← Session guards & role enforcement
├── schema.sql                ← Full database schema + menu data
├── login.php                 ← Student/staff Microsoft login
├── callback.php              ← Microsoft OAuth2 callback handler
├── index.php                 ← Menu browsing & cart page
├── checkout.php              ← Stripe Checkout session creator
├── order_success.php         ← Post-payment order confirmation
├── stripe_webhook.php        ← Stripe webhook listener
├── order.php                 ← Order placement API endpoint
├── my_orders.php             ← Order tracking for students/staff
├── admin_login.php           ← Admin/kitchen login (DB credentials)
├── dashboard.php             ← Admin panel (CRUD + order management)
├── logout.php                ← Session destruction & redirect
├── style.css                 ← Full application stylesheet
└── script.js                 ← Cart, search, tab interactivity
```

---

## Prerequisites
- PHP 8.1+ with extensions: pdo_mysql, curl, json, openssl
- MySQL 8.0+ or MariaDB 10.6+
- Apache 2.4+ with mod_rewrite enabled
- Composer (PHP package manager)
- HTTPS (required for Microsoft OAuth2 and Stripe)

---

## Step 1: Database Setup

```sql
-- Run schema.sql in your MySQL client:
mysql -u root -p < schema.sql
```

This creates the database, all tables, and inserts the full menu.

**Default admin credentials** (change immediately in production!):
- Username: `admin`
- Password: `Admin@1234`

To set a new hashed password:
```php
echo password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost' => 12]);
```
Then run:
```sql
UPDATE admins SET password_hash='<new_hash>' WHERE username='admin';
```

---

## Step 2: Install Stripe PHP SDK

```bash
cd /path/to/tamcc_foodie
composer require stripe/stripe-php
```

---

## Step 3: Microsoft Azure AD Configuration

1. Go to [Azure Portal](https://portal.azure.com) → **Azure Active Directory** → **App registrations** → **New registration**
2. Name: `TAMCC Foodie`
3. Supported account types: **Accounts in this organizational directory only** (your TAMCC tenant)
4. Redirect URI: `https://yourdomain.com/callback.php`
5. After registration, note the **Application (client) ID** and **Directory (tenant) ID**
6. Go to **Certificates & secrets** → **New client secret** → copy the secret value
7. Go to **API permissions** → Add `User.Read` (Microsoft Graph, Delegated)

---

## Step 4: Stripe Configuration

1. Create a [Stripe account](https://dashboard.stripe.com)
2. In **Developers → API keys**, copy your **Publishable key** and **Secret key**
3. In **Developers → Webhooks**, click **Add endpoint**:
   - URL: `https://yourdomain.com/stripe_webhook.php`
   - Events: `checkout.session.completed`, `payment_intent.payment_failed`
4. Copy the **Webhook signing secret**

**Currency Note:** The app uses **XCD (Eastern Caribbean Dollar)**. Stripe must have XCD enabled for your account. Check [Stripe's currency support](https://stripe.com/docs/currencies) — if XCD is not available, change `STRIPE_CURRENCY` in `config.php` to `'usd'` or another supported currency.

---

## Step 5: Environment Variables

**Never hardcode secrets!** Set these environment variables on your server:

```bash
# Apache: Add to /etc/apache2/envvars or your VirtualHost
export DB_HOST="localhost"
export DB_NAME="tamcc_foodie"
export DB_USER="tamcc_user"
export DB_PASS="your_db_password"

export AZURE_CLIENT_ID="your-azure-client-id"
export AZURE_CLIENT_SECRET="your-azure-client-secret"
export AZURE_TENANT_ID="your-azure-tenant-id"
export AZURE_REDIRECT_URI="https://yourdomain.com/callback.php"

export STRIPE_SECRET_KEY="sk_live_..."
export STRIPE_PUBLISHABLE_KEY="pk_live_..."
export STRIPE_WEBHOOK_SECRET="whsec_..."

export APP_URL="https://yourdomain.com"
export APP_ENV="production"
```

For **cPanel hosting**, add these in **cPanel → Environments** or use a `.env` file with a library like `vlucas/phpdotenv`.

---

## Step 6: Logo Asset

Copy the TAMCC Foodie logo image to:
```
tamcc_foodie/assets/logo.png
```

---

## Step 7: Apache VirtualHost

```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/tamcc_foodie

    <Directory /var/www/tamcc_foodie>
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
</VirtualHost>
```

---

## Security Checklist

- [x] Admin passwords hashed with bcrypt (cost=12)
- [x] PDO prepared statements (SQL injection prevention)
- [x] CSRF state token on OAuth2 flow
- [x] Session regeneration on login and periodically
- [x] Role-based access control on every protected page
- [x] HttpOnly + Secure + SameSite session cookies
- [x] Stripe webhook signature verification
- [x] Server-side cart price validation (never trust client prices)
- [x] Sensitive files blocked via .htaccess
- [x] HTML output escaped with htmlspecialchars()
- [ ] Add rate limiting on admin_login.php (recommend fail2ban or PHP rate-limit library)
- [ ] Enable HTTPS and set `APP_ENV=production`
- [ ] Rotate default admin credentials immediately

---

## User Roles Summary

| Role | Login Method | Can Access |
|------|-------------|-----------|
| Student/Staff | Microsoft SSO (Azure AD) | Menu, Cart, Checkout, My Orders |
| Kitchen Staff | Database credentials | Dashboard → Orders only |
| Admin | Database credentials | Dashboard → Orders + Menu CRUD + Stats |

---

## Checkout Flow

```
Student clicks "Proceed to Checkout"
    → checkout.php: Validates cart server-side, creates Stripe Checkout Session
    → Redirects to Stripe hosted payment page
    → After payment: Stripe redirects to order_success.php?session_id=...
    → order_success.php: Verifies payment, creates order in DB, clears cart
    → Stripe also calls stripe_webhook.php (backup confirmation)
    → Student lands on order confirmation page with order number
    → Kitchen sees order in dashboard.php → updates status
    → my_orders.php auto-refreshes every 30s to show status changes
```
