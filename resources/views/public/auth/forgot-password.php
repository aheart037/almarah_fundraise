<?php
/** Request a password reset link. */
?>
<section class="auth-shell">
  <div class="auth-art">
    <div class="art-copy">
      <span class="eyebrow">Account Recovery</span>
      <h2>Let us send you a new link</h2>
      <p>Enter the email address on your account and we will email you a secure link to choose a new password.</p>
      <p class="text-muted mt-3" style="font-size:13.5px">For your security we cannot tell you whether an address is registered — the message is the same either way.</p>
    </div>
  </div>

  <div class="auth-form-wrap">
    <div class="auth-card">
      <h1>Reset your password</h1>
      <p class="text-muted">The link expires in two hours.</p>

      <form method="post" action="<?= e(base_url('forgot-password')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field <?= error_for('email') !== '' ? 'is-invalid' : '' ?>">
          <label for="email">Email address<span class="req">*</span></label>
          <input class="input" type="email" id="email" name="email" required autofocus autocomplete="email" value="<?= old('email') ?>">
          <?php if (error_for('email') !== ''): ?><span class="field-error"><?= error_for('email') ?></span><?php endif; ?>
        </div>

        <button class="btn btn-brand btn-lg btn-block" type="submit">Email me a reset link</button>
      </form>

      <div class="divider-or"><span>or</span></div>
      <p class="auth-alt"><a href="<?= e(base_url('login')) ?>">Back to sign in</a></p>
    </div>
  </div>
</section>
