<?php
/**
 * Registration page — creates a fundraiser account.
 *
 * @var string $next
 */
$err = static function (string $f): string { return (string) error_for($f); };
$cls = static function (string $f) use ($err): string { return $err($f) !== '' ? 'is-invalid' : ''; };
$canCreate = can_fundraiser_capability('manage_pages');

// Renders the error message for a field (escaped) with a stable id for aria-describedby.
$msg = static function (string $f) use ($err): string {
    $m = $err($f);
    return $m === '' ? '' : '<span class="field-error" id="' . $f . '-error" role="alert">' . e($m) . '</span>';
};
$desc = static function (string $f, string $extra = '') use ($err): string {
    $ids = trim(($err($f) !== '' ? $f . '-error ' : '') . $extra);
    return ($ids !== '' ? ' aria-describedby="' . $ids . '"' : '') . ($err($f) !== '' ? ' aria-invalid="true"' : '');
};
?>
<section class="auth-shell auth-shell--split">
  <div class="auth-stage">
    <div class="auth-card--split">

      <!-- ============ Left: brand panel ============ -->
      <aside class="auth-art" aria-label="About starting a fundraiser">
        <div class="art-copy">
          <h2>One idea is all it takes</h2>
          <p>Create your page, tell your story, and give your friends and family a simple way to help a child in Pakistan.</p>

          <ul class="checkline">
            <li>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
              <span>No setup fee and no minimum goal</span>
            </li>
            <li>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
              <span>Our team reviews your page, usually within a day</span>
            </li>
            <li>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
              <span>Almarah staff support you from start to finish</span>
            </li>
          </ul>
        </div>
        <p class="art-foot">Payments happen on secure bank pages. Almarah never sees card details.</p>
      </aside>

      <!-- ============ Right: form ============ -->
      <div class="auth-form-wrap">
        <div class="auth-form">
          <h1>Create your account</h1>
          <p class="auth-lede">
            <?php if ($canCreate): ?>
              It takes about a minute, and you can start a fundraiser right away.
            <?php else: ?>
              Fundraiser page creation is currently turned off by an administrator. You can still register to use the other fundraising tools.
            <?php endif; ?>
          </p>

          <form method="post" action="<?= e(base_url('register')) ?>" id="register-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <div class="form-row">
              <div class="field <?= $cls('first_name') ?>">
                <label for="first_name">First name<span class="req">*</span></label>
                <input class="input" type="text" id="first_name" name="first_name" required maxlength="60" autofocus
                       autocomplete="given-name" value="<?= e(old('first_name')) ?>"<?= $desc('first_name') ?>>
                <?= $msg('first_name') ?>
              </div>
              <div class="field <?= $cls('last_name') ?>">
                <label for="last_name">Last name<span class="req">*</span></label>
                <input class="input" type="text" id="last_name" name="last_name" required maxlength="60"
                       autocomplete="family-name" value="<?= e(old('last_name')) ?>"<?= $desc('last_name') ?>>
                <?= $msg('last_name') ?>
              </div>
            </div>

            <div class="field <?= $cls('email') ?>">
              <label for="email">Email address<span class="req">*</span></label>
              <input class="input" type="email" id="email" name="email" required maxlength="190"
                     autocomplete="email" autocapitalize="off" spellcheck="false" inputmode="email"
                     placeholder="you@example.com" value="<?= e(old('email')) ?>"<?= $desc('email', 'email-help') ?>>
              <span class="form-help" id="email-help">We send your verification link and donation receipts here.</span>
              <?= $msg('email') ?>
            </div>

            <div class="field <?= $cls('phone') ?>">
              <label for="phone">Phone <span class="opt">(optional)</span></label>
              <input class="input" type="tel" id="phone" name="phone" maxlength="30"
                     autocomplete="tel" inputmode="tel" value="<?= e(old('phone')) ?>"<?= $desc('phone') ?>>
              <?= $msg('phone') ?>
            </div>

            <div class="form-row">
              <div class="field <?= $cls('password') ?>">
                <label for="password">Password<span class="req">*</span></label>
                <div class="input-group">
                  <input class="input" type="password" id="password" name="password" required minlength="8"
                         autocomplete="new-password"<?= $desc('password', 'pw-meter-label') ?>>
                  <button type="button" class="pw-toggle" data-toggle-password="#password"
                          aria-label="Show password" aria-pressed="false">
                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                  </button>
                </div>
                <div class="pw-meter" id="pw-meter" data-level="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                <span class="form-help" id="pw-meter-label" role="status">At least 8 characters.</span>
                <?= $msg('password') ?>
              </div>

              <div class="field <?= $cls('password_confirmation') ?>">
                <label for="password_confirmation">Confirm password<span class="req">*</span></label>
                <div class="input-group">
                  <input class="input" type="password" id="password_confirmation" name="password_confirmation" required
                         autocomplete="new-password"<?= $desc('password_confirmation', 'match-hint') ?>>
                  <button type="button" class="pw-toggle" data-toggle-password="#password_confirmation"
                          aria-label="Show password" aria-pressed="false">
                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                  </button>
                </div>
                <span class="form-help" id="match-hint" role="status" hidden></span>
                <?= $msg('password_confirmation') ?>
              </div>
            </div>

            <div class="checkbox-row">
              <input type="checkbox" id="marketing_opt_in" name="marketing_opt_in" value="1"
                     <?= (string) old('marketing_opt_in') !== '' ? 'checked' : '' ?>>
              <label for="marketing_opt_in">Email me occasional stories from the care homes and campaign news</label>
            </div>

            <p class="form-help legal">
              By creating an account you agree to our
              <a href="<?= e(base_url('terms')) ?>">terms of use</a> and
              <a href="<?= e(base_url('privacy-policy')) ?>">privacy policy</a>.
            </p>

            <div class="form-actions">
              <button class="btn btn-brand btn-lg btn-block" type="submit" id="register-submit">
                <span class="btn-label">Create account</span>
              </button>
            </div>
          </form>

          <div class="divider-or"><span>already registered?</span></div>
          <p class="auth-alt"><a href="<?= e(base_url('login')) ?>">Sign in instead</a></p>
        </div>
      </div>

    </div>
  </div>
</section>

<style>
/* Same visual system as the sign-in page. If both pages load, move this block
   into your main stylesheet once. Colour rules use :where() so your theme wins. */
.auth-shell--split{--header-gap:0px;--a-pink:#af2f67;--a-deep:#6b1038;--a-yellow:#ffda00;--a-ink:#2a1020;--a-mute:#7a4560;--a-line:#ecd3df;
  display:block;width:100%;padding:calc(var(--header-gap) + clamp(32px,7vh,88px)) clamp(16px,4vw,40px) clamp(48px,8vh,96px)}
.auth-shell--split .auth-stage{width:100%;max-width:1080px;margin:0 auto}
.auth-card--split{display:grid;grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr);border-radius:22px;overflow:hidden;background:#fff;
  box-shadow:0 30px 60px -34px rgba(107,16,56,.35),0 2px 8px rgba(15,23,42,.05)}

.auth-art{position:relative;isolation:isolate;display:flex;flex-direction:column;justify-content:space-between;gap:32px;
  padding:clamp(32px,5vw,56px) clamp(28px,4vw,48px);overflow:hidden}
:where(.auth-shell--split .auth-art){color:#fff;background:linear-gradient(150deg,var(--a-deep) 0%,#8a2050 50%,var(--a-pink) 100%)}
.auth-art::before{content:"";position:absolute;inset:0;z-index:-1;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'%3E%3Cpath fill='none' stroke='%23ffda00' stroke-opacity='.17' d='M36 4l8.5 15.5L60 12l-7.5 15.5L68 36l-15.5 8.5L60 60l-15.5-7.5L36 68l-8.5-15.5L12 60l7.5-15.5L4 36l15.5-8.5L12 12l15.5 7.5z'/%3E%3C/svg%3E");
  -webkit-mask-image:linear-gradient(160deg,#000 0%,transparent 78%);mask-image:linear-gradient(160deg,#000 0%,transparent 78%)}
.auth-art::after{content:"\0627";position:absolute;right:-.06em;bottom:-.32em;z-index:-1;font:700 clamp(15rem,28vw,25rem)/1 Amiri,"Times New Roman",serif;
  color:var(--a-yellow);opacity:.13;pointer-events:none;user-select:none}
.auth-art .art-copy{max-width:38ch}
.auth-art h2{margin:0 0 14px;font:700 clamp(2rem,3.4vw,2.6rem)/1.1 Amiri,Georgia,serif;letter-spacing:-.01em}
.auth-art p{margin:0;line-height:1.7;opacity:.9}
.auth-art .checkline{display:grid;gap:14px;margin:28px 0 0;padding:0;list-style:none}
.auth-art .checkline li{display:flex;align-items:flex-start;gap:12px;font-size:.95rem;line-height:1.5}
.auth-art .checkline svg{flex:0 0 auto;width:22px;height:22px;padding:4px;margin-top:1px;border-radius:50%}
:where(.auth-art .checkline svg){background:var(--a-yellow);color:var(--a-deep)}
.auth-art .art-foot{max-width:34ch;font-size:.8rem;opacity:.7}

.auth-form-wrap{display:flex;align-items:center;padding:clamp(32px,5vw,56px) clamp(28px,4vw,52px)}
.auth-form{width:100%;max-width:520px;margin:0 auto}
.auth-form h1{margin:0 0 6px;font-size:clamp(1.6rem,2.6vw,2rem);line-height:1.15;letter-spacing:-.015em}
.auth-lede{margin:0 0 24px}
:where(.auth-form .auth-lede,.auth-form .form-help){color:var(--a-mute)}
.auth-form .form-row{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
.auth-form .field{margin-bottom:16px;min-width:0}
.auth-form label{display:block;margin-bottom:6px;font-weight:600;font-size:.88rem}
.auth-form .req{margin-left:2px;color:var(--a-pink)}
.auth-form .opt{font-weight:400;opacity:.7}
:where(.auth-form .input){width:100%;padding:12px 14px;font:inherit;color:var(--a-ink);background:#fff;border:1.5px solid var(--a-line);border-radius:12px;transition:border-color .15s ease,box-shadow .15s ease}
:where(.auth-form .input:focus){outline:none;border-color:var(--a-pink);box-shadow:0 0 0 3px rgba(175,47,103,.14)}
:where(.auth-form .is-invalid .input){border-color:#c0392b}
:where(.auth-form a){color:var(--a-pink);font-weight:600;text-underline-offset:3px}
.form-help,.field-error{display:block;margin-top:6px;font-size:.8rem;line-height:1.45}
:where(.field-error){color:#b3261e}
.form-help[hidden]{display:none}
.form-help.is-bad{color:#b3261e}.form-help.is-good{color:#17633a}

.input-group{position:relative}.input-group .input{padding-right:48px}
.pw-toggle{position:absolute;top:50%;right:6px;transform:translateY(-50%);display:grid;place-items:center;width:36px;height:36px;padding:0;border:0;border-radius:9px;background:transparent;color:var(--a-mute);cursor:pointer;transition:background .15s ease,color .15s ease}
.pw-toggle:hover{background:rgba(175,47,103,.08);color:var(--a-pink)}
.pw-toggle:focus-visible{outline:2px solid var(--a-pink);outline-offset:1px}
.pw-toggle svg{width:18px;height:18px;display:block}
.pw-toggle .icon-eye-off,.pw-toggle[aria-pressed="true"] .icon-eye{display:none}
.pw-toggle[aria-pressed="true"] .icon-eye-off{display:block}

/* Password strength: four segments that fill as the password improves */
.pw-meter{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:8px}
.pw-meter i{height:4px;border-radius:4px;background:var(--a-line);transition:background .2s ease}
.pw-meter[data-level="1"] i:nth-child(-n+1){background:#d9534f}
.pw-meter[data-level="2"] i:nth-child(-n+2){background:#e6a23c}
.pw-meter[data-level="3"] i:nth-child(-n+3){background:#8bbf4a}
.pw-meter[data-level="4"] i:nth-child(-n+4){background:#2e9e5b}

.checkbox-row{display:flex;align-items:flex-start;gap:10px;margin:4px 0 12px}
.checkbox-row input{flex:0 0 auto;width:18px;height:18px;margin:2px 0 0;accent-color:var(--a-pink)}
.checkbox-row label{margin:0;font-weight:400;font-size:.88rem;line-height:1.5}
.auth-form .legal{margin:0 0 4px}

.auth-form .form-actions{margin-top:20px}
.auth-form .form-actions .btn{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:10px}
:where(.auth-form .btn-brand){padding:14px 24px;font:800 1rem inherit;font-family:inherit;color:#fff;background:var(--a-pink);border:0;border-radius:999px;cursor:pointer;transition:background .15s ease}
:where(.auth-form .btn-brand:hover:not(:disabled)){background:#8a2050}
:where(.auth-form .btn-brand:focus-visible){outline:3px solid var(--a-yellow);outline-offset:2px}
.btn[disabled]{opacity:.75;cursor:progress}
.btn.is-loading::before{content:"";width:16px;height:16px;border-radius:50%;border:2px solid currentColor;border-right-color:transparent;animation:auth-spin .7s linear infinite}
@keyframes auth-spin{to{transform:rotate(360deg)}}
.divider-or{display:flex;align-items:center;gap:14px;margin:24px 0 16px;font-size:.78rem;opacity:.6}
.divider-or::before,.divider-or::after{content:"";flex:1;height:1px;background:currentColor;opacity:.3}
.auth-alt{margin:0;text-align:center}

@media (max-width:900px){
  .auth-shell--split .auth-stage{max-width:560px}
  .auth-card--split{grid-template-columns:1fr}
  .auth-art{order:-1;gap:0;padding:28px 26px}
  .auth-art .checkline,.auth-art .art-foot{display:none}
  .auth-art::after{font-size:11rem;bottom:-.4em}
  .auth-art h2{margin-bottom:8px}
  .auth-form-wrap{padding:30px 26px 34px}
}
@media (max-width:520px){
  .auth-shell--split{padding-left:12px;padding-right:12px}
  .auth-form-wrap{padding:26px 20px 30px}
  .auth-form .form-row{grid-template-columns:1fr}
}
@media (prefers-reduced-motion:reduce){.auth-shell--split *,.auth-shell--split *::before{transition:none!important;animation-duration:1.5s!important}}
</style>

<script>
(function () {
  var $ = function (s) { return document.querySelector(s); };

  // Show / hide password (works for both fields)
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

  // Password strength guidance
  var pw = $('#password'), conf = $('#password_confirmation'),
      meter = $('#pw-meter'), label = $('#pw-meter-label'), match = $('#match-hint');
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
  if (pw && meter && label) {
    pw.addEventListener('input', function () {
      var s = score(pw.value);
      meter.setAttribute('data-level', s);
      label.textContent = words[s];
      if (conf) checkMatch();
    });
  }
  if (conf && match) conf.addEventListener('input', checkMatch);

  // Prevent double submits and show progress
  var form = $('#register-form'), btn = $('#register-submit');
  if (form && btn) {
    form.addEventListener('submit', function () {
      if (btn.disabled) return;
      btn.disabled = true;
      btn.classList.add('is-loading');
      btn.querySelector('.btn-label').textContent = 'Creating account\u2026';
    });
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) {
        btn.disabled = false;
        btn.classList.remove('is-loading');
        btn.querySelector('.btn-label').textContent = 'Create account';
      }
    });
  }
})();
</script>
