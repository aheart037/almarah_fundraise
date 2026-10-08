<?php
/**
 * Site footer.
 */

use App\Core\Config;

$org = (array) Config::get('app.org', []);
$appName = (string) Config::get('app.name', 'Almarah Foundation');
$year = date('Y');
$footerLogoUrl = site_brand_asset('site.footer_logo');
?>
<footer class="site-footer" id="site-footer">
  <div class="wrap">
    <div class="footer-top">
      <div class="footer-brand">
        <a class="brand-lockup" href="<?= e(base_url('/')) ?>" aria-label="<?= e($appName) ?> home">
          <?php if ($footerLogoUrl !== null): ?>
            <img class="brand-custom-logo" src="<?= e($footerLogoUrl) ?>" alt="">
          <?php else: ?>
            <svg width="42" height="42" viewBox="0 0 48 48" fill="none" aria-hidden="true">
              <path d="M24 15.2c5.9 0 11.2 2.3 15.2 6.1v17.2a3 3 0 0 1-3 3H11.8a3 3 0 0 1-3-3V21.3A21.6 21.6 0 0 1 24 15.2z" fill="#f2c200"/>
              <path d="M24 22.4l2 4.1 4.5.6-3.3 3.2.8 4.5-4-2.1-4 2.1.8-4.5-3.3-3.2 4.5-.6z" fill="#6b0f35"/>
            </svg>
            <span class="word"><strong>ALMARAH</strong><span>Foundation</span></span>
          <?php endif; ?>
        </a>
        <p><?= e((string) ($org['footer_note'] ?? ($appName . ' has been walking alongside orphaned and vulnerable children since ' . ($org['founded'] ?? '2021') . '. A safe home, a full plate, a place in school — and the dignity to dream bigger.'))) ?></p>
        <div class="socials">
          <a href="https://www.almarah.org" target="_blank" rel="noopener" aria-label="Facebook">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/></svg>
          </a>
          <a href="https://www.almarah.org" target="_blank" rel="noopener" aria-label="Instagram">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/></svg>
          </a>
          <a href="https://www.almarah.org" target="_blank" rel="noopener" aria-label="WhatsApp">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm5.8 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.7-.6a11 11 0 0 1-4.2-3.7c-.3-.5-.7-1.2-.7-2s.3-1.4.5-1.6c.2-.3.5-.3.6-.3h.5c.2 0 .4 0 .5.4l.7 1.6c.1.2 0 .4-.1.5l-.3.4c-.1.1-.2.3-.1.5.2.5.6 1 1 1.4.5.5 1.1.9 1.7 1.2.2.1.4.1.5 0l.6-.7c.2-.2.3-.2.5-.1l1.6.7c.2.1.4.2.4.3.1.2.1.7-.1 1.3z"/></svg>
          </a>
        </div>
      </div>

      <div>
        <h5>Fundraise</h5>
        <ul class="footer-links">
          <?php if (can_fundraiser_capability('manage_pages')): ?><li><a href="<?= e(base_url('register')) ?>">Start a Fundraiser</a></li><?php endif; ?>
          <li><a href="<?= e(base_url('fundraisers')) ?>">Support a Fundraiser</a></li>
          <li><a href="<?= e(base_url('teams')) ?>">Top Teams</a></li>
          <li><a href="<?= e(base_url('campaigns')) ?>">Appeal Campaigns</a></li>
          <li><a href="<?= e(base_url('login')) ?>">Fundraiser Login</a></li>
        </ul>
      </div>

      <div>
        <h5>Our Work</h5>
        <ul class="footer-links">
          <li><a href="<?= e(base_url('campaigns')) ?>">Parent the Orphan</a></li>
          <li><a href="<?= e(base_url('campaigns')) ?>">Feed the Hungry</a></li>
          <li><a href="<?= e(base_url('campaigns')) ?>">Educate Pakistan</a></li>
          <li><a href="<?= e(base_url('about-us')) ?>">Izzat ki Roti</a></li>
          <li><a href="<?= e(base_url('about-us')) ?>">Compassionate Haven</a></li>
        </ul>
      </div>

      <div>
        <h5>Get in Touch</h5>
        <ul class="footer-contact">
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span><?= e((string) ($org['address'] ?? '')) ?></span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 6 10-6"/></svg>
            <a href="mailto:<?= e((string) ($org['email'] ?? '')) ?>"><?= e((string) ($org['email'] ?? '')) ?></a>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) ($org['phone'] ?? '')) ?? '') ?>"><?= e((string) ($org['phone'] ?? '')) ?></a>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
            <a href="<?= e((string) ($org['website'] ?? 'https://www.almarah.org')) ?>" target="_blank" rel="noopener">almarah.org</a>
          </li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?= e($year) ?> <?= e($appName) ?>. All Rights Reserved.</span>
      <span class="legal">
        <a href="<?= e(base_url('faq')) ?>">FAQs</a>
        <a href="<?= e(base_url('privacy-policy')) ?>">Privacy Policy</a>
        <a href="<?= e(base_url('terms')) ?>">Terms</a>
        <a href="<?= e(base_url('support')) ?>">Support</a>
      </span>
    </div>
  </div>
</footer>
