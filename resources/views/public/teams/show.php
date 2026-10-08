<?php
/**
 * Public team page.
 *
 * @var array $team
 * @var array $members
 * @var array $supporters
 */

$currency = (string) ($team['currency'] ?? 'PKR');
$raised = (int) ($team['raised_minor'] ?? 0);
$goal = (int) ($team['goal_minor'] ?? 0);
$percent = $goal > 0 ? min(100.0, round($raised / $goal * 100, 1)) : 0.0;
$owner = trim((string) ($team['first_name'] ?? '') . ' ' . (string) ($team['last_name'] ?? ''));
$shareUrl = base_url('teams/' . (string) $team['slug']);
?>
<section class="page-banner">
  <div class="wrap">
    <span class="eyebrow">Fundraising Team</span>
    <h1><?= e((string) $team['name']) ?></h1>
    <?php if (!empty($team['campaign_title'])): ?>
      <p>Raising for <?= e((string) $team['campaign_title']) ?></p>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="fr-layout">
      <div>
        <article class="prose reveal">
          <h2>About this team</h2>
          <p><?= nl2br(e((string) ($team['description'] ?? 'This team is raising money for Almarah Foundation.'))) ?></p>
          <?php if ($owner !== ''): ?>
            <p class="text-muted">Led by <?= e($owner) ?>.</p>
          <?php endif; ?>
        </article>

        <section class="panel reveal">
          <h2>Team members</h2>
          <p class="panel-sub"><?= e((string) count($members)) ?> member<?= count($members) === 1 ? '' : 's' ?>.</p>
          <?php if ($members === []): ?>
            <p class="text-muted">No members have joined yet.</p>
          <?php else: ?>
            <ul class="donations-list">
              <?php foreach ($members as $member): ?>
                <?php $memberName = trim((string) ($member['first_name'] ?? '') . ' ' . (string) ($member['last_name'] ?? '')); ?>
                <li>
                  <span class="avatar"><?= e(initials($memberName !== '' ? $memberName : 'Member')) ?></span>
                  <div class="d-info">
                    <b><?= e($memberName !== '' ? $memberName : 'Team member') ?></b>
                    <span class="d-when">Joined <?= e(dt((string) ($member['joined_at'] ?? ''), 'j M Y')) ?></span>
                  </div>
                  <?php if ((string) ($member['role'] ?? '') === 'owner'): ?>
                    <span class="badge badge-featured">Captain</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <?php if ($supporters !== []): ?>
          <section class="panel reveal">
            <h2>Recent donations to this team</h2>
            <ul class="donations-list">
              <?php foreach ($supporters as $donation): ?>
                <li>
                  <span class="avatar"><?= e(initials(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? 'Supporter'))) ?></span>
                  <div class="d-info">
                    <b><?= e(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? 'Supporter')) ?></b>
                    <span class="d-when"><?= e(relative_time((string) ($donation['completed_at'] ?? $donation['created_at']))) ?></span>
                  </div>
                  <span class="d-amt"><?= e(money((int) $donation['amount_minor'], (string) ($donation['currency'] ?? $currency))) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>
      </div>

      <aside>
        <div class="donation-box preview-sticky">
          <?php if ($goal > 0): ?>
            <div class="progress lg" role="progressbar" aria-valuenow="<?= e((string) (int) $percent) ?>" aria-valuemin="0" aria-valuemax="100">
              <span data-w="<?= e(number_format($percent, 1, '.', '')) ?>"></span>
            </div>
          <?php endif; ?>
          <div class="raised-row">
            <b><?= e(money($raised, $currency)) ?></b>
            <span>raised<?= $goal > 0 ? ' of ' . e(money($goal, $currency)) . ' goal' : ' so far' ?></span>
          </div>
          <div class="meta-row">
            <span><b><?= e(number_format((int) ($team['donor_count'] ?? 0))) ?></b> donors</span>
            <span><b><?= e((string) count($members)) ?></b> members</span>
          </div>

          <div class="donation-btns">
            <a class="btn btn-brand btn-block" href="<?= e(base_url('donate/' . (string) $team['slug'] . '?type=team')) ?>">Donate to this team</a>
            <button class="btn btn-outline-brand btn-block" type="button" data-copy="<?= e($shareUrl) ?>">Copy team link</button>
          </div>

          <div class="org-card">
            <span class="avatar"><?= e(initials((string) $team['name'])) ?></span>
            <div>
              <b><?= e($owner !== '' ? $owner : 'Team captain') ?></b>
              <span>Captain</span>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</section>
