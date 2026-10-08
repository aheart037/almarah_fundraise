<?php
/**
 * Password and security settings.
 *
 * @var array $user
 */
?>
<div class="dash-head">
  <div>
    <h1>Password &amp; security</h1>
    <p class="sub">Keep your account safe — you are handling other people&rsquo;s donations.</p>
  </div>
</div>

<div class="grid-2">
  <section class="panel">
    <h2>Change your password</h2>
    <p class="panel-sub">You will stay signed in here; other sessions are not affected.</p>

    <form method="post" action="<?= e(base_url('dashboard/security/password')) ?>" novalidate>
      <?= csrf_field() ?>

      <div class="field <?= error_for('current_password') !== '' ? 'is-invalid' : '' ?>">
        <label for="current_password">Current password<span class="req">*</span></label>
        <input class="input" type="password" id="current_password" name="current_password" required autocomplete="current-password">
        <?php if (error_for('current_password') !== ''): ?><span class="field-error"><?= error_for('current_password') ?></span><?php endif; ?>
      </div>

      <div class="field <?= error_for('password') !== '' ? 'is-invalid' : '' ?>">
        <label for="password">New password<span class="req">*</span></label>
        <input class="input" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <span class="form-help">At least 8 characters. Longer is better than complicated.</span>
        <?php if (error_for('password') !== ''): ?><span class="field-error"><?= error_for('password') ?></span><?php endif; ?>
      </div>

      <div class="field <?= error_for('password_confirmation') !== '' ? 'is-invalid' : '' ?>">
        <label for="password_confirmation">Confirm new password<span class="req">*</span></label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
        <?php if (error_for('password_confirmation') !== ''): ?><span class="field-error"><?= error_for('password_confirmation') ?></span><?php endif; ?>
      </div>

      <button class="btn btn-brand btn-lg" type="submit">Update password</button>
    </form>
  </section>

  <section class="panel">
    <h2>How we protect your account</h2>
    <ul class="checkline" style="display:grid;gap:12px;margin-top:10px">
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg>
        <span>Passwords are stored only as one-way hashes — nobody here can read yours.</span>
      </li>
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg>
        <span>Sign-in attempts are rate limited to block password guessing.</span>
      </li>
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg>
        <span>Every change to a fundraiser, donation or role is written to an append-only audit log.</span>
      </li>
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg>
        <span>We email you whenever your password changes.</span>
      </li>
    </ul>

    <h3 class="mt-3" style="font-size:16px">Account details</h3>
    <dl class="kv" style="grid-template-columns:150px 1fr">
      <dt>Email</dt><dd><?= e((string) $user['email']) ?></dd>
      <dt>Last sign-in</dt>
      <dd><?= e($user['last_login_at'] !== null ? dt((string) $user['last_login_at'], 'j M Y H:i') . ' UTC' : 'This session') ?></dd>
      <dt>Last IP</dt><dd class="mono"><?= e((string) ($user['last_login_ip'] ?? '—')) ?></dd>
    </dl>

    <p class="text-muted mt-3" style="font-size:14px">
      Saw something you do not recognise? Change your password and
      <a href="<?= e(base_url('support')) ?>">tell our team</a> right away.
    </p>
  </section>
</div>
