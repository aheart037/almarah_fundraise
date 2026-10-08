<?php
/**
 * Public team directory.
 *
 * @var array $teams
 * @var int $total
 * @var int $page
 * @var int $lastPage
 * @var string $financial
 */
?>
<section class="page-banner">
  <div class="wrap">
    <span class="eyebrow">Together We Do More</span>
    <h1>Fundraising teams</h1>
    <p>Groups of friends, families, offices and schools raising money together. Join one, or start your own.</p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="chip-row">
      <a class="chip <?= $financial === '' ? 'active' : '' ?>" href="<?= e(base_url('teams')) ?>">All teams</a>
      <a class="chip <?= $financial === 'school' ? 'active' : '' ?>" href="<?= e(base_url('teams?type=school')) ?>">Schools</a>
      <a class="chip <?= $financial === 'corporate' ? 'active' : '' ?>" href="<?= e(base_url('teams?type=corporate')) ?>">Companies</a>
      <a class="chip <?= $financial === 'community' ? 'active' : '' ?>" href="<?= e(base_url('teams?type=community')) ?>">Community</a>
    </div>

    <p class="text-muted mt-2"><?= e(number_format($total)) ?> active team<?= $total === 1 ? '' : 's' ?>.</p>

    <?php if ($teams === []): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <h3>No teams here yet</h3>
        <?php if (can_fundraiser_capability('manage_teams')): ?>
          <p>Be the first: create a team, invite your friends, and raise money together.</p>
          <a class="btn btn-brand" href="<?= e(base_url($authUser ? 'dashboard/teams' : 'register')) ?>">Start a team</a>
        <?php else: ?>
          <p>Creating fundraising teams is currently disabled by an administrator.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="card-grid cols-3 mt-3">
        <?php foreach ($teams as $team): ?>
          <?php require __DIR__ . '/../../partials/team-card.php'; ?>
        <?php endforeach; ?>
      </div>

      <?php $query = ['type' => $financial]; require __DIR__ . '/../../partials/pagination.php'; ?>
    <?php endif; ?>
  </div>
</section>
