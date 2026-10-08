<?php

declare(strict_types=1);

use App\Core\Env;

return [
    /*
     |--------------------------------------------------------------------------
     | Gateway registry
     |--------------------------------------------------------------------------
     | Database-backed settings (managed in the admin UI) override these
     | environment defaults at runtime via SettingsService. Environment values
     | are the safe fallback so a fresh install can boot without a DB row.
     */
    'gateways' => [
        'meezan' => [
            'label'       => 'Meezan Bank',
            'description' => 'Meezan Bank hosted payment page (register.do / getOrderStatus.do).',
            'enabled'     => (bool) Env::get('MEEZAN_ENABLED', false),
            'environment' => Env::get('MEEZAN_ENVIRONMENT', 'sandbox'),
            'sandbox_url' => Env::get('MEEZAN_SANDBOX_URL', ''),
            'live_url'    => Env::get('MEEZAN_LIVE_URL', ''),
            'username'    => Env::secret('MEEZAN_USERNAME'),
            'password'    => Env::secret('MEEZAN_PASSWORD'),
            'merchant_id' => Env::get('MEEZAN_MERCHANT_ID', ''),
            'currency_code' => Env::get('MEEZAN_CURRENCY_CODE', '586'),
            'currency_name' => Env::get('MEEZAN_CURRENCY_NAME', 'PKR'),
            'timeout'     => (int) Env::get('MEEZAN_TIMEOUT', 30),
            // Payment page hosts that may legitimately appear in a formUrl.
            'allowed_hosts' => array_filter(array_map('trim', explode(',', (string) Env::get(
                'MEEZAN_ALLOWED_HOSTS',
                'meezanbank.com,mpay.meezanbank.com,payment.meezanbank.com,sandbox.meezanbank.com'
            )))),
        ],

        'etisalat' => [
            'label'       => 'Etisalat / UBL EPG',
            'description' => 'Etisalat (UBL EPG REST) card processing via Registration + Finalization.',
            'enabled'     => (bool) Env::get('ETISALAT_ENABLED', false),
            'environment' => Env::get('ETISALAT_ENVIRONMENT', 'sandbox'),
            'sandbox_url' => Env::get('ETISALAT_SANDBOX_URL', 'https://demo-ipg.ctdev.comtrust.ae:2443'),
            'live_url'    => Env::get('ETISALAT_LIVE_URL', 'https://ipg.comtrust.ae:2443'),
            'customer'    => Env::get('ETISALAT_CUSTOMER', ''),
            'username'    => Env::secret('ETISALAT_USERNAME'),
            'password'    => Env::secret('ETISALAT_PASSWORD'),
            'store'       => Env::get('ETISALAT_STORE', ''),
            'terminal'    => Env::get('ETISALAT_TERMINAL', ''),
            'currency'    => Env::get('ETISALAT_CURRENCY', 'PKR'),
            'timeout'     => (int) Env::get('ETISALAT_TIMEOUT', 30),
            'allowed_hosts' => array_filter(array_map('trim', explode(',', (string) Env::get(
                'ETISALAT_ALLOWED_HOSTS',
                'ipg.comtrust.ae,demo-ipg.ctdev.comtrust.ae'
            )))),
        ],
    ],

    'environment' => [
        // Sandbox and live credentials are never mixed: a transaction is
        // permanently bound to the environment it was registered in.
        'allowed' => ['sandbox', 'live'],
    ],

    'callback' => [
        'state_ttl_minutes'   => (int) Env::get('CALLBACK_STATE_TTL_MINUTES', 180),
        'lock_timeout_seconds'=> (int) Env::get('PAYMENT_LOCK_TIMEOUT_SECONDS', 45),
        'max_callback_bytes'  => 65536,
    ],

    'status_map' => [
        // Gateway-agnostic statuses used by the platform.
        'statuses' => ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded', 'abandoned'],
        // A completed donation may never be downgraded by a later callback.
        'terminal' => ['completed', 'refunded'],
    ],
];
