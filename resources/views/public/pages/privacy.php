<?php
/**
 * Privacy policy. Copy is editable from the admin area.
 *
 * @var array $policy
 */
$body = (string) ($policy['body'] ?? '');

// Render the plain-text policy as headings + paragraphs.
$blocks = preg_split('/\n\s*\n/', trim($body)) ?: [];
?>
<section class="page-banner">
  <div class="wrap">
    <span class="eyebrow">Your Data</span>
    <h1><?= e((string) ($policy['heading'] ?? 'Privacy Policy')) ?></h1>
    <?php if (!empty($policy['updated'])): ?>
      <p><?= e((string) $policy['updated']) ?></p>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="wrap wrap-narrow">
    <article class="prose">
      <?php foreach ($blocks as $block): ?>
        <?php
          $lines = array_values(array_filter(array_map('trim', explode("\n", trim((string) $block))), static fn ($l): bool => $l !== ''));
          if ($lines === []) { continue; }
          $isHeading = count($lines) > 1 || mb_strlen($lines[0]) < 60;
        ?>
        <?php if ($isHeading && count($lines) > 1): ?>
          <h2><?= e($lines[0]) ?></h2>
          <p><?= nl2br(e(implode("\n", array_slice($lines, 1)))) ?></p>
        <?php else: ?>
          <p><?= nl2br(e(implode("\n", $lines))) ?></p>
        <?php endif; ?>
      <?php endforeach; ?>
    </article>

    <div class="panel mt-3">
      <h2>Contact us about your data</h2>
      <p class="panel-sub">Write to us for access, correction or deletion requests.</p>
      <p><a class="btn btn-brand" href="<?= e(base_url('support')) ?>">Contact support</a></p>
    </div>
  </div>
</section>
