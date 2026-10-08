<?php
/**
 * Minimal layout used for intermediate pages (gateway bridge, print views).
 *
 * @var string $content
 */

use App\Core\Config;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Redirecting') ?> | <?= e((string) Config::get('app.name', 'Almarah Foundation')) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body>
<style>
  .bridge-shell { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: var(--brand-tint); }
  .bridge-card { background: #fff; border-radius: var(--radius-lg); box-shadow: var(--shadow); padding: 36px 30px; max-width: 460px; text-align: center; }
  .bridge-card h1 { font-size: 22px; margin: 0 0 10px; }
  .bridge-card p { color: var(--muted); font-size: 14.5px; margin: 0 0 18px; }
  .spinner { width: 38px; height: 38px; margin: 0 auto 18px; border: 3px solid var(--brand-tint-2); border-top-color: var(--brand); border-radius: 50%; animation: spin .8s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
</style>
<?= $content ?>
</body>
</html>
