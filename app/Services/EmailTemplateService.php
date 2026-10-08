<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\View;

/**
 * Renders transactional emails from database templates, falling back to the
 * on-disk defaults shipped in resources/views/emails.
 *
 * Every template must produce both an HTML and a plain-text alternative.
 */
final class EmailTemplateService
{
    public function __construct(private Database $db, private View $view)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @return array{subject:string, html:string, text:string, enabled:bool}
     */
    public function render(string $eventKey, array $data = []): array
    {
        $template = null;
        try {
            $template = $this->db->selectOne(
                'SELECT subject, html_body, text_body, enabled FROM email_templates WHERE event_key = :k',
                ['k' => $eventKey]
            );
        } catch (\Throwable $e) {
            $template = null;
        }

        $safe = $this->escapeData($data);
        $safe['appName'] = (string) \App\Core\Config::get('app.name', 'Almarah Foundation');
        $safe['org'] = \App\Core\Config::get('app.org', []);
        $safe['year'] = gmdate('Y');

        if ($template !== null) {
            return [
                'subject' => $this->interpolate((string) $template['subject'], $safe),
                // Values are escaped inside HTML bodies (donor names and
                // fundraiser titles are user input); the stored markup itself
                // is passed through untouched. Plain-text bodies must stay raw.
                'html'    => $this->interpolate((string) $template['html_body'], $safe, true),
                'text'    => $this->interpolate((string) $template['text_body'], $safe, false),
                'enabled' => (bool) $template['enabled'],
            ];
        }

        return $this->renderDefaults($eventKey, $data);
    }

    /**
     * Renders the shipped default for an event, ignoring any stored template.
     *
     * Used by the seeder, by the admin "reset to default" action and whenever a
     * stored template is missing.
     *
     * @param array<string,mixed> $data
     * @return array{subject:string, html:string, text:string, enabled:bool}
     */
    public function renderDefaults(string $eventKey, array $data = []): array
    {
        $safe = $this->escapeData($data);
        $safe['appName'] = (string) \App\Core\Config::get('app.name', 'Almarah Foundation');
        $safe['org'] = \App\Core\Config::get('app.org', []);
        $safe['year'] = gmdate('Y');

        return [
            'subject' => $this->interpolate($this->defaultSubject($eventKey), $safe),
            'html'    => $this->renderDefaultHtml($eventKey, $safe),
            'text'    => $this->renderDefaultText($eventKey, $safe),
            'enabled' => true,
        ];
    }

    /**
     * Every token the default templates understand, mapped to its own
     * placeholder. Rendering a default with this data produces a template that
     * is still fully tokenised, which is what the seeder stores.
     *
     * @return array<string,string>
     */
    public function defaultPlaceholderData(): array
    {
        $data = [];
        foreach (array_keys($this->sampleData()) as $token) {
            $data[$token] = '{{' . $token . '}}';
        }
        return $data;
    }

    /**
     * Realistic values for the same tokens, used for admin previews.
     *
     * @return array<string,string>
     */
    public function sampleData(): array
    {
        return [
            'heading'          => 'Thank you for your donation',
            'donor_name'       => 'Ayesha Khan',
            'body'             => "Your donation has been received.\n\nThis email is your official receipt — please keep it for your records.",
            'cta_label'        => 'View the fundraiser',
            'cta_url'          => base_url('fundraisers/ayesha-s-birthday-for-50-ration-packs'),
            'expires'          => '48 hours',
            'fundraiser_title' => "Ayesha's Birthday for 50 Ration Packs",
            'reference'        => 'ALM-2026-000123',
            'amount'           => 'Rs 25,000',
            'currency'         => 'PKR',
            'status'           => 'Completed',
            'gateway'          => 'Meezan Bank',
            'receipt_date'     => gmdate('j F Y'),
            'days_left'        => '7',
            'supporter_name'   => 'Fatima Noor',
            'team_name'        => 'Team Izzat Ki Roti',
            'member_name'      => 'Bilal Ahmad',
            'inviter'          => 'Sana Malik',
        ];
    }

    private function defaultSubject(string $eventKey): string
    {
        return match ($eventKey) {
            'account.created'            => 'Welcome to {{appName}} fundraising',
            'account.verify_email'       => 'Please verify your email address',
            'account.password_reset'     => 'Reset your {{appName}} password',
            'account.password_changed'   => 'Your password was changed',
            'account.suspended'          => 'Your account has been suspended',
            'account.reactivated'        => 'Your account has been reactivated',
            'fundraiser.submitted'       => 'We received your fundraiser "{{fundraiser_title}}"',
            'fundraiser.approved'        => 'Your fundraiser is live: {{fundraiser_title}}',
            'fundraiser.rejected'        => 'Update needed on "{{fundraiser_title}}"',
            'fundraiser.paused'          => 'Your fundraiser has been paused',
            'fundraiser.published'       => 'Your fundraiser is published',
            'fundraiser.update_published'=> 'New update on your fundraiser',
            'fundraiser.goal_reached'    => 'Goal reached! 🎉 {{fundraiser_title}}',
            'fundraiser.ending_soon'     => 'Your fundraiser ends in {{days_left}} days',
            'fundraiser.new_donation'    => 'New donation of {{amount}} on {{fundraiser_title}}',
            'team.invitation'            => 'You have been invited to join {{team_name}}',
            'team.member_joined'         => '{{member_name}} joined {{team_name}}',
            'donation.receipt'           => 'Thank you — your donation receipt ({{reference}})',
            'donation.pending'           => 'Your donation is being processed ({{reference}})',
            'donation.failed'            => 'Your donation could not be completed ({{reference}})',
            'donation.cancelled'         => 'Your donation was cancelled ({{reference}})',
            'donation.refunded'          => 'Your donation has been refunded ({{reference}})',
            'admin.payment_alert'        => '[Action needed] Payment issue on {{reference}}',
            default                      => '{{appName}} notification',
        };
    }

    /** @param array<string,string> $data */
    private function renderDefaultHtml(string $eventKey, array $data): string
    {
        $view = 'emails.' . $this->viewName($eventKey);

        // Fall back to the shared shell so a new event key always produces a
        // real email rather than an error.
        if (!$this->view->exists($view)) {
            $view = 'emails.generic';
        }

        return $this->view->render($view, $data, 'emails.layout');
    }

    /** @param array<string,string> $data */
    private function renderDefaultText(string $eventKey, array $data): string
    {
        $lines = [
            $data['heading'] ?? $this->defaultSubject($eventKey),
            str_repeat('-', 60),
            $data['body'] ?? '',
            '',
            'Almarah Foundation',
            (string) (\App\Core\Config::get('app.org.email', 'info@almarah.org')),
            (string) (\App\Core\Config::get('app.org.website', 'https://www.almarah.org')),
        ];

        if (!empty($data['cta_url']) && !empty($data['cta_label'])) {
            $lines[] = '';
            $lines[] = $data['cta_label'] . ': ' . $data['cta_url'];
        }

        return implode("\n", array_filter($lines, static fn ($l): bool => $l !== null));
    }

    private function viewName(string $eventKey): string
    {
        return str_replace(['.', '-'], '_', $eventKey);
    }

    /**
     * Replaces {{token}} placeholders. HTML bodies escape values; text bodies
     * do not need escaping but still receive plain values.
     *
     * @param array<string,string> $data
     */
    private function interpolate(string $content, array $data, bool $escape = true): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static function (array $m) use ($data, $escape): string {
                $value = $data[$m[1]] ?? '';
                return $escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
            },
            $content
        ) ?? $content;
    }

    /** @param array<string,mixed> $data @return array<string,string> */
    private function escapeData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $out[(string) $key] = (string) $value;
            } elseif (is_array($value)) {
                $out[(string) $key] = implode(', ', array_map('strval', $value));
            }
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->select('SELECT id, event_key, name, subject, enabled, updated_at FROM email_templates ORDER BY event_key');
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM email_templates WHERE id = :id', ['id' => $id]);
    }

    public function findByEvent(string $eventKey): ?array
    {
        return $this->db->selectOne('SELECT * FROM email_templates WHERE event_key = :k', ['k' => $eventKey]);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data, ?int $userId = null): void
    {
        $this->db->update('email_templates', [
            'subject'    => $data['subject'],
            'html_body'  => $data['html_body'],
            'text_body'  => $data['text_body'],
            'enabled'    => !empty($data['enabled']) ? 1 : 0,
            'updated_by' => $userId,
        ], 'id = :id', ['id' => $id]);
    }
}
