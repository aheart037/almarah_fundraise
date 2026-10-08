<?php
/**
 * Appeal campaign listing.
 *
 * @var array $campaigns
 */
?>
<section class="page-banner">
  <div class="wrap">
    <span class="eyebrow">Appeal Campaigns</span>
    <h1>Where your support goes</h1>
    <p>Our long-running appeals run alongside personal fundraisers. Choose a programme and fund it directly.</p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <?php if ($campaigns === []): ?>
      <div class="empty-state">
        <h3>No active campaigns right now</h3>
        <p>Check back soon, or browse the fundraisers our supporters have started.</p>
        <a class="btn btn-brand" href="<?= e(base_url('fundraisers')) ?>">Browse fundraisers</a>
      </div>
    <?php else: ?>
      <div class="card-grid cols-3">
        <?php foreach ($campaigns as $campaign): ?>
          <article class="insp-card reveal">
            <div class="insp-media">
              <img src="<?= e(!empty($campaign['image_path']) ? base_url((string) $campaign['image_path']) : asset('assets/img/insp-school.jpg')) ?>" alt="" loading="lazy">
            </div>
            <div class="insp-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
            </div>
            <h3><?= e((string) $campaign['title']) ?></h3>
            <p><?= e(mb_substr(strip_tags((string) ($campaign['description'] ?? '')), 0, 150)) ?></p>
            <div class="donation-btns">
              <a class="btn btn-brand btn-sm" href="<?= e(base_url('donate/' . (string) $campaign['slug'] . '?type=campaign')) ?>">Donate</a>
              <a class="btn btn-outline-brand btn-sm" href="<?= e(base_url('campaigns/' . (string) $campaign['slug'])) ?>">Learn more</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
