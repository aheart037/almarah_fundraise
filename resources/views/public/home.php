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

$hero = is_array($hero ?? null) ? $hero : [];
$impact = is_array($impact ?? null) ? $impact : [];
$trust = is_array($trust ?? null) ? $trust : [];
$featured = is_array($featured ?? null) ? $featured : [];
$topFundraisers = is_array($topFundraisers ?? null) ? $topFundraisers : [];
$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
$topTeams = is_array($topTeams ?? null) ? $topTeams : [];
$endingSoon = is_array($endingSoon ?? null) ? $endingSoon : [];
$stats = is_array($stats ?? null) ? $stats : [];

$heading = (string) ($hero['heading'] ?? 'Fundraise for a child’s Apna Ghar');
$emphasis = (string) ($hero['heading_em'] ?? 'Apna Ghar');
if ($emphasis !== '' && str_contains($heading, $emphasis)) {
    $parts = explode($emphasis, $heading, 2);
    $headingHtml = e($parts[0]) . '<em>' . e($emphasis) . '</em>' . e($parts[1] ?? '');
} else {
    $headingHtml = e($heading);
}

$heroImage = trim((string) ($hero['image'] ?? 'assets/img/hero.jpg'));
if ($heroImage === '') {
    $heroImage = 'assets/img/hero.jpg';
}

$raisedMinor = (int) ($stats['raised_minor'] ?? 0);
$donationCount = (int) ($stats['donation_count'] ?? 0);
$donorCount = (int) ($stats['donor_count'] ?? 0);
$fundraiserCount = (int) ($stats['fundraiser_count'] ?? 0);
$trustPoints = array_values(array_filter(
    array_map('trim', explode('|', (string) ($trust['points'] ?? ''))),
    static fn (string $point): bool => $point !== ''
));
?>

<div class="home-page">
  <!-- The dark, image-led hero keeps the fixed global navigation legible. -->
  <section class="home-hero" aria-labelledby="home-title">
    <div class="home-hero__glow" aria-hidden="true"></div>
    <div class="wrap home-hero__layout">
      <div class="home-hero__content">
        <p class="home-kicker home-hero__kicker"><span aria-hidden="true"></span><?= e((string) ($hero['eyebrow'] ?? 'Transform Lives Through Care')) ?></p>
        <h1 class="home-hero__title" id="home-title"><?= $headingHtml ?></h1>
        <p class="home-hero__copy"><?= e((string) ($hero['subheading'] ?? 'Create a fundraiser and bring your community together to give children a safe home, a full plate and a place in school.')) ?></p>

        <?php if (trim((string) ($hero['note'] ?? '')) !== ''): ?>
          <p class="home-hero__note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a7 7 0 0 0-4.2 12.6c.8.6 1.2 1.3 1.2 2.2h6c0-.9.4-1.6 1.2-2.2A7 7 0 0 0 12 3Z"/><path d="M9.5 21h5M10 18h4"/></svg>
            <span><?= e((string) $hero['note']) ?></span>
          </p>
        <?php endif; ?>

        <div class="home-hero__actions">
          <?php if (can_fundraiser_capability('manage_pages')): ?>
            <a class="btn btn-gold btn-lg" href="<?= e(base_url('register')) ?>">
              <?= e((string) ($hero['primary_cta_label'] ?? 'Start Your Fundraiser')) ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
          <?php endif; ?>
          <a class="btn btn-outline-light btn-lg" href="<?= e(base_url('fundraisers')) ?>"><?= e((string) ($hero['secondary_cta_label'] ?? 'Support a Fundraiser')) ?></a>
        </div>

        <div class="home-hero__proof" aria-label="Fundraising community impact">
          <div class="home-hero__proof-item">
            <strong><?= e(number_format($fundraiserCount)) ?></strong>
            <span><?= $fundraiserCount === 1 ? 'live fundraiser' : 'live fundraisers' ?></span>
          </div>
          <div class="home-hero__proof-item">
            <strong><?= e(number_format($donorCount)) ?></strong>
            <span><?= $donorCount === 1 ? 'supporter' : 'supporters' ?></span>
          </div>
          <div class="home-hero__proof-item">
            <strong><?= e(money_short($raisedMinor)) ?></strong>
            <span>raised so far</span>
          </div>
        </div>
      </div>

      <div class="home-hero__art">
        <div class="home-hero__photo">
          <img src="<?= e(asset($heroImage)) ?>" alt="Children reading and spending time together in an Almarah care home" fetchpriority="high" decoding="async">
          <div class="home-hero__photo-shade" aria-hidden="true"></div>
          <div class="home-hero__photo-caption">
            <span class="home-hero__caption-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/><path d="M9 12h6"/></svg>
            </span>
            <span><small>Rooted in care</small><strong>A place to grow</strong></span>
          </div>
        </div>
        <div class="home-hero__float-card">
          <span class="home-hero__float-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>
          </span>
          <span><strong>Every gift matters</strong><small>Care, learning and nourishment</small></span>
        </div>
      </div>
    </div>
  </section>

  <?php if ($impact !== []): ?>
    <!-- A compact, measurable proof point immediately follows the hero. -->
    <section class="home-impact" aria-labelledby="home-impact-title">
      <div class="wrap">
        <div class="home-impact__card">
          <div class="home-impact__heading">
            <span class="home-kicker">The difference we make</span>
            <h2 id="home-impact-title">Care that changes what comes next.</h2>
            <p>Every number represents a child or family supported with dignity.</p>
          </div>
          <div class="home-impact__grid">
            <?php foreach ($impact as $item): ?>
              <?php
                $raw = (string) ($item['value'] ?? '0');
                preg_match('/[0-9,.]+/', $raw, $m);
                $number = (int) str_replace([',', '.'], '', $m[0] ?? '0');
                $suffix = trim(str_replace($m[0] ?? '', '', $raw));
              ?>
              <div class="home-impact__item">
                <strong><span data-count="<?= e((string) $number) ?>" data-suffix="<?= e($suffix) ?>"><?= e($raw) ?></span></strong>
                <span><?= e((string) ($item['label'] ?? '')) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ============ HOW IT WORKS ============ -->
  <section class="home-section home-how" id="how" aria-labelledby="home-how-title">
    <div class="wrap">
      <div class="home-section-heading home-section-heading--center">
        <span class="home-kicker">A simple way to make a difference</span>
        <h2 id="home-how-title">Your idea can become real support.</h2>
        <p>You do not need to be a professional fundraiser. Start with a reason, then invite the people who care about the same things you do.</p>
      </div>
      <ol class="home-steps">
        <li class="home-step">
          <span class="home-step__number">01</span>
          <span class="home-step__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>
          </span>
          <h3>Create your page</h3>
          <p>Tell your story, choose a goal and add a photo. Our team reviews each page before it goes live.</p>
        </li>
        <li class="home-step">
          <span class="home-step__number">02</span>
          <span class="home-step__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.7 10.7 6.6-4.4M8.7 13.3l6.6 4.4"/></svg>
          </span>
          <h3>Bring people together</h3>
          <p>Share your page by WhatsApp, email or social media and make it easy for others to join in.</p>
        </li>
        <li class="home-step">
          <span class="home-step__number">03</span>
          <span class="home-step__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>
          </span>
          <h3>Help a child thrive</h3>
          <p>Your fundraiser supports Almarah&rsquo;s programmes, while updates help supporters see the difference they made.</p>
        </li>
      </ol>
    </div>
  </section>

  <!-- ============ FEATURED FUNDRAISERS ============ -->
  <?php if ($featured !== []): ?>
    <section class="home-section home-collection home-collection--soft" aria-labelledby="home-featured-title">
      <div class="wrap">
        <div class="home-section-heading">
          <div>
            <span class="home-kicker">Stories worth sharing</span>
            <h2 id="home-featured-title">Featured fundraisers</h2>
            <p>Meet the people turning a personal idea into a brighter future for children.</p>
          </div>
          <a class="home-inline-link" href="<?= e(base_url('fundraisers')) ?>">Explore all fundraisers <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="card-grid cols-3 home-card-grid">
          <?php foreach ($featured as $f): ?>
            <?php $showProgress = true; require __DIR__ . '/../partials/fundraiser-card.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ============ TOP FUNDRAISERS ============ -->
  <?php if ($topFundraisers !== []): ?>
    <section class="home-section home-collection" aria-labelledby="home-top-fundraisers-title">
      <div class="wrap">
        <div class="home-section-heading">
          <div>
            <span class="home-kicker">Community-powered</span>
            <h2 id="home-top-fundraisers-title">Making the biggest impact</h2>
            <p>See what is possible when a good idea finds its people.</p>
          </div>
          <a class="home-inline-link" href="<?= e(base_url('fundraisers')) ?>">See every fundraiser <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="card-grid cols-3 home-card-grid">
          <?php foreach ($topFundraisers as $f): ?>
            <?php $showProgress = true; require __DIR__ . '/../partials/fundraiser-card.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ============ CAMPAIGNS ============ -->
  <?php if ($campaigns !== []): ?>
    <section class="home-section home-campaigns" aria-labelledby="home-campaigns-title">
      <div class="wrap">
        <div class="home-section-heading home-section-heading--center">
          <span class="home-kicker">Choose what matters to you</span>
          <h2 id="home-campaigns-title">A cause for every kind of hope.</h2>
          <p>Explore Almarah&rsquo;s programmes and find the appeal you would most like to support.</p>
        </div>
        <div class="home-campaign-grid">
          <?php foreach ($campaigns as $campaign): ?>
            <?php
              $campaignUrl = base_url('campaigns/' . (string) ($campaign['slug'] ?? ''));
              $campaignImage = !empty($campaign['image_path'])
                  ? base_url((string) $campaign['image_path'])
                  : asset('assets/img/insp-school.jpg');
              $campaignDescription = trim(mb_substr(strip_tags((string) ($campaign['description'] ?? '')), 0, 140));
            ?>
            <article class="home-campaign-card">
              <a class="home-campaign-card__image" href="<?= e($campaignUrl) ?>" aria-label="Explore <?= e((string) ($campaign['title'] ?? 'this appeal')) ?>">
                <img src="<?= e($campaignImage) ?>" alt="" loading="lazy" width="480" height="320">
                <span>Almarah appeal</span>
              </a>
              <div class="home-campaign-card__body">
                <h3><a href="<?= e($campaignUrl) ?>"><?= e((string) ($campaign['title'] ?? '')) ?></a></h3>
                <?php if ($campaignDescription !== ''): ?>
                  <p><?= e($campaignDescription) ?><?= mb_strlen(strip_tags((string) ($campaign['description'] ?? ''))) > 140 ? '&hellip;' : '' ?></p>
                <?php endif; ?>
                <a class="home-inline-link" href="<?= e($campaignUrl) ?>">Explore this appeal <span aria-hidden="true">&rarr;</span></a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ============ TOP TEAMS ============ -->
  <?php if ($topTeams !== []): ?>
    <section class="home-section home-collection home-collection--soft" aria-labelledby="home-teams-title">
      <div class="wrap">
        <div class="home-section-heading">
          <div>
            <span class="home-kicker">Better, together</span>
            <h2 id="home-teams-title">Meet the teams leading the way.</h2>
            <p>Join a team, cheer them on, or bring your own people together for a cause.</p>
          </div>
          <a class="home-inline-link" href="<?= e(base_url('teams')) ?>">Discover all teams <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="card-grid cols-3 home-card-grid">
          <?php foreach ($topTeams as $team): ?>
            <?php require __DIR__ . '/../partials/team-card.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ============ ENDING SOON ============ -->
  <?php if ($endingSoon !== []): ?>
    <section class="home-section home-collection home-collection--urgent" aria-labelledby="home-ending-title">
      <div class="wrap">
        <div class="home-section-heading">
          <div>
            <span class="home-kicker">A little time left</span>
            <h2 id="home-ending-title">Help a fundraiser finish strong.</h2>
            <p>These campaigns are nearly at their end date. A share or a gift can make today count.</p>
          </div>
          <a class="home-inline-link" href="<?= e(base_url('fundraisers')) ?>">Browse fundraisers <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="card-grid cols-3 home-card-grid">
          <?php foreach ($endingSoon as $f): ?>
            <?php $showProgress = true; require __DIR__ . '/../partials/fundraiser-card.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ============ ABOUT ALMARAH ============ -->
  <section class="home-section home-about" aria-labelledby="home-about-title">
    <div class="wrap home-about__layout">
      <div class="home-about__copy">
        <span class="home-kicker">The heart behind the work</span>
        <h2 id="home-about-title">Every child deserves a place to call <em>Apna Ghar.</em></h2>
        <p class="home-about__lead">Almarah Foundation believes a child&rsquo;s beginning should not determine their end. Since June 2021, we have provided safe homes, daily meals, free schooling and dignified support to families who need it most.</p>
        <ul class="home-about__list">
          <li>
            <span class="home-about__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg></span>
            <span><strong>Safe homes</strong><small>13 care homes offering children a family, a routine and room to grow.</small></span>
          </li>
          <li>
            <span class="home-about__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg></span>
            <span><strong>Free schooling</strong><small>Education, books and uniforms, so every child has a chance to learn.</small></span>
          </li>
          <li>
            <span class="home-about__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h18a9 9 0 0 1-18 0Z"/><path d="M12 3v5M8 4v4M16 4v4"/></svg></span>
            <span><strong>Daily meals</strong><small>More than 1,500 meals a day, plus ration packs for families facing a hard month.</small></span>
          </li>
        </ul>
        <a class="home-inline-link home-about__link" href="<?= e(base_url('about-us')) ?>">Get to know our work <span aria-hidden="true">&rarr;</span></a>
      </div>
      <div class="home-about__visual">
        <div class="home-about__photo">
          <img src="<?= e(asset('assets/img/fund-family.jpg')) ?>" alt="Children spending time together in their community in Pakistan" loading="lazy" width="680" height="760">
        </div>
        <div class="home-about__photo-note">
          <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11Z"/></svg></span>
          <span><small>Growing with dignity</small><strong>Care for today. Hope for tomorrow.</strong></span>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ FUNDRAISER CALL TO ACTION ============ -->
  <section class="home-cta" aria-labelledby="home-cta-title">
    <div class="wrap">
      <div class="home-cta__panel">
        <div class="home-cta__copy">
          <span class="home-kicker">Make your next moment matter</span>
          <h2 id="home-cta-title">Celebrate, run, gather, give.</h2>
          <p>Turn a birthday, marathon, iftar drive or a simple idea into practical support for children and families.</p>
        </div>
        <div class="home-cta__actions">
          <?php if (can_fundraiser_capability('manage_pages')): ?>
            <a class="btn btn-gold btn-lg" href="<?= e(base_url('register')) ?>">Start your fundraiser <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          <?php endif; ?>
          <a class="home-cta__secondary" href="<?= e(base_url('fundraisers')) ?>">Or support an existing fundraiser</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ TRUST & ACCOUNTABILITY ============ -->
  <section class="home-section home-trust" aria-labelledby="home-trust-title">
    <div class="wrap">
      <div class="home-trust__panel">
        <div class="home-trust__main">
          <span class="home-trust__symbol" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg>
          </span>
          <span class="home-kicker">Give with confidence</span>
          <h2 id="home-trust-title"><?= e((string) ($trust['heading'] ?? 'Your donation is safe and accountable')) ?></h2>
          <p><?= e((string) ($trust['body'] ?? 'Donations are handled securely and recorded so supporters can follow the impact of their gift.')) ?></p>
          <a class="home-inline-link" href="<?= e(base_url('about-us')) ?>">Learn how we work <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="home-trust__details">
          <h3>Clear from payment to impact</h3>
          <ul>
            <?php foreach ($trustPoints as $point): ?>
              <li>
                <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span>
                <p><?= e($point) ?></p>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="home-trust__record"><strong><?= e(number_format($donationCount)) ?></strong><span>donations completed on this platform to date</span></div>
        </div>
      </div>
    </div>
  </section>
</div>
