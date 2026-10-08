<?php
/**
 * Support and contact page.
 *
 * @var array $support
 * @var array $faqs
 */

use App\Core\Config;

$org = (array) Config::get('app.org', []);
?>
<section class="page-banner">
  <div class="wrap">
    <span class="eyebrow">We Are Here To Help</span>
    <h1><?= e((string) ($support['heading'] ?? 'Support')) ?></h1>
    <p><?= e((string) ($support['body'] ?? 'Our team in Lahore replies to every message.')) ?></p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="grid-3">
      <div class="panel">
        <h2>Email us</h2>
        <p class="panel-sub">Best for donation queries and receipts.</p>
        <p><a class="btn btn-brand" href="mailto:<?= e((string) ($support['email'] ?? $org['email'] ?? '')) ?>"><?= e((string) ($support['email'] ?? $org['email'] ?? '')) ?></a></p>
        <p class="text-muted mt-2" style="font-size:13.5px"><?= e((string) ($support['response'] ?? 'We usually reply within one working day.')) ?></p>
      </div>

      <div class="panel">
        <h2>Call or visit</h2>
        <p class="panel-sub">Our Lahore office is open six days a week.</p>
        <dl class="kv" style="grid-template-columns:1fr">
          <dt>Phone</dt>
          <dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) ($support['phone'] ?? $org['phone'] ?? '')) ?? '') ?>"><?= e((string) ($support['phone'] ?? $org['phone'] ?? '')) ?></a></dd>
          <dt>Address</dt>
          <dd><?= e((string) ($support['address'] ?? $org['address'] ?? '')) ?></dd>
          <dt>Hours</dt>
          <dd><?= e((string) ($support['hours'] ?? $org['hours'] ?? '')) ?></dd>
        </dl>
      </div>

      <div class="panel">
        <h2>Fundraiser help</h2>
        <p class="panel-sub">Questions about approvals, edits or your dashboard?</p>
        <ul class="footer-links" style="display:block">
          <li><a href="<?= e(base_url('faq')) ?>">Read the FAQ</a></li>
          <li><a href="<?= e(base_url('dashboard')) ?>">Open your dashboard</a></li>
          <li><a href="<?= e(base_url('privacy-policy')) ?>">Privacy policy</a></li>
          <li><a href="<?= e(base_url('terms')) ?>">Terms of use</a></li>
        </ul>
      </div>
    </div>

    <?php if ($faqs !== []): ?>
      <section class="panel mt-3">
        <h2>Common questions</h2>
        <p class="panel-sub">The answers people ask for most.</p>
        <?php foreach ($faqs as $faq): ?>
          <details style="border-bottom:1px solid var(--line-soft);padding:12px 0">
            <summary style="cursor:pointer;font-weight:700;color:var(--ink-2)"><?= e((string) $faq['question']) ?></summary>
            <div class="text-muted mt-1" style="line-height:1.7"><?= nl2br(e((string) $faq['answer'])) ?></div>
          </details>
        <?php endforeach; ?>
        <p class="mt-2"><a class="link-arrow" href="<?= e(base_url('faq')) ?>">See all questions</a></p>
      </section>
    <?php endif; ?>

    <div class="panel mt-3">
      <h2>Something wrong with a payment?</h2>
      <p class="panel-sub">If you were charged but the donation does not show as completed, we can reconcile it with the bank.</p>
      <p>Send us the public reference from your receipt (it looks like <span class="mono">ALM-XXXXXXXX-XXXX</span>) and we will trace the transaction with Meezan Bank or UBL EPG and reply with the outcome. Never send us your card number or CVV &mdash; we never need them.</p>
    </div>
  </div>
</section>
