<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Core\Logger;

/**
 * Non-secret settings only. Gateway credentials and SMTP passwords are never
 * seeded — they are entered by an administrator and stored encrypted.
 */
final class SettingSeeder
{
    private const SETTINGS = [
        'site.name'                => 'Almarah Foundation Fundraising',
        'site.tagline'             => 'Every Child Deserves a Place to Call "Apna Ghar"',
        'site.currency'            => 'PKR',
        'site.support_email'       => 'info@almarah.org',
        'site.support_phone'       => '+92 42 111 262 272',
        'site.maintenance_mode'    => '0',

        // Fundraiser moderation.
        'moderation.auto_approve'  => '0',
        'moderation.require_email_verification' => '1',
        'moderation.min_story_length' => '120',

        // Uploads.
        'uploads.max_bytes'        => '4194304',
        'uploads.image_width'      => '1600',

        // Sessions.
        'session.lifetime_minutes' => '120',
        'session.secure_cookie'    => '1',

        // Donations.
        'donations.min_minor'      => '10000',
        'donations.max_minor'      => '500000000',

        // Gateway defaults (non-secret).
        'gateway.meezan.environment'   => 'sandbox',
        'gateway.meezan.currency_code' => '586',
        'gateway.meezan.currency_name' => 'PKR',
        'gateway.meezan.timeout'       => '30',
        'gateway.meezan.refund_enabled'=> '0',
        'gateway.etisalat.environment' => 'sandbox',
        'gateway.etisalat.currency'    => 'PKR',
        'gateway.etisalat.timeout'     => '30',

        // Mail defaults (no credentials).
        'mail.from_name'    => 'Almarah Foundation',
        'mail.from_address' => 'fundraise@almarah.org',
        'mail.reply_to'     => 'info@almarah.org',
        'mail.encryption'   => 'tls',
        'mail.port'         => '587',
        'mail.verify_peer'  => '1',
    ];

    public function __construct(private Database $db, private Logger $logger)
    {
    }

    public function run(): int
    {
        $count = 0;
        foreach (self::SETTINGS as $key => $value) {
            $exists = $this->db->int('SELECT COUNT(*) FROM site_settings WHERE setting_key = :k', ['k' => $key]);
            if ($exists > 0) {
                continue;
            }
            $this->db->insert('site_settings', [
                'setting_key'   => $key,
                'setting_value' => $value,
                'is_encrypted'  => 0,
            ]);
            $count++;
        }

        return $count;
    }
}
