<?php
/**
 * Site header. Mirrors the marketing header from the design system.
 *
 * @var array|null $authUser
 * @var bool|null $isAdmin
 */

use App\Core\Config;

$org = (array) Config::get('app.org', []);
$currentPath = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$isActive = static fn (string $path): string => str_starts_with($currentPath, $path) ? 'active' : '';
$fullName = !empty($authUser) ? trim((string) $authUser['first_name'] . ' ' . (string) $authUser['last_name']) : '';
$headerLogoUrl = site_brand_asset('site.header_logo');
?>
<header class="site-header" id="site-header">
  <div class="wrap">
    <a class="brand-lockup" href="<?= e(base_url('/')) ?>" aria-label="<?= e((string) Config::get('app.name', 'Almarah Foundation')) ?> home">
      <?php if ($headerLogoUrl !== null): ?>
        <img class="brand-custom-logo" src="<?= e($headerLogoUrl) ?>" alt="">
      <?php else: ?>
        <svg width="42" height="42" viewBox="0 0 48 48" fill="none" aria-hidden="true">
          <path d="M24 3.4c3.9 5.2 6.1 9.2 6.1 13.6 0 3.9-2.7 6.6-6.1 6.6s-6.1-2.7-6.1-6.6C17.9 12.6 20.1 8.6 24 3.4z" fill="#f2c200" opacity=".82"/>
          <path d="M24 15.2c5.9 0 11.2 2.3 15.2 6.1v17.2a3 3 0 0 1-3 3H11.8a3 3 0 0 1-3-3V21.3A21.6 21.6 0 0 1 24 15.2z" fill="#f2c200"/>
          <path d="M24 22.4l2 4.1 4.5.6-3.3 3.2.8 4.5-4-2.1-4 2.1.8-4.5-3.3-3.2 4.5-.6z" fill="#6b0f35"/>
          <path d="M24 35.6c1.9-1.7 4.4-3.2 6.4-4.3a2 2 0 0 1 2 3.5c-3.2 1.8-6.2 3.9-8.4 5.9-2.2-2-5.2-4.1-8.4-5.9a2 2 0 1 1 2-3.5c2 1.1 4.5 2.6 6.4 4.3z" fill="#f2c200" opacity=".9"/>
        </svg>
        <span class="word"><strong>ALMARAH</strong><span>Foundation</span></span>
      <?php endif; ?>
    </a>

    <nav class="main-nav" aria-label="Primary">
      <a href="<?= e(base_url('fundraisers')) ?>" class="<?= $isActive('/fundraisers') ?>">Support a Fundraiser</a>
      <a href="<?= e(base_url('teams')) ?>" class="<?= $isActive('/teams') ?>">Teams</a>
      <a href="<?= e(base_url('campaigns')) ?>" class="<?= $isActive('/campaigns') ?>">Campaigns</a>
      <a href="<?= e(base_url('about-us')) ?>" class="<?= $isActive('/about-us') ?>">About Us</a>
    </nav>

    <div class="header-cta">
      <?php if (!empty($authUser)): ?>
        <?php if (!empty($isAdmin)): ?>
          <a class="btn btn-outline-brand btn-sm" href="<?= e(base_url('admin')) ?>">Admin</a>
        <?php endif; ?>
        <?php if (can_fundraiser_capability('manage_pages')): ?>
          <a class="btn btn-gold btn-sm" href="<?= e(base_url('dashboard/fundraisers/create')) ?>">Start Fundraising</a>
        <?php endif; ?>
        <a class="btn-login" href="<?= e(base_url('dashboard')) ?>"><?= e($fullName !== '' ? $fullName : 'Dashboard') ?></a>
        <form class="inline-form" method="post" action="<?= e(base_url('logout')) ?>">
          <?= csrf_field() ?>
          <button class="btn-login" type="submit">Sign out</button>
        </form>
      <?php else: ?>
        <?php if (can_fundraiser_capability('manage_pages')): ?>
          <a class="btn btn-gold btn-sm" href="<?= e(base_url('register')) ?>">Start Fundraising</a>
        <?php endif; ?>
        <a class="btn-login" href="<?= e(base_url('login')) ?>">Login</a>
      <?php endif; ?>
      <button class="nav-toggle" type="button" aria-label="Menu" aria-expanded="false" data-nav-toggle>
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>
