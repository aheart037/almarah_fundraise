<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Core\Logger;
use App\Services\EmailTemplateService;

/**
 * Seeds editable copies of every transactional email.
 *
 * Bodies use {{token}} placeholders that are substituted at send time; values
 * are HTML-escaped for HTML bodies. Templates ship in the "enabled" state so
 * the queue has something to render immediately after install.
 */
final class EmailTemplateSeeder
{
    public function __construct(
        private Database $db,
        private Logger $logger,
        private ?EmailTemplateService $templates = null
    ) {
        $this->templates ??= app(EmailTemplateService::class);
    }

    public function run(): int
    {
        $templates = [
            'account.created' => [
                'account'  => 'Account created',
                'subject'  => 'Welcome to {{appName}} fundraising',
                'heading'  => 'Welcome to Almarah Foundation fundraising',
                'intro'    => 'Your Almarah Foundation account is ready. You can start a fundraiser, join a team and track your impact from your dashboard.',
            ],
            'account.verify_email' => [
                'account'  => 'Verify your email address',
                'subject'  => 'Please verify your email address',
                'heading'  => 'Verify your email address',
                'intro'    => 'Please confirm your email address so we can keep your fundraiser secure and send you donation receipts. This link expires in {{expires}}.',
            ],
            'account.password_reset' => [
                'account'  => 'Password reset requested',
                'subject'  => 'Reset your {{appName}} password',
                'heading'  => 'Reset your password',
                'intro'    => 'We received a request to reset your Almarah Foundation password. This link expires in {{expires}}. If this was not you, you can safely ignore this email.',
            ],
            'account.password_changed' => [
                'account'  => 'Password changed',
                'subject'  => 'Your password was changed',
                'heading'  => 'Your password was changed',
                'intro'    => 'Your Almarah Foundation password was changed. If this was not you, please contact us immediately.',
            ],
            'account.suspended' => [
                'account'  => 'Account suspended',
                'subject'  => 'Your account has been suspended',
                'heading'  => 'Your account has been suspended',
                'intro'    => 'Your account has been suspended. If you believe this is a mistake, please contact our team.',
            ],
            'account.reactivated' => [
                'account'  => 'Account reactivated',
                'subject'  => 'Your account has been reactivated',
                'heading'  => 'Your account is active again',
                'intro'    => 'Your Almarah Foundation account has been reactivated. Welcome back — your fundraisers are available again.',
            ],
            'fundraiser.submitted' => [
                'account'  => 'Fundraiser submitted for review',
                'subject'  => 'We received your fundraiser "{{fundraiser_title}}"',
                'heading'  => 'We received your fundraiser',
                'intro'    => 'Thank you for fundraising for Almarah Foundation. Our team reviews new pages to keep the platform safe — you will hear from us shortly.',
            ],
            'fundraiser.approved' => [
                'account'  => 'Fundraiser approved',
                'subject'  => 'Your fundraiser is live: {{fundraiser_title}}',
                'heading'  => 'Your fundraiser is approved',
                'intro'    => 'Great news — your fundraiser has been approved and is now visible to supporters. Share your link to start raising.',
            ],
            'fundraiser.rejected' => [
                'account'  => 'Fundraiser rejected / changes requested',
                'subject'  => 'Update needed on "{{fundraiser_title}}"',
                'heading'  => 'Your fundraiser needs a change',
                'intro'    => 'Our team reviewed your fundraiser and asked for a change. Update your page and resubmit — we will review it again straight away.',
            ],
            'fundraiser.paused' => [
                'account'  => 'Fundraiser paused',
                'subject'  => 'Your fundraiser has been paused',
                'heading'  => 'Your fundraiser has been paused',
                'intro'    => 'Your fundraiser is paused and no longer accepting donations. Please contact support if you have questions.',
            ],
            'fundraiser.published' => [
                'account'  => 'Fundraiser published',
                'subject'  => 'Your fundraiser is published',
                'heading'  => 'Your fundraiser is live',
                'intro'    => 'Your fundraiser is now published and accepting donations.',
            ],
            'fundraiser.update_published' => [
                'account'  => 'Fundraiser update published',
                'subject'  => 'New update on your fundraiser',
                'heading'  => 'New update published',
                'intro'    => 'You published a new update on "{{fundraiser_title}}". Supporters can see it on your fundraiser page.',
            ],
            'fundraiser.goal_reached' => [
                'account'  => 'Fundraiser goal reached',
                'subject'  => 'Goal reached! {{fundraiser_title}}',
                'heading'  => 'You reached your goal!',
                'intro'    => 'Congratulations — "{{fundraiser_title}}" has reached its goal. Every extra rupee now funds even more of our work.',
            ],
            'fundraiser.ending_soon' => [
                'account'  => 'Fundraiser ending soon',
                'subject'  => 'Your fundraiser ends in {{days_left}} days',
                'heading'  => 'Your fundraiser is ending soon',
                'intro'    => 'Your fundraiser ends in {{days_left}} days. A short update to your supporters is the most effective way to finish strong.',
            ],
            'fundraiser.new_donation' => [
                'account'  => 'New donation received',
                'subject'  => 'New donation of {{amount}} on {{fundraiser_title}}',
                'heading'  => 'You received a donation',
                'intro'    => '{{supporter_name}} donated {{amount}} to "{{fundraiser_title}}". Log in to see your updated total and say thank you.',
            ],
            'team.invitation' => [
                'account'  => 'Team invitation',
                'subject'  => 'You have been invited to join {{team_name}}',
                'heading'  => 'You have been invited to a fundraising team',
                'intro'    => '{{inviter}} invited you to join "{{team_name}}" on Almarah Foundation fundraising.',
            ],
            'team.member_joined' => [
                'account'  => 'Team member joined',
                'subject'  => '{{member_name}} joined {{team_name}}',
                'heading'  => 'A new member joined your team',
                'intro'    => '{{member_name}} has joined "{{team_name}}". Welcome them and help them get started.',
            ],
            'donation.receipt' => [
                'account'  => 'Donation receipt',
                'subject'  => 'Thank you — your donation receipt ({{reference}})',
                'heading'  => 'Thank you for your donation',
                'intro'    => 'Your donation of {{amount}} has been received. This email is your official receipt — please keep it for your records.',
            ],
            'donation.pending' => [
                'account'  => 'Donation pending',
                'subject'  => 'Your donation is being processed ({{reference}})',
                'heading'  => 'Your donation is being processed',
                'intro'    => 'We have not yet received final confirmation from the payment provider. We will email your receipt as soon as it is confirmed — no action is needed.',
            ],
            'donation.failed' => [
                'account'  => 'Donation failed',
                'subject'  => 'Your donation could not be completed ({{reference}})',
                'heading'  => 'Your donation could not be completed',
                'intro'    => 'The payment was not completed, so no money has left your account. You are welcome to try again.',
            ],
            'donation.cancelled' => [
                'account'  => 'Donation cancelled',
                'subject'  => 'Your donation was cancelled ({{reference}})',
                'heading'  => 'Your donation was cancelled',
                'intro'    => 'Your donation was cancelled at the payment page and no money has been taken.',
            ],
            'donation.refunded' => [
                'account'  => 'Donation refunded',
                'subject'  => 'Your donation has been refunded ({{reference}})',
                'heading'  => 'Your donation has been refunded',
                'intro'    => 'We have processed a refund for your donation. Depending on your bank, it may take several working days to appear.',
            ],
            'admin.payment_alert' => [
                'account'  => 'Admin payment alert',
                'subject'  => '[Action needed] Payment issue on {{reference}}',
                'heading'  => 'Payment requires attention',
                'intro'    => 'A payment needs manual reconciliation. Please review it in the admin dashboard.',
            ],
        ];

        $count = 0;
        foreach ($templates as $eventKey => $template) {
            $exists = $this->db->int('SELECT COUNT(*) FROM email_templates WHERE event_key = :k', ['k' => $eventKey]);
            if ($exists > 0) {
                continue;
            }

            // The stored body is the shipped default rendered with tokens still
            // in place, so a fresh install starts from the real branded design
            // and administrators can edit it without losing the layout.
            $rendered = $this->templates->renderDefaults($eventKey, $this->templates->defaultPlaceholderData());

            $this->db->insert('email_templates', [
                'event_key' => $eventKey,
                'name'      => $template['account'],
                'subject'   => $template['subject'],
                'html_body' => $rendered['html'],
                'text_body' => $rendered['text'],
                'enabled'   => 1,
            ]);
            $count++;
        }

        return $count;
    }

    private function htmlBody(string $heading, string $intro): string
    {
        return <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fdf5f8;padding:32px 16px;font-family:Helvetica,Arial,sans-serif">
  <tr>
    <td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr>
          <td style="background:#6b0f35;padding:22px 28px">
            <span style="color:#ffffff;font-size:19px;font-weight:bold;letter-spacing:1px">ALMARAH FOUNDATION</span>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 28px 8px">
            <h1 style="margin:0 0 16px;font-size:22px;color:#1a0810">{{heading}}</h1>
            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#333333">{$intro}</p>
          </td>
        </tr>
        <tr>
          <td style="padding:8px 28px 32px">
            <a href="{{cta_url}}" style="display:inline-block;background:#f2c200;color:#1a0810;text-decoration:none;font-weight:bold;padding:13px 26px;border-radius:5px">{{cta_label}}</a>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 28px;background:#fbf7f4;font-size:12px;color:#717171;line-height:1.6">
            Almarah Foundation &middot; Canal Road, Lahore, Punjab, Pakistan<br>
            <a href="mailto:info@almarah.org" style="color:#a92d63">info@almarah.org</a> &middot;
            <a href="https://www.almarah.org" style="color:#a92d63">www.almarah.org</a><br>
            &copy; {{year}} Almarah Foundation. All rights reserved.
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
HTML;
    }

    private function textBody(string $heading, string $intro): string
    {
        return "{$heading}\n"
            . str_repeat('-', 60) . "\n"
            . "{$intro}\n\n"
            . "{{cta_label}}: {{cta_url}}\n\n"
            . "Almarah Foundation\ninfo@almarah.org\nwww.almarah.org\n";
    }
}
