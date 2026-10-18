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

    <nav class="main-nav" id="main-nav" aria-label="Primary">
      <a href="<?= e(base_url('fundraisers')) ?>" class="<?= $isActive('/fundraisers') ?>">Support a Fundraiser</a>
      <a href="<?= e(base_url('teams')) ?>" class="<?= $isActive('/teams') ?>">Teams</a>
      <a href="<?= e(base_url('campaigns')) ?>" class="<?= $isActive('/campaigns') ?>">Campaigns</a>
      <a href="<?= e(base_url('about-us')) ?>" class="<?= $isActive('/about-us') ?>">About Us</a>

      <?php /* MOBILE ONLY: same buttons as .header-cta, shown inside the opened menu. Hidden on desktop. */ ?>
      <div class="mobile-menu-cta">
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
      </div>
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
        <a class="header-login" href="<?= e(base_url('login')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.2 3.6-7 8-7s8 2.8 8 7"/></svg>
          <span>Login</span>
        </a>
      <?php endif; ?>
      <button class="nav-toggle" type="button" aria-label="Menu" aria-expanded="false" aria-controls="main-nav" data-nav-toggle>
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<style>
/* ===== MOBILE MENU ADDITIONS ONLY =====
   Desktop is untouched. Colours and fonts for links and buttons still come from your theme.
   - To change when the mobile menu starts, replace 900px below.
   - To force a menu colour, set  --mobile-menu-bg: #yourcolor;  in your CSS.
     Otherwise it picks white or deep wine automatically, whichever keeps your link colour readable. */

.site-header .mobile-menu-cta { display: none; }
html.menu-lock, html.menu-lock body { overflow: hidden; }

@media (max-width: 900px) {
  /* Bar keeps only the logo and the menu button */
  .site-header .header-cta > :not(.nav-toggle) { display: none; }
  .site-header .wrap { flex-wrap: wrap; }

  /* While open, the bar becomes solid so it never shows the page behind it */
  .site-header.menu-open {
    background-color: var(--mobile-menu-bg, var(--ah-menu-bg, #fff)) !important;
    background-image: none !important;
    -webkit-backdrop-filter: none !important;
    backdrop-filter: none !important;
  }

  /* The menu is a solid sheet that starts right under the bar and fills the rest of the screen */
  .site-header .main-nav {
    display: none;
    position: fixed;
    left: 0;
    right: 0;
    top: var(--ah-top, 72px);
    bottom: 0;
    z-index: 99;
    flex-direction: column;
    align-items: stretch;
    gap: 0;
    margin: 0;
    padding: 6px clamp(16px, 5vw, 32px) calc(28px + env(safe-area-inset-bottom, 0px));
    background: var(--mobile-menu-bg, var(--ah-menu-bg, #fff));
    border-top: 1px solid rgba(128, 128, 128, .25);
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
  }
  .site-header.menu-open .main-nav { display: flex; }

  .site-header .main-nav > a {
    display: block;
    padding: 17px 4px;
    font-size: 1.05rem;
    border-top: 1px solid rgba(128, 128, 128, .22);
  }
  .site-header .main-nav > a:first-child { border-top: 0; }

  /* The bar's buttons, repeated at the bottom of the menu at full width */
  .site-header.menu-open .mobile-menu-cta {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 10px;
    padding-top: 18px;
    border-top: 1px solid rgba(128, 128, 128, .22);
  }
  .site-header .mobile-menu-cta .btn,
  .site-header .mobile-menu-cta .btn-login,
  .site-header .mobile-menu-cta .inline-form,
  .site-header .mobile-menu-cta .inline-form button {
    display: block;
    width: 100%;
    text-align: center;
    box-sizing: border-box;
  }
  .site-header .mobile-menu-cta .btn { padding-top: 14px; padding-bottom: 14px; }
}

/* ===== DESKTOP LOGIN BUTTON =====
   An outlined pill with a user icon. It takes its colour from your nav links, so it
   works on a light or a dark header, and sits nicely beside the gold Start Fundraising button. */
@media (min-width: 901px) {
  .site-header .header-cta .header-login {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border: 1.5px solid rgba(128, 128, 128, .6);
    border-color: color-mix(in srgb, currentColor 55%, transparent);
    border-radius: 999px;
    background: transparent;
    color: var(--ah-login-color, inherit);
    font: inherit;
    font-size: .9rem;
    font-weight: 700;
    line-height: 1.2;
    text-decoration: none;
    white-space: nowrap;
    transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
  }
  .site-header .header-cta .header-login svg { width: 18px; height: 18px; flex: 0 0 auto; }
  .site-header .header-cta .header-login:hover {
    border-color: currentColor;
    background: rgba(128, 128, 128, .16);
    transform: translateY(-1px);
  }
  .site-header .header-cta .header-login:focus-visible { outline: 2px solid currentColor; outline-offset: 3px; }
}
@media (prefers-reduced-motion: reduce) {
  .site-header .header-cta .header-login { transition: none; transform: none; }
}
</style>

<script>
(function () {
  var header = document.getElementById('site-header');
  var toggle = header && header.querySelector('[data-nav-toggle]');
  var nav = document.getElementById('main-nav');
  if (!header || !toggle || !nav) return;

  var root = document.documentElement;
  var mq = window.matchMedia('(max-width: 900px)');
  function onMq(fn) {
    if (mq.addEventListener) mq.addEventListener('change', fn);
    else if (mq.addListener) mq.addListener(fn);   // Safari 13 and older
  }

  // Choose a solid menu colour that keeps your existing link colour readable:
  // light link text gets deep wine, dark link text gets white.
  function pickMenuColour() {
    var link = nav.querySelector('a');
    var m = link && getComputedStyle(link).color.match(/[\d.]+/g);
    var light = m ? (0.299 * m[0] + 0.587 * m[1] + 0.114 * m[2]) / 255 > 0.6 : false;
    header.style.setProperty('--ah-menu-bg', light ? '#6b1038' : '#ffffff');
  }
  // Start the sheet exactly under the bar, however tall your bar is
  function placeMenu() {
    var bottom = header.getBoundingClientRect().bottom;
    header.style.setProperty('--ah-top', Math.max(0, Math.round(bottom)) + 'px');
  }

  function setOpen(open) {
    if (open) {
      pickMenuColour();
      placeMenu();                     // measure while the menu is still closed
    }
    header.classList.toggle('menu-open', open);
    root.classList.toggle('menu-lock', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Menu');
  }
  function isOpen() { return header.classList.contains('menu-open'); }

  // Capture phase: stops any older script bound to this same button from also toggling it.
  document.addEventListener('click', function (e) {
    if (!toggle.contains(e.target)) return;
    e.stopPropagation();
    setOpen(!isOpen());
  }, true);

  nav.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
  document.addEventListener('keydown', function (e) {
    if (!isOpen()) return;
    if (e.key === 'Escape') { setOpen(false); toggle.focus(); return; }
    if (e.key !== 'Tab') return;
    // Tab loops through the bar and the menu only, never the page hidden behind the sheet
    var items = Array.prototype.filter.call(
      header.querySelectorAll('a[href],button:not([disabled])'),
      function (el) { return el.offsetParent !== null; }
    );
    if (!items.length) return;
    var first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
  window.addEventListener('resize', function () { if (isOpen()) { if (!mq.matches) setOpen(false); else placeMenu(); } });
  window.addEventListener('orientationchange', function () { if (isOpen()) setTimeout(placeMenu, 250); });
  onMq(function () { if (!mq.matches) setOpen(false); });

  // Safety net: if your theme hides the menu button at this width, show it so the menu is reachable
  function ensureToggle() {
    toggle.style.display = '';
    if (mq.matches && getComputedStyle(toggle).display === 'none') toggle.style.display = 'inline-flex';
  }
  ensureToggle();
  onMq(ensureToggle);

  // Match the login button colour to your nav links, including when the header changes colour on scroll
  var login = header.querySelector('.header-login');
  var firstLink = nav.querySelector('a');
  if (login && firstLink) {
    var ticking = false;
    var syncLogin = function () {
      header.style.setProperty('--ah-login-color', getComputedStyle(firstLink).color);
      ticking = false;
    };
    var queue = function () { if (!ticking) { ticking = true; requestAnimationFrame(syncLogin); } };
    syncLogin();
    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue);
  }
})();
</script>
