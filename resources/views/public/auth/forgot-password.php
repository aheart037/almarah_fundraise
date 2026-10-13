<?php
/** Request a password reset link. */
$emailError = (string) error_for('email');
?>
<section class="auth-shell auth-shell--split">
  <div class="auth-stage">
    <div class="auth-card--split">

      <!-- ============ Left: brand panel ============ -->
      <aside class="auth-art" aria-label="How password recovery works">
        <div class="art-copy">
          <h2>Locked out? It happens.</h2>
          <p>Tell us the email on your account and we will send you a secure link to choose a new password.</p>

          <ol class="steps">
            <li><span class="step-dot" aria-hidden="true">1</span><span>Enter your email address</span></li>
            <li><span class="step-dot" aria-hidden="true">2</span><span>Open the link we email you</span></li>
            <li><span class="step-dot" aria-hidden="true">3</span><span>Choose a new password and sign in</span></li>
          </ol>
        </div>
        <p class="art-foot">The link works for two hours and can be used once.</p>
      </aside>

      <!-- ============ Right: form ============ -->
      <div class="auth-form-wrap">
        <div class="auth-form">
          <h1>Reset your password</h1>
          <p class="auth-lede">We will email you a link that expires in two hours.</p>

          <form method="post" action="<?= e(base_url('forgot-password')) ?>" id="forgot-form" novalidate>
            <?= csrf_field() ?>

            <div class="field <?= $emailError !== '' ? 'is-invalid' : '' ?>">
              <label for="email">Email address<span class="req">*</span></label>
              <input class="input" type="email" id="email" name="email" required autofocus
                     autocomplete="email" autocapitalize="off" spellcheck="false" inputmode="email"
                     placeholder="you@example.com" value="<?= e(old('email')) ?>"
                     aria-describedby="<?= $emailError !== '' ? 'email-error ' : '' ?>privacy-note"
                     <?= $emailError !== '' ? 'aria-invalid="true"' : '' ?>>
              <?php if ($emailError !== ''): ?>
                <span class="field-error" id="email-error" role="alert"><?= e($emailError) ?></span>
              <?php endif; ?>
            </div>

            <div class="form-actions">
              <button class="btn btn-brand btn-lg btn-block" type="submit" id="forgot-submit">
                <span class="btn-label">Email me a reset link</span>
              </button>
            </div>
          </form>

          <p class="privacy-note" id="privacy-note">
            For your security, we show the same message whether or not the address is registered.
            If nothing arrives within a few minutes, check your spam folder.
          </p>

          <div class="divider-or"><span>or</span></div>
          <p class="auth-alt"><a href="<?= e(base_url('login')) ?>">Back to sign in</a></p>
        </div>
      </div>

    </div>
  </div>
</section>

<style>
/* Same visual system as sign-in and register. When all three pages are in, move the shared
   block into your main stylesheet once. Colour rules use :where() so your theme wins. */
.auth-shell--split{--header-gap:0px;--a-pink:#af2f67;--a-deep:#6b1038;--a-yellow:#ffda00;--a-ink:#2a1020;--a-mute:#7a4560;--a-line:#ecd3df;
  display:block;width:100%;padding:calc(var(--header-gap) + clamp(32px,7vh,88px)) clamp(16px,4vw,40px) clamp(48px,8vh,96px)}
.auth-shell--split .auth-stage{width:100%;max-width:1000px;margin:0 auto}
.auth-card--split{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);border-radius:22px;overflow:hidden;background:#fff;
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
/* These steps really are a sequence, so they are numbered */
.steps{display:grid;gap:16px;margin:28px 0 0;padding:0;list-style:none}
.steps li{display:flex;align-items:center;gap:14px;font-size:.95rem;line-height:1.5}
.step-dot{flex:0 0 auto;display:grid;place-items:center;width:28px;height:28px;border-radius:50%;font-weight:800;font-size:.85rem}
:where(.step-dot){background:var(--a-yellow);color:var(--a-deep)}
.auth-art .art-foot{max-width:34ch;font-size:.8rem;opacity:.7}

.auth-form-wrap{display:flex;align-items:center;padding:clamp(32px,5vw,56px) clamp(28px,4vw,52px)}
.auth-form{width:100%;max-width:420px;margin:0 auto}
.auth-form h1{margin:0 0 6px;font-size:clamp(1.6rem,2.6vw,2rem);line-height:1.15;letter-spacing:-.015em}
.auth-lede{margin:0 0 26px}
:where(.auth-form .auth-lede,.auth-form .privacy-note){color:var(--a-mute)}
.auth-form .field{margin-bottom:18px}
.auth-form label{display:block;margin-bottom:6px;font-weight:600;font-size:.88rem}
.auth-form .req{margin-left:2px;color:var(--a-pink)}
:where(.auth-form .input){width:100%;padding:12px 14px;font:inherit;color:var(--a-ink);background:#fff;border:1.5px solid var(--a-line);border-radius:12px;transition:border-color .15s ease,box-shadow .15s ease}
:where(.auth-form .input:focus){outline:none;border-color:var(--a-pink);box-shadow:0 0 0 3px rgba(175,47,103,.14)}
:where(.auth-form .is-invalid .input){border-color:#c0392b}
:where(.auth-form a){color:var(--a-pink);font-weight:600;text-underline-offset:3px}
.field-error{display:block;margin-top:6px;font-size:.82rem;line-height:1.4}
:where(.field-error){color:#b3261e}

.auth-form .form-actions{margin-top:22px}
.auth-form .form-actions .btn{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:10px}
:where(.auth-form .btn-brand){padding:14px 24px;font:800 1rem inherit;font-family:inherit;color:#fff;background:var(--a-pink);border:0;border-radius:999px;cursor:pointer;transition:background .15s ease}
:where(.auth-form .btn-brand:hover:not(:disabled)){background:#8a2050}
:where(.auth-form .btn-brand:focus-visible){outline:3px solid var(--a-yellow);outline-offset:2px}
.btn[disabled]{opacity:.75;cursor:progress}
.btn.is-loading::before{content:"";width:16px;height:16px;border-radius:50%;border:2px solid currentColor;border-right-color:transparent;animation:auth-spin .7s linear infinite}
@keyframes auth-spin{to{transform:rotate(360deg)}}
.privacy-note{margin:18px 0 0;font-size:.82rem;line-height:1.6}
.divider-or{display:flex;align-items:center;gap:14px;margin:24px 0 16px;font-size:.78rem;opacity:.6}
.divider-or::before,.divider-or::after{content:"";flex:1;height:1px;background:currentColor;opacity:.3}
.auth-alt{margin:0;text-align:center}

@media (max-width:860px){
  .auth-shell--split .auth-stage{max-width:520px}
  .auth-card--split{grid-template-columns:1fr}
  .auth-art{order:-1;gap:0;padding:28px 26px}
  .auth-art .steps,.auth-art .art-foot{display:none}
  .auth-art::after{font-size:11rem;bottom:-.4em}
  .auth-art h2{margin-bottom:8px}
  .auth-form-wrap{padding:30px 26px 34px}
}
@media (max-width:420px){.auth-shell--split{padding-left:12px;padding-right:12px}.auth-form-wrap{padding:26px 20px 30px}}
@media (prefers-reduced-motion:reduce){.auth-shell--split *,.auth-shell--split *::before{transition:none!important;animation-duration:1.5s!important}}
</style>

<script>
(function () {
  var form = document.getElementById('forgot-form'), btn = document.getElementById('forgot-submit');
  if (!form || !btn) return;
  var label = btn.querySelector('.btn-label'), original = label.textContent;
  form.addEventListener('submit', function () {
    if (btn.disabled) return;
    btn.disabled = true;
    btn.classList.add('is-loading');
    label.textContent = 'Sending\u2026';
  });
  // Restore the button if the browser brings this page back from its back/forward cache
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      btn.disabled = false;
      btn.classList.remove('is-loading');
      label.textContent = original;
    }
  });
})();
</script>
