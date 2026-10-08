<?php
/**
 * Fundraiser card. Markup matches the design system's .f-card component so the
 * existing CSS and JS behaviour applies unchanged.
 *
 * @var array $f   fundraiser row (id, title, slug, goal_minor, raised_minor, currency, category_name, cover_image_path, impact_statement)
 * @var bool  $showProgress
 */

$showProgress = $showProgress ?? true;

$raised = (int) ($f['raised_minor'] ?? 0);
$goal = (int) ($f['goal_minor'] ?? 0);
$currency = (string) ($f['currency'] ?? 'PKR');
$percent = $goal > 0 ? min(100.0, round($raised / $goal * 100, 1)) : 0.0;
$image = !empty($f['cover_image_path']) ? base_url((string) $f['cover_image_path']) : asset('assets/img/insp-meals.jpg');
$tag = (string) ($f['category_name'] ?? 'Fundraiser');
$url = base_url('fundraisers/' . (string) ($f['slug'] ?? $f['id']));
$impact = trim((string) ($f['impact_statement'] ?? ''));
if ($impact === '') {
    $impact = trim(mb_substr(strip_tags((string) ($f['story'] ?? '')), 0, 70));
}
?>
<article class="f-card reveal">
  <a class="f-media" href="<?= e($url) ?>" aria-label="<?= e((string) $f['title']) ?>">
    <span class="f-tag"><?= e($tag) ?></span>
    <img src="<?= e($image) ?>" alt="" loading="lazy" width="400" height="260">
  </a>
  <div class="f-body">
    <h4><a href="<?= e($url) ?>"><?= e((string) $f['title']) ?></a></h4>
    <?php if ($showProgress): ?>
      <div class="f-progress">
        <div class="progress" role="progressbar" aria-valuenow="<?= e((string) (int) $percent) ?>" aria-valuemin="0" aria-valuemax="100">
          <span data-w="<?= e(number_format($percent, 1, '.', '')) ?>"></span>
        </div>
        <div class="f-nums">
          <span><b><?= e(money_short($raised, $currency)) ?></b> raised</span>
          <span><?= e((string) (int) round($percent)) ?>%</span>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($impact !== ''): ?>
      <div class="f-impact">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21.2l7.7-7.8 1.1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        <span><?= e($impact) ?></span>
      </div>
    <?php endif; ?>
  </div>
</article>
