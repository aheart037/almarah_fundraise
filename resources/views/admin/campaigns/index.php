<?php
/**
 * Admin campaign list.
 *
 * @var array $campaigns
 * @var array $counts
 */
?>
<div class="dash-head">
  <div>
    <h1>Campaigns</h1>
    <p class="sub">Create, edit and remove the appeals shown on the public site.</p>
  </div>
  <a class="btn btn-brand" href="<?= e(base_url('admin/campaigns/create')) ?>">New campaign</a>
</div>

<div class="stat-cards">
  <div class="stat-card">
    <div class="k">Live</div>
    <div class="v"><?= e((string) ($counts['active'] ?? 0)) ?></div>
    <div class="d">Visible to the public</div>
  </div>
  <div class="stat-card">
    <div class="k">Drafts</div>
    <div class="v"><?= e((string) ($counts['draft'] ?? 0)) ?></div>
    <div class="d">Not yet published</div>
  </div>
  <div class="stat-card">
    <div class="k">Paused</div>
    <div class="v"><?= e((string) ($counts['paused'] ?? 0)) ?></div>
    <div class="d">Hidden for now</div>
  </div>
  <div class="stat-card">
    <div class="k">Closed</div>
    <div class="v"><?= e((string) ($counts['closed'] ?? 0)) ?></div>
    <div class="d">Appeal finished</div>
  </div>
</div>

<section class="panel">
  <?php if ($campaigns === []): ?>
    <div class="empty-state">
      <h3>No campaigns yet</h3>
      <p>Add your first campaign and it will appear on the public Campaigns page once it is set to Active.</p>
      <a class="btn btn-brand" href="<?= e(base_url('admin/campaigns/create')) ?>">New campaign</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Campaign</th><th>Status</th><th>Dates</th><th class="num">Raised</th><th>Created</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($campaigns as $row): ?>
            <?php
              $id = (int) $row['id'];
              $status = (string) $row['status'];
            ?>
            <tr>
              <td class="wrap">
                <b><a href="<?= e(base_url('admin/campaigns/' . $id . '/edit')) ?>"><?= e((string) $row['title']) ?></a></b>
                <?php if (!empty($row['featured'])): ?>
                  <span class="badge badge-featured" style="margin-left:6px">featured</span>
                <?php endif; ?>
                <div class="muted" style="font-size:12px">/campaigns/<?= e((string) $row['slug']) ?></div>
              </td>
              <td><span class="<?= e(status_badge_class($status)) ?>"><?= e($status) ?></span></td>
              <td>
                <?php if (!empty($row['start_at']) || !empty($row['end_at'])): ?>
                  <?= !empty($row['start_at']) ? e(dt((string) $row['start_at'], 'j M Y')) : '—' ?>
                  &rarr;
                  <?= !empty($row['end_at']) ? e(dt((string) $row['end_at'], 'j M Y')) : 'open' ?>
                <?php else: ?>
                  <span class="muted">Open-ended</span>
                <?php endif; ?>
              </td>
              <td class="num"><?= e(money_short((int) ($row['raised_minor'] ?? 0))) ?></td>
              <td><?= e(dt((string) $row['created_at'], 'j M Y')) ?></td>
              <td style="white-space:nowrap">
                <?php if ($status === 'active'): ?>
                  <a class="btn btn-light btn-sm" href="<?= e(base_url('campaigns/' . (string) $row['slug'])) ?>" target="_blank" rel="noopener">View</a>
                <?php endif; ?>
                <a class="btn btn-outline-brand btn-sm" href="<?= e(base_url('admin/campaigns/' . $id . '/edit')) ?>">Edit</a>
                <form method="post" action="<?= e(base_url('admin/campaigns/' . $id . '/delete')) ?>" style="display:inline"
                      data-confirm="Delete this campaign? It is removed from the site, but donations already made to it are kept in the records.">
                  <?= csrf_field() ?>
                  <button class="btn btn-light btn-sm" type="submit" style="color:#a3231b">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
