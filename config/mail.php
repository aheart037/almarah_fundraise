<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'mailer'      => Env::get('MAIL_MAILER', 'smtp'), // smtp only; mail() is never used
    'host'        => Env::get('SMTP_HOST', ''),
    'port'        => (int) Env::get('SMTP_PORT', 587),
    'encryption'  => Env::get('SMTP_ENCRYPTION', 'tls'), // tls | ssl | none
    'username'    => Env::get('SMTP_USERNAME', ''),
    'password'    => Env::secret('SMTP_PASSWORD'),
    'from_address'=> Env::get('MAIL_FROM_ADDRESS', 'fundraise@almarah.org'),
    'from_name'   => Env::get('MAIL_FROM_NAME', 'Almarah Foundation'),
    'reply_to'    => Env::get('MAIL_REPLY_TO', 'info@almarah.org'),
    'verify_peer' => (bool) Env::get('MAIL_VERIFY_PEER', true),
    'debug'       => (bool) Env::get('MAIL_DEBUG', false),
    'queue'       => [
        'enabled'     => (bool) Env::get('MAIL_QUEUE_ENABLED', true),
        'max_retries' => (int) Env::get('MAIL_MAX_RETRIES', 3),
        'batch'       => (int) Env::get('MAIL_BATCH_SIZE', 25),
        'backoff_seconds' => [60, 300, 900], // attempt 1, 2, 3
    ],
];
