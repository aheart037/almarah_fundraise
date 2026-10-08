<?php
/**
 * Campaign detail page.
 *
 * @var array $campaign
 * @var array $fundraisers
 */

$raised = 0;
$goal = 0;
foreach ($fundraisers as $row) {
    $raised += (int) ($row['raised_minor'] ?? 0);
    $goal += (int) ($row['goal_minor'] ?? 0);
}
$percent = $goal > 0 ? min(100.0, round($raised / $goal * 100, 1)) : 0.0;
$currency = 'PKR';
?>
<section class="fr-hero">
  <div class="hero-bg" style="background-image:url('<?= e(!empty($campaign['image_path']) ? base_url((string) $campaign['image_path']) : asset('assets/img/hero.jpg')) ?>')"></div>
  <div class="wrap">
    <div class="hero-caption">
      <span class="pill">Appeal campaign</span>
      <h1><?= e((string) $campaign['title']) ?></h1>
      <?php if (!empty($campaign['start_at']) || !empty($campaign['end_at'])): ?>
        <p><?= e(dt((string) ($campaign['start_at'] ?? ''), 'j M Y')) ?><?php if (!empty($campaign['end_at'])): ?> &ndash; <?= e(dt((string) $campaign['end_at'], 'j M Y')) ?><?php endif; ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="fr-layout">
      <div>
        <article class="prose reveal">
          <h2>About this appeal</h2>
          <p><?= nl2br(e((string) ($campaign['description'] ?? ''))) ?></p>
        </article>

        <?php if ($fundraisers !== []): ?>
          <section class="panel mt-3 reveal">
            <h2>Fundraisers supporting this campaign</h2>
            <p class="panel-sub">Every one of these pages feeds into the same programme.</p>
            <div class="card-grid cols-3">
              <?php foreach ($fundraisers as $f): ?>
                <?php $showProgress = true; require __DIR__ . '/../../partials/fundraiser-card.php'; ?>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      </div>

      <aside>
        <div class="donation-box preview-sticky">
          <?php if ($goal > 0): ?>
            <div class="progress lg" role="progressbar" aria-valuenow="<?= e((string) (int) $percent) ?>" aria-valuemin="0" aria-valuemax="100">
              <span data-w="<?= e(number_format($percent, 1, '.', '')) ?>"></span>
            </div>
            <div class="raised-row">
              <b><?= e(money($raised, $currency)) ?></b>
              <span>raised across <?= e((string) count($fundraisers)) ?> fundraiser<?= count($fundraisers) === 1 ? '' : 's' ?></span>
            </div>
          <?php else: ?>
            <div class="raised-row">
              <b><?= e(money($raised, $currency)) ?></b>
              <span>raised across <?= e((string) count($fundraisers)) ?> fundraiser<?= count($fundraisers) === 1 ? '' : 's' ?></span>
            </div>
          <?php endif; ?>

          <div class="donation-btns">
            <a class="btn btn-brand btn-block" href="<?= e(base_url('donate/' . (string) $campaign['slug'] . '?type=campaign')) ?>">Donate to this appeal</a>
            <a class="btn btn-outline-brand btn-block" href="<?= e(base_url('fundraisers')) ?>">See all fundraisers</a>
          </div>

          <p class="text-muted" style="font-size:13.5px;margin:12px 0 0">
            Prefer to support someone you know? Browse the fundraisers above and donate to a personal page instead.
          </p>
        </div>
      </aside>
    </div>
  </div>
</section>
