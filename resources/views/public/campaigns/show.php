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
$count = count($fundraisers);

$donateUrl = base_url('donate/' . (string) $campaign['slug'] . '?type=campaign');
$heroImage = !empty($campaign['image_path']) ? base_url((string) $campaign['image_path']) : asset('assets/img/hero.jpg');

// Time left, from the campaign's own end date
$startAt = !empty($campaign['start_at']) ? (string) $campaign['start_at'] : '';
$endAt   = !empty($campaign['end_at']) ? (string) $campaign['end_at'] : '';
$endTs   = $endAt !== '' ? strtotime($endAt) : false;
$daysLeft = $endTs ? (int) ceil(($endTs - time()) / 86400) : null;
if ($endTs === false || $endTs === null) {
    $timeValue = 'Ongoing';
    $timeLabel = 'Open appeal';
} elseif ($daysLeft > 0) {
    $timeValue = number_format($daysLeft);
    $timeLabel = $daysLeft === 1 ? 'Day left' : 'Days left';
} else {
    $timeValue = 'Ended';
    $timeLabel = dt($endAt, 'j M Y');
}
?>
<div class="cmp-page">

  <!-- ================= Hero ================= -->
  <section class="cmp-hero">
    <div class="cmp-hero-bg" style="background-image:url('<?= e($heroImage) ?>')"></div>
    <div class="wrap">
      <nav class="cmp-crumbs" aria-label="Breadcrumb">
        <a href="<?= e(base_url('campaigns')) ?>">Campaigns</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= e((string) $campaign['title']) ?></span>
      </nav>

      <div class="hero-caption cmp-caption">
        <span class="pill">Appeal campaign</span>
        <h1><?= e((string) $campaign['title']) ?></h1>
        <?php if ($startAt !== '' || $endAt !== ''): ?>
          <p class="cmp-dates">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></svg>
            <?php if ($startAt !== ''): ?><?= e(dt($startAt, 'j M Y')) ?><?php endif; ?><?php if ($endAt !== ''): ?><?= $startAt !== '' ? ' &ndash; ' : 'Until ' ?><?= e(dt($endAt, 'j M Y')) ?><?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ================= Impact strip ================= -->
  <div class="wrap">
    <ul class="cmp-stats" aria-label="Appeal at a glance">
      <li><strong><?= e(money($raised, $currency)) ?></strong><span>Raised so far</span></li>
      <li><strong><?= e(number_format($count)) ?></strong><span>Fundrais<?= $count === 1 ? 'er' : 'ers' ?> supporting</span></li>
      <li>
        <?php if ($goal > 0): ?>
          <strong><?= e(rtrim(rtrim(number_format($percent, 1), '0'), '.')) ?>%</strong><span>Of combined goal</span>
        <?php else: ?>
          <strong>Open</strong><span>No fixed goal</span>
        <?php endif; ?>
      </li>
      <li><strong><?= e($timeValue) ?></strong><span><?= e($timeLabel) ?></span></li>
    </ul>
  </div>

  <!-- ================= Body ================= -->
  <section class="section cmp-body">
    <div class="wrap">
      <div class="fr-layout">
        <div class="cmp-main">

          <article class="prose reveal cmp-about">
            <span class="cmp-eyebrow">The appeal</span>
            <h2 class="cmp-h">About this <em>appeal</em></h2>
            <p><?= nl2br(e((string) ($campaign['description'] ?? ''))) ?></p>
          </article>

          <section class="panel mt-3 reveal cmp-fundraisers" aria-labelledby="cmp-fr-title">
            <span class="cmp-eyebrow">Community</span>
            <h2 class="cmp-h" id="cmp-fr-title">Fundraisers <em>supporting</em> this appeal</h2>
            <p class="panel-sub">Every one of these pages feeds into the same programme.</p>

            <?php if ($fundraisers !== []): ?>
              <div class="card-grid cols-3">
                <?php foreach ($fundraisers as $f): ?>
                  <?php $showProgress = true; require __DIR__ . '/../../partials/fundraiser-card.php'; ?>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="cmp-empty">
                <h3>Be the first to fundraise for this appeal</h3>
                <p>No personal fundraiser pages have been started yet. Create yours in a few minutes and ask friends and family to help.</p>
                <a class="btn btn-brand" href="<?= e(base_url('dashboard/fundraisers/create')) ?>">Start a fundraiser</a>
              </div>
            <?php endif; ?>
          </section>

        </div>

        <!-- ===== Donate card (kept as the sidebar) ===== -->
        <aside>
          <div class="donation-box preview-sticky">
            <?php if ($goal > 0): ?>
              <div class="progress lg" role="progressbar" aria-valuenow="<?= e((string) (int) $percent) ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Funding progress">
                <span data-w="<?= e(number_format($percent, 1, '.', '')) ?>" style="width:<?= e(number_format($percent, 1, '.', '')) ?>%"></span>
              </div>
              <div class="raised-row">
                <b><?= e(money($raised, $currency)) ?></b>
                <span>raised across <?= e((string) $count) ?> fundraiser<?= $count === 1 ? '' : 's' ?></span>
              </div>
            <?php else: ?>
              <div class="raised-row">
                <b><?= e(money($raised, $currency)) ?></b>
                <span>raised across <?= e((string) $count) ?> fundraiser<?= $count === 1 ? '' : 's' ?></span>
              </div>
            <?php endif; ?>

            <div class="donation-btns">
              <a class="btn btn-brand btn-block" href="<?= e($donateUrl) ?>">Donate to this appeal</a>
              <a class="btn btn-outline-brand btn-block" href="<?= e(base_url('fundraisers')) ?>">See all fundraisers</a>
            </div>

            <p class="text-muted" style="font-size:13.5px;margin:12px 0 0">
              Prefer to support someone you know? Browse the fundraisers and donate to a personal page instead.
            </p>

            <ul class="cmp-trust">
              <li>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></svg>
                Secure payment on bank-hosted pages
              </li>
              <li>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
                Totals count confirmed donations only
              </li>
            </ul>
            <button class="cmp-share" type="button" data-cmp-copy>
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 10.5l6.8-4M8.6 13.5l6.8 4"/></svg>
              <span>Copy link to share</span>
            </button>
          </div>
        </aside>
      </div>
    </div>
  </section>

  <!-- ================= Closing call to action ================= -->
  <section class="cmp-cta" aria-labelledby="cmp-cta-title">
    <div class="wrap">
      <div class="cmp-cta-card">
        <div>
          <span class="cmp-eyebrow cmp-eyebrow--light">Get involved</span>
          <h2 id="cmp-cta-title">Together, we can do <em>more</em></h2>
          <p>Give directly to this appeal, or start your own page and bring your community with you. Every contribution fuels a child's future.</p>
        </div>
        <div class="cmp-cta-actions">
          <a class="btn btn-gold" href="<?= e($donateUrl) ?>">Donate now</a>
          <a class="cmp-cta-link" href="<?= e(base_url('dashboard/fundraisers/create')) ?>">Start a fundraiser</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= Phone: sticky donate bar ================= -->
  <div class="cmp-mbar">
    <div class="cmp-mbar-info">
      <strong><?= e(money($raised, $currency)) ?></strong>
      <span><?= $goal > 0 ? e(rtrim(rtrim(number_format($percent, 1), '0'), '.')) . '% funded' : 'raised so far' ?></span>
    </div>
    <a class="btn btn-brand" href="<?= e($donateUrl) ?>">Donate</a>
  </div>
</div>

<style>
/* Scoped to .cmp-page. Your theme's buttons, panel, prose, pill, card-grid and the donate card keep their own styling. */
.cmp-page{--c-pink:#af2f67;--c-deep:#6b1038;--c-gold:#ffda00;--c-ink:#2a1020;--c-mute:#7a4560;--c-line:#efdde6;--c-soft:#fceef4}

/* Hero */
.cmp-hero{position:relative;isolation:isolate;display:flex;align-items:flex-end;min-height:clamp(360px,54vh,560px);padding:clamp(28px,5vw,48px) 0 clamp(72px,9vw,96px);color:#fff;overflow:hidden}
.cmp-hero-bg{position:absolute;inset:0;z-index:-2;background-color:#6b1038;background-size:cover;background-position:center 30%;background-repeat:no-repeat}
/* Light at the top so the featured image is clearly visible; darkens only behind the title for readable text. */
.cmp-hero::before{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(42,16,32,.10) 0%,rgba(42,16,32,.18) 38%,rgba(42,16,32,.55) 68%,rgba(86,14,46,.88) 100%)}
.cmp-hero::after{content:"\0627";position:absolute;right:2%;bottom:-.3em;z-index:-1;font:700 clamp(12rem,24vw,22rem)/1 Amiri,"Times New Roman",serif;color:var(--c-gold);opacity:.1;pointer-events:none;user-select:none}
.cmp-hero .wrap{width:100%}
.cmp-crumbs{text-shadow:0 1px 8px rgba(0,0,0,.5);display:flex;flex-wrap:wrap;gap:8px;margin-bottom:clamp(20px,4vw,40px);font-size:.85rem;opacity:.9}
.cmp-crumbs a{color:#fff;text-decoration:underline;text-underline-offset:3px}
.cmp-crumbs span[aria-current]{max-width:46ch;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cmp-caption{max-width:820px}
.cmp-caption h1{margin:12px 0 14px;color:#fff;text-shadow:0 2px 18px rgba(0,0,0,.45);font:700 clamp(2.1rem,5.2vw,3.6rem)/1.08 Amiri,Georgia,serif;letter-spacing:-.01em}
.cmp-dates{display:inline-flex;align-items:center;gap:8px;margin:0;padding:7px 14px;border-radius:999px;background:rgba(255,255,255,.14);font-size:.9rem;font-weight:600}
.cmp-dates svg{flex:0 0 auto}

/* Impact strip, overlapping the hero like the counters on almarah.org */
.cmp-stats{position:relative;z-index:2;display:grid;grid-template-columns:repeat(4,1fr);gap:0;margin:calc(-1 * clamp(44px,6vw,60px)) 0 0;padding:0;list-style:none;border-radius:20px;background:#fff;box-shadow:0 24px 48px -28px rgba(107,16,56,.45),0 2px 8px rgba(15,23,42,.05)}
.cmp-stats li{padding:clamp(16px,2.4vw,26px) clamp(14px,2vw,24px);text-align:center;border-left:1px solid var(--c-line)}
.cmp-stats li:first-child{border-left:0}
.cmp-stats strong{display:block;font:700 clamp(1.4rem,2.8vw,2.1rem)/1.1 Amiri,Georgia,serif;color:var(--c-pink);overflow-wrap:anywhere}
.cmp-stats span{display:block;margin-top:6px;font-size:.78rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--c-mute)}

/* Body */
.cmp-body{padding-top:clamp(32px,5vw,56px)}
.cmp-eyebrow{display:inline-block;margin-bottom:10px;padding:5px 14px;border-radius:999px;background:var(--c-soft);color:var(--c-pink);font-size:.76rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
.cmp-eyebrow--light{background:rgba(255,255,255,.14);color:var(--c-gold)}
.cmp-h{margin:0 0 14px;font:700 clamp(1.7rem,3.2vw,2.5rem)/1.15 Amiri,Georgia,serif;color:var(--c-deep);letter-spacing:-.01em}
.cmp-h em,.cmp-cta h2 em{font-style:italic;color:var(--c-pink)}
.cmp-about p{font-size:1.05rem;line-height:1.85}
.cmp-fundraisers .panel-sub{margin:0 0 22px}

.cmp-empty{padding:clamp(24px,5vw,44px) 20px;border:1.5px dashed #e4bfd0;border-radius:18px;background:#fdf6fa;text-align:center}
.cmp-empty h3{margin:0 0 8px;color:var(--c-deep)}
.cmp-empty p{max-width:48ch;margin:0 auto 18px;color:var(--c-mute);line-height:1.65}

/* Additions inside the donate card */
.cmp-trust{display:grid;gap:8px;margin:16px 0 0;padding:16px 0 0;list-style:none;border-top:1px solid var(--c-line);font-size:.85rem;color:var(--c-mute)}
.cmp-trust li{display:flex;align-items:center;gap:10px}
.cmp-trust svg{flex:0 0 auto;color:var(--c-pink)}
.cmp-share{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;margin-top:14px;padding:10px 14px;border:1.5px dashed #e4bfd0;border-radius:12px;background:transparent;color:var(--c-pink);font:700 .88rem inherit;font-family:inherit;cursor:pointer;transition:background .15s ease}
.cmp-share:hover{background:var(--c-soft)}
.cmp-share:focus-visible{outline:3px solid var(--c-gold);outline-offset:2px}

/* Closing call to action */
.cmp-cta{padding:0 0 clamp(40px,6vw,72px)}
.cmp-cta-card{position:relative;isolation:isolate;display:flex;align-items:center;justify-content:space-between;gap:28px;padding:clamp(28px,5vw,56px);border-radius:24px;overflow:hidden;color:#fff;background:linear-gradient(150deg,var(--c-deep) 0%,#8a2050 50%,var(--c-pink) 100%)}
.cmp-cta-card::before{content:"\0627";position:absolute;right:-.05em;bottom:-.34em;z-index:-1;font:700 clamp(12rem,22vw,20rem)/1 Amiri,"Times New Roman",serif;color:var(--c-gold);opacity:.12;pointer-events:none}
.cmp-cta h2{margin:0 0 10px;color:#fff;font:700 clamp(1.8rem,3.4vw,2.6rem)/1.12 Amiri,Georgia,serif}
.cmp-cta h2 em{color:var(--c-gold)}
.cmp-cta p{max-width:52ch;margin:0;line-height:1.7;opacity:.92}
.cmp-cta-actions{display:flex;flex-direction:column;align-items:center;gap:14px;flex:0 0 auto}
.cmp-cta-link{color:#fff;font-weight:700;text-decoration:underline;text-underline-offset:4px}
.cmp-cta-link:focus-visible{outline:3px solid var(--c-gold);outline-offset:3px}

/* Phone donate bar */
.cmp-mbar{display:none}

@media (max-width:900px){
  .cmp-stats{grid-template-columns:repeat(2,1fr)}
  .cmp-stats li:nth-child(3){border-left:0}
  .cmp-stats li:nth-child(n+3){border-top:1px solid var(--c-line)}
  .cmp-cta-card{flex-direction:column;align-items:flex-start}
  .cmp-cta-actions{flex-direction:row;flex-wrap:wrap;align-items:center}
  .cmp-page{padding-bottom:calc(76px + env(safe-area-inset-bottom,0px))}
  .cmp-mbar{position:fixed;left:0;right:0;bottom:0;z-index:90;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:10px clamp(14px,4vw,24px) calc(10px + env(safe-area-inset-bottom,0px));background:#fff;border-top:1px solid var(--c-line);box-shadow:0 -10px 28px -18px rgba(107,16,56,.4)}
  .cmp-mbar-info strong{display:block;font:700 1.2rem/1.1 Amiri,Georgia,serif;color:var(--c-pink)}
  .cmp-mbar-info span{font-size:.76rem;color:var(--c-mute)}
  .cmp-mbar .btn{flex:0 0 auto;padding-left:28px;padding-right:28px}
}
@media (max-width:480px){.cmp-hero::after{font-size:10rem}}
@media (prefers-reduced-motion:reduce){.cmp-share{transition:none}}
</style>

<script>
(function () {
  // Copy-link button in the donate card
  document.querySelectorAll('[data-cmp-copy]').forEach(function (btn) {
    var label = btn.querySelector('span'), original = label.textContent;
    btn.addEventListener('click', function () {
      var url = location.href.split('#')[0];
      function done() { label.textContent = 'Link copied'; setTimeout(function () { label.textContent = original; }, 2000); }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copy this link:', url); });
      } else {
        window.prompt('Copy this link:', url);
      }
    });
  });
})();
</script>
