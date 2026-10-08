<?php
/**
 * Terms of use. Copy is editable from the admin area.
 *
 * @var array $terms
 */
$body = (string) ($terms['body'] ?? '');
$blocks = preg_split('/\n\s*\n/', trim($body)) ?: [];
?>
<section class="page-banner">
  <div class="wrap">
    <span class="eyebrow">The Fine Print</span>
    <h1><?= e((string) ($terms['title'] ?? 'Terms of use')) ?></h1>
    <?php if (!empty($terms['updated'])): ?>
      <p><?= e((string) $terms['updated']) ?></p>
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
        ?>
        <?php if (count($lines) > 1): ?>
          <h2><?= e($lines[0]) ?></h2>
          <p><?= nl2br(e(implode("\n", array_slice($lines, 1)))) ?></p>
        <?php else: ?>
          <p><?= nl2br(e($lines[0])) ?></p>
        <?php endif; ?>
      <?php endforeach; ?>
    </article>
  </div>
</section>
