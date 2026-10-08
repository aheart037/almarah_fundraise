<?php
/**
 * Choose a new password using a reset token.
 *
 * @var string $token
 */
?>
<section class="auth-shell">
  <div class="auth-art">
    <div class="art-copy">
      <span class="eyebrow">Account Recovery</span>
      <h2>Choose a new password</h2>
      <p>Pick something you have not used elsewhere. You will be signed out of other sessions once it is changed.</p>
    </div>
  </div>

  <div class="auth-form-wrap">
    <div class="auth-card">
      <h1>New password</h1>
      <p class="text-muted">At least 8 characters.</p>

      <form method="post" action="<?= e(base_url('reset-password')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="field <?= error_for('password') !== '' ? 'is-invalid' : '' ?>">
          <label for="password">New password<span class="req">*</span></label>
          <input class="input" type="password" id="password" name="password" required minlength="8" autofocus autocomplete="new-password">
          <?php if (error_for('password') !== ''): ?><span class="field-error"><?= error_for('password') ?></span><?php endif; ?>
        </div>

        <div class="field <?= error_for('password_confirmation') !== '' ? 'is-invalid' : '' ?>">
          <label for="password_confirmation">Confirm new password<span class="req">*</span></label>
          <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
          <?php if (error_for('password_confirmation') !== ''): ?><span class="field-error"><?= error_for('password_confirmation') ?></span><?php endif; ?>
        </div>

        <button class="btn btn-brand btn-lg btn-block" type="submit">Set new password</button>
      </form>

      <div class="divider-or"><span>or</span></div>
      <p class="auth-alt"><a href="<?= e(base_url('login')) ?>">Back to sign in</a></p>
    </div>
  </div>
</section>
