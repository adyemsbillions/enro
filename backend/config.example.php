<?php
// Copy to config.php and fill in. config.php must never be committed.
return [
    'debug'      => true,                       // false in production (hides errors, hides OTP in responses)
    'app_url'    => 'http://localhost:8080',    // public base URL of this backend
    'timezone'   => 'Africa/Lagos',

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'enro',
        'user' => 'root',
        'pass' => '',
    ],

    // Long random string. Generate with: php -r "echo bin2hex(random_bytes(32));"
    'jwt_secret' => 'CHANGE_ME',
    'jwt_ttl_days' => 30,

    // Email (verification codes) is set up in Admin → Settings → Email, not here.

    // Airtime/data (VTpass). sandbox: https://sandbox.vtpass.com/api  live: https://vtpass.com/api
    'vtpass' => ['base_url' => 'https://sandbox.vtpass.com/api', 'api_key' => '', 'secret_key' => '', 'public_key' => ''],

    // Bank payouts + account name lookup (Paystack). Use sk_test_ while testing.
    'paystack' => ['secret_key' => ''],

    // Push notifications: Firebase service account JSON (Firebase console → Project settings → Service accounts).
    // Keep it inside storage/ (blocked from the web).
    'fcm' => ['service_account_file' => __DIR__ . '/storage/firebase-service-account.json'],

    'upload_max_mb' => 5,
];
