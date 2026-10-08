<?php
/**
 * "Please verify your email" page.
 *
 * @var array $user
 */
?>
<section class="auth-shell">
  <div class="auth-art">
    <div class="art-copy">
      <span class="eyebrow">Almost There</span>
      <h2>Verify your email address</h2>
      <p>Verification lets us send you donation receipts, fundraiser approvals and payment updates — and protects your account from being taken over.</p>
    </div>
  </div>

  <div class="auth-form-wrap">
    <div class="auth-card">
      <h1>Check your inbox</h1>
      <p class="text-muted">
        We sent a verification link to <b><?= e((string) ($user['email'] ?? '')) ?></b>.
        The link is valid for 48 hours.
      </p>

      <div class="alert alert-info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
        <p>Nothing in your inbox? Check the spam folder, then send yourself a fresh link below.</p>
      </div>

      <form method="post" action="<?= e(base_url('verify-email')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-brand btn-lg btn-block" type="submit">Send a new verification link</button>
      </form>

      <div class="divider-or"><span>or</span></div>
      <p class="auth-alt">
        <a href="<?= e(base_url('dashboard')) ?>">Continue to your dashboard</a> &mdash;
        you can keep working while you wait.
      </p>
    </div>
  </div>
</section>
