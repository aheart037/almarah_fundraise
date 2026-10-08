<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Mail\MailQueue;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\EmailTemplateService;
use App\Services\SettingsService;

/**
 * Email templates and the outbound queue.
 */
final class EmailController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private EmailTemplateService $templates,
        private MailQueue $queue,
        private SettingsService $settings,
        private AuditService $audit,
        private Database $db
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function templates(): Response
    {
        return $this->render('admin/email/templates', [
            'pageTitle' => 'Email templates',
            'templates' => $this->templates->all(),
            'mailConfigured' => $this->settings->mailConfigured(),
        ], 'layouts/admin');
    }

    public function editTemplate(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        $template = $this->templates->find($id);

        if ($template === null) {
            abort(404, 'Email template not found.');
        }

        // Preview with realistic sample values so the admin sees the email a
        // donor would receive, not the raw tokens.
        $preview = null;
        try {
            $preview = $this->templates->renderDefaults((string) $template['event_key'], $this->templates->sampleData());
        } catch (\Throwable $e) {
            $preview = ['subject' => (string) ($template['subject'] ?? ''), 'html' => '<p class="muted">Preview unavailable.</p>', 'text' => ''];
        }

        return $this->render('admin/email/edit', [
            'pageTitle' => 'Edit template',
            'template'  => $template,
            'preview'   => $preview,
            'tokens'    => array_keys($this->templates->sampleData()),
        ], 'layouts/admin');
    }

    public function saveTemplate(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        $template = $this->templates->find($id);

        if ($template === null) {
            abort(404, 'Email template not found.');
        }

        $subject = trim((string) $request->input('subject', ''));
        $bodyHtml = (string) $request->input('body_html', '');
        $bodyText = (string) $request->input('body_text', '');

        if ($subject === '' || $bodyHtml === '') {
            $this->flashError('A subject and an HTML body are both required.');
            return $this->redirect('/admin/email-templates/' . $id);
        }

        $this->templates->update((int) $id, [
            'subject'   => $subject,
            'html_body' => $bodyHtml,
            'text_body' => $bodyText,
            'enabled'   => $request->bool('enabled', true) ? 1 : 0,
        ], (int) $this->auth->id());

        $this->audit->log('email.template_updated', 'email_template', (string) $id, [], (int) $this->auth->id());

        $this->flashSuccess('Template saved. New emails will use this version.');
        return $this->redirect('/admin/email-templates/' . $id);
    }

    /**
     * Restores a template to the version shipped with the application,
     * rendered with its tokens intact so it stays editable.
     */
    public function resetTemplate(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        $template = $this->templates->find($id);

        if ($template === null) {
            abort(404, 'Email template not found.');
        }

        $rendered = $this->templates->renderDefaults(
            (string) $template['event_key'],
            $this->templates->defaultPlaceholderData()
        );

        $this->templates->update($id, [
            'subject'   => $rendered['subject'],
            'html_body' => $rendered['html'],
            'text_body' => $rendered['text'],
            'enabled'   => (int) ($template['enabled'] ?? 1),
        ], (int) $this->auth->id());

        $this->audit->log('email.template_reset', 'email_template', (string) $id, [], (int) $this->auth->id());

        $this->flashSuccess('Template restored to the default design.');
        return $this->redirect('/admin/email-templates/' . $id);
    }

    public function queue(Request $request): Response
    {
        $status = (string) $request->input('status', '');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 30;

        $where = ['1 = 1'];
        $params = [];

        if (in_array($status, ['queued', 'sending', 'sent', 'failed', 'cancelled'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $clause = implode(' AND ', $where);
        $offset = max(0, ($page - 1) * $perPage);

        $total = $this->db->int("SELECT COUNT(*) FROM email_jobs WHERE {$clause}", $params);

        $jobs = $this->db->select(
            "SELECT id, event_key, recipient_email, subject, status, attempts, last_error,
                    available_at, sent_at, created_at
             FROM email_jobs
             WHERE {$clause}
             ORDER BY id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return $this->render('admin/email/queue', [
            'pageTitle' => 'Email queue',
            'jobs'      => $jobs,
            'stats'     => $this->queue->stats(),
            'total'     => $total,
            'page'      => $page,
            'lastPage'  => max(1, (int) ceil($total / $perPage)),
            'filters'   => ['status' => $status],
        ], 'layouts/admin');
    }

    /** Re-queue a failed job with its attempt counter reset. */
    public function retry(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        $job = $this->db->selectOne('SELECT id, status FROM email_jobs WHERE id = :id', ['id' => $id]);

        if ($job === null) {
            $this->flashError('That email job could not be found.');
            return $this->redirect('/admin/email-queue');
        }

        if ((string) $job['status'] === 'sent') {
            $this->flashWarning('That email has already been sent.');
            return $this->redirect('/admin/email-queue');
        }

        $this->db->update('email_jobs', [
            'status'       => 'queued',
            'attempts'     => 0,
            'last_error'   => null,
            'available_at' => gmdate('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => (int) $id]);

        $this->audit->log('email.job_requeued', 'email_jobs', (string) $id, [], (int) $this->auth->id());

        $this->flashSuccess('Email re-queued. The worker will pick it up on its next run.');
        return $this->redirect('/admin/email-queue');
    }
}
