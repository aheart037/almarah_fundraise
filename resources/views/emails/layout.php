<?php
/**
 * Shared shell for every transactional email.
 *
 * Email clients are unpredictable, so this layout uses inline styles only and
 * a single-column table structure. No external CSS, fonts or images are loaded.
 *
 * @var string $content
 */

$appName    = (string) ($appName ?? config('app.name', 'Almarah Foundation'));
$org        = is_array($org ?? null) ? $org : [];
$orgName    = (string) ($org['legal_name'] ?? $appName);
$orgSite    = (string) ($org['website'] ?? 'https://www.almarah.org');
$orgEmail   = (string) ($org['email'] ?? 'info@almarah.org');
$orgPhone   = (string) ($org['phone'] ?? '');
$orgAddress = (string) ($org['address'] ?? '');
$preheader  = trim((string) ($preheader ?? ''));
$sansStack  = "'Helvetica Neue',Helvetica,Arial,FreeSans,sans-serif";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light only">
  <title><?= e((string) ($heading ?? $appName)) ?></title>
</head>
<body style="margin:0;padding:0;background:#f6f4f5;font-family:<?= e($sansStack) ?>;color:#333333">
  <?php if ($preheader !== ''): ?>
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f6f4f5"><?= e($preheader) ?></div>
  <?php endif; ?>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f6f4f5">
    <tr>
      <td align="center" style="padding:30px 12px 40px">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px">

          <tr>
            <td style="padding:0 6px 18px">
              <a href="<?= e($orgSite) ?>" style="font-family:<?= e($sansStack) ?>;font-size:21px;font-weight:700;color:#a92d63;text-decoration:none"><?= e($orgName) ?></a>
              <div style="font-family:<?= e($sansStack) ?>;font-size:12.5px;color:#717171;margin-top:4px">Fundraising for Pakistan&rsquo;s children &middot; PKR</div>
            </td>
          </tr>

          <tr>
            <td style="background:#ffffff;border-radius:15px;box-shadow:0 5px 16px -3px rgba(0,0,0,0.3);padding:34px 34px 30px">
              <?= $content ?>
            </td>
          </tr>

          <tr>
            <td style="padding:22px 8px 0;font-family:<?= e($sansStack) ?>;font-size:12px;line-height:1.75;color:#717171">
              <div style="font-weight:700;color:#2a1119"><?= e($orgName) ?></div>
              <?php if ($orgAddress !== ''): ?><div><?= e($orgAddress) ?></div><?php endif; ?>
              <div>
                <?php if ($orgPhone !== ''): ?><?= e($orgPhone) ?> &middot; <?php endif; ?>
                <a href="mailto:<?= e($orgEmail) ?>" style="color:#a92d63;text-decoration:none"><?= e($orgEmail) ?></a> &middot;
                <a href="<?= e($orgSite) ?>" style="color:#a92d63;text-decoration:none"><?= e(preg_replace('#^https?://#', '', $orgSite)) ?></a>
              </div>
              <div style="margin-top:12px">
                You are receiving this message because of your activity on Almarah Foundation&rsquo;s fundraising platform.
                If it was not intended for you, you can safely ignore it.
              </div>
              <div style="margin-top:8px">&copy; <?= e((string) ($year ?? gmdate('Y'))) ?> <?= e($orgName) ?>. All rights reserved.</div>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
