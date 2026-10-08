<?php
/**
 * Admin fundraiser list and review queue.
 *
 * @var array $fundraisers
 * @var int $total
 * @var int $page
 * @var int $lastPage
 * @var array $filters
 * @var array $categories
 * @var array $counts
 */
?>
<div class="dash-head">
  <div>
    <h1>Fundraisers</h1>
    <p class="sub">Review submissions, moderate content and manage what is public.</p>
  </div>
</div>

<div class="stat-cards">
  <a class="stat-card" href="<?= e(base_url('admin/fundraisers?status=pending_review')) ?>">
    <div class="k">Waiting for review</div>
    <div class="v"><?= e((string) ($counts['pending_review'] ?? 0)) ?></div>
    <div class="d">Action needed</div>
  </a>
  <a class="stat-card" href="<?= e(base_url('admin/fundraisers?status=published')) ?>">
    <div class="k">Live</div>
    <div class="v"><?= e((string) ($counts['published'] ?? 0)) ?></div>
    <div class="d">Visible to the public</div>
  </a>
  <a class="stat-card" href="<?= e(base_url('admin/fundraisers?status=draft')) ?>">
    <div class="k">Drafts</div>
    <div class="v"><?= e((string) ($counts['draft'] ?? 0)) ?></div>
    <div class="d">Owners still writing</div>
  </a>
  <a class="stat-card" href="<?= e(base_url('admin/fundraisers?status=paused')) ?>">
    <div class="k">Paused / archived</div>
    <div class="v"><?= e((string) ((int) ($counts['paused'] ?? 0) + (int) ($counts['archived'] ?? 0))) ?></div>
    <div class="d">Not accepting donations</div>
  </a>
</div>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(base_url('admin/fundraisers')) ?>">
    <div class="search-field">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input class="input" type="search" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Title, owner or slug">
    </div>
    <div class="select-field">
      <select class="select-field" name="status" data-autosubmit aria-label="Status">
        <option value="">Any status</option>
        <?php foreach (['draft', 'pending_review', 'changes_requested', 'published', 'paused', 'rejected', 'archived'] as $status): ?>
          <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(str_replace('_', ' ', ucfirst($status))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="select-field">
      <select class="select-field" name="category" data-autosubmit aria-label="Cause">
        <option value="">All causes</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= e((string) $category['id']) ?>" <?= (string) $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e((string) $category['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="select-field">
      <select class="select-field" name="sort" data-autosubmit aria-label="Sort">
        <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest first</option>
        <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
        <option value="most_raised" <?= $filters['sort'] === 'most_raised' ? 'selected' : '' ?>>Most raised</option>
      </select>
    </div>
    <button class="btn btn-brand" type="submit">Filter</button>
    <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['category_id'] !== ''): ?>
      <a class="btn btn-light" href="<?= e(base_url('admin/fundraisers')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <?php if ($fundraisers === []): ?>
    <div class="empty-state">
      <h3>Nothing matches those filters</h3>
      <p><?= e(number_format($total)) ?> fundraiser<?= $total === 1 ? '' : 's' ?> in total.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Fundraiser</th><th>Owner</th><th>Status</th><th>Approval</th>
            <th class="num">Raised</th><th class="num">Goal</th><th>Created</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fundraisers as $row): ?>
            <?php
              $id = (int) $row['id'];
              $status = (string) $row['status'];
              $approval = (string) $row['approval_status'];
              $raised = (int) ($row['raised_minor'] ?? 0);
              $goal = (int) ($row['goal_minor'] ?? 0);
              $percent = $goal > 0 ? min(100, (int) round($raised / $goal * 100)) : 0;
            ?>
            <tr>
              <td class="wrap">
                <b><a href="<?= e(base_url('admin/fundraisers/' . $id)) ?>"><?= e((string) $row['title']) ?></a></b>
                <?php if (!empty($row['featured'])): ?>
                  <span class="badge badge-featured" style="margin-left:6px">featured</span>
                <?php endif; ?>
                <div class="muted" style="font-size:12px">
                  <?= e((string) ($row['category_name'] ?? 'No cause')) ?>
                  <?php if (!empty($row['campaign_title'])): ?> &middot; <?= e((string) $row['campaign_title']) ?><?php endif; ?>
                </div>
              </td>
              <td class="wrap">
                <?= e(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?>
                <div class="muted" style="font-size:12px"><?= e((string) ($row['owner_email'] ?? '')) ?></div>
              </td>
              <td><span class="<?= e(status_badge_class($status)) ?>"><?= e(str_replace('_', ' ', $status)) ?></span></td>
              <td>
                <span class="<?= e(status_badge_class($approval)) ?>"><?= e($approval) ?></span>
              </td>
              <td class="num">
                <?= e(money_short($raised)) ?>
                <?php if ($percent > 0): ?><div class="muted" style="font-size:12px"><?= e((string) $percent) ?>%</div><?php endif; ?>
              </td>
              <td class="num"><?= e(money_short($goal)) ?></td>
              <td><?= e(dt((string) $row['created_at'], 'j M Y')) ?></td>
              <td><a class="btn btn-outline-brand btn-sm" href="<?= e(base_url('admin/fundraisers/' . $id)) ?>">Review</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php
    $query = ['q' => $filters['q'], 'status' => $filters['status'], 'category' => (string) $filters['category_id'], 'sort' => $filters['sort']];
    require __DIR__ . '/../../partials/pagination.php';
    ?>
  <?php endif; ?>
</section>
