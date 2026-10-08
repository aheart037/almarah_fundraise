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

<section class="section">
  <div class="wrap">
    <div class="split">
      <div class="reveal">
        <span class="eyebrow brand left">Accountability</span>
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
      </div>
    </div>
  </div>
</section>

<section class="section tight cta-band">
  <div class="wrap">
    <h2>Turn your occasion into a child&rsquo;s future</h2>
    <p>Start a fundraiser in five minutes and invite the people who already care about what you care about.</p>
    <div class="cta-actions">
      <a class="btn btn-gold btn-lg" href="<?= e(base_url('register')) ?>">Start Your Fundraiser</a>
      <span class="or">or</span>
      <a class="btn btn-outline-light btn-lg" href="<?= e(base_url('support')) ?>">Talk to our team</a>
    </div>
  </div>
</section>
