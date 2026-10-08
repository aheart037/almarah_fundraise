<?php
/**
 * Fundraiser detail page with donation box.
 *
 * @var array $fundraiser
 * @var array $updates
 * @var array $supporters
 * @var array $otherFundraisers
 * @var int $donorCount
 */

use App\Core\Config;

$f = $fundraiser;
$currency = (string) ($f['currency'] ?? 'PKR');
$raised = (int) ($f['raised_minor'] ?? 0);
$goal = (int) ($f['goal_minor'] ?? 0);
$percent = $goal > 0 ? min(100.0, round($raised / $goal * 100, 1)) : 0.0;
$remaining = max(0, $goal - $raised);
$organiser = trim((string) ($f['first_name'] ?? '') . ' ' . (string) ($f['last_name'] ?? ''));
$daysLeft = null;
if (!empty($f['end_at'])) {
    $daysLeft = (int) ceil((strtotime((string) $f['end_at'] . ' UTC') - time()) / 86400);
}
$donateParams = ['type' => 'fundraiser'];
$donateUrl = base_url('donate/' . (string) $f['slug']);
$shareUrl = base_url('fundraisers/' . (string) $f['slug']);
$image = !empty($f['cover_image_path']) ? base_url((string) $f['cover_image_path']) : asset('assets/img/hero.jpg');
$storyHtml = nl2br(e((string) ($f['story'] ?? '')));
?>

<section class="fr-hero">
  <div class="hero-bg" style="background-image:url('<?= e($image) ?>')"></div>
  <div class="wrap">
    <div class="hero-caption">
      <span class="pill"><?= e((string) ($f['category_name'] ?? 'Fundraiser')) ?></span>
      <h1><?= e((string) $f['title']) ?></h1>
      <p>Organised by <?= e($organiser !== '' ? $organiser : 'a supporter') ?><?php if (!empty($f['campaign_title'])): ?> for <?= e((string) $f['campaign_title']) ?><?php endif; ?></p>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="<?= e(base_url('/')) ?>">Home</a>
      <span>&rsaquo;</span>
      <a href="<?= e(base_url('fundraisers')) ?>">Fundraisers</a>
      <span>&rsaquo;</span>
      <span><?= e(mb_substr((string) $f['title'], 0, 48)) ?></span>
    </nav>

    <div class="fr-layout">
      <div>
        <article class="prose reveal">
          <h2>Our story</h2>
          <p><?= $storyHtml ?></p>

          <?php if (!empty($f['impact_statement'])): ?>
            <div class="quote-band mt-3">
              <p><?= e((string) $f['impact_statement']) ?></p>
              <span>&mdash; the impact you are funding</span>
            </div>
          <?php endif; ?>

          <div class="meta-row mt-3">
            <span class="badge badge-published">Verified fundraiser</span>
            <?php if ($daysLeft !== null): ?>
              <span class="badge <?= $daysLeft > 0 ? 'badge-amber' : 'badge-grey' ?>">
                <?= $daysLeft > 0 ? e((string) $daysLeft) . ' days left' : 'Campaign finished' ?>
              </span>
            <?php endif; ?>
            <span class="badge badge-grey">Goal <?= e(money_short($goal, $currency)) ?></span>
          </div>

          <div class="share-row mt-3">
            <span>Share this fundraiser</span>
            <a class="share-btn" target="_blank" rel="noopener"
               href="https://wa.me/?text=<?= e(rawurlencode((string) $f['title'] . ' — ' . $shareUrl)) ?>">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm5.8 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.7-.6a11 11 0 0 1-4.2-3.7c-.3-.5-.7-1.2-.7-2s.3-1.4.5-1.6c.2-.3.5-.3.6-.3h.5c.2 0 .4 0 .5.4l.7 1.6c.1.2 0 .4-.1.5l-.3.4c-.1.1-.2.3-.1.5.2.5.6 1 1 1.4.5.5 1.1.9 1.7 1.2.2.1.4.1.5 0l.6-.7c.2-.2.3-.2.5-.1l1.6.7c.2.1.4.2.4.3.1.2.1.7-.1 1.3z"/></svg>
              WhatsApp
            </a>
            <a class="share-btn" target="_blank" rel="noopener"
               href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($shareUrl)) ?>">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/></svg>
              Facebook
            </a>
            <a class="share-btn" target="_blank" rel="noopener"
               href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode($shareUrl)) ?>&text=<?= e(rawurlencode((string) $f['title'])) ?>">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.2 2H21l-6.6 7.5L22 22h-6.4l-4.4-6.1L6 22H3.2l7-8L2 2h6.6l4 5.6zm-.9 18h1.5L7.8 3.9H6.2z"/></svg>
              X
            </a>
            <button class="share-btn" type="button" data-copy="<?= e($shareUrl) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
              Copy link
            </button>
          </div>
        </article>

        <?php if ($updates !== []): ?>
          <section class="panel mt-3 reveal">
            <h2>Updates from <?= e($organiser !== '' ? $organiser : 'the organiser') ?></h2>
            <p class="panel-sub"><?= e((string) count($updates)) ?> update<?= count($updates) === 1 ? '' : 's' ?> posted.</p>
            <ul class="timeline">
              <?php foreach ($updates as $update): ?>
                <li>
                  <span class="when"><?= e(dt((string) ($update['published_at'] ?? $update['created_at']), 'j M Y')) ?></span>
                  <div class="what"><?= e((string) $update['title']) ?></div>
                  <div class="meta"><?= nl2br(e((string) $update['body'])) ?></div>
                  <?php if (!empty($update['image_path'])): ?>
                    <img src="<?= e(base_url((string) $update['image_path'])) ?>" alt="" style="max-width:100%;border-radius:var(--radius);margin-top:10px" loading="lazy">
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>

        <?php if ($supporters !== []): ?>
          <section class="panel reveal">
            <h2>Recent donations</h2>
            <p class="panel-sub">Only donations confirmed by the bank appear here.</p>
            <ul class="donations-list">
              <?php foreach ($supporters as $donation): ?>
                <li>
                  <span class="avatar"><?= e(initials(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? 'Supporter'))) ?></span>
                  <div class="d-info">
                    <b><?= e(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? 'Supporter')) ?></b>
                    <span class="d-when"><?= e(relative_time((string) ($donation['completed_at'] ?? $donation['created_at']))) ?></span>
                    <?php if (!empty($donation['donor_message'])): ?>
                      <p class="text-muted" style="margin:4px 0 0">“<?= e((string) $donation['donor_message']) ?>”</p>
                    <?php endif; ?>
                  </div>
                  <span class="d-amt"><?= e(money((int) $donation['amount_minor'], (string) ($donation['currency'] ?? $currency))) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>

        <?php if ($otherFundraisers !== []): ?>
          <section class="panel reveal">
            <h2>Other fundraisers you can help</h2>
            <div class="card-grid cols-3 mt-2">
              <?php foreach ($otherFundraisers as $other): ?>
                <?php $f = $other; $showProgress = true; require __DIR__ . '/../../partials/fundraiser-card.php'; ?>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      </div>

      <aside>
        <div class="donation-box preview-sticky">
          <div class="progress lg" role="progressbar" aria-valuenow="<?= e((string) (int) $percent) ?>" aria-valuemin="0" aria-valuemax="100">
            <span data-w="<?= e(number_format($percent, 1, '.', '')) ?>"></span>
          </div>
          <div class="raised-row">
            <b><?= e(money($raised, $currency)) ?></b>
            <span>raised of <?= e(money($goal, $currency)) ?> goal</span>
          </div>
          <div class="meta-row">
            <span><b><?= e(number_format($donorCount)) ?></b> donors</span>
            <span><b><?= e((string) (int) round($percent)) ?>%</b> funded</span>
            <?php if ($daysLeft !== null && $daysLeft > 0): ?>
              <span><b><?= e((string) $daysLeft) ?></b> days left</span>
            <?php endif; ?>
          </div>

          <?php if ($remaining > 0): ?>
            <p class="text-muted" style="font-size:14px;margin:14px 0 0">
              <?= e(money($remaining, $currency)) ?> still needed to reach the goal.
            </p>
          <?php else: ?>
            <p class="text-muted" style="font-size:14px;margin:14px 0 0">This fundraiser has reached its goal — thank you.</p>
          <?php endif; ?>

          <div class="donation-btns">
            <a class="btn btn-brand btn-block" href="<?= e($donateUrl) ?>">Donate now</a>
            <a class="btn btn-outline-brand btn-block" href="<?= e($shareUrl) ?>" data-share="<?= e($shareUrl) ?>" data-share-title="<?= e((string) $f['title']) ?>">Share</a>
          </div>

          <div class="org-card">
            <span class="avatar"><?= e(initials($organiser !== '' ? $organiser : 'Almarah')) ?></span>
            <div>
              <b><?= e($organiser !== '' ? $organiser : 'Almarah Foundation') ?></b>
              <span>Organiser</span>
            </div>
          </div>

          <div class="checkline mt-2" style="font-size:13.5px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg>
            <span style="color:var(--muted)">Card payments are handled on the bank&rsquo;s own secure page.</span>
          </div>
        </div>
      </aside>
    </div>
  </div>
</section>
