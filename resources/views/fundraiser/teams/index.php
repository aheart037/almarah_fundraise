<?php
/**
 * The fundraiser's teams.
 *
 * @var array $teams
 */
?>
<div class="dash-head">
  <div>
    <h1>My teams</h1>
    <p class="sub">Fundraise together with friends, family or colleagues.</p>
  </div>
</div>

<div class="grid-2">
  <section class="panel">
    <h2>Create a team</h2>
    <p class="panel-sub">You become the team captain and can invite members by email.</p>

    <form method="post" action="<?= e(base_url('dashboard/teams')) ?>" novalidate>
      <?= csrf_field() ?>

      <div class="field <?= error_for('name') !== '' ? 'is-invalid' : '' ?>">
        <label for="name">Team name<span class="req">*</span></label>
        <input class="input" type="text" id="name" name="name" required minlength="4" maxlength="120"
               value="<?= old('name') ?>" placeholder="Team Izzat ki Roti">
        <?php if (error_for('name') !== ''): ?><span class="field-error"><?= error_for('name') ?></span><?php endif; ?>
      </div>

      <div class="field">
        <label for="description">What is your team doing?</label>
        <textarea class="textarea" id="description" name="description" rows="4" maxlength="2000"><?= old('description') ?></textarea>
      </div>

      <div class="field">
        <label for="goal">Team goal (optional, PKR)</label>
        <div class="input-group">
          <span class="prefix">Rs</span>
          <input class="input" type="number" id="goal" name="goal" step="100" min="0" value="<?= old('goal') ?>">
        </div>
      </div>

      <button class="btn btn-brand btn-lg" type="submit">Create team</button>
    </form>
  </section>

  <section class="panel">
    <h2>Your teams</h2>
    <p class="panel-sub"><?= e((string) count($teams)) ?> team<?= count($teams) === 1 ? '' : 's' ?>.</p>

    <?php if ($teams === []): ?>
      <div class="empty-state">
        <h3>You are not on a team yet</h3>
        <p>Create one above, or ask a team captain to invite you by email.</p>
      </div>
    <?php else: ?>
      <ul class="donations-list">
        <?php foreach ($teams as $team): ?>
          <li>
            <span class="avatar"><?= e(initials((string) $team['name'])) ?></span>
            <div class="d-info">
              <b><a href="<?= e(base_url('dashboard/teams/' . (string) $team['id'])) ?>"><?= e((string) $team['name']) ?></a></b>
              <span class="d-when">
                <?= e(money_short((int) $team['raised_minor'])) ?> raised
                <?php if ((int) ($team['donor_count'] ?? 0) > 0): ?> &middot; <?= e((string) (int) $team['donor_count']) ?> donors<?php endif; ?>
              </span>
            </div>
            <?php if ((string) ($team['role'] ?? '') === 'owner'): ?>
              <span class="badge badge-featured">Captain</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
