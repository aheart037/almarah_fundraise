<?php
/**
 * Donations received by one fundraiser (owner view).
 *
 * @var array $fundraiser
 * @var array $donations
 * @var int $total
 * @var int $page
 * @var int $lastPage
 * @var array $filters
 */

$id = (int) $fundraiser['id'];
$currency = (string) ($fundraiser['currency'] ?? 'PKR');
?>
<div class="dash-head">
  <div>
    <h1>Donations</h1>
    <p class="sub"><?= e((string) $fundraiser['title']) ?></p>
  </div>
  <div class="sr-actions">
    <a class="btn btn-outline-brand" href="<?= e(base_url('dashboard/fundraisers/' . $id . '/donations/export')) ?>">Export CSV</a>
    <?php if (can_fundraiser_capability('manage_pages')): ?>
      <a class="btn btn-light" href="<?= e(base_url('dashboard/fundraisers/' . $id . '/edit')) ?>">Edit fundraiser</a>
    <?php endif; ?>
  </div>
</div>

<p class="text-muted">
  Only donations confirmed by the bank are counted toward your total. Pending, failed and cancelled
  attempts are still shown so you can see everything that happened.
</p>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(base_url('dashboard/fundraisers/' . $id . '/donations')) ?>">
    <div class="search-field">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input class="input" type="search" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Reference, donor name or email">
    </div>
    <div class="select-field">
      <label class="sr-only" for="status">Status</label>
      <select class="select-field" id="status" name="status" data-autosubmit>
        <option value="">All statuses</option>
        <?php foreach (['completed', 'processing', 'pending', 'failed', 'cancelled', 'refunded', 'abandoned'] as $status): ?>
          <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-brand" type="submit">Filter</button>
    <?php if ($filters['q'] !== '' || $filters['status'] !== ''): ?>
      <a class="btn btn-light" href="<?= e(base_url('dashboard/fundraisers/' . $id . '/donations')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <?php if ($donations === []): ?>
    <div class="empty-state">
      <h3>No donations yet</h3>
      <p>Share your fundraiser link and the first ones will appear here. Verified donations update your total automatically.</p>
      <button class="btn btn-brand" type="button" data-copy="<?= e(base_url('fundraisers/' . (string) $fundraiser['slug'])) ?>">Copy your fundraiser link</button>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Date</th>
            <th>Donor</th>
            <th>Status</th>
            <th class="num">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($donations as $donation): ?>
            <tr>
              <td class="mono"><?= e((string) $donation['public_reference']) ?></td>
              <td><?= e(dt((string) $donation['created_at'], 'j M Y H:i')) ?></td>
              <td class="wrap">
                <?= e(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? 'Supporter')) ?>
                <?php if (empty($donation['anonymous']) && !empty($donation['donor_message'])): ?>
                  <div class="muted" style="font-size:12.5px">“<?= e(mb_substr((string) $donation['donor_message'], 0, 90)) ?>”</div>
                <?php endif; ?>
              </td>
              <td>
                <span class="<?= e(status_badge_class((string) $donation['status'])) ?>"><?= e((string) $donation['status']) ?></span>
                <?php if (!empty($donation['gateway_code'])): ?>
                  <div class="muted" style="font-size:12px"><?= e(ucfirst((string) $donation['gateway_code'])) ?></div>
                <?php endif; ?>
              </td>
              <td class="num"><?= e(money((int) $donation['amount_minor'], (string) ($donation['currency'] ?? $currency))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php $query = ['status' => $filters['status'], 'q' => $filters['q']]; require __DIR__ . '/../../partials/pagination.php'; ?>
  <?php endif; ?>
</section>

<div class="alert alert-info">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
  <p>For donor privacy we show names and amounts but never card details or bank data &mdash; we never receive those.</p>
</div>
