<?php
/**
 * Team card — uses the same visual system with a team-specific badge.
 *
 * @var array $team
 */

$raised = (int) ($team['raised_minor'] ?? 0);
$goal = (int) ($team['goal_minor'] ?? 0);
$currency = (string) ($team['currency'] ?? 'PKR');
$percent = $goal > 0 ? min(100.0, round($raised / $goal * 100, 1)) : 0.0;
$url = base_url('teams/' . (string) ($team['slug'] ?? $team['id']));
$members = (int) ($team['member_count'] ?? 0);
?>
<article class="f-card reveal">
  <a class="f-media" href="<?= e($url) ?>" aria-label="<?= e((string) $team['name']) ?>">
    <span class="f-tag">Team</span>
    <div style="width:100%;height:100%;min-height:150px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--brand) 0%,var(--brand-darker) 100%);color:#fff;font-size:34px;font-weight:800;letter-spacing:.02em">
      <?= e(initials((string) $team['name'])) ?>
    </div>
  </a>
  <div class="f-body">
    <h4><a href="<?= e($url) ?>"><?= e((string) $team['name']) ?></a></h4>
    <?php if ($goal > 0): ?>
      <div class="f-progress">
        <div class="progress" role="progressbar" aria-valuenow="<?= e((string) (int) $percent) ?>" aria-valuemin="0" aria-valuemax="100">
          <span data-w="<?= e(number_format($percent, 1, '.', '')) ?>"></span>
        </div>
        <div class="f-nums">
          <span><b><?= e(money_short($raised, $currency)) ?></b> raised</span>
          <span><?= e((string) (int) round($percent)) ?>%</span>
        </div>
      </div>
    <?php else: ?>
      <div class="f-nums" style="margin-top:10px">
        <span><b><?= e(money_short($raised, $currency)) ?></b> raised</span>
      </div>
    <?php endif; ?>
    <div class="f-impact">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span><?= e($members > 0 ? $members . ' member' . ($members === 1 ? '' : 's') : 'Be the first to join') ?></span>
    </div>
  </div>
</article>
