<?php
/** Site favicon with the original Almarah SVG as the no-upload fallback. */
$faviconUrl = site_brand_asset('site.favicon');
?>
<?php if ($faviconUrl !== null): ?>
<link rel="icon" type="image/png" href="<?= e($faviconUrl) ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Crect width='48' height='48' rx='10' fill='%23a92d63'/%3E%3Cpath d='M24 15.2c5.9 0 11.2 2.3 15.2 6.1v17.2a3 3 0 0 1-3 3H11.8a3 3 0 0 1-3-3V21.3A21.6 21.6 0 0 1 24 15.2z' fill='%23f2c200'/%3E%3C/svg%3E">
<?php endif; ?>
