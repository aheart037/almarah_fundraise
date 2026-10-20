<?php
/**
 * Fonts chosen in Admin → Fonts. Prints nothing until something is customised,
 * so the stylesheet alone defines the look by default.
 */
try {
    $siteTypographyCss = app(\App\Services\TypographyService::class)->css();
} catch (\Throwable $e) {
    $siteTypographyCss = '';   // never let a settings problem break a page
}
if ($siteTypographyCss !== ''): ?>
<style id="site-typography"><?= $siteTypographyCss ?></style>
<?php endif; ?>
