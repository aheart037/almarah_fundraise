<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'session' => [
        'name'     => Env::get('SESSION_NAME', 'almarah_session'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 120), // minutes
        'secure'   => (bool) Env::get('SESSION_SECURE_COOKIE', true),
        'samesite' => Env::get('SESSION_SAMESITE', 'Lax'),
    ],

    'login' => [
        'max_attempts'    => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
        'decay_minutes'   => (int) Env::get('LOGIN_DECAY_MINUTES', 15),
    ],

    'donation_throttle' => [
        'max_attempts'  => (int) Env::get('DONATION_MAX_ATTEMPTS', 12),
        'decay_minutes' => (int) Env::get('DONATION_DECAY_MINUTES', 10),
    ],

    'tokens' => [
        'email_verification_ttl_hours' => 48,
        'password_reset_ttl_hours'     => 2,
    ],

    'uploads' => [
        'max_bytes'      => (int) Env::get('UPLOAD_MAX_BYTES', 4194304),
        'allowed_mime'   => array_filter(array_map('trim', explode(',', (string) Env::get('UPLOAD_ALLOWED_MIME', 'image/jpeg,image/png,image/webp')))),
        'allowed_ext'    => array_filter(array_map('trim', explode(',', (string) Env::get('UPLOAD_ALLOWED_EXT', 'jpg,jpeg,png,webp')))),
        'image_width'    => (int) Env::get('UPLOAD_IMAGE_WIDTH', 1600),
        'directory'      => Env::get('UPLOAD_DIRECTORY', 'uploads'),
    ],

    'headers' => [
        'content_security_policy' => Env::get(
            'CSP',
            "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self' data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'"
        ),
    ],
];
