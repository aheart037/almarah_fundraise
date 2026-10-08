<?php
/**
 * Pagination control.
 *
 * @var int    $page
 * @var int    $lastPage
 * @var int    $total
 * @var array  $query  existing query string parameters to preserve
 */

$page = max(1, (int) ($page ?? 1));
$lastPage = max(1, (int) ($lastPage ?? 1));
$total = (int) ($total ?? 0);
$query = $query ?? [];

if ($lastPage <= 1) {
    return;
}

$link = static function (int $target) use ($query): string {
    $query['page'] = $target;
    $items = array_filter($query, static fn ($v): bool => $v !== '' && $v !== null);
    return base_url(ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/'))
        . '?' . http_build_query($items);
};

$window = 2;
$start = max(1, $page - $window);
$end = min($lastPage, $page + $window);
?>
<nav class="pagination" aria-label="Pagination">
  <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e($link(max(1, $page - 1))) ?>" rel="prev">Prev</a>

  <?php if ($start > 1): ?>
    <a href="<?= e($link(1)) ?>">1</a>
    <?php if ($start > 2): ?><span class="info">…</span><?php endif; ?>
  <?php endif; ?>

  <?php for ($i = $start; $i <= $end; $i++): ?>
    <?php if ($i === $page): ?>
      <span class="current" aria-current="page"><?= e((string) $i) ?></span>
    <?php else: ?>
      <a href="<?= e($link($i)) ?>"><?= e((string) $i) ?></a>
    <?php endif; ?>
  <?php endfor; ?>

  <?php if ($end < $lastPage): ?>
    <?php if ($end < $lastPage - 1): ?><span class="info">…</span><?php endif; ?>
    <a href="<?= e($link($lastPage)) ?>"><?= e((string) $lastPage) ?></a>
  <?php endif; ?>

  <a class="<?= $page >= $lastPage ? 'disabled' : '' ?>" href="<?= e($link(min($lastPage, $page + 1))) ?>" rel="next">Next</a>
  <span class="info"><?= e(number_format($total)) ?> total</span>
</nav>
