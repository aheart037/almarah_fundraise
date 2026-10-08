<?php
/**
 * Donation ledger.
 *
 * @var array $donations
 * @var int $total
 * @var array $sums
 * @var int $page
 * @var int $lastPage
 * @var array $filters
 * @var array $totals
 * @var array $counts
 */
?>
<div class="dash-head">
  <div>
    <h1>Donations</h1>
    <p class="sub">Every payment attempt, and what the bank said about it.</p>
  </div>
  <div class="sr-actions">
    <a class="btn btn-outline-brand" href="<?= e(base_url('admin/donations/export?' . http_build_query(array_filter(['q' => $filters['q'], 'status' => $filters['status'], 'gateway' => $filters['gateway']])))) ?>">Export filtered CSV</a>
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card brand">
    <div class="k">Confirmed</div>
    <div class="v"><?= e(money((int) ($sums['completed'] ?? 0))) ?></div>
    <div class="d">Filtered total</div>
  </div>
  <div class="stat-card">
    <div class="k">Awaiting the bank</div>
    <div class="v"><?= e(money((int) ($sums['pending'] ?? 0))) ?></div>
    <div class="d">Pending and processing</div>
  </div>
  <div class="stat-card">
    <div class="k">Failed / cancelled</div>
    <div class="v"><?= e(money((int) ($sums['failed'] ?? 0))) ?></div>
    <div class="d">Never counted</div>
  </div>
  <div class="stat-card">
    <div class="k">Refunded</div>
    <div class="v"><?= e(money((int) ($sums['refunded'] ?? 0))) ?></div>
    <div class="d"><?= e(number_format((int) ($counts['refunded'] ?? 0))) ?> donation(s)</div>
  </div>
</div>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(base_url('admin/donations')) ?>">
    <div class="search-field">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input class="input" type="search" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Reference, donor, email or bank transaction">
    </div>
    <div class="select-field">
      <select class="select-field" name="status" data-autosubmit aria-label="Status">
        <option value="">Any status</option>
        <?php foreach (['completed', 'processing', 'pending', 'failed', 'cancelled', 'refunded', 'abandoned'] as $status): ?>
          <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="select-field">
      <select class="select-field" name="gateway" data-autosubmit aria-label="Gateway">
        <option value="">Any gateway</option>
        <option value="meezan" <?= $filters['gateway'] === 'meezan' ? 'selected' : '' ?>>Meezan Bank</option>
        <option value="etisalat" <?= $filters['gateway'] === 'etisalat' ? 'selected' : '' ?>>Etisalat / UBL EPG</option>
      </select>
    </div>
    <div class="field" style="margin:0">
      <label class="sr-only" for="date_from">From</label>
      <input class="input" type="date" id="date_from" name="date_from" value="<?= e((string) $filters['date_from']) ?>">
    </div>
    <div class="field" style="margin:0">
      <label class="sr-only" for="date_to">To</label>
      <input class="input" type="date" id="date_to" name="date_to" value="<?= e((string) $filters['date_to']) ?>">
    </div>
    <button class="btn btn-brand" type="submit">Filter</button>
  </form>

  <p class="text-muted"><?= e(number_format($total)) ?> donation<?= $total === 1 ? '' : 's' ?> match.</p>

  <?php if ($donations === []): ?>
    <div class="empty-state">
      <h3>No donations match</h3>
      <p>Try clearing the filters, or widen the date range.</p>
      <a class="btn btn-brand" href="<?= e(base_url('admin/donations')) ?>">Show everything</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Reference</th><th>Date</th><th>Donor</th><th>Fundraiser</th><th>Gateway</th><th>Status</th><th class="num">Amount</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($donations as $donation): ?>
            <tr>
              <td class="mono"><a href="<?= e(base_url('admin/donations/' . (string) $donation['id'])) ?>"><?= e((string) $donation['public_reference']) ?></a></td>
              <td><?= e(dt((string) $donation['created_at'], 'j M Y H:i')) ?></td>
              <td class="wrap">
                <?= e(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? '—')) ?>
                <?php if (empty($donation['anonymous']) && !empty($donation['donor_email'])): ?>
                  <div class="muted" style="font-size:12px"><?= e((string) $donation['donor_email']) ?></div>
                <?php endif; ?>
              </td>
              <td class="wrap"><?= e((string) ($donation['fundraiser_title'] ?? 'General fund')) ?></td>
              <td>
                <?= e(ucfirst((string) ($donation['gateway_code'] ?? '—'))) ?>
                <div class="muted" style="font-size:12px"><?= e(ucfirst((string) ($donation['environment'] ?? ''))) ?></div>
              </td>
              <td><span class="<?= e(status_badge_class((string) $donation['status'])) ?>"><?= e((string) $donation['status']) ?></span></td>
              <td class="num"><?= e(money((int) $donation['amount_minor'], (string) $donation['currency'])) ?></td>
              <td><a class="btn btn-light btn-sm" href="<?= e(base_url('admin/donations/' . (string) $donation['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php
    $query = ['q' => $filters['q'], 'status' => $filters['status'], 'gateway' => $filters['gateway'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']];
    require __DIR__ . '/../../partials/pagination.php';
    ?>
  <?php endif; ?>
</section>
