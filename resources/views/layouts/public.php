<?php
/**
 * Public site layout — header, main content, footer.
 *
 * @var string $content
 * @var string $pageTitle
 * @var array|null $authUser
 * @var bool|null $isAdmin
 */

use App\Core\Config;

$appName = (string) Config::get('app.name', 'Almarah Foundation');
$org = (array) Config::get('app.org', []);
$title = isset($pageTitle) && $pageTitle !== ''
    ? $pageTitle . ' | ' . $appName
    : $appName . ' | Fundraise for Orphan Care, Food & Education';
$description = $metaDescription ?? (string) Config::get('app.org.tagline', '');
$currentPath = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$isAuthPage = preg_match('~/(?:login|register|forgot-password|reset-password|verify-email)(?:/|$)~', $currentPath) === 1;

// ---------------------------------------------------------------------------
// SEO (v15). Everything below only builds values for the <head>.
// Controllers may pass: $robots (e.g. 'noindex,follow'), $canonicalPath,
// $ogImage, $ogType ('website'|'article'), $structuredData (extra JSON-LD
// array or list of arrays), $breadcrumbs ([['name' => '', 'path' => ''], ...]).
// ---------------------------------------------------------------------------
try {
    $seoPath = request()->path();           // path without the install folder
} catch (\Throwable) {
    $seoPath = '/' . trim($currentPath, '/');
}
$seoPath = $seoPath === '' ? '/' : $seoPath;

// Pages that must never appear in search results: account pages, the donation
// flow and bank return pages, error pages, and search/filter variants of the
// list pages (they duplicate the main list).
$seoPrivate = $isAuthPage
    || preg_match('~^/(?:donate|donation|payments|setup|dashboard|admin|team-invitations|fundraise-|meezanreturn)~', $seoPath) === 1
    || (isset($status) && (int) $status >= 400);

$seoFilterKeys = ['q', 'category', 'state', 'sort', 'financial', 'search', 'type'];
$seoHasFilter = false;
foreach ($seoFilterKeys as $seoKey) {
    if (isset($_GET[$seoKey]) && $_GET[$seoKey] !== '') {
        $seoHasFilter = true;
        break;
    }
}

if (!empty($robots)) {
    $robotsContent = (string) $robots;
} elseif ($seoPrivate) {
    $robotsContent = 'noindex,nofollow';
} elseif ($seoHasFilter) {
    $robotsContent = 'noindex,follow';
} else {
    $robotsContent = 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';
}
$seoIndexable = str_starts_with($robotsContent, 'index');

// Canonical address: the clean path (no filters, no tracking parameters), plus
// ?page=N for page two onward of a list.
if (!empty($canonicalPath)) {
    $canonicalUrl = base_url(ltrim((string) $canonicalPath, '/'));
} else {
    $canonicalUrl = $seoPath === '/' ? base_url() . '/' : base_url(ltrim($seoPath, '/'));
    $seoPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($seoPage > 1 && !$seoHasFilter) {
        $canonicalUrl .= '?page=' . $seoPage;
    }
}

// Descriptions work best at about 150-160 characters; cut on a word boundary.
$description = trim((string) preg_replace('/\s+/u', ' ', strip_tags($description)));
if (mb_strlen($description) > 160) {
    $cut = mb_substr($description, 0, 157);
    $space = mb_strrpos($cut, ' ');
    $description = rtrim($space !== false && $space > 100 ? mb_substr($cut, 0, $space) : $cut, " ,.;:-") . '...';
}

// Share image: the page's own image, else the site's default hero photo.
$ogImageUrl = !empty($ogImage)
    ? (preg_match('~^https?://~i', (string) $ogImage) === 1 ? (string) $ogImage : base_url((string) $ogImage))
    : base_url('assets/img/hero.jpg');
$ogTypeValue = !empty($ogType) ? (string) $ogType : 'website';

$orgName = (string) ($org['legal_name'] ?? $appName);
$orgLogo = function_exists('site_brand_asset') ? site_brand_asset('site.header_logo') : null;
$orgLogoUrl = $orgLogo !== null && $orgLogo !== ''
    ? (preg_match('~^https?://~i', (string) $orgLogo) === 1 ? (string) $orgLogo : base_url((string) $orgLogo))
    : base_url('assets/img/hero.jpg');

$jsonLd = [];
$jsonLd[] = [
    '@context'  => 'https://schema.org',
    '@type'     => 'NGO',
    '@id'       => base_url() . '/#organization',
    'name'      => $orgName,
    'url'       => (string) ($org['website'] ?? base_url()),
    'logo'      => $orgLogoUrl,
    'image'     => base_url('assets/img/hero.jpg'),
    'slogan'    => (string) ($org['tagline'] ?? ''),
    'email'     => (string) ($org['email'] ?? ''),
    'telephone' => (string) ($org['phone'] ?? ''),
    'foundingDate' => (string) ($org['founded'] ?? ''),
    'address'   => [
        '@type'          => 'PostalAddress',
        'streetAddress'  => 'Canal Road',
        'addressLocality'=> 'Lahore',
        'addressRegion'  => 'Punjab',
        'addressCountry' => 'PK',
    ],
    'sameAs'    => [
        'https://www.facebook.com/almarahfoundation',
        'https://www.instagram.com/almarahfoundation',
        'https://www.youtube.com/@almarahfoundation',
        'https://www.linkedin.com/company/almarahfoundation/home/',
        'https://www.tiktok.com/@almarahfoundation1',
    ],
];
if ($seoPath === '/') {
    $jsonLd[] = [
        '@context'  => 'https://schema.org',
        '@type'     => 'WebSite',
        '@id'       => base_url() . '/#website',
        'url'       => base_url() . '/',
        'name'      => $appName,
        'publisher' => ['@id' => base_url() . '/#organization'],
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => base_url('fundraisers') . '?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];
}
if (!empty($breadcrumbs) && is_array($breadcrumbs)) {
    $crumbItems = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => base_url() . '/']];
    foreach (array_values($breadcrumbs) as $crumbIndex => $crumb) {
        $crumbItems[] = [
            '@type'    => 'ListItem',
            'position' => $crumbIndex + 2,
            'name'     => (string) ($crumb['name'] ?? ''),
            'item'     => base_url(ltrim((string) ($crumb['path'] ?? ''), '/')),
        ];
    }
    $jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbItems];
}
if (!empty($structuredData) && is_array($structuredData)) {
    $extra = isset($structuredData['@type']) ? [$structuredData] : $structuredData;
    foreach ($extra as $block) {
        if (is_array($block) && $block !== []) {
            $jsonLd[] = array_filter($block, static fn ($v): bool => $v !== null);
        }
    }
}
if (!$seoIndexable) {
    $jsonLd = []; // no point describing pages that are kept out of search
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="robots" content="<?= e($robotsContent) ?>">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<meta name="theme-color" content="#a92d63">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta property="og:site_name" content="<?= e($appName) ?>">
<meta property="og:locale" content="en_US">
<meta property="og:type" content="<?= e($ogTypeValue) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:image" content="<?= e($ogImageUrl) ?>">
<meta property="og:image:alt" content="<?= e(isset($pageTitle) && $pageTitle !== '' ? (string) $pageTitle : $appName) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
<?php foreach ($jsonLd as $jsonLdBlock): ?>
<script type="application/ld+json"><?= json_encode($jsonLdBlock, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach; ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<?php require __DIR__ . '/../partials/typography.php'; ?>
<?php require __DIR__ . '/../partials/favicon.php'; ?>
</head>
<body<?= $isAuthPage ? ' class="auth-page-body"' : '' ?>>

<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main">
<?php require __DIR__ . '/../partials/alerts.php'; ?>
<?= $content ?>
</main>

<?php require __DIR__ . '/../partials/footer.php'; ?>

<div class="mobile-panel" id="mobilePanel">
  <?php if (!empty($headerMenu)): ?>
    <?php foreach ($headerMenu['items'] as $menuItem): ?>
      <a class="m-link" href="<?= e($menuItem['url']) ?>"<?= $menuItem['newtab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($menuItem['label']) ?></a>
    <?php endforeach; ?>
  <?php else: ?>
  <a class="m-link" href="<?= e(base_url('fundraisers')) ?>">Browse fundraisers</a>
  <a class="m-link" href="<?= e(base_url('teams')) ?>">Teams</a>
  <a class="m-link" href="<?= e(base_url('campaigns')) ?>">Campaigns</a>
  <a class="m-link" href="<?= e(base_url('about-us')) ?>">About us</a>
  <a class="m-link" href="<?= e(base_url('faq')) ?>">FAQ</a>
  <?php endif; ?>
  <?php if (!empty($authUser)): ?>
    <a class="m-link" href="<?= e(base_url('dashboard')) ?>">Dashboard</a>
    <a class="m-link" href="<?= e(base_url('logout')) ?>" data-method="post">Sign out</a>
  <?php else: ?>
    <a class="m-link" href="<?= e(base_url('login')) ?>">Sign in</a>
    <?php if (can_fundraiser_capability('manage_pages')): ?>
      <a class="btn btn-gold" href="<?= e(base_url('register')) ?>">Start Your Fundraiser</a>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
