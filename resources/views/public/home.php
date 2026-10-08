<?php
/**
 * Landing page.
 *
 * @var array $hero
 * @var array $impact
 * @var array $trust
 * @var array $featured
 * @var array $topFundraisers
 * @var array $campaigns
 * @var array $topTeams
 * @var array $categories
 * @var array $stats
 * @var array $endingSoon
 */

$heading = (string) ($hero['heading'] ?? '');
$emphasis = (string) ($hero['heading_em'] ?? '');
if ($emphasis !== '' && str_contains($heading, $emphasis)) {
    $parts = explode($emphasis, $heading, 2);
    $headingHtml = e($parts[0]) . '<em>' . e($emphasis) . '</em>' . e($parts[1] ?? '');
} else {
    $headingHtml = e($heading);
}

$raisedMinor = (int) ($stats['raised_minor'] ?? 0);
$donationCount = (int) ($stats['donation_count'] ?? 0);
$donorCount = (int) ($stats['donor_count'] ?? 0);
$fundraiserCount = (int) ($stats['fundraiser_count'] ?? 0);
?>

<!-- ============ HERO ============ -->
<section class="hero">
  <div class="hero-bg" style="background-image:url('<?= e(asset((string) ($hero['image'] ?? 'assets/img/hero.jpg'))) ?>')"></div>
  <div class="wrap">
    <div class="hero-inner">
      <span class="eyebrow"><?= e((string) ($hero['eyebrow'] ?? 'Transform Lives Through Care')) ?></span>
      <h1 class="display"><?= $headingHtml ?></h1>
      <p class="hero-copy"><?= e((string) ($hero['subheading'] ?? '')) ?></p>
      <p class="hero-note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
        <?= e((string) ($hero['note'] ?? '')) ?>
      </p>
      <div class="hero-actions">
        <a class="btn btn-gold btn-lg" href="<?= e(base_url('register')) ?>"><?= e((string) ($hero['primary_cta_label'] ?? 'Start Your Fundraiser')) ?></a>
        <a class="btn btn-outline-light btn-lg" href="<?= e(base_url('fundraisers')) ?>"><?= e((string) ($hero['secondary_cta_label'] ?? 'Support a Fundraiser')) ?></a>
      </div>
      <p class="hero-note" style="margin-top:14px">
        <span><?= e(number_format($fundraiserCount)) ?> live fundraisers &middot; <?= e(number_format($donorCount)) ?> donors &middot; <?= e(money_short($raisedMinor)) ?> raised so far</span>
      </p>
    </div>
  </div>
  <a class="scroll-cue" href="#how" aria-label="Scroll down"><i></i></a>
</section>

<!-- ============ STAT BAR ============ -->
<section class="stat-bar">
  <div class="wrap">
    <?php foreach ($impact as $item): ?>
      <?php
        $raw = (string) ($item['value'] ?? '0');
        preg_match('/[0-9,.]+/', $raw, $m);
        $number = (int) str_replace([',', '.'], '', $m[0] ?? '0');
        $suffix = trim(str_replace($m[0] ?? '', '', $raw));
      ?>
      <div class="stat">
        <b><span data-count="<?= e((string) $number) ?>" data-suffix="<?= e($suffix) ?>">0</span></b>
        <span><?= e((string) ($item['label'] ?? '')) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ============ HOW IT WORKS ============ -->
<section class="section" id="how">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow center">Three Simple Steps</span>
      <h2 class="section-title">Starting a fundraiser is easy</h2>
      <div class="rule center"></div>
      <p class="section-sub">You do not need to be a professional fundraiser. You need a reason, a page, and people who care about the same thing you do.</p>
    </div>
    <div class="steps">
      <div class="step reveal">
        <div class="step-badge">1</div>
        <div class="step-copy">
          <h3>Create your page</h3>
          <p>Tell your story, set a goal and add a photo. It takes about five minutes and our team reviews every page before it goes live.</p>
        </div>
      </div>
      <div class="step reveal">
        <div class="step-badge">2</div>
        <div class="step-copy">
          <h3>Tell your people</h3>
          <p>Share your page by WhatsApp, email or social media. Every donation is listed instantly with a public reference.</p>
        </div>
      </div>
      <div class="step reveal">
        <div class="step-badge">3</div>
        <div class="step-copy">
          <h3>Change a life</h3>
          <p>Funds reach the programme you chose. You can post updates so your supporters see exactly what they made possible.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ FEATURED ============ -->
<?php if ($featured !== []): ?>
<section class="section tint">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow center">Get Inspired</span>
      <h2 class="section-title">Featured fundraisers</h2>
      <div class="rule center"></div>
      <p class="section-sub">Real people, real stories. Pick one and help it over the line today.</p>
    </div>
    <div class="card-grid cols-3">
      <?php foreach ($featured as $f): ?>
        <?php $showProgress = true; require __DIR__ . '/../partials/fundraiser-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============ TOP FUNDRAISERS ============ -->
<?php if ($topFundraisers !== []): ?>
<section class="section">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow center">Leading The Way</span>
      <h2 class="section-title">Top fundraisers</h2>
      <div class="rule center"></div>
    </div>
    <div class="card-grid cols-3">
      <?php foreach ($topFundraisers as $f): ?>
        <?php $showProgress = true; require __DIR__ . '/../partials/fundraiser-card.php'; ?>
      <?php endforeach; ?>
    </div>
    <div class="text-right mt-3">
      <a class="link-arrow" href="<?= e(base_url('fundraisers')) ?>">See every fundraiser</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============ CAMPAIGNS ============ -->
<?php if ($campaigns !== []): ?>
<section class="section tight cream">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow center">Appeal Campaigns</span>
      <h2 class="section-title">Where your support goes</h2>
      <div class="rule center"></div>
    </div>
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
          <p><?= e(mb_substr(strip_tags((string) ($campaign['description'] ?? '')), 0, 140)) ?></p>
          <a class="link-arrow" href="<?= e(base_url('campaigns/' . (string) $campaign['slug'])) ?>">Explore this appeal</a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============ TOP TEAMS ============ -->
<?php if ($topTeams !== []): ?>
<section class="section tight">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow center">Together We Do More</span>
      <h2 class="section-title">Top teams</h2>
      <div class="rule center"></div>
    </div>
    <div class="card-grid cols-3">
      <?php foreach ($topTeams as $team): ?>
        <?php require __DIR__ . '/../partials/team-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============ ENDING SOON ============ -->
<?php if ($endingSoon !== []): ?>
<section class="section tint">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow center">Time Is Short</span>
      <h2 class="section-title">Ending soon</h2>
      <div class="rule center"></div>
    </div>
    <div class="card-grid cols-3">
      <?php foreach ($endingSoon as $f): ?>
        <?php $showProgress = true; require __DIR__ . '/../partials/fundraiser-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============ ABOUT SPLIT ============ -->
<section class="section">
  <div class="wrap">
    <div class="split">
      <div class="reveal">
        <span class="eyebrow brand left">About Almarah</span>
        <h2 class="section-title">Every child deserves a place to call &ldquo;Apna Ghar&rdquo;</h2>
        <div class="rule"></div>
        <p class="lead">Almarah Foundation believes a child&rsquo;s beginning should not determine their end. Since June 2021 we have provided safe homes, daily meals, free schooling and dignified support to families who need it most.</p>
        <div class="pillars">
          <div class="pillar">
            <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg></i>
            <div><b>Safe homes</b><span>13 care homes giving vulnerable children a family, a routine and a future.</span></div>
          </div>
          <div class="pillar">
            <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></i>
            <div><b>Free schooling</b><span>Education, books and uniforms so no child is turned away for want of money.</span></div>
          </div>
          <div class="pillar">
            <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h18a9 9 0 0 1-18 0z"/><path d="M12 3v5M8 4v4M16 4v4"/></svg></i>
            <div><b>Daily meals</b><span>Over 1,500 meals a day, plus ration packs for families facing a hard month.</span></div>
          </div>
        </div>
      </div>
      <div class="reveal">
        <div class="quote-band">
          <p>&ldquo;We cannot help everyone, but everyone can help someone. Start a fundraiser and give one child in Pakistan the childhood they deserve.&rdquo;</p>
          <span>&mdash; Almarah Foundation</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ CTA BAND ============ -->
<section class="section tight cta-band">
  <div class="wrap">
    <h2>How will you fundraise for Almarah?</h2>
    <p>Birthday, marathon, iftar drive or a quiet ask to your office &mdash; every rupee becomes a bed, a meal or a school book.</p>
    <div class="cta-actions">
      <a class="btn btn-gold btn-lg" href="<?= e(base_url('register')) ?>">Start Your Fundraiser</a>
      <span class="or">or</span>
      <a class="btn btn-outline-light btn-lg" href="<?= e(base_url('fundraisers')) ?>">Find an existing fundraiser</a>
    </div>
  </div>
</section>

<!-- ============ TRUST ============ -->
<section class="section">
  <div class="wrap">
    <div class="split">
      <div class="reveal">
        <span class="eyebrow brand left">Safety First</span>
        <h2 class="section-title"><?= e((string) ($trust['heading'] ?? 'Your donation is safe and accountable')) ?></h2>
        <div class="rule"></div>
        <p class="lead"><?= e((string) ($trust['body'] ?? '')) ?></p>
      </div>
      <div class="reveal">
        <ul class="checkline">
          <?php foreach (explode('|', (string) ($trust['points'] ?? '')) as $point): ?>
            <?php if (trim($point) === '') { continue; } ?>
            <li>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
              <span><?= e(trim($point)) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="mt-2 text-muted"><?= e(number_format($donationCount)) ?> donations completed on this platform to date.</p>
      </div>
    </div>
  </div>
</section>
