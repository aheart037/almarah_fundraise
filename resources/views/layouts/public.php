<?php
/**
 * Public site layout — header, main content, footer.
 *
 * @var string $content
 * @var string $pageTitle
 * @var array|null $authUser
 * @var bool|null $isAdmin
 */

use App\Core\Config;

$appName = (string) Config::get('app.name', 'Almarah Foundation');
$org = (array) Config::get('app.org', []);
$title = isset($pageTitle) && $pageTitle !== ''
    ? $pageTitle . ' | ' . $appName
    : $appName . ' | Fundraise for Orphan Care, Food & Education';
$description = $metaDescription ?? (string) Config::get('app.org.tagline', '');
$currentPath = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$isAuthPage = preg_match('~/(?:login|register|forgot-password|reset-password|verify-email)(?:/|$)~', $currentPath) === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<?php if (!empty($ogImage)): ?>
<meta property="og:image" content="<?= e(base_url((string) $ogImage)) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="website">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<?php require __DIR__ . '/../partials/favicon.php'; ?>
</head>
<body<?= $isAuthPage ? ' class="auth-page-body"' : '' ?>>

<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main">
<?php require __DIR__ . '/../partials/alerts.php'; ?>
<?= $content ?>
</main>

<?php require __DIR__ . '/../partials/footer.php'; ?>

<div class="mobile-panel" id="mobilePanel">
  <a class="m-link" href="<?= e(base_url('fundraisers')) ?>">Browse fundraisers</a>
  <a class="m-link" href="<?= e(base_url('teams')) ?>">Teams</a>
  <a class="m-link" href="<?= e(base_url('campaigns')) ?>">Campaigns</a>
  <a class="m-link" href="<?= e(base_url('about-us')) ?>">About us</a>
  <a class="m-link" href="<?= e(base_url('faq')) ?>">FAQ</a>
  <?php if (!empty($authUser)): ?>
    <a class="m-link" href="<?= e(base_url('dashboard')) ?>">Dashboard</a>
    <a class="m-link" href="<?= e(base_url('logout')) ?>" data-method="post">Sign out</a>
  <?php else: ?>
    <a class="m-link" href="<?= e(base_url('login')) ?>">Sign in</a>
    <?php if (can_fundraiser_capability('manage_pages')): ?>
      <a class="btn btn-gold" href="<?= e(base_url('register')) ?>">Start Your Fundraiser</a>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
