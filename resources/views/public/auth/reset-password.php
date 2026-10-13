<?php
/**
 * Choose a new password using a reset token.
 *
 * @var string $token
 */
$pwError   = (string) error_for('password');
$confError = (string) error_for('password_confirmation');
$eye = '<svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>'
     . '<svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';
$tick = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';
?>
<link rel="stylesheet" href="<?= e(base_url('assets/auth-pages.css')) ?>">
<section class="auth-shell auth-shell--split">
  <div class="auth-stage">
    <div class="auth-card--split">

      <aside class="auth-art" aria-label="Tips for a good password">
        <div class="art-copy">
          <h2>Choose a new password</h2>
          <p>Pick something you have not used on other sites. Once it is changed, you will be signed out of your other sessions.</p>
          <ul class="checkline">
            <li><?= $tick ?><span>Use 12 or more characters when you can</span></li>
            <li><?= $tick ?><span>Mix letters, numbers and symbols</span></li>
            <li><?= $tick ?><span>Avoid names, birthdays and old passwords</span></li>
          </ul>
        </div>
        <p class="art-foot">This link can be used once. If it has expired, you can request a new one.</p>
      </aside>

      <div class="auth-form-wrap">
        <div class="auth-form">
          <h1>New password</h1>
          <p class="auth-lede">Enter it twice so we know it is typed correctly.</p>

          <form method="post" action="<?= e(base_url('reset-password')) ?>" id="reset-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="field <?= $pwError !== '' ? 'is-invalid' : '' ?>">
              <label for="password">New password<span class="req">*</span></label>
              <div class="input-group">
                <input class="input" type="password" id="password" name="password" required minlength="8" autofocus
                       autocomplete="new-password"
                       aria-describedby="<?= $pwError !== '' ? 'password-error ' : '' ?>pw-meter-label"
                       <?= $pwError !== '' ? 'aria-invalid="true"' : '' ?>>
                <button type="button" class="pw-toggle" data-toggle-password="#password" aria-label="Show password" aria-pressed="false"><?= $eye ?></button>
              </div>
              <div class="pw-meter" id="pw-meter" data-level="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
              <span class="form-help" id="pw-meter-label" role="status">At least 8 characters.</span>
              <?php if ($pwError !== ''): ?><span class="field-error" id="password-error" role="alert"><?= e($pwError) ?></span><?php endif; ?>
            </div>

            <div class="field <?= $confError !== '' ? 'is-invalid' : '' ?>">
              <label for="password_confirmation">Confirm new password<span class="req">*</span></label>
              <div class="input-group">
                <input class="input" type="password" id="password_confirmation" name="password_confirmation" required
                       autocomplete="new-password"
                       aria-describedby="<?= $confError !== '' ? 'confirm-error ' : '' ?>match-hint"
                       <?= $confError !== '' ? 'aria-invalid="true"' : '' ?>>
                <button type="button" class="pw-toggle" data-toggle-password="#password_confirmation" aria-label="Show password" aria-pressed="false"><?= $eye ?></button>
              </div>
              <span class="form-help" id="match-hint" role="status" hidden></span>
              <?php if ($confError !== ''): ?><span class="field-error" id="confirm-error" role="alert"><?= e($confError) ?></span><?php endif; ?>
            </div>

            <div class="form-actions">
              <button class="btn btn-brand btn-lg btn-block" type="submit" id="reset-submit"><span class="btn-label">Set new password</span></button>
            </div>
          </form>

          <div class="divider-or"><span>or</span></div>
          <p class="auth-alt"><a href="<?= e(base_url('login')) ?>">Back to sign in</a></p>
        </div>
      </div>

    </div>
  </div>
</section>

<script>
(function () {
  var $ = function (s) { return document.querySelector(s); };
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    var input = $(btn.getAttribute('data-toggle-password'));
    if (!input) return;
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      input.focus({ preventScroll: true });
    });
  });

  var pw = $('#password'), conf = $('#password_confirmation'), meter = $('#pw-meter'), label = $('#pw-meter-label'), match = $('#match-hint');
  var words = ['At least 8 characters.', 'Too short. Add more characters.', 'Fair. Try adding numbers or symbols.', 'Good.', 'Strong.'];
  function score(v) {
    if (!v) return 0;
    if (v.length < 8) return 1;
    var s = 1 + (v.length >= 12 ? 1 : 0);
    var kinds = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].filter(function (r) { return r.test(v); }).length;
    if (kinds >= 3) s++;
    if (kinds === 4 && v.length >= 12) s++;
    return Math.min(4, Math.max(2, s));
  }
  function checkMatch() {
    if (!conf.value) { match.hidden = true; return; }
    var ok = conf.value === pw.value;
    match.hidden = false;
    match.textContent = ok ? 'Passwords match.' : 'Passwords do not match yet.';
    match.className = 'form-help ' + (ok ? 'is-good' : 'is-bad');
  }
  pw.addEventListener('input', function () {
    var s = score(pw.value);
    meter.setAttribute('data-level', s);
    label.textContent = words[s];
    checkMatch();
  });
  conf.addEventListener('input', checkMatch);

  var form = $('#reset-form'), btn = $('#reset-submit'), txt = btn.querySelector('.btn-label');
  form.addEventListener('submit', function () {
    if (btn.disabled) return;
    btn.disabled = true; btn.classList.add('is-loading'); txt.textContent = 'Saving\u2026';
  });
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) { btn.disabled = false; btn.classList.remove('is-loading'); txt.textContent = 'Set new password'; }
  });
})();
</script>
