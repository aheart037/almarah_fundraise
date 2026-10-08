<?php
/**
 * Team management for the captain.
 *
 * @var array $team
 * @var array $members
 * @var array $invitations
 * @var bool $isOwner
 */

$id = (int) $team['id'];
$currency = (string) ($team['currency'] ?? 'PKR');
$raised = (int) ($team['raised_minor'] ?? 0);
$goal = (int) ($team['goal_minor'] ?? 0);
$percent = $goal > 0 ? min(100.0, round($raised / $goal * 100, 1)) : 0.0;
?>
<div class="dash-head">
  <div>
    <h1><?= e((string) $team['name']) ?></h1>
    <p class="sub"><?= e((string) ($team['description'] ?? 'Team fundraising page')) ?></p>
  </div>
  <div class="sr-actions">
    <button class="btn btn-outline-brand" type="button" data-copy="<?= e(base_url('teams/' . (string) $team['slug'])) ?>">Copy public link</button>
    <a class="btn btn-light" href="<?= e(base_url('teams/' . (string) $team['slug'])) ?>">View public page</a>
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card brand">
    <div class="k">Raised together</div>
    <div class="v"><?= e(money($raised, $currency)) ?></div>
    <div class="d"><?= $goal > 0 ? 'Goal ' . e(money($goal, $currency)) : 'No goal set' ?></div>
  </div>
  <div class="stat-card">
    <div class="k">Members</div>
    <div class="v"><?= e((string) count($members)) ?></div>
    <div class="d"><?= e((string) count($invitations)) ?> pending invitation<?= count($invitations) === 1 ? '' : 's' ?></div>
  </div>
  <div class="stat-card">
    <div class="k">Progress</div>
    <div class="v"><?= e((string) (int) round($percent)) ?>%</div>
    <div class="d"><?= e((string) (int) ($team['donor_count'] ?? 0)) ?> donors</div>
  </div>
</div>

<?php if ($goal > 0): ?>
  <div class="progress lg mb-3" role="progressbar" aria-valuenow="<?= e((string) (int) $percent) ?>" aria-valuemin="0" aria-valuemax="100">
    <span data-w="<?= e(number_format($percent, 1, '.', '')) ?>"></span>
  </div>
<?php endif; ?>

<div class="grid-2">
  <section class="panel">
    <h2>Team details</h2>
    <p class="panel-sub"><?= $isOwner ? 'You are the team captain.' : 'Only the captain can change these details.' ?></p>

    <?php if ($isOwner): ?>
      <form method="post" action="<?= e(base_url('dashboard/teams/' . $id)) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field <?= error_for('name') !== '' ? 'is-invalid' : '' ?>">
          <label for="name">Team name<span class="req">*</span></label>
          <input class="input" type="text" id="name" name="name" required minlength="4" maxlength="120" value="<?= old('name', (string) $team['name']) ?>">
          <?php if (error_for('name') !== ''): ?><span class="field-error"><?= error_for('name') ?></span><?php endif; ?>
        </div>

        <div class="field">
          <label for="description">Description</label>
          <textarea class="textarea" id="description" name="description" rows="4"><?= old('description', (string) ($team['description'] ?? '')) ?></textarea>
        </div>

        <div class="field">
          <label for="goal">Team goal (PKR)</label>
          <div class="input-group">
            <span class="prefix">Rs</span>
            <input class="input" type="number" id="goal" name="goal" step="100" min="0"
                   value="<?= old('goal', $goal > 0 ? number_format($goal / 100, 0, '.', '') : '') ?>">
          </div>
        </div>

        <button class="btn btn-brand" type="submit">Save team</button>
      </form>
    <?php else: ?>
      <dl class="kv" style="grid-template-columns:140px 1fr">
        <dt>Name</dt><dd><?= e((string) $team['name']) ?></dd>
        <dt>Public link</dt><dd class="mono"><?= e(base_url('teams/' . (string) $team['slug'])) ?></dd>
        <dt>Goal</dt><dd><?= $goal > 0 ? e(money($goal, $currency)) : 'Not set' ?></dd>
      </dl>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2>Members</h2>
    <p class="panel-sub">Everyone on this team can share the page. Only you can manage it.</p>

    <ul class="donations-list">
      <?php foreach ($members as $member): ?>
        <?php $name = trim((string) ($member['first_name'] ?? '') . ' ' . (string) ($member['last_name'] ?? '')); ?>
        <li>
          <span class="avatar"><?= e(initials($name !== '' ? $name : 'Member')) ?></span>
          <div class="d-info">
            <b><?= e($name !== '' ? $name : (string) $member['email']) ?></b>
            <span class="d-when">Joined <?= e(dt((string) ($member['joined_at'] ?? ''), 'j M Y')) ?></span>
          </div>
          <?php if ((string) $member['role'] === 'owner'): ?>
            <span class="badge badge-featured">Captain</span>
          <?php elseif ($isOwner): ?>
            <form method="post" class="inline-form" action="<?= e(base_url('dashboard/teams/' . $id . '/members/' . (string) $member['id'] . '/remove')) ?>"
                  data-confirm="Remove this member from the team?">
              <?= csrf_field() ?>
              <button class="btn btn-light btn-sm" type="submit">Remove</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($isOwner): ?>
      <h3 class="mt-3" style="font-size:16px">Invite a member</h3>
      <form method="post" action="<?= e(base_url('dashboard/teams/' . $id . '/invite')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field <?= error_for('email') !== '' ? 'is-invalid' : '' ?>">
          <label for="email">Email address<span class="req">*</span></label>
          <div class="input-group">
            <input class="input" type="email" id="email" name="email" required value="<?= old('email') ?>" placeholder="friend@example.com">
            <button class="btn btn-brand" type="submit">Send invite</button>
          </div>
          <span class="form-help">They receive a link that is valid for 7 days and must be signed in with that address to accept.</span>
          <?php if (error_for('email') !== ''): ?><span class="field-error"><?= error_for('email') ?></span><?php endif; ?>
        </div>
      </form>

      <?php if ($invitations !== []): ?>
        <h3 class="mt-3" style="font-size:16px">Pending invitations</h3>
        <ul class="donations-list">
          <?php foreach ($invitations as $invitation): ?>
            <li>
              <span class="avatar"><?= e(initials((string) $invitation['email'])) ?></span>
              <div class="d-info">
                <b><?= e((string) $invitation['email']) ?></b>
                <span class="d-when">
                  <?= e((string) $invitation['status']) ?> &middot;
                  expires <?= e(dt((string) $invitation['expires_at'], 'j M Y')) ?>
                </span>
              </div>
              <span class="<?= e(status_badge_class((string) $invitation['status'])) ?>"><?= e((string) $invitation['status']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
