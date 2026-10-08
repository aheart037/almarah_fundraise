<?php
/**
 * Administrator layout. Navigation is rendered for everyone who reaches these
 * pages; the routes themselves are protected by role middleware.
 *
 * @var string $content
 * @var array|null $authUser
 * @var bool|null $isAdmin
 * @var string $pageTitle
 */

use App\Core\Config;

$appName = (string) Config::get('app.name', 'Almarah Foundation');
$title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' | Admin' : 'Admin';
$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$is = static function (string $prefix) use ($path): string {
    if ($prefix === '/admin') {
        return $path === '/admin' ? 'active' : '';
    }
    return str_starts_with($path, $prefix) ? 'active' : '';
};
$fullName = trim((string) ($authUser['first_name'] ?? '') . ' ' . (string) ($authUser['last_name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | <?= e($appName) ?></title>
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<?php require __DIR__ . '/../partials/favicon.php'; ?>
</head>
<body class="admin-body">

<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="wrap">
<?php require __DIR__ . '/../partials/alerts.php'; ?>

  <div class="dash-shell">
    <aside class="dash-side">
      <div class="dash-side-head" style="background:linear-gradient(135deg,#2a1119 0%,#6b0f35 100%)">
        <span class="avatar"><?= e(initials($fullName !== '' ? $fullName : 'Admin')) ?></span>
        <b><?= e($fullName !== '' ? $fullName : 'Administrator') ?></b>
        <span><?= e((string) ($authUser['email'] ?? '')) ?></span>
      </div>
      <nav class="dash-nav" aria-label="Admin">
        <a href="<?= e(base_url('admin')) ?>" class="<?= $is('/admin') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
          Overview
        </a>
        <a href="<?= e(base_url('admin/fundraisers')) ?>" class="<?= $is('/admin/fundraisers') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
          Fundraisers
        </a>
        <a href="<?= e(base_url('admin/donations')) ?>" class="<?= $is('/admin/donations') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21.2l7.7-7.8 1.1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
          Donations
        </a>
        <a href="<?= e(base_url('admin/payment-callbacks')) ?>" class="<?= $is('/admin/payment-callbacks') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"/></svg>
          Payment callbacks
        </a>
        <a href="<?= e(base_url('admin/users')) ?>" class="<?= $is('/admin/users') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Users
        </a>
        <a href="<?= e(base_url('admin/donors')) ?>" class="<?= $is('/admin/donors') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 9.5h4.5a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3H15"/></svg>
          Donors
        </a>
        <hr>
        <a href="<?= e(base_url('admin/content')) ?>" class="<?= $is('/admin/content') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
          Content &amp; FAQs
        </a>
        <a href="<?= e(base_url('admin/email-templates')) ?>" class="<?= $is('/admin/email') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 6 10-6"/></svg>
          Emails
        </a>
        <a href="<?= e(base_url('admin/settings')) ?>" class="<?= $is('/admin/settings') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7.5 19l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3.6 13H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.7 6.5l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 3.6V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.5 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.6 1.6 0 0 0 20.4 10H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/></svg>
          Settings
        </a>
        <a href="<?= e(base_url('admin/audit')) ?>" class="<?= $is('/admin/audit') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
          Audit log
        </a>
        <hr>
        <a href="<?= e(base_url('/')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
          Back to the site
        </a>
      </nav>
    </aside>

    <div class="dash-main">
<?= $content ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>

<div class="mobile-panel" id="mobilePanel">
  <a class="m-link" href="<?= e(base_url('admin')) ?>">Overview</a>
  <a class="m-link" href="<?= e(base_url('admin/fundraisers')) ?>">Fundraisers</a>
  <a class="m-link" href="<?= e(base_url('admin/donations')) ?>">Donations</a>
  <a class="m-link" href="<?= e(base_url('admin/users')) ?>">Users</a>
  <a class="m-link" href="<?= e(base_url('admin/settings')) ?>">Settings</a>
  <a class="m-link" href="<?= e(base_url('logout')) ?>">Sign out</a>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
