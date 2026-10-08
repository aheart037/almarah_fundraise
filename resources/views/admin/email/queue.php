<?php
/**
 * Outbound email queue.
 *
 * @var array $jobs
 * @var array $stats
 * @var int $total
 * @var int $page
 * @var int $lastPage
 * @var array $filters
 */
?>
<div class="dash-head">
  <div>
    <h1>Email queue</h1>
    <p class="sub">Every queued, sent and failed message.</p>
  </div>
  <div class="sr-actions">
    <a class="btn btn-light" href="<?= e(base_url('admin/email-templates')) ?>">Templates</a>
    <a class="btn btn-outline-brand" href="<?= e(base_url('admin/settings#smtp')) ?>">SMTP settings</a>
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card">
    <div class="k">Queued</div>
    <div class="v"><?= e((string) (int) ($stats['queued'] ?? 0)) ?></div>
    <div class="d">Waiting for the worker</div>
  </div>
  <div class="stat-card">
    <div class="k">Sent</div>
    <div class="v"><?= e((string) (int) ($stats['sent'] ?? 0)) ?></div>
    <div class="d">Delivered to the mail server</div>
  </div>
  <div class="stat-card">
    <div class="k">Failed</div>
    <div class="v"><?= e((string) (int) ($stats['failed'] ?? 0)) ?></div>
    <div class="d">Retry after fixing the cause</div>
  </div>
  <div class="stat-card">
    <div class="k">Sending now</div>
    <div class="v"><?= e((string) (int) ($stats['sending'] ?? 0)) ?></div>
    <div class="d">Claimed by a worker</div>
  </div>
</div>

<div class="alert alert-info">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
  <p>
    Process the queue with <span class="mono">php bin/console mail:work --once</span> from cron, or
    <span class="mono">php bin/console mail:work</span> to run the worker continuously.
    Failed jobs retry with backoff, and the same email is never sent twice.
  </p>
</div>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(base_url('admin/email-queue')) ?>">
    <div class="select-field">
      <select class="select-field" name="status" data-autosubmit aria-label="Status">
        <option value="">Any status</option>
        <?php foreach (['queued', 'sending', 'sent', 'failed', 'cancelled'] as $status): ?>
          <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-brand" type="submit">Filter</button>
    <?php if ($filters['status'] !== ''): ?>
      <a class="btn btn-light" href="<?= e(base_url('admin/email-queue')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <p class="text-muted"><?= e(number_format($total)) ?> job<?= $total === 1 ? '' : 's' ?>.</p>

  <?php if ($jobs === []): ?>
    <div class="empty-state">
      <h3>Nothing in the queue</h3>
      <p>When a donation completes or a fundraiser changes state, the email appears here.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Event</th><th>Recipient</th><th class="wrap">Subject</th><th>Status</th><th class="num">Attempts</th><th>Available</th><th class="wrap">Last error</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($jobs as $job): ?>
            <tr>
              <td><?= e((string) $job['id']) ?></td>
              <td class="mono"><?= e((string) $job['event_key']) ?></td>
              <td><?= e((string) $job['recipient_email']) ?></td>
              <td class="wrap"><?= e((string) ($job['subject'] ?? '')) ?></td>
              <td>
                <span class="<?= e(status_badge_class((string) $job['status'])) ?>"><?= e((string) $job['status']) ?></span>
                <?php if (!empty($job['sent_at'])): ?>
                  <div class="muted" style="font-size:12px"><?= e(dt((string) $job['sent_at'], 'j M Y H:i')) ?></div>
                <?php endif; ?>
              </td>
              <td class="num"><?= e((string) (int) $job['attempts']) ?></td>
              <td><?= e(dt((string) $job['available_at'], 'j M Y H:i')) ?></td>
              <td class="wrap muted" style="font-size:12.5px"><?= e(mb_substr((string) ($job['last_error'] ?? ''), 0, 160)) ?></td>
              <td>
                <?php if ((string) $job['status'] !== 'sent'): ?>
                  <form method="post" action="<?= e(base_url('admin/email-queue/' . (string) $job['id'] . '/retry')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-light btn-sm" type="submit">Retry</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php $query = ['status' => $filters['status']]; require __DIR__ . '/../../partials/pagination.php'; ?>
  <?php endif; ?>
</section>
