<?php
/**
 * Sign-in page.
 *
 * @var string $intended
 * @var bool   $resetDone
 */
$emailError    = error_for('email');
$passwordError = error_for('password');
$oldEmail      = (string) old('email');
// Land the cursor where the person needs to type next.
$focusPassword = $oldEmail !== '' && $emailError === '';
?>
<section class="auth-shell auth-shell--split">
  <div class="auth-stage">
    <div class="auth-card--split">

      <!-- ============ Left: brand panel ============ -->
      <aside class="auth-art" aria-label="About Almarah fundraising">
        <div class="art-copy">
          <h2>Welcome back</h2>
          <p>See what your supporters have given, post an update, and keep everyone close to the work they funded.</p>

          <ul class="checkline">
            <li>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
              <span>Totals come from confirmed bank payments only</span>
            </li>
            <li>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
              <span>Donor list you can export for your records</span>
            </li>
            <li>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
              <span>Edit your story whenever you like</span>
            </li>
          </ul>
        </div>
        <p class="art-foot">Payments happen on secure bank pages. Almarah never sees card details.</p>
      </aside>

      <!-- ============ Right: form ============ -->
      <div class="auth-form-wrap">
        <div class="auth-form">
          <h1>Sign in</h1>
          <p class="auth-lede">Use the email address you registered with.</p>

          <?php if ($resetDone): ?>
            <p class="auth-flash" role="status">Your password was reset. Sign in with the new one.</p>
          <?php endif; ?>

          <form method="post" action="<?= e(base_url('login')) ?>" id="login-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($intended) ?>">

            <div class="field <?= $emailError !== '' ? 'is-invalid' : '' ?>">
              <label for="email">Email address<span class="req">*</span></label>
              <input class="input" type="email" id="email" name="email" required
                     <?= $focusPassword ? '' : 'autofocus' ?>
                     autocomplete="email" autocapitalize="off" spellcheck="false" inputmode="email"
                     placeholder="you@example.com"
                     value="<?= e($oldEmail) ?>"
                     <?= $emailError !== '' ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
              <?php if ($emailError !== ''): ?>
                <span class="field-error" id="email-error" role="alert"><?= e($emailError) ?></span>
              <?php endif; ?>
            </div>

            <div class="field <?= $passwordError !== '' ? 'is-invalid' : '' ?>">
              <div class="label-row">
                <label for="password">Password<span class="req">*</span></label>
                <a class="link-arrow link-quiet" href="<?= e(base_url('forgot-password')) ?>">Forgot password?</a>
              </div>

              <div class="input-group">
                <input class="input" type="password" id="password" name="password" required
                       <?= $focusPassword ? 'autofocus' : '' ?>
                       autocomplete="current-password" placeholder="Your password"
                       aria-describedby="<?= $passwordError !== '' ? 'password-error ' : '' ?>caps-hint"
                       <?= $passwordError !== '' ? 'aria-invalid="true"' : '' ?>>
                <button type="button" class="pw-toggle" data-toggle-password="#password"
                        aria-label="Show password" aria-pressed="false">
                  <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/>
                  </svg>
                </button>
              </div>

              <span class="field-hint" id="caps-hint" role="status" hidden>Caps Lock is on.</span>
              <?php if ($passwordError !== ''): ?>
                <span class="field-error" id="password-error" role="alert"><?= e($passwordError) ?></span>
              <?php endif; ?>
            </div>

            <div class="form-actions">
              <button class="btn btn-brand btn-lg" type="submit" id="login-submit">
                <span class="btn-label">Sign in</span>
              </button>
            </div>
          </form>

          <div class="divider-or"><span>or</span></div>

          <p class="auth-alt">
            New to Almarah fundraising?
            <a href="<?= e(base_url('register')) ?>">Create an account</a>
          </p>

          <p class="auth-note">
            Just want to give? You don't need an account.
            <a href="<?= e(base_url('fundraisers')) ?>">Browse fundraisers</a> and donate in a couple of clicks.
          </p>
        </div>
      </div>

    </div>
  </div>
</section>

<style>
/* Layout, spacing and a graceful colour fallback.
   Colour rules sit inside :where() (zero specificity), so if your theme already
   styles .auth-art, .btn-brand, .input etc., the theme always wins. */

.auth-shell--split {
  --header-gap: 0px;               /* set to your header height only if it is fixed/sticky */
  --a-pink: #af2f67;
  --a-deep: #6b1038;
  --a-yellow: #ffda00;
  --a-ink: #2a1020;
  --a-mute: #7a4560;
  --a-line: #ecd3df;

  display: block;
  width: 100%;
  padding: calc(var(--header-gap) + clamp(32px, 7vh, 88px)) clamp(16px, 4vw, 40px) clamp(48px, 8vh, 96px);
}
.auth-shell--split .auth-stage { width: 100%; max-width: 1040px; margin: 0 auto; }

.auth-card--split {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr);
  border-radius: 22px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 30px 60px -34px rgba(107, 16, 56, .35), 0 2px 8px rgba(15, 23, 42, .05);
}

/* ---------- Brand panel ---------- */
.auth-art {
  position: relative;
  isolation: isolate;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 32px;
  padding: clamp(32px, 5vw, 56px) clamp(28px, 4vw, 48px);
  overflow: hidden;
}
:where(.auth-shell--split .auth-art) {
  color: #fff;
  background: linear-gradient(150deg, var(--a-deep) 0%, #8a2050 50%, var(--a-pink) 100%);
}
/* Eight-point star lattice, drawn once, kept very quiet */
.auth-art::before {
  content: "";
  position: absolute;
  inset: 0;
  z-index: -1;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'%3E%3Cpath fill='none' stroke='%23ffda00' stroke-opacity='.17' d='M36 4l8.5 15.5L60 12l-7.5 15.5L68 36l-15.5 8.5L60 60l-15.5-7.5L36 68l-8.5-15.5L12 60l7.5-15.5L4 36l15.5-8.5L12 12l15.5 7.5z'/%3E%3C/svg%3E");
  -webkit-mask-image: linear-gradient(160deg, #000 0%, transparent 78%);
          mask-image: linear-gradient(160deg, #000 0%, transparent 78%);
}
/* The one memorable element: a large alif watermark */
.auth-art::after {
  content: "\0627";
  position: absolute;
  right: -.06em;
  bottom: -.32em;
  z-index: -1;
  font: 700 clamp(15rem, 28vw, 25rem)/1 Amiri, "Times New Roman", serif;
  color: var(--a-yellow);
  opacity: .13;
  pointer-events: none;
  user-select: none;
}
.auth-art .art-copy { max-width: 40ch; }
.auth-art h2 {
  margin: 0 0 14px;
  font: 700 clamp(2rem, 3.6vw, 2.75rem)/1.1 Amiri, Georgia, serif;
  letter-spacing: -.01em;
}
.auth-art p { margin: 0; line-height: 1.7; opacity: .9; }

.auth-art .checkline { display: grid; gap: 14px; margin: 28px 0 0; padding: 0; list-style: none; }
.auth-art .checkline li { display: flex; align-items: flex-start; gap: 12px; font-size: .95rem; line-height: 1.5; }
.auth-art .checkline svg {
  flex: 0 0 auto; width: 22px; height: 22px; padding: 4px; margin-top: 1px;
  border-radius: 50%;
}
:where(.auth-art .checkline svg) { background: var(--a-yellow); color: var(--a-deep); }
.auth-art .art-foot { max-width: 34ch; font-size: .8rem; opacity: .7; }

/* ---------- Form panel ---------- */
.auth-form-wrap { display: flex; align-items: center; padding: clamp(32px, 5vw, 56px) clamp(28px, 4vw, 52px); }
.auth-form { width: 100%; max-width: 420px; margin: 0 auto; }
.auth-form h1 { margin: 0 0 6px; font-size: clamp(1.6rem, 2.6vw, 2rem); line-height: 1.15; letter-spacing: -.015em; }
.auth-lede { margin: 0 0 26px; }
:where(.auth-form .auth-lede, .auth-form .auth-note) { color: var(--a-mute); }

.auth-form .field { margin-bottom: 18px; }
.auth-form label { display: block; margin-bottom: 6px; font-weight: 600; font-size: .88rem; }
.auth-form .req { margin-left: 2px; color: var(--a-pink); }
.label-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.label-row label { margin-bottom: 6px; }
.link-quiet { font-size: .82rem; }

:where(.auth-form .input) {
  width: 100%; padding: 12px 14px; font: inherit; color: var(--a-ink);
  background: #fff; border: 1.5px solid var(--a-line); border-radius: 12px;
  transition: border-color .15s ease, box-shadow .15s ease;
}
:where(.auth-form .input:focus) {
  outline: none; border-color: var(--a-pink); box-shadow: 0 0 0 3px rgba(175, 47, 103, .14);
}
:where(.auth-form .is-invalid .input) { border-color: #c0392b; }
:where(.auth-form a) { color: var(--a-pink); font-weight: 600; text-underline-offset: 3px; }

.field-error, .field-hint { display: block; margin-top: 6px; font-size: .82rem; line-height: 1.4; }
:where(.field-error) { color: #b3261e; }
:where(.field-hint)  { color: var(--a-mute); }
.field-hint[hidden] { display: none; }

/* Password field with inline toggle */
.input-group { position: relative; }
.input-group .input { padding-right: 48px; }
.pw-toggle {
  position: absolute; top: 50%; right: 6px; transform: translateY(-50%);
  display: grid; place-items: center; width: 36px; height: 36px; padding: 0;
  border: 0; border-radius: 9px; background: transparent; color: var(--a-mute);
  cursor: pointer; transition: background .15s ease, color .15s ease;
}
.pw-toggle:hover { background: rgba(175, 47, 103, .08); color: var(--a-pink); }
.pw-toggle:focus-visible { outline: 2px solid var(--a-pink); outline-offset: 1px; }
.pw-toggle svg { width: 18px; height: 18px; display: block; }
.pw-toggle .icon-eye-off,
.pw-toggle[aria-pressed="true"] .icon-eye { display: none; }
.pw-toggle[aria-pressed="true"] .icon-eye-off { display: block; }

.auth-flash {
  margin: 0 0 20px; padding: 12px 14px; font-size: .9rem; line-height: 1.5;
  border-radius: 12px; border: 1px solid #a6d9b8; background: #eaf7ef; color: #17633a;
}

/* Submit */
.auth-form .form-actions { margin-top: 24px; }
.auth-form .form-actions .btn { width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 10px; }
:where(.auth-form .btn-brand) {
  padding: 14px 24px; font: 800 1rem inherit; font-family: inherit; color: #fff;
  background: var(--a-pink); border: 0; border-radius: 999px; cursor: pointer;
  transition: background .15s ease, transform .15s ease;
}
:where(.auth-form .btn-brand:hover:not(:disabled)) { background: #8a2050; }
:where(.auth-form .btn-brand:focus-visible) { outline: 3px solid var(--a-yellow); outline-offset: 2px; }
.btn[disabled] { opacity: .75; cursor: progress; }
.btn.is-loading::before {
  content: ""; width: 16px; height: 16px; border-radius: 50%;
  border: 2px solid currentColor; border-right-color: transparent;
  animation: auth-spin .7s linear infinite;
}
@keyframes auth-spin { to { transform: rotate(360deg); } }

/* Secondary links */
.divider-or { display: flex; align-items: center; gap: 14px; margin: 26px 0 18px; font-size: .78rem; opacity: .6; }
.divider-or::before, .divider-or::after { content: ""; flex: 1; height: 1px; background: currentColor; opacity: .3; }
.auth-alt { margin: 0 0 16px; text-align: center; }
.auth-note { margin: 0; padding-top: 16px; border-top: 1px solid var(--a-line); font-size: .84rem; line-height: 1.6; text-align: center; }

/* ---------- Narrow screens: form first, brand panel becomes a short banner ---------- */
@media (max-width: 860px) {
  .auth-shell--split .auth-stage { max-width: 520px; }
  .auth-card--split { grid-template-columns: 1fr; }
  .auth-art { order: -1; gap: 0; padding: 28px 26px; }
  .auth-art .checkline, .auth-art .art-foot { display: none; }
  .auth-art::after { font-size: 11rem; bottom: -.4em; }
  .auth-art h2 { margin-bottom: 8px; }
  .auth-form-wrap { padding: 30px 26px 34px; }
}
@media (max-width: 420px) {
  .auth-shell--split { padding-left: 12px; padding-right: 12px; }
  .auth-form-wrap { padding: 26px 20px 30px; }
}
@media (prefers-reduced-motion: reduce) {
  .auth-shell--split *, .auth-shell--split *::before { transition: none !important; animation-duration: 1.5s !important; }
}
</style>

<script>
(function () {
  // Show / hide password
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    var input = document.querySelector(btn.getAttribute('data-toggle-password'));
    if (!input) return;
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      input.focus({ preventScroll: true });
    });
  });

  // Caps Lock hint
  var pw = document.getElementById('password'), hint = document.getElementById('caps-hint');
  if (pw && hint) {
    var check = function (e) {
      if (e.getModifierState) hint.hidden = !e.getModifierState('CapsLock');
    };
    pw.addEventListener('keydown', check);
    pw.addEventListener('keyup', check);
    pw.addEventListener('blur', function () { hint.hidden = true; });
  }

  // Prevent double submits and show progress
  var form = document.getElementById('login-form'), btn = document.getElementById('login-submit');
  if (form && btn) {
    form.addEventListener('submit', function () {
      if (btn.disabled) return;
      btn.disabled = true;
      btn.classList.add('is-loading');
      btn.querySelector('.btn-label').textContent = 'Signing in\u2026';
    });
    // Re-enable if the browser restores this page from the back/forward cache
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) {
        btn.disabled = false;
        btn.classList.remove('is-loading');
        btn.querySelector('.btn-label').textContent = 'Sign in';
      }
    });
  }
})();
</script>
