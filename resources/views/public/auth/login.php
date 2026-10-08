<?php
/**
 * Sign-in page.
 *
 * @var string $intended
 * @var bool $resetDone
 */
?>
<section class="auth-shell">
  <div class="auth-art">
    <div class="art-copy">
      <span class="eyebrow">Fundraiser Login</span>
      <h2>Welcome back</h2>
      <p>Track your donations, post updates and keep your supporters close to the work they funded.</p>
      <ul class="checkline mt-3">
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><span>Live totals from the bank, not estimates</span></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><span>Donor list and CSV export for your records</span></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><span>Update your story whenever you like</span></li>
      </ul>
    </div>
  </div>

  <div class="auth-form-wrap">
    <div class="auth-card">
      <h1>Sign in</h1>
      <p class="text-muted">Use the email address you registered with.</p>

      <form method="post" action="<?= e(base_url('login')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($intended) ?>">

        <div class="field <?= error_for('email') !== '' ? 'is-invalid' : '' ?>">
          <label for="email">Email address<span class="req">*</span></label>
          <input class="input" type="email" id="email" name="email" required autofocus autocomplete="email"
                 value="<?= old('email') ?>">
          <?php if (error_for('email') !== ''): ?><span class="field-error"><?= error_for('email') ?></span><?php endif; ?>
        </div>

        <div class="field <?= error_for('password') !== '' ? 'is-invalid' : '' ?>">
          <label for="password">Password<span class="req">*</span></label>
          <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
          <?php if (error_for('password') !== ''): ?><span class="field-error"><?= error_for('password') ?></span><?php endif; ?>
        </div>

        <div class="form-actions" style="justify-content:space-between">
          <button class="btn btn-brand btn-lg" type="submit">Sign in</button>
          <a class="link-arrow" href="<?= e(base_url('forgot-password')) ?>">Forgot password?</a>
        </div>
      </form>

      <div class="divider-or"><span>or</span></div>

      <p class="auth-alt">
        New to Almarah fundraising?
        <a href="<?= e(base_url('register')) ?>">Create an account</a>
      </p>

      <p class="text-muted mt-2" style="font-size:13.5px">
        Signing in as a donor? You do not need an account to give &mdash;
        <a href="<?= e(base_url('fundraisers')) ?>">browse fundraisers</a> and donate in a couple of clicks.
      </p>
    </div>
  </div>
</section>
