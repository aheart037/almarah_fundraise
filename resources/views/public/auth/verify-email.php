<?php
/**
 * "Please verify your email" page.
 *
 * @var array $user
 */
$email = (string) ($user['email'] ?? '');
$tick  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';
?>
<link rel="stylesheet" href="<?= e(base_url('assets/auth-pages.css')) ?>">
<section class="auth-shell auth-shell--split">
  <div class="auth-stage">
    <div class="auth-card--split">

      <aside class="auth-art" aria-label="Why we verify email addresses">
        <div class="art-copy">
          <h2>Verify your email address</h2>
          <p>It takes one click, and it keeps your account and your supporters' details safe.</p>
          <ul class="checkline">
            <li><?= $tick ?><span>Donation receipts and payment updates reach you</span></li>
            <li><?= $tick ?><span>You hear when your fundraiser is approved</span></li>
            <li><?= $tick ?><span>Nobody else can take over your account</span></li>
          </ul>
        </div>
        <p class="art-foot">The link we email you is valid for 48 hours.</p>
      </aside>

      <div class="auth-form-wrap">
        <div class="auth-form">
          <div class="mail-badge" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 7.5l8.5 6 8.5-6"/></svg>
          </div>

          <h1>Check your inbox</h1>
          <p class="auth-lede">
            We sent a verification link to <strong><?= e($email) ?></strong>.
            Open it to finish setting up your account.
          </p>

          <div class="alert alert-info" role="note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
            <p>Nothing there yet? Check your spam folder, then send yourself a new link below.</p>
          </div>

          <form method="post" action="<?= e(base_url('verify-email')) ?>" id="resend-form">
            <?= csrf_field() ?>
            <button class="btn btn-brand btn-lg btn-block" type="submit" id="resend-btn"><span class="btn-label">Send a new verification link</span></button>
            <span class="form-help" id="resend-status" role="status" hidden></span>
          </form>

          <div class="divider-or"><span>or</span></div>
          <p class="auth-alt">
            <a href="<?= e(base_url('dashboard')) ?>">Continue to your dashboard</a><br>
            <span class="form-help">You can keep working while you wait.</span>
          </p>
        </div>
      </div>

    </div>
  </div>
</section>

<script>
(function () {
  // After sending, hold the button for 60 seconds so people don't flood their own inbox.
  var form = document.getElementById('resend-form'), btn = document.getElementById('resend-btn'),
      status = document.getElementById('resend-status'), txt = btn.querySelector('.btn-label'),
      KEY = 'almarah.verify.resend', WAIT = 60, original = txt.textContent, timer;

  function get() { try { return parseInt(sessionStorage.getItem(KEY), 10) || 0; } catch (e) { return 0; } }
  function set(v) { try { sessionStorage.setItem(KEY, String(v)); } catch (e) {} }

  function tick() {
    var left = Math.ceil((get() + WAIT * 1000 - Date.now()) / 1000);
    if (left > 0) {
      btn.disabled = true;
      status.hidden = false;
      status.textContent = 'Link sent. You can request another in ' + left + ' seconds.';
    } else {
      clearInterval(timer);
      btn.disabled = false;
      status.hidden = true;
      txt.textContent = original;
    }
  }
  if (get() && get() + WAIT * 1000 > Date.now()) { tick(); timer = setInterval(tick, 1000); }

  form.addEventListener('submit', function () {
    if (btn.disabled) return;
    set(Date.now());
    btn.classList.add('is-loading');
    txt.textContent = 'Sending\u2026';
  });
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) { btn.classList.remove('is-loading'); txt.textContent = original; }
  });
})();
</script>
