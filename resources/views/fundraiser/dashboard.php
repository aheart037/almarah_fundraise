<?php
/**
 * Fundraiser dashboard overview.
 *
 * @var array $fundraisers
 * @var array $totals
 * @var array $recentDonations
 * @var array $needsAttention
 * @var array $teams
 * @var array $endingSoon
 * @var bool $verified
 */

$canManagePages = can_fundraiser_capability('manage_pages');
$canManageTeams = can_fundraiser_capability('manage_teams');
$canPublishUpdates = can_fundraiser_capability('publish_updates');
$raised = (int) ($totals['raised_minor'] ?? 0);
$donationCount = (int) ($totals['donation_count'] ?? 0);
$active = 0;
foreach ($fundraisers as $row) {
    if ((string) $row['status'] === 'published') { $active++; }
}
?>
<div class="dash-head">
  <div>
    <h1>Welcome back, <?= e((string) ($authUser['first_name'] ?? 'friend')) ?></h1>
    <p class="sub">Here is how your fundraising is going.</p>
  </div>
  <div class="sr-actions">
    <?php if ($canManageTeams): ?>
      <a class="btn btn-outline-brand" href="<?= e(base_url('dashboard/teams')) ?>">My teams</a>
    <?php endif; ?>
    <?php if ($canManagePages): ?>
      <a class="btn btn-brand" href="<?= e(base_url('dashboard/fundraisers/create')) ?>">Start a fundraiser</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$verified): ?>
  <div class="alert alert-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/></svg>
    <div>
      <strong>Please verify your email address.</strong>
      <p>Until then we cannot send you donation receipts. <a href="<?= e(base_url('verify-email')) ?>">Resend the verification link</a>.</p>
    </div>
  </div>
<?php endif; ?>

<div class="stat-cards">
  <div class="stat-card brand">
    <div class="k">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21.2l7.7-7.8 1.1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
      Total raised
    </div>
    <div class="v"><?= e(money($raised)) ?></div>
    <div class="d">Verified donations only</div>
  </div>

  <div class="stat-card">
    <div class="k">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 9.5h4.5a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3H15"/></svg>
      Donations
    </div>
    <div class="v"><?= e(number_format($donationCount)) ?></div>
    <div class="d">Across all your fundraisers</div>
  </div>

  <div class="stat-card">
    <div class="k">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
      Live pages
    </div>
    <div class="v"><?= e((string) $active) ?></div>
    <div class="d"><?= e((string) count($fundraisers)) ?> total fundraiser<?= count($fundraisers) === 1 ? '' : 's' ?></div>
  </div>

  <?php if ($canManageTeams): ?>
    <div class="stat-card">
      <div class="k">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Teams
      </div>
      <div class="v"><?= e((string) count($teams)) ?></div>
      <div class="d">Teams you belong to</div>
    </div>
  <?php endif; ?>
</div>

<?php if ($needsAttention !== [] && $canManagePages): ?>
  <section class="panel tint">
    <h2>Needs your attention</h2>
    <p class="panel-sub">These fundraisers are not live yet.</p>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Fundraiser</th><th>Status</th><th>What to do</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($needsAttention as $row): ?>
            <tr>
              <td class="wrap"><?= e((string) $row['title']) ?></td>
              <td><span class="<?= e(status_badge_class((string) $row['status'])) ?>"><?= e(str_replace('_', ' ', (string) $row['status'])) ?></span></td>
              <td class="wrap">
                <?php if ((string) $row['status'] === 'draft'): ?>
                  Finish your story and submit it for review.
                <?php elseif ((string) $row['status'] === 'changes_requested'): ?>
                  Our team asked for changes — open the page to see what is needed.
                <?php else: ?>
                  Read our note and update the page, then submit again.
                <?php endif; ?>
              </td>
              <td><a class="btn btn-brand btn-sm" href="<?= e(base_url('dashboard/fundraisers/' . (string) $row['id'] . '/edit')) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<div class="grid-2">
  <section class="panel">
    <div class="panel-head">
      <h2>Your fundraisers</h2>
      <a class="link-arrow" href="<?= e(base_url('dashboard/fundraisers')) ?>">View all</a>
    </div>

    <?php if ($fundraisers === []): ?>
      <div class="empty-state">
        <h3>No fundraisers yet</h3>
        <?php if ($canManagePages): ?>
          <p>Start one and share it with the people who already care about your cause.</p>
          <a class="btn btn-brand" href="<?= e(base_url('dashboard/fundraisers/create')) ?>">Start a fundraiser</a>
        <?php else: ?>
          <p>Fundraiser page creation is currently disabled by an administrator.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Fundraiser</th><th>Status</th><th class="num">Raised</th><th class="num">Goal</th></tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($fundraisers, 0, 6) as $row): ?>
              <tr>
                <td class="wrap">
                  <?php if ($canManagePages): ?>
                    <a href="<?= e(base_url('dashboard/fundraisers/' . (string) $row['id'] . '/edit')) ?>"><?= e((string) $row['title']) ?></a>
                  <?php else: ?>
                    <?= e((string) $row['title']) ?>
                  <?php endif; ?>
                  <div class="muted mono" style="font-size:12px"><?= e((string) $row['slug']) ?></div>
                </td>
                <td><span class="<?= e(status_badge_class((string) $row['status'])) ?>"><?= e(str_replace('_', ' ', (string) $row['status'])) ?></span></td>
                <td class="num"><?= e(money_short((int) $row['raised_minor'])) ?></td>
                <td class="num"><?= e(money_short((int) $row['goal_minor'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2>Recent donations</h2>
    </div>

    <?php if ($recentDonations === []): ?>
      <div class="empty-state">
        <h3>No donations yet</h3>
        <p>Share your page link on WhatsApp and social media to get the first ones in.</p>
      </div>
    <?php else: ?>
      <ul class="donations-list">
        <?php foreach ($recentDonations as $donation): ?>
          <li>
            <span class="avatar"><?= e(initials(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? 'Supporter'))) ?></span>
            <div class="d-info">
              <b><?= e(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? 'Supporter')) ?></b>
              <span class="d-when"><?= e(relative_time((string) $donation['created_at'])) ?> &middot; <?= e(mb_substr((string) $donation['fundraiser_title'], 0, 28)) ?></span>
            </div>
            <span class="d-amt">
              <?= e(money((int) $donation['amount_minor'], (string) $donation['currency'])) ?>
              <span class="<?= e(status_badge_class((string) $donation['status'])) ?>" style="display:block;margin-top:5px"><?= e((string) $donation['status']) ?></span>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php if ($endingSoon !== []): ?>
  <section class="panel">
    <h2>Ending in the next 14 days</h2>
    <p class="panel-sub">A good moment to post an update and ask your supporters for one last push.</p>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Fundraiser</th><th>Ends</th><th class="num">Raised</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($endingSoon as $row): ?>
            <tr>
              <td class="wrap"><?= e((string) $row['title']) ?></td>
              <td><?= e(dt((string) $row['end_at'], 'j M Y')) ?></td>
              <td class="num"><?= e(money_short((int) ($row['raised_minor'] ?? 0))) ?></td>
              <td><a class="btn btn-light btn-sm" href="<?= e(base_url('dashboard/fundraisers/' . (string) $row['id'] . '/updates')) ?>"><?= $canPublishUpdates ? 'Post update' : 'View updates' ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
