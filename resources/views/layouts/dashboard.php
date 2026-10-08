<?php
/**
 * Fundraiser dashboard layout.
 *
 * @var string $content
 * @var array|null $authUser
 * @var string $pageTitle
 */

use App\Core\Config;

$appName = (string) Config::get('app.name', 'Almarah Foundation');
$title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' | Dashboard' : 'Dashboard';
$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$is = static fn (string $prefix): string => str_starts_with($path, $prefix) && ($prefix !== '/dashboard' || $path === '/dashboard') ? 'active' : '';
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
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Crect width='48' height='48' rx='10' fill='%23a92d63'/%3E%3Cpath d='M24 15.2c5.9 0 11.2 2.3 15.2 6.1v17.2a3 3 0 0 1-3 3H11.8a3 3 0 0 1-3-3V21.3A21.6 21.6 0 0 1 24 15.2z' fill='%23f2c200'/%3E%3C/svg%3E">
</head>
<body class="dash-body">

<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="wrap">
<?php require __DIR__ . '/../partials/alerts.php'; ?>

  <div class="dash-shell">
    <aside class="dash-side">
      <div class="dash-side-head">
        <span class="avatar"><?= e(initials($fullName !== '' ? $fullName : 'Fundraiser')) ?></span>
        <b><?= e($fullName !== '' ? $fullName : 'Fundraiser') ?></b>
        <span><?= e((string) ($authUser['email'] ?? '')) ?></span>
        <?php if (!empty($authUser) && empty($authUser['email_verified_at'])): ?>
          <span class="badge badge-pending_review" style="margin-top:8px">Email unverified</span>
        <?php endif; ?>
      </div>
      <nav class="dash-nav" aria-label="Dashboard">
        <a href="<?= e(base_url('dashboard')) ?>" class="<?= $is('/dashboard') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
          Overview
        </a>
        <a href="<?= e(base_url('dashboard/fundraisers')) ?>" class="<?= $is('/dashboard/fundraisers') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
          My fundraisers
        </a>
        <a href="<?= e(base_url('dashboard/teams')) ?>" class="<?= $is('/dashboard/teams') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          My teams
        </a>
        <hr>
        <a href="<?= e(base_url('dashboard/profile')) ?>" class="<?= $is('/dashboard/profile') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Profile
        </a>
        <a href="<?= e(base_url('dashboard/security')) ?>" class="<?= $is('/dashboard/security') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          Password &amp; security
        </a>
        <a href="<?= e(base_url('dashboard/email-preferences')) ?>" class="<?= $is('/dashboard/email-preferences') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 6 10-6"/></svg>
          Email preferences
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
  <a class="m-link" href="<?= e(base_url('dashboard')) ?>">Overview</a>
  <a class="m-link" href="<?= e(base_url('dashboard/fundraisers')) ?>">My fundraisers</a>
  <a class="m-link" href="<?= e(base_url('dashboard/teams')) ?>">My teams</a>
  <a class="m-link" href="<?= e(base_url('dashboard/profile')) ?>">Profile</a>
  <a class="m-link" href="<?= e(base_url('dashboard/security')) ?>">Password &amp; security</a>
  <a class="m-link" href="<?= e(base_url('logout')) ?>">Sign out</a>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
