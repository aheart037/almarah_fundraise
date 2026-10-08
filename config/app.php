<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name'        => Env::get('APP_NAME', 'Almarah Foundation'),
    'env'         => Env::get('APP_ENV', 'production'),
    'debug'       => (bool) Env::get('APP_DEBUG', false),
    'url'         => rtrim((string) Env::get('APP_URL', ''), '/'),
    'timezone'    => Env::get('APP_TIMEZONE', 'UTC'),
    'currency'    => Env::get('APP_CURRENCY', 'PKR'),
    'key'         => (string) Env::get('APP_KEY', ''),

    /*
     | Path to the web root (the directory the web server serves). Empty — the
     | normal case — means the application folder itself, which is where it is
     | served from in the single-folder layout this package ships. Set it only
     | on a host that serves the site from a different folder.
     */
    'public_path' => (string) Env::get('PUBLIC_PATH', ''),

    /*
     | Optional lock for the one-click installer at /setup. Empty — the default —
     | means the setup form asks for nothing but a name, an email address and a
     | password. Set a word here and the same word must be typed on that form.
     */
    'setup_lock' => (string) Env::get('SETUP_LOCK', ''),

    'org' => [
        'legal_name' => 'Almarah Foundation',
        'tagline'    => 'Every Child Deserves a Place to Call "Apna Ghar"',
        'founded'    => '2021-06-04',
        'website'    => 'https://www.almarah.org',
        'email'      => 'info@almarah.org',
        'phone'      => '+92 42 111 262 272',
        'address'    => 'Canal Road, Lahore, Punjab, Pakistan',
        'hours'      => 'Monday to Saturday, 9:00am – 6:00pm (PKT)',
    ],

    'maintenance' => [
        'enabled' => (bool) Env::get('MAINTENANCE_MODE', false),
        'allow_ips' => array_filter(array_map('trim', explode(',', (string) Env::get('MAINTENANCE_ALLOW_IPS', '')))),
    ],

    'features' => [
        'team_fundraising'  => (bool) Env::get('TEAM_FUNDRAISING_ENABLED', true),
        'auto_approve'      => (bool) Env::get('FUNDRAISER_AUTO_APPROVE', false),
        'donor_accounts'    => true,
    ],

    'donations' => [
        'min_minor' => (int) Env::get('DONATION_MIN_MINOR', 10000),       // Rs 100
        'max_minor' => (int) Env::get('DONATION_MAX_MINOR', 500000000),   // Rs 5,000,000
        'presets_minor' => [250000, 1000000, 2500000, 10000000],          // Rs 2,500 / 10,000 / 25,000 / 100,000
    ],

    'fundraisers' => [
        'goal_min_minor' => 1000000,     // Rs 10,000
        'goal_max_minor' => 2000000000,  // Rs 20,000,000
        'default_duration_days' => 60,
        'max_duration_days' => 365,
    ],

    'pagination' => [
        'per_page' => 12,
        'admin_per_page' => 25,
    ],
];
