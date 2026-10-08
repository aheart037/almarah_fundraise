<?php
/**
 * Administrator overview.
 *
 * @var array $totals
 * @var array $gatewayTotals
 * @var array $monthly
 * @var int $pendingFundraisers
 * @var int $pendingDonations
 * @var int $failedDonations
 * @var array $paymentStatusCounts
 * @var int $userCount
 * @var int $activeUsers
 * @var array $recentDonations
 * @var array $recentFundraisers
 * @var array $recentAudit
 * @var array $gatewayHealth
 * @var bool $mailConfigured
 * @var array $callbacks
 */

$completedMinor = (int) ($totals['completed_minor'] ?? 0);
$completedCount = (int) ($totals['completed_count'] ?? 0);
$maxMonth = 0;
foreach ($monthly as $row) {
    $maxMonth = max($maxMonth, (int) $row['completed_minor']);
}
?>
<div class="dash-head">
  <div>
    <h1>Admin overview</h1>
    <p class="sub">Everything that needs a human, and everything that is going well.</p>
  </div>
  <div class="sr-actions">
    <a class="btn btn-light" href="<?= e(base_url('admin/donations/export')) ?>">Export donations</a>
    <a class="btn btn-brand" href="<?= e(base_url('admin/fundraisers?approval=pending')) ?>">
      Review queue<?= $pendingFundraisers > 0 ? ' (' . e((string) $pendingFundraisers) . ')' : '' ?>
    </a>
  </div>
</div>

<?php if (!$mailConfigured): ?>
  <div class="alert alert-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/></svg>
    <div>
      <strong>Outbound email is not configured.</strong>
      <p>Donation receipts, verification links and fundraiser notifications cannot be sent until SMTP is set up.
        <a href="<?= e(base_url('admin/settings#smtp')) ?>">Configure SMTP now</a>.</p>
    </div>
  </div>
<?php endif; ?>

<div class="stat-cards">
  <div class="stat-card brand">
    <div class="k">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21.2l7.7-7.8 1.1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
      Confirmed donations
    </div>
    <div class="v"><?= e(money($completedMinor)) ?></div>
    <div class="d"><?= e(number_format($completedCount)) ?> verified payments</div>
  </div>

  <div class="stat-card">
    <div class="k">Awaiting review</div>
    <div class="v"><?= e((string) $pendingFundraisers) ?></div>
    <div class="d">Fundraiser<?= $pendingFundraisers === 1 ? '' : 's' ?> in the queue</div>
  </div>

  <div class="stat-card">
    <div class="k">Payments in flight</div>
    <div class="v"><?= e((string) $pendingDonations) ?></div>
    <div class="d"><?= e((string) $failedDonations) ?> failed or cancelled</div>
  </div>

  <div class="stat-card">
    <div class="k">Users</div>
    <div class="v"><?= e(number_format($userCount)) ?></div>
    <div class="d"><?= e(number_format($activeUsers)) ?> active</div>
  </div>
</div>

<div class="grid-2">
  <section class="panel">
    <h2>Confirmed donations by month</h2>
    <p class="panel-sub">The last six months of verified payments.</p>

    <?php if ($monthly === []): ?>
      <p class="text-muted">No confirmed donations recorded yet.</p>
    <?php else: ?>
      <div class="chart-bars">
        <?php foreach ($monthly as $row): ?>
          <?php $height = $maxMonth > 0 ? max(3, (int) round(((int) $row['completed_minor'] / $maxMonth) * 100)) : 3; ?>
          <div class="bar">
            <b><?= e(money_short((int) $row['completed_minor'])) ?></b>
            <i style="height:<?= e((string) $height) ?>%"></i>
            <span><?= e(date('M', strtotime((string) $row['month'] . '-01'))) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2>By payment method</h2>
    <p class="panel-sub">Confirmed totals per gateway.</p>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Gateway</th><th class="num">Donations</th><th class="num">Confirmed</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($gatewayTotals as $row): ?>
            <?php $status = $gatewayHealth[$row['code']] ?? []; ?>
            <tr>
              <td><?= e((string) $row['name']) ?></td>
              <td class="num"><?= e(number_format((int) $row['donation_count'])) ?></td>
              <td class="num"><?= e(money_short((int) $row['completed_minor'])) ?></td>
              <td>
                <?php if (!empty($status['enabled']) && !empty($status['configured'])): ?>
                  <span class="badge badge-green">ready</span>
                <?php elseif (!empty($status['enabled'])): ?>
                  <span class="badge badge-amber">not configured</span>
                <?php else: ?>
                  <span class="badge badge-grey">disabled</span>
                <?php endif; ?>
                <div class="muted" style="font-size:12px"><?= e(ucfirst((string) ($status['environment'] ?? 'sandbox'))) ?></div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="mt-2"><a class="link-arrow" href="<?= e(base_url('admin/settings')) ?>">Payment settings</a></p>
  </section>
</div>

<section class="panel">
  <div class="panel-head">
    <h2>Latest donations</h2>
    <a class="link-arrow" href="<?= e(base_url('admin/donations')) ?>">All donations</a>
  </div>

  <?php if ($recentDonations === []): ?>
    <p class="text-muted">No donations recorded yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Reference</th><th>Donor</th><th>Fundraiser</th><th>Gateway</th><th>Status</th><th class="num">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($recentDonations as $donation): ?>
            <tr>
              <td class="mono"><a href="<?= e(base_url('admin/donations/' . (string) $donation['id'])) ?>"><?= e((string) $donation['public_reference']) ?></a></td>
              <td><?= e((string) ($donation['donor_name'] ?? '—')) ?></td>
              <td class="wrap"><?= e((string) ($donation['fundraiser_title'] ?? 'General fund')) ?></td>
              <td><?= e(ucfirst((string) ($donation['gateway_code'] ?? '—'))) ?></td>
              <td><span class="<?= e(status_badge_class((string) $donation['status'])) ?>"><?= e((string) $donation['status']) ?></span></td>
              <td class="num"><?= e(money((int) $donation['amount_minor'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<div class="grid-2">
  <section class="panel">
    <div class="panel-head">
      <h2>Fundraiser queue</h2>
      <a class="link-arrow" href="<?= e(base_url('admin/fundraisers')) ?>">Manage all</a>
    </div>
    <?php if ($recentFundraisers === []): ?>
      <p class="text-muted">Nothing in the queue.</p>
    <?php else: ?>
      <ul class="donations-list">
        <?php foreach ($recentFundraisers as $row): ?>
          <li>
            <span class="avatar"><?= e(initials((string) $row['title'])) ?></span>
            <div class="d-info">
              <b><a href="<?= e(base_url('admin/fundraisers/' . (string) $row['id'])) ?>"><?= e(mb_substr((string) $row['title'], 0, 42)) ?></a></b>
              <span class="d-when">
                <?= e(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?>
                &middot; <?= e(dt((string) $row['created_at'], 'j M Y')) ?>
              </span>
            </div>
            <span class="<?= e(status_badge_class((string) $row['status'])) ?>"><?= e(str_replace('_', ' ', (string) $row['status'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2>Recent activity</h2>
      <a class="link-arrow" href="<?= e(base_url('admin/audit')) ?>">Full audit log</a>
    </div>
    <ul class="timeline">
      <?php foreach ($recentAudit as $entry): ?>
        <li>
          <span class="when"><?= e(relative_time((string) $entry['created_at'])) ?></span>
          <div class="what"><?= e((string) $entry['action']) ?></div>
          <div class="meta">
            <?= e((string) ($entry['user_email'] ?? 'system')) ?>
            <?php if (!empty($entry['entity_type'])): ?> &middot; <?= e((string) $entry['entity_type']) ?> #<?= e((string) $entry['entity_id']) ?><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<section class="panel">
  <div class="panel-head">
    <h2>Latest gateway callbacks</h2>
    <a class="link-arrow" href="<?= e(base_url('admin/payment-callbacks')) ?>">All callbacks</a>
  </div>
  <p class="panel-sub">Raw provider traffic, recorded for reconciliation. Credentials and card data are never stored.</p>
  <?php if ($callbacks === []): ?>
    <p class="text-muted">No callbacks recorded yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>When</th><th>Gateway</th><th>Method</th><th>Result</th><th>Transaction</th><th class="wrap">Reason</th></tr></thead>
        <tbody>
          <?php foreach ($callbacks as $callback): ?>
            <tr>
              <td><?= e(dt((string) $callback['created_at'], 'j M Y H:i')) ?></td>
              <td><?= e(ucfirst((string) ($callback['gateway_code'] ?? '—'))) ?></td>
              <td><?= e((string) $callback['callback_method']) ?></td>
              <td>
                <?php
                  $result = (string) $callback['validation_result'];
                  $badge = match ($result) {
                      'accepted' => 'badge-green',
                      'duplicate' => 'badge-purple',
                      'rejected', 'error' => 'badge-red',
                      default => 'badge-grey',
                  };
                ?>
                <span class="badge <?= e($badge) ?>"><?= e($result) ?></span>
              </td>
              <td class="mono"><?= e((string) ($callback['received_transaction_id'] ?: '—')) ?></td>
              <td class="wrap muted"><?= e((string) ($callback['reason'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
