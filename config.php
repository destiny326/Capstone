<?php
// ============================================================
// config.php — Application Configuration
// Load secrets from environment variables (NEVER hardcode!)
// ============================================================

// ----------------------------------------------------------
// Azure AD / Microsoft Identity Platform
// Register your app at: https://portal.azure.com
// ----------------------------------------------------------


define('AZURE_CLIENT_ID',     getenv('AZURE_CLIENT_ID')     ?: 'YOUR_AZURE_CLIENT_ID');
define('AZURE_CLIENT_SECRET', getenv('AZURE_CLIENT_SECRET') ?: 'YOUR_AZURE_CLIENT_SECRET');
define('AZURE_TENANT_ID',     getenv('AZURE_TENANT_ID')     ?: 'common'); // or your org tenant ID
define('AZURE_REDIRECT_URI',  getenv('AZURE_REDIRECT_URI')  ?: 'https://yourdomain.com/callback.php');

define('AZURE_AUTHORITY',     'https://login.microsoftonline.com/' . AZURE_TENANT_ID);
define('AZURE_SCOPES',        'openid profile email User.Read');

// ----------------------------------------------------------
// Stripe Payment Gateway
// Get keys from: https://dashboard.stripe.com/apikeys
// ----------------------------------------------------------

define('STRIPE_SECRET_KEY',      getenv('STRIPE_SECRET_KEY')      ?: 'sk_test_YOUR_SECRET_KEY');
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_YOUR_PUBLISHABLE_KEY');
define('STRIPE_WEBHOOK_SECRET',  getenv('STRIPE_WEBHOOK_SECRET')  ?: 'whsec_YOUR_WEBHOOK_SECRET');
define('STRIPE_CURRENCY',        'xcd'); // Eastern Caribbean Dollar

// ----------------------------------------------------------
// Application Settings
// ----------------------------------------------------------
define('APP_NAME',    'TAMCC Foodie');
define('APP_URL',     getenv('APP_URL') ?: 'https://yourdomain.com');
define('APP_ENV',     getenv('APP_ENV') ?: 'production'); // 'development' or 'production'
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

// Session cookie config (set before session_start())
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure',   APP_ENV === 'production' ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime',  SESSION_LIFETIME);
