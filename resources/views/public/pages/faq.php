<?php
/**
 * FAQ page with accessible accordions (native <details>).
 *
 * @var array $faqs
 */
?>
<section class="page-banner">
  <div class="wrap">
    <span class="eyebrow">Questions &amp; Answers</span>
    <h1>Frequently asked questions</h1>
    <p>Everything about starting a fundraiser, donating, receipts and how we protect your data.</p>
  </div>
</section>

<section class="section">
  <div class="wrap wrap-narrow">
    <?php if ($faqs === []): ?>
      <div class="empty-state">
        <h3>No questions published yet</h3>
        <p>Our team is still writing these up. In the meantime, support is happy to answer anything directly.</p>
        <a class="btn btn-brand" href="<?= e(base_url('support')) ?>">Contact support</a>
      </div>
    <?php else: ?>
      <div id="faqs">
        <?php foreach ($faqs as $index => $faq): ?>
          <details class="panel" <?= $index === 0 ? 'open' : '' ?> style="padding:0">
            <summary style="cursor:pointer;padding:18px 22px;font-weight:700;color:var(--ink-2);font-size:16px;list-style:none">
              <?= e((string) $faq['question']) ?>
            </summary>
            <div style="padding:0 22px 20px;color:var(--text);line-height:1.7">
              <?= nl2br(e((string) $faq['answer'])) ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>

      <div class="alert alert-info mt-3">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
        <p>Still stuck? <a href="<?= e(base_url('support')) ?>">Contact our team</a> and we will come back to you within one working day.</p>
      </div>
    <?php endif; ?>
  </div>
</section>
