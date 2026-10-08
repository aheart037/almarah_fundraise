<?php
/**
 * Donor list.
 *
 * @var array $donors
 * @var int $total
 * @var int $page
 * @var int $lastPage
 * @var array $filters
 */
?>
<div class="dash-head">
  <div>
    <h1>Donors</h1>
    <p class="sub">Everyone who has given through the platform, whether or not they hold an account.</p>
  </div>
</div>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(base_url('admin/donors')) ?>">
    <div class="search-field">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input class="input" type="search" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Name, email or phone">
    </div>
    <button class="btn btn-brand" type="submit">Search</button>
    <?php if ($filters['q'] !== ''): ?>
      <a class="btn btn-light" href="<?= e(base_url('admin/donors')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <p class="text-muted"><?= e(number_format($total)) ?> donor<?= $total === 1 ? '' : 's' ?>.</p>

  <?php if ($donors === []): ?>
    <div class="empty-state">
      <h3>No donors found</h3>
      <p>Donors are created automatically the first time somebody gives.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Donor</th><th>Email</th><th>Phone</th><th class="num">Donations</th><th class="num">Confirmed</th><th>First seen</th></tr>
        </thead>
        <tbody>
          <?php foreach ($donors as $donor): ?>
            <tr>
              <td class="wrap">
                <b><?= e((string) $donor['name']) ?></b>
                <?php if (!empty($donor['user_id'])): ?>
                  <span class="badge badge-featured" style="margin-left:6px">has account</span>
                <?php endif; ?>
              </td>
              <td><?= e((string) $donor['email']) ?></td>
              <td><?= e((string) ($donor['phone'] ?? '—')) ?></td>
              <td class="num"><?= e((string) (int) ($donor['donation_count'] ?? 0)) ?></td>
              <td class="num"><?= e(money_short((int) ($donor['completed_minor'] ?? 0))) ?></td>
              <td><?= e(dt((string) $donor['created_at'], 'j M Y')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php $query = ['q' => $filters['q']]; require __DIR__ . '/../partials/pagination.php'; ?>
  <?php endif; ?>
</section>

<div class="alert alert-info">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
  <p>Donor records hold contact details and donation history only. Card numbers, expiry dates and CVVs are never received by this platform.</p>
</div>
