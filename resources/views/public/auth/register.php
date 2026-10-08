<?php
/**
 * Registration page — creates a fundraiser account.
 *
 * @var string $next
 */
?>
<section class="auth-shell">
  <div class="auth-art">
    <div class="art-copy">
      <span class="eyebrow">Start Fundraising</span>
      <h2>One idea is all it takes</h2>
      <p>Create your page, tell your story, and give your friends and family a simple way to help a child in Pakistan.</p>
      <ul class="checkline mt-3">
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><span>No setup fee, no minimum goal</span></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><span>Reviewed by our team, usually within a day</span></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg><span>Teams, updates and CSV exports included</span></li>
      </ul>
    </div>
  </div>

  <div class="auth-form-wrap">
    <div class="auth-card">
      <h1>Create your account</h1>
      <p class="text-muted">Takes a minute. You can start a fundraiser straight away.</p>

      <form method="post" action="<?= e(base_url('register')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div class="form-row">
          <div class="field <?= error_for('first_name') !== '' ? 'is-invalid' : '' ?>">
            <label for="first_name">First name<span class="req">*</span></label>
            <input class="input" type="text" id="first_name" name="first_name" required maxlength="60"
                   value="<?= old('first_name') ?>" autocomplete="given-name" autofocus>
            <?php if (error_for('first_name') !== ''): ?><span class="field-error"><?= error_for('first_name') ?></span><?php endif; ?>
          </div>
          <div class="field <?= error_for('last_name') !== '' ? 'is-invalid' : '' ?>">
            <label for="last_name">Last name<span class="req">*</span></label>
            <input class="input" type="text" id="last_name" name="last_name" required maxlength="60"
                   value="<?= old('last_name') ?>" autocomplete="family-name">
            <?php if (error_for('last_name') !== ''): ?><span class="field-error"><?= error_for('last_name') ?></span><?php endif; ?>
          </div>
        </div>

        <div class="field <?= error_for('email') !== '' ? 'is-invalid' : '' ?>">
          <label for="email">Email address<span class="req">*</span></label>
          <input class="input" type="email" id="email" name="email" required maxlength="190"
                 value="<?= old('email') ?>" autocomplete="email">
          <span class="form-help">We send a verification link here. Donation receipts go here too.</span>
          <?php if (error_for('email') !== ''): ?><span class="field-error"><?= error_for('email') ?></span><?php endif; ?>
        </div>

        <div class="field <?= error_for('phone') !== '' ? 'is-invalid' : '' ?>">
          <label for="phone">Phone (optional)</label>
          <input class="input" type="tel" id="phone" name="phone" maxlength="30"
                 value="<?= old('phone') ?>" autocomplete="tel">
          <?php if (error_for('phone') !== ''): ?><span class="field-error"><?= error_for('phone') ?></span><?php endif; ?>
        </div>

        <div class="form-row">
          <div class="field <?= error_for('password') !== '' ? 'is-invalid' : '' ?>">
            <label for="password">Password<span class="req">*</span></label>
            <input class="input" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            <span class="form-help">At least 8 characters.</span>
            <?php if (error_for('password') !== ''): ?><span class="field-error"><?= error_for('password') ?></span><?php endif; ?>
          </div>
          <div class="field <?= error_for('password_confirmation') !== '' ? 'is-invalid' : '' ?>">
            <label for="password_confirmation">Confirm password<span class="req">*</span></label>
            <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            <?php if (error_for('password_confirmation') !== ''): ?><span class="field-error"><?= error_for('password_confirmation') ?></span><?php endif; ?>
          </div>
        </div>

        <div class="checkbox-row">
          <input type="checkbox" id="marketing_opt_in" name="marketing_opt_in" value="1" <?= old('marketing_opt_in') !== '' ? 'checked' : '' ?>>
          <label for="marketing_opt_in">Email me occasional stories from the care homes and campaign news</label>
        </div>

        <p class="form-help mt-2">
          By creating an account you agree to our <a href="<?= e(base_url('terms')) ?>">terms of use</a> and
          <a href="<?= e(base_url('privacy-policy')) ?>">privacy policy</a>.
        </p>

        <button class="btn btn-brand btn-lg btn-block mt-2" type="submit">Create account</button>
      </form>

      <div class="divider-or"><span>already registered?</span></div>

      <p class="auth-alt"><a href="<?= e(base_url('login')) ?>">Sign in instead</a></p>
    </div>
  </div>
</section>
