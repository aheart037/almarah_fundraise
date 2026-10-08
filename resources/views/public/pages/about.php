<?php
/**
 * About page — mission, impact and accountability.
 *
 * @var array $hero
 * @var array $impact
 * @var array $trust
 */
?>
<section class="page-banner">
  <div class="wrap">
    <span class="eyebrow">About Almarah</span>
    <h1>Every child deserves a place to call &ldquo;Apna Ghar&rdquo;</h1>
    <p>Almarah Foundation is a Pakistani welfare organisation serving orphaned children, widows and families in need since June 2021.</p>
  </div>
</section>

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

<section class="section">
  <div class="wrap">
    <div class="split">
      <div class="reveal">
        <span class="eyebrow brand left">Our Mission</span>
        <h2 class="section-title">Care that reaches the child, not the paperwork</h2>
        <div class="rule"></div>
        <p class="lead">We provide safe homes, daily meals, free schooling and dignified support to the families who need it most. Because a child&rsquo;s beginning should never determine their end.</p>
        <p>Almarah began with one care home on Canal Road, Lahore. Today thirteen homes across Pakistan give vulnerable children a family, a routine and a future &mdash; and ration packs help families facing a hard month stay together.</p>
      </div>
      <div class="reveal">
        <img src="<?= e(asset('assets/img/hero.jpg')) ?>" alt="Children supported by Almarah Foundation" style="width:100%;border-radius:var(--radius-lg);box-shadow:var(--shadow)" loading="lazy">
      </div>
    </div>
  </div>
</section>

<section class="section tint">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow center">Our Programmes</span>
      <h2 class="section-title">Where your fundraising goes</h2>
      <div class="rule center"></div>
    </div>
    <div class="pillars" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px">
      <div class="pillar">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5V20a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg></i>
        <div><b>Parent the Orphan</b><span>Monthly care for a child: shelter, food, schooling, healthcare and a housemother.</span></div>
      </div>
      <div class="pillar">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h18a9 9 0 0 1-18 0z"/><path d="M12 3v5M8 4v4M16 4v4"/></svg></i>
        <div><b>Feed the Hungry</b><span>Over 1,500 meals a day in our homes, plus ration packs for families in crisis.</span></div>
      </div>
      <div class="pillar">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></i>
        <div><b>Educate Pakistan</b><span>Free schooling, books and uniforms so no child is turned away for want of money.</span></div>
      </div>
      <div class="pillar">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></i>
        <div><b>Izzat ki Roti</b><span>Dignified food support that protects a family&rsquo;s self-respect as well as its table.</span></div>
      </div>
      <div class="pillar">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21.2l7.7-7.8 1.1-1a5.5 5.5 0 0 0 0-7.8z"/></svg></i>
        <div><b>Compassionate Haven</b><span>Special-needs support and a sensory room for children who need a quieter world.</span></div>
      </div>
      <div class="pillar">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l2.5 2.5M16.5 16.5L19 19M19 5l-2.5 2.5M7.5 16.5L5 19"/></svg></i>
        <div><b>Where It Is Needed Most</b><span>Unrestricted giving, applied wherever the need is greatest that month.</span></div>
      </div>
    </div>
  </div>
</section>

<?php
$trustHeading = trim((string) ($trust['heading'] ?? ''));
$trustHeading = $trustHeading !== '' ? $trustHeading : 'Your donation is safe and accountable';
$trustBody = trim((string) ($trust['body'] ?? ''));
$trustBody = $trustBody !== ''
    ? $trustBody
    : 'Card payments are completed on the payment provider’s secure page. We verify each payment before marking it complete and record a reference for follow-up.';
$trustPoints = array_values(array_filter(
    array_map(static fn (string $point): string => trim($point), explode('|', (string) ($trust['points'] ?? ''))),
    static fn (string $point): bool => $point !== ''
));
if ($trustPoints === []) {
    $trustPoints = [
        'Card details never touch our servers.',
        'We verify payments with the provider before counting them.',
        'Every donation has a reference our team can use to help trace it.',
    ];
}
?>
<section class="section about-trust" aria-labelledby="about-trust-title">
  <div class="wrap">
    <div class="about-trust__panel">
      <div class="about-trust__header">
        <div class="about-trust__intro">
          <span class="about-trust__eyebrow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg>
            Payment security &amp; accountability
          </span>
          <h2 id="about-trust-title"><?= e($trustHeading) ?></h2>
          <p><?= e($trustBody) ?></p>
        </div>

        <div class="about-trust__secure-badge">
          <span class="about-trust__secure-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="m9.5 15 1.7 1.7 3.5-3.5"/></svg>
          </span>
          <span><strong>Secure card checkout</strong><small>Your card details stay with the payment provider.</small></span>
        </div>
      </div>

      <div class="about-trust__content">
        <div class="about-trust__label">
          <span>How we protect your donation</span>
          <span>Clear safeguards, from payment to follow-up</span>
        </div>
        <div class="about-trust__grid">
          <?php foreach ($trustPoints as $index => $point): ?>
            <article class="about-trust__card">
              <div class="about-trust__card-top">
                <span class="about-trust__number"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                <span class="about-trust__check" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg>
                </span>
              </div>
              <p><?= e($point) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="about-trust__footer">
        <span class="about-trust__footer-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>
        </span>
        <div class="about-trust__footer-copy">
          <strong>Need help with a donation?</strong>
          <span>Keep your public reference handy so our team can find the transaction.</span>
        </div>
        <a class="btn btn-outline-brand" href="<?= e(base_url('support')) ?>">
          Contact support
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
    </div>
  </div>
</section>

<section class="section tight cta-band">
  <div class="wrap">
    <h2>Turn your occasion into a child&rsquo;s future</h2>
    <p>Start a fundraiser in five minutes and invite the people who already care about what you care about.</p>
    <div class="cta-actions">
      <?php if (can_fundraiser_capability('manage_pages')): ?>
        <a class="btn btn-gold btn-lg" href="<?= e(base_url('register')) ?>">Start Your Fundraiser</a>
        <span class="or">or</span>
      <?php endif; ?>
      <a class="btn btn-outline-light btn-lg" href="<?= e(base_url('support')) ?>">Talk to our team</a>
    </div>
  </div>
</section>
