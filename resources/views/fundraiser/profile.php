<?php
/**
 * Profile settings.
 *
 * @var array $user
 */
?>
<div class="dash-head">
  <div>
    <h1>Your profile</h1>
    <p class="sub">This is how your name appears on the fundraisers you create.</p>
  </div>
</div>

<div class="grid-2">
  <section class="panel">
    <h2>Details</h2>

    <form method="post" action="<?= e(base_url('dashboard/profile')) ?>" novalidate>
      <?= csrf_field() ?>

      <div class="form-row">
        <div class="field <?= error_for('first_name') !== '' ? 'is-invalid' : '' ?>">
          <label for="first_name">First name<span class="req">*</span></label>
          <input class="input" type="text" id="first_name" name="first_name" required maxlength="60" value="<?= old('first_name', (string) $user['first_name']) ?>">
          <?php if (error_for('first_name') !== ''): ?><span class="field-error"><?= error_for('first_name') ?></span><?php endif; ?>
        </div>
        <div class="field <?= error_for('last_name') !== '' ? 'is-invalid' : '' ?>">
          <label for="last_name">Last name<span class="req">*</span></label>
          <input class="input" type="text" id="last_name" name="last_name" required maxlength="60" value="<?= old('last_name', (string) $user['last_name']) ?>">
          <?php if (error_for('last_name') !== ''): ?><span class="field-error"><?= error_for('last_name') ?></span><?php endif; ?>
        </div>
      </div>

      <div class="field">
        <label for="phone">Phone</label>
        <input class="input" type="tel" id="phone" name="phone" maxlength="30" value="<?= old('phone', (string) ($user['phone'] ?? '')) ?>">
        <span class="form-help">Only our team can see this. It is never shown on your public page.</span>
      </div>

      <div class="field">
        <label>Email address</label>
        <input class="input" type="email" value="<?= e((string) $user['email']) ?>" disabled>
        <span class="form-help">
          Contact <a href="<?= e(base_url('support')) ?>">support</a> to change the email on your account.
          <?php if (empty($user['email_verified_at'])): ?>
            It is not verified yet — <a href="<?= e(base_url('verify-email')) ?>">send a verification link</a>.
          <?php endif; ?>
        </span>
      </div>

      <div class="checkbox-row">
        <input type="checkbox" id="marketing_opt_in" name="marketing_opt_in" value="1"
               <?= !empty($user['marketing_opt_in']) ? 'checked' : '' ?>>
        <label for="marketing_opt_in">Email me occasional stories from the care homes</label>
      </div>

      <button class="btn btn-brand btn-lg mt-2" type="submit">Save profile</button>
    </form>
  </section>

  <section class="panel">
    <h2>Account</h2>
    <dl class="kv" style="grid-template-columns:150px 1fr">
      <dt>Status</dt>
      <dd><span class="<?= e(status_badge_class((string) $user['status'])) ?>"><?= e((string) $user['status']) ?></span></dd>
      <dt>Email</dt>
      <dd>
        <?= e((string) $user['email']) ?>
        <?php if (!empty($user['email_verified_at'])): ?>
          <span class="badge badge-green" style="margin-left:6px">verified</span>
        <?php else: ?>
          <span class="badge badge-amber" style="margin-left:6px">unverified</span>
        <?php endif; ?>
      </dd>
      <dt>Member since</dt>
      <dd><?= e(dt((string) $user['created_at'], 'j F Y')) ?></dd>
      <dt>Last sign-in</dt>
      <dd><?= e($user['last_login_at'] !== null ? dt((string) $user['last_login_at'], 'j M Y H:i') . ' UTC' : 'First visit') ?></dd>
    </dl>

    <h3 class="mt-3" style="font-size:16px">Your data</h3>
    <p class="text-muted" style="font-size:14px">
      You can request a copy of your personal data, or ask us to delete your account, by writing to
      <a href="mailto:<?= e((string) config('app.org.email')) ?>"><?= e((string) config('app.org.email')) ?></a>.
      Donation records are kept for the period required by law, and we will tell you what we must retain.
    </p>

    <p><a class="link-arrow" href="<?= e(base_url('dashboard/email-preferences')) ?>">Manage email preferences</a></p>
  </section>
</div>
