<?php
/**
 * Fundraiser detail page with donation box — TCF-inspired layout, Almarah brand colours.
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
$fallbackImage = asset('assets/img/hero.jpg');
$image = !empty($f['cover_image_path']) ? base_url((string) $f['cover_image_path']) : $fallbackImage;
$storyHtml = nl2br(e((string) ($f['story'] ?? '')));

/* Top donors (top 5 by amount) */
$topDonors = $supporters;
usort($topDonors, function ($a, $b) {
    return ((int) ($b['amount_minor'] ?? 0)) <=> ((int) ($a['amount_minor'] ?? 0));
});
$topDonors = array_slice($topDonors, 0, 5);
?>

<style>
/* ═══════════════════════════════════════════
   ALMARAH FUNDRAISER — TCF-INSPIRED LAYOUT
═══════════════════════════════════════════ */
.frp {
  --pink: #A92D63;
  --pink-d: #6B0F35;
  --pink-m: #C4386F;
  --yellow: #F2C200;
  --yellow-l: #FFD740;
  --ink: #1A0810;
  --mid: #5A2040;
  --soft: #9A6080;
  --bg: #FDF5F8;
  --cream: #FAF0F4;
  --line: rgba(169,45,99,.14);
  font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: var(--bg);
  color: var(--ink);
  line-height: 1.65;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
  padding-top: 140px;
}
.frp *, .frp *::before, .frp *::after { box-sizing: border-box; }

@media (min-width: 900px) { .frp { padding-top: 200px; } }

.frp .wrap { max-width: 1180px; margin: 0 auto; padding: 0 20px; }
@media (min-width: 768px) { .frp .wrap { padding: 0 36px; } }

/* PAGE TOP SPACING (replaces the old hero) */
.frp .frp-section { padding: 60px 0 100px; position: relative; }
@media (min-width: 900px) { .frp .frp-section { padding: 80px 0 120px; } }
.frp .frp-layout { display: grid; grid-template-columns: 1fr; gap: 32px; align-items: start; }
@media (min-width: 1024px) { .frp .frp-layout { grid-template-columns: 1fr 380px; gap: 40px; } }

/* CARDS */
.frp .frp-card {
  background: #fff;
  border-radius: 22px;
  padding: 34px 32px;
  box-shadow: 0 0 0 1px rgba(169,45,99,.06), 0 14px 40px rgba(169,45,99,.06);
  position: relative;
  overflow: hidden;
  margin-bottom: 26px;
}
@media (max-width: 600px) { .frp .frp-card { padding: 26px 22px; border-radius: 18px; } }
.frp .frp-card:last-child { margin-bottom: 0; }
.frp .frp-card-label {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: .55rem;
  font-weight: 800;
  letter-spacing: 4px;
  text-transform: uppercase;
  color: var(--pink);
  margin-bottom: 14px;
}
.frp .frp-card-label::before { content: ''; width: 20px; height: 1.5px; background: var(--pink); opacity: .6; }
.frp .frp-card h2 {
  font-weight: 800;
  font-size: clamp(1.25rem, 2.6vw, 1.55rem);
  color: var(--ink);
  letter-spacing: -.02em;
  margin-bottom: 18px;
  line-height: 1.2;
}

/* ── STORY CARD (with featured image + header underneath) ── */
.frp .frp-story {
  padding: 0;
}
.frp .frp-story > .frp-card-label,
.frp .frp-story > h2,
.frp .frp-story > p,
.frp .frp-story > .frp-badges,
.frp .frp-story > .frp-share {
  margin-left: 32px;
  margin-right: 32px;
}
.frp .frp-story > .frp-card-label { margin-top: 34px; }
.frp .frp-story > .frp-share { margin-bottom: 34px; }

@media (max-width: 600px) {
  .frp .frp-story > .frp-card-label,
  .frp .frp-story > h2,
  .frp .frp-story > p,
  .frp .frp-story > .frp-badges,
  .frp .frp-story > .frp-share {
    margin-left: 22px;
    margin-right: 22px;
  }
  .frp .frp-story > .frp-card-label { margin-top: 26px; }
  .frp .frp-story > .frp-share { margin-bottom: 26px; }
}

/* Featured image inside the story card */
.frp .frp-story-image {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: var(--cream);
}
@media (min-width: 900px) {
  .frp .frp-story-image { aspect-ratio: 21 / 9; }
}
.frp .frp-story-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 1.4s cubic-bezier(.19,1,.22,1);
}
.frp .frp-story:hover .frp-story-image img {
  transform: scale(1.04);
}
.frp .frp-story-image::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 55%, rgba(26,8,16,.35) 100%);
  pointer-events: none;
}

/* Category pill on the image */
.frp .frp-story-image-pill {
  position: absolute;
  top: 18px;
  left: 18px;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 15px;
  border-radius: 100px;
  background: rgba(242,194,0,.94);
  backdrop-filter: blur(10px);
  font-size: .56rem;
  font-weight: 800;
  letter-spacing: 2.5px;
  text-transform: uppercase;
  color: var(--ink);
  box-shadow: 0 6px 20px rgba(242,194,0,.30);
}
.frp .frp-story-image-pill::before {
  content: '';
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--pink-d);
}

/* Title + organiser block (below the featured image) */
.frp .frp-story-header {
  margin: 32px 32px 4px;
  padding-bottom: 24px;
  border-bottom: 1px dashed var(--line);
}
@media (max-width: 600px) {
  .frp .frp-story-header { margin: 24px 22px 4px; padding-bottom: 20px; }
}
.frp .frp-story-title {
  font-weight: 800;
  font-size: clamp(1.6rem, 3.6vw, 2.35rem);
  letter-spacing: -.035em;
  line-height: 1.1;
  color: var(--ink);
  margin: 0 0 12px;
}
.frp .frp-story-organiser {
  font-size: .94rem;
  color: var(--mid);
  line-height: 1.6;
  margin: 0;
}
.frp .frp-story-organiser strong { color: var(--pink); font-weight: 800; }

.frp .frp-story p { font-size: .96rem; color: var(--mid); line-height: 1.85; margin-bottom: 16px; }
.frp .frp-story p:last-child { margin-bottom: 0; }
.frp .frp-quote {
  position: relative;
  padding: 24px 28px 24px 62px;
  background: linear-gradient(135deg, var(--cream), var(--bg));
  border-radius: 18px;
  border-left: 5px solid var(--pink);
  margin-top: 28px;
  margin-left: 32px;
  margin-right: 32px;
}
@media (max-width: 600px) {
  .frp .frp-quote { margin-left: 22px; margin-right: 22px; padding: 20px 22px 20px 52px; }
}
.frp .frp-quote::before {
  content: '\201C';
  position: absolute;
  left: 20px; top: 10px;
  font-family: Georgia, serif;
  font-size: 4.5rem;
  line-height: 1;
  color: var(--pink);
  opacity: .25;
}
.frp .frp-quote p { font-size: 1rem; font-weight: 600; color: var(--ink); line-height: 1.7; font-style: italic; margin: 0 0 8px; }
.frp .frp-quote span { font-size: .66rem; font-weight: 800; letter-spacing: 2.5px; text-transform: uppercase; color: var(--pink); }

/* BADGES */
.frp .frp-badges { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 26px; }
.frp .frp-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 7px 15px;
  border-radius: 100px;
  font-size: .62rem;
  font-weight: 800;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  line-height: 1;
}
.frp .frp-badge-verified { background: rgba(27,122,62,.10); color: #1B7A3E; border: 1.5px solid rgba(27,122,62,.22); }
.frp .frp-badge-verified::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #1B7A3E; box-shadow: 0 0 8px rgba(27,122,62,.6); }
.frp .frp-badge-amber { background: rgba(242,194,0,.14); color: #8B6B00; border: 1.5px solid rgba(242,194,0,.30); }
.frp .frp-badge-grey { background: rgba(154,96,128,.08); color: var(--mid); border: 1.5px solid rgba(154,96,128,.16); }

/* SHARE */
.frp .frp-share { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 26px; padding-top: 24px; border-top: 1px dashed var(--line); }
.frp .frp-share-label {
  font-size: .60rem;
  font-weight: 800;
  letter-spacing: 2.5px;
  text-transform: uppercase;
  color: var(--soft);
  margin-right: 4px;
  width: 100%;
  margin-bottom: 4px;
}
@media (min-width: 640px) { .frp .frp-share-label { width: auto; margin-bottom: 0; } }
.frp .frp-share-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 16px;
  border-radius: 100px;
  background: #fff;
  border: 1.5px solid var(--line);
  color: var(--mid);
  font-size: .70rem;
  font-weight: 700;
  font-family: inherit;
  text-decoration: none;
  cursor: pointer;
  transition: all .25s ease;
}
.frp .frp-share-btn svg { width: 14px; height: 14px; flex-shrink: 0; }
.frp .frp-share-btn:hover {
  background: var(--pink);
  border-color: var(--pink);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(169,45,99,.25);
}

/* DONATIONS */
.frp .frp-donations { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 2px; }
.frp .frp-donation {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 16px 16px;
  border-radius: 14px;
  transition: background .25s, transform .25s;
}
.frp .frp-donation:hover { background: var(--bg); transform: translateX(3px); }
.frp .frp-donation-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px; height: 44px;
  min-width: 44px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--pink-d), var(--pink));
  color: #fff;
  font-weight: 800;
  font-size: .82rem;
  letter-spacing: .5px;
  box-shadow: 0 4px 12px rgba(169,45,99,.22);
}
.frp .frp-donation-avatar.top {
  background: linear-gradient(135deg, var(--yellow), var(--yellow-l));
  color: var(--ink);
  box-shadow: 0 4px 12px rgba(242,194,0,.35);
}
.frp .frp-donation-body { flex: 1; min-width: 0; }
.frp .frp-donation-name { font-weight: 700; font-size: .92rem; color: var(--ink); letter-spacing: -.01em; }
.frp .frp-donation-time { font-size: .68rem; color: var(--soft); font-weight: 600; margin-top: 2px; }
.frp .frp-donation-msg {
  font-size: .84rem;
  color: var(--mid);
  line-height: 1.55;
  margin: 8px 0 0;
  font-style: italic;
  padding-left: 12px;
  border-left: 2px solid var(--line);
}
.frp .frp-donation-amount {
  font-weight: 800;
  font-size: .98rem;
  color: var(--pink);
  letter-spacing: -.01em;
  white-space: nowrap;
  flex-shrink: 0;
  align-self: center;
}
.frp .frp-topdonors {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px dashed var(--line);
}
.frp .frp-topdonor-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px 6px 6px;
  border-radius: 100px;
  background: var(--bg);
  border: 1px solid var(--line);
  font-size: .72rem;
  font-weight: 700;
  color: var(--mid);
}
.frp .frp-topdonor-chip .rank {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px; height: 24px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--yellow), var(--yellow-l));
  color: var(--ink);
  font-size: .62rem;
  font-weight: 800;
}

/* TIMELINE */
.frp .frp-timeline { list-style: none; padding: 0; margin: 0; position: relative; }
.frp .frp-timeline::before {
  content: '';
  position: absolute;
  left: 7px; top: 10px; bottom: 10px;
  width: 2px;
  background: linear-gradient(180deg, var(--pink), rgba(169,45,99,.08));
  border-radius: 2px;
}
.frp .frp-timeline li { position: relative; padding: 0 0 26px 34px; }
.frp .frp-timeline li:last-child { padding-bottom: 0; }
.frp .frp-timeline li::before {
  content: '';
  position: absolute;
  left: 0; top: 6px;
  width: 16px; height: 16px;
  border-radius: 50%;
  background: #fff;
  border: 3px solid var(--pink);
  box-shadow: 0 0 0 4px rgba(169,45,99,.10);
}
.frp .frp-timeline-when {
  display: inline-block;
  font-size: .58rem;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--pink);
  margin-bottom: 6px;
}
.frp .frp-timeline-what { font-weight: 800; font-size: 1rem; color: var(--ink); margin-bottom: 6px; letter-spacing: -.01em; }
.frp .frp-timeline-body { font-size: .9rem; color: var(--mid); line-height: 1.7; }
.frp .frp-timeline-body img { max-width: 100%; border-radius: 14px; margin-top: 12px; box-shadow: 0 8px 24px rgba(169,45,99,.10); }

/* SIDEBAR */
.frp .frp-sidebar { position: relative; }
@media (min-width: 1024px) { .frp .frp-sidebar-inner { position: sticky; top: 24px; } }

/* DONATION BOX */
.frp .frp-donate-box {
  background: #fff;
  border-radius: 22px;
  padding: 30px 28px;
  box-shadow:
    0 0 0 1px rgba(169,45,99,.08),
    0 20px 60px rgba(169,45,99,.09);
  position: relative;
  overflow: hidden;
}
@media (max-width: 600px) { .frp .frp-donate-box { padding: 24px 20px; border-radius: 18px; } }
.frp .frp-donate-box::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 5px;
  background: linear-gradient(90deg, var(--pink-d), var(--pink), var(--yellow));
}
.frp .frp-box-head { text-align: center; margin-bottom: 20px; }
.frp .frp-box-raised {
  font-weight: 800;
  font-size: clamp(1.6rem, 3vw, 2rem);
  color: var(--pink);
  letter-spacing: -.03em;
  line-height: 1;
}
.frp .frp-box-goal { font-size: .78rem; color: var(--soft); font-weight: 600; margin-top: 6px; }

/* Progress bar (used inside donation box) */
.frp .frp-progress-track {
  width: 100%;
  height: 10px;
  border-radius: 100px;
  background: rgba(169,45,99,.10);
  overflow: hidden;
  position: relative;
  margin-bottom: 8px;
}
.frp .frp-progress-fill {
  display: block;
  height: 100%;
  width: 0%;
  border-radius: 100px;
  background: linear-gradient(90deg, var(--pink-d), var(--pink), var(--pink-m), var(--yellow));
  transition: width 1.8s cubic-bezier(.19,1,.22,1);
  position: relative;
}
.frp .frp-progress-fill::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: linear-gradient(90deg, transparent 60%, rgba(255,255,255,.35) 100%);
  animation: frpShine 2.5s ease-in-out infinite;
}
@keyframes frpShine { 0%, 100% { opacity: 0; } 50% { opacity: 1; } }

.frp .frp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 16px 24px;
  border-radius: 100px;
  font-family: inherit;
  font-weight: 800;
  font-size: .76rem;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  text-decoration: none;
  transition: all .3s ease;
  cursor: pointer;
  border: 2px solid transparent;
  margin-top: 6px;
}
.frp .frp-btn-primary {
  background: linear-gradient(135deg, var(--pink-d), var(--pink), var(--pink-m));
  color: #fff;
  box-shadow: 0 10px 28px rgba(169,45,99,.35);
}
.frp .frp-btn-primary:hover { transform: translateY(-3px); box-shadow: 0 16px 44px rgba(169,45,99,.45); }
.frp .frp-btn-outline {
  background: #fff;
  border-color: var(--line);
  color: var(--pink);
  margin-top: 10px;
}
.frp .frp-btn-outline:hover {
  background: var(--pink);
  border-color: var(--pink);
  color: #fff;
  transform: translateY(-3px);
  box-shadow: 0 10px 28px rgba(169,45,99,.25);
}
.frp .frp-btn svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

.frp .frp-box-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0;
  padding: 18px 0;
  margin-top: 20px;
  border-top: 1px solid var(--line);
}
.frp .frp-box-stats span {
  text-align: center;
  font-size: .68rem;
  color: var(--soft);
  font-weight: 600;
  position: relative;
}
.frp .frp-box-stats span + span::before {
  content: '';
  position: absolute;
  left: 0; top: 4px; bottom: 4px;
  width: 1px;
  background: var(--line);
}
.frp .frp-box-stats span b {
  display: block;
  font-weight: 800;
  font-size: 1rem;
  color: var(--ink);
  letter-spacing: -.02em;
  margin-bottom: 2px;
}
.frp .frp-remaining {
  font-size: .82rem;
  color: var(--mid);
  line-height: 1.55;
  padding: 12px 14px;
  background: rgba(242,194,0,.08);
  border-radius: 12px;
  border-left: 3px solid var(--yellow);
  margin: 18px 0 0;
}
.frp .frp-remaining strong { color: var(--ink); font-weight: 800; }
.frp .frp-organiser {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  background: var(--bg);
  border-radius: 16px;
  margin-top: 16px;
}
.frp .frp-organiser-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 46px; height: 46px;
  min-width: 46px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--pink-d), var(--pink));
  color: #fff;
  font-weight: 800;
  font-size: .82rem;
  box-shadow: 0 4px 14px rgba(169,45,99,.25);
}
.frp .frp-organiser-body { min-width: 0; }
.frp .frp-organiser-body b { display: block; font-weight: 700; font-size: .92rem; color: var(--ink); letter-spacing: -.01em; }
.frp .frp-organiser-body span {
  display: block;
  font-size: .60rem;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--pink);
  margin-top: 2px;
}
.frp .frp-secure {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  background: rgba(27,122,62,.06);
  border-radius: 12px;
  border: 1px solid rgba(27,122,62,.14);
  margin-top: 14px;
}
.frp .frp-secure svg { width: 16px; height: 16px; color: #1B7A3E; flex-shrink: 0; margin-top: 2px; }
.frp .frp-secure span { font-size: .76rem; color: #1B7A3E; line-height: 1.5; font-weight: 600; }

/* OTHER FUNDRAISERS */
.frp .frp-grid { display: grid; gap: 20px; margin-top: 24px; }
@media (min-width: 600px) { .frp .frp-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 960px) { .frp .frp-grid { grid-template-columns: repeat(3, 1fr); } }

@media (max-width: 600px) {
  .frp .frp-box-stats span { font-size: .60rem; }
  .frp .frp-box-stats span b { font-size: .88rem; }
  .frp .frp-donation-avatar { width: 38px; height: 38px; min-width: 38px; font-size: .72rem; }
  .frp .frp-donation-amount { font-size: .88rem; }
  .frp .frp-donation { padding: 14px 12px; }
}

@media (prefers-reduced-motion: reduce) {
  .frp .frp-progress-fill::after { animation: none; opacity: 0; }
}
</style>

<div class="frp">

  <!-- MAIN -->
  <section class="frp-section">
    <div class="wrap">
      <div class="frp-layout">

        <!-- LEFT -->
        <div>

          <!-- ═══════════════════════════════════
               STORY CARD (with featured image + title underneath)
          ═══════════════════════════════════ -->
          <article class="frp-card frp-story">

            <!-- Featured image -->
            <div class="frp-story-image">
              <img src="<?= e($image) ?>"
                   alt="<?= e((string) $f['title']) ?>"
                   fetchpriority="high"
                   decoding="async"
                   onerror="this.onerror=null;this.src='<?= e($fallbackImage) ?>';">
              <span class="frp-story-image-pill">
                <?= e((string) ($f['category_name'] ?? 'Fundraiser')) ?>
              </span>
            </div>

            <!-- Title + organiser (under the image) -->
            <div class="frp-story-header">
              <h1 class="frp-story-title"><?= e((string) $f['title']) ?></h1>
              <p class="frp-story-organiser">
                Organised by <strong><?= e($organiser !== '' ? $organiser : 'a supporter') ?></strong>
                <?php if (!empty($f['campaign_title'])): ?>
                  for <strong><?= e((string) $f['campaign_title']) ?></strong>
                <?php endif; ?>
              </p>
            </div>

            <div class="frp-card-label">Our Story</div>
           
            <p><?= $storyHtml ?></p>

            <?php if (!empty($f['impact_statement'])): ?>
              <div class="frp-quote">
                <p><?= e((string) $f['impact_statement']) ?></p>
                <span>&mdash; The impact you are funding</span>
              </div>
            <?php endif; ?>

            <div class="frp-badges">
              <span class="frp-badge frp-badge-verified">Verified fundraiser</span>
              <?php if ($daysLeft !== null): ?>
                <span class="frp-badge <?= $daysLeft > 0 ? 'frp-badge-amber' : 'frp-badge-grey' ?>">
                  <?= $daysLeft > 0 ? e((string) $daysLeft) . ' days left' : 'Campaign finished' ?>
                </span>
              <?php endif; ?>
              <span class="frp-badge frp-badge-grey">Goal <?= e(money_short($goal, $currency)) ?></span>
            </div>

            <div class="frp-share">
              <span class="frp-share-label">Share this fundraiser</span>
              <a class="frp-share-btn" target="_blank" rel="noopener"
                 href="https://wa.me/?text=<?= e(rawurlencode((string) $f['title'] . ' — ' . $shareUrl)) ?>">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm5.8 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.7-.6a11 11 0 0 1-4.2-3.7c-.3-.5-.7-1.2-.7-2s.3-1.4.5-1.6c.2-.3.5-.3.6-.3h.5c.2 0 .4 0 .5.4l.7 1.6c.1.2 0 .4-.1.5l-.3.4c-.1.1-.2.3-.1.5.2.5.6 1 1 1.4.5.5 1.1.9 1.7 1.2.2.1.4.1.5 0l.6-.7c.2-.2.3-.2.5-.1l1.6.7c.2.1.4.2.4.3.1.2.1.7-.1 1.3z"/></svg>
                WhatsApp
              </a>
              <a class="frp-share-btn" target="_blank" rel="noopener"
                 href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($shareUrl)) ?>">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/></svg>
                Facebook
              </a>
              <a class="frp-share-btn" target="_blank" rel="noopener"
                 href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode($shareUrl)) ?>&text=<?= e(rawurlencode((string) $f['title'])) ?>">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.2 2H21l-6.6 7.5L22 22h-6.4l-4.4-6.1L6 22H3.2l7-8L2 2h6.6l4 5.6zm-.9 18h1.5L7.8 3.9H6.2z"/></svg>
                X
              </a>
              <button class="frp-share-btn" type="button" data-copy="<?= e($shareUrl) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                Copy link
              </button>
            </div>
          </article>

          <?php if ($updates !== []): ?>
            <section class="frp-card">
              <div class="frp-card-label">Campaign Updates</div>
              <h2>Latest from <?= e($organiser !== '' ? $organiser : 'the organiser') ?></h2>
              <ul class="frp-timeline">
                <?php foreach ($updates as $update): ?>
                  <li>
                    <span class="frp-timeline-when"><?= e(dt((string) ($update['published_at'] ?? $update['created_at']), 'j M Y')) ?></span>
                    <div class="frp-timeline-what"><?= e((string) $update['title']) ?></div>
                    <div class="frp-timeline-body"><?= nl2br(e((string) $update['body'])) ?></div>
                    <?php if (!empty($update['image_path'])): ?>
                      <img src="<?= e(base_url((string) $update['image_path'])) ?>" alt="" loading="lazy">
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endif; ?>

          <?php if ($supporters !== []): ?>
            <section class="frp-card">
              <div class="frp-card-label">Donation Feed</div>
              <h2>Recent Donations</h2>
              <p style="font-size:.86rem;color:var(--soft);margin-bottom:20px;">Only donations confirmed by the bank appear here.</p>

              <?php if (!empty($topDonors)): ?>
                <div class="frp-topdonors">
                  <?php foreach ($topDonors as $i => $donor): ?>
                    <?php if (!empty($donor['anonymous'])) continue; ?>
                    <span class="frp-topdonor-chip">
                      <span class="rank"><?= e((string) ($i + 1)) ?></span>
                      <?= e((string) ($donor['donor_name'] ?? 'Supporter')) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <ul class="frp-donations">
                <?php foreach ($supporters as $i => $donation): ?>
                  <li class="frp-donation">
                    <span class="frp-donation-avatar <?= $i < 5 ? 'top' : '' ?>">
                      <?= e(initials(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? 'Supporter'))) ?>
                    </span>
                    <div class="frp-donation-body">
                      <div class="frp-donation-name">
                        <?= e(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? 'Supporter')) ?>
                      </div>
                      <div class="frp-donation-time">
                        <?= e(relative_time((string) ($donation['completed_at'] ?? $donation['created_at']))) ?>
                      </div>
                      <?php if (!empty($donation['donor_message'])): ?>
                        <p class="frp-donation-msg">“<?= e((string) $donation['donor_message']) ?>”</p>
                      <?php endif; ?>
                    </div>
                    <span class="frp-donation-amount">
                      <?= e(money((int) $donation['amount_minor'], (string) ($donation['currency'] ?? $currency))) ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endif; ?>

          <?php if ($otherFundraisers !== []): ?>
            <section class="frp-card">
              <div class="frp-card-label">More Causes</div>
              <h2>Other Fundraisers You Can Help</h2>
              <div class="frp-grid">
                <?php foreach ($otherFundraisers as $other): ?>
                  <?php $f = $other; $showProgress = true; require __DIR__ . '/../../partials/fundraiser-card.php'; ?>
                <?php endforeach; ?>
                <?php $f = $fundraiser; /* restore for the sidebar below */ ?>
              </div>
            </section>
          <?php endif; ?>

        </div>

        <!-- SIDEBAR -->
        <aside class="frp-sidebar">
          <div class="frp-sidebar-inner">

            <div class="frp-donate-box">
              <div class="frp-box-head">
                <div class="frp-box-raised"><?= e(money($raised, $currency)) ?></div>
                <div class="frp-box-goal">raised of <?= e(money($goal, $currency)) ?> goal</div>
              </div>

              <div class="frp-progress-track" role="progressbar"
                   aria-valuenow="<?= e((string) (int) $percent) ?>" aria-valuemin="0" aria-valuemax="100">
                <span class="frp-progress-fill" data-w="<?= e(number_format($percent, 1, '.', '')) ?>"></span>
              </div>

              <div class="frp-box-stats">
                <span><b><?= e(number_format($donorCount)) ?></b>Donors</span>
                <span><b><?= e((string) (int) round($percent)) ?>%</b>Funded</span>
                <span>
                  <?php if ($daysLeft !== null && $daysLeft > 0): ?>
                    <b><?= e((string) $daysLeft) ?></b>Days left
                  <?php else: ?>
                    <b>—</b>Ended
                  <?php endif; ?>
                </span>
              </div>

              <a class="frp-btn frp-btn-primary" href="<?= e($donateUrl) ?>">
                <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                Donate Now
              </a>
              <a class="frp-btn frp-btn-outline" href="<?= e($shareUrl) ?>" data-share="<?= e($shareUrl) ?>" data-share-title="<?= e((string) $f['title']) ?>">
                <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                Share
              </a>

              <?php if ($remaining > 0): ?>
                <p class="frp-remaining">
                  <strong><?= e(money($remaining, $currency)) ?></strong> still needed to reach the goal.
                </p>
              <?php else: ?>
                <p class="frp-remaining">
                  🎉 This fundraiser has reached its goal — thank you.
                </p>
              <?php endif; ?>

              <div class="frp-organiser">
                <span class="frp-organiser-avatar"><?= e(initials($organiser !== '' ? $organiser : 'Almarah')) ?></span>
                <div class="frp-organiser-body">
                  <b><?= e($organiser !== '' ? $organiser : 'Almarah Foundation') ?></b>
                  <span>Organiser</span>
                </div>
              </div>

              <div class="frp-secure">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                <span>Card payments are handled on the bank's own secure page.</span>
              </div>
            </div>

          </div>
        </aside>

      </div>
    </div>
  </section>

</div>

<script>
(function(){
  var fills = document.querySelectorAll('.frp .frp-progress-fill[data-w]');
  if(fills.length){
    var animate = function(el){
      var w = parseFloat(el.getAttribute('data-w')) || 0;
      setTimeout(function(){ el.style.width = w + '%'; }, 100);
    };
    if('IntersectionObserver' in window){
      var obs = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if(entry.isIntersecting){ animate(entry.target); obs.unobserve(entry.target); }
        });
      }, { threshold: 0.2 });
      fills.forEach(function(f){ obs.observe(f); });
    } else { fills.forEach(animate); }
  }

  document.querySelectorAll('.frp [data-copy]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var text = btn.getAttribute('data-copy');
      var original = btn.innerHTML;
      function done(){
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><polyline points="20 6 9 17 4 12"/></svg> Copied';
        setTimeout(function(){ btn.innerHTML = original; }, 1800);
      }
      if(navigator.clipboard && window.isSecureContext){
        navigator.clipboard.writeText(text).then(done, function(){ fallbackCopy(text, done); });
      } else { fallbackCopy(text, done); }
    });
  });

  function fallbackCopy(text, cb){
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;top:-100px;left:-100px;opacity:0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch(e){}
    document.body.removeChild(ta);
    if(cb) cb();
  }
})();
</script>