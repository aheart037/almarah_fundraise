<?php
/**
 * User administration.
 *
 * @var array $users
 * @var int $total
 * @var int $page
 * @var int $lastPage
 * @var array $filters
 * @var array $counts
 */
?>
<div class="dash-head">
  <div>
    <h1>Users</h1>
    <p class="sub">Fundraisers, donors and staff accounts.</p>
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card">
    <div class="k">Total</div>
    <div class="v"><?= e(number_format((int) $counts['all'])) ?></div>
    <div class="d">Registered accounts</div>
  </div>
  <div class="stat-card">
    <div class="k">Active</div>
    <div class="v"><?= e(number_format((int) $counts['active'])) ?></div>
    <div class="d">Can sign in</div>
  </div>
  <div class="stat-card">
    <div class="k">Suspended</div>
    <div class="v"><?= e(number_format((int) $counts['suspended'])) ?></div>
    <div class="d">Blocked from signing in</div>
  </div>
  <div class="stat-card">
    <div class="k">Donors</div>
    <div class="v"><?= e(number_format(app(\App\Repositories\DonorRepository::class)->countAll())) ?></div>
    <div class="d"><a href="<?= e(base_url('admin/donors')) ?>">See donor list</a></div>
  </div>
</div>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(base_url('admin/users')) ?>">
    <div class="search-field">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input class="input" type="search" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Name or email">
    </div>
    <div class="select-field">
      <select class="select-field" name="status" data-autosubmit aria-label="Status">
        <option value="">Any status</option>
        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="suspended" <?= $filters['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
      </select>
    </div>
    <div class="select-field">
      <select class="select-field" name="role" data-autosubmit aria-label="Role">
        <option value="">Any role</option>
        <?php foreach (['super_admin', 'admin', 'fundraiser', 'donor'] as $role): ?>
          <option value="<?= e($role) ?>" <?= $filters['role'] === $role ? 'selected' : '' ?>><?= e(str_replace('_', ' ', ucfirst($role))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-brand" type="submit">Search</button>
  </form>

  <?php if ($users === []): ?>
    <div class="empty-state">
      <h3>No users match</h3>
      <p><?= e(number_format($total)) ?> account<?= $total === 1 ? '' : 's' ?> in total.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>User</th><th>Roles</th><th>Status</th><th>Verified</th><th>Joined</th><th>Last sign-in</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <?php
              $name = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
              $roles = array_filter(array_map('trim', explode(',', (string) ($user['roles'] ?? ''))));
            ?>
            <tr>
              <td class="wrap">
                <b><?= e($name !== '' ? $name : (string) $user['email']) ?></b>
                <div class="muted" style="font-size:12px"><?= e((string) $user['email']) ?></div>
              </td>
              <td>
                <?php foreach ($roles as $role): ?>
                  <span class="badge badge-<?= e(str_replace('_', '', $role === 'super_admin' ? 'super_admin' : $role)) ?>"><?= e(str_replace('_', ' ', $role)) ?></span>
                <?php endforeach; ?>
              </td>
              <td><span class="<?= e(status_badge_class((string) $user['status'])) ?>"><?= e((string) $user['status']) ?></span></td>
              <td>
                <?php if (!empty($user['email_verified_at'])): ?>
                  <span class="badge badge-green">yes</span>
                <?php else: ?>
                  <span class="badge badge-amber">no</span>
                <?php endif; ?>
              </td>
              <td><?= e(dt((string) $user['created_at'], 'j M Y')) ?></td>
              <td><?= e($user['last_login_at'] !== null ? dt((string) $user['last_login_at'], 'j M Y') : '—') ?></td>
              <td><a class="btn btn-outline-brand btn-sm" href="<?= e(base_url('admin/users/' . (string) $user['id'])) ?>">Manage</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php $query = ['q' => $filters['q'], 'status' => $filters['status'], 'role' => $filters['role']]; require __DIR__ . '/../../partials/pagination.php'; ?>
  <?php endif; ?>
</section>
