<?php
/**
 * Shared shell for every transactional email.
 *
 * One 600px card on a soft pink page: gold top strip, logo, the content rows
 * produced by the helpers in _partials.php (hero first), then a wine footer
 * with the contact details of each office.
 *
 * Email clients are unpredictable, so this layout uses inline styles and
 * tables, with a small <style> block only for phone sizes. The one remote
 * resource is the logo image.
 *
 * Contact details come from config/app.php → org (legal_name, website, email,
 * phone, address). The logo, "Contact us" link and the list of offices shown in
 * the footer have built-in defaults below; to change them, add any of
 * 'email_logo', 'contact_url' or 'offices' to the org array in config/app.php
 * (same shape as $defaultOffices). Missing values simply fall back, so nothing
 * here can break a send.
 *
 * @var string $content  rows produced by the email_* helpers
 */

$appName    = (string) ($appName ?? config('app.name', 'Almarah Foundation'));
$org        = is_array($org ?? null) ? $org : [];
$orgName    = (string) ($org['legal_name'] ?? $appName);
$orgSite    = (string) ($org['website'] ?? 'https://www.almarah.org');
$orgEmail   = (string) ($org['email'] ?? 'info@almarah.org');
$orgPhone   = (string) ($org['phone'] ?? '');
$orgAddress = (string) ($org['address'] ?? '');

// Defaults for the logo, contact link and footer offices.
$defaultLogo    = 'https://almarah.org.uk/wp-content/uploads/2026/09/header-logo-01.png';
$defaultContact = 'https://almarah.org/contact';
$defaultOffices = [
    [
        'region'  => 'Pakistan',
        'name'    => 'Almarah Foundation',
        'address' => ['3 Canal Park, Gulberg-II,', 'Lahore, Pakistan'],
        'phones'  => [['Phone', '+92 326 8050116'], ['UAN', '(042) 111 111 130']],
    ],
    [
        'region'  => 'United Kingdom',
        'name'    => 'Almarah Foundation UK',
        'address' => ['17 Chamber House Drive,', 'Rochdale, England, OL11 3LS'],
        'phones'  => [['Phone', '+44 7540 951882']],
    ],
];

$logoUrl    = trim((string) ($org['email_logo'] ?? '')) !== '' ? trim((string) $org['email_logo']) : $defaultLogo;
$contactUrl = trim((string) ($org['contact_url'] ?? '')) !== '' ? trim((string) $org['contact_url']) : $defaultContact;
$preheader  = trim((string) ($preheader ?? ''));

$sans  = 'Arial,Helvetica,sans-serif';
$serif = "Georgia,'Times New Roman',serif";

// Offices shown in the footer. Falls back to the single address/phone.
$offices = [];
foreach ((array) ($org['offices'] ?? $defaultOffices) as $office) {
    if (is_array($office) && (!empty($office['name']) || !empty($office['region']))) {
        $offices[] = $office;
    }
}
if ($offices === [] && ($orgAddress !== '' || $orgPhone !== '')) {
    $offices[] = [
        'region'  => '',
        'name'    => $orgName,
        'address' => $orgAddress !== '' ? [$orgAddress] : [],
        'phones'  => $orgPhone !== '' ? [['Phone', $orgPhone]] : [],
    ];
}
$telHref = static fn (string $number): string => 'tel:' . preg_replace('/[^0-9+]/', '', $number);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="color-scheme" content="light only">
  <meta name="supported-color-schemes" content="light only">
  <title><?= e((string) ($heading ?? $appName)) ?></title>
  <style>
    body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table { border-collapse: collapse; }
    img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
    a { text-decoration: none; }
    @media only screen and (max-width: 620px) {
      .em-outer  { padding: 14px 8px !important; }
      .em-pad    { padding-left: 20px !important; padding-right: 20px !important; }
      .em-hero   { padding: 34px 20px 32px 20px !important; }
      .em-h1     { font-size: 26px !important; line-height: 34px !important; }
      .em-amount { font-size: 34px !important; line-height: 40px !important; }
      .em-logo   { width: 150px !important; }
      .em-foot   { padding: 30px 18px 8px 18px !important; }
      .em-btn a  { display: block !important; padding-left: 18px !important; padding-right: 18px !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#faf0f4;font-family:<?= e($sans) ?>;color:#5a2040">
  <?php if ($preheader !== ''): ?>
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;color:#faf0f4"><?= e($preheader) ?></div>
  <?php endif; ?>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#FAF0F4" style="background-color:#faf0f4">
    <tr>
      <td class="em-outer" align="center" style="padding:28px 12px">

        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFFFFF"
               style="width:100%;max-width:600px;background-color:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #ebd3de">

          <!-- gold accent strip -->
          <tr>
            <td bgcolor="#F2C200" height="6" style="background-color:#f2c200;font-size:0;line-height:0;height:6px">&nbsp;</td>
          </tr>

          <!-- logo -->
          <tr>
            <td align="center" bgcolor="#FFFFFF" style="padding:30px 30px 24px 30px;background-color:#ffffff">
              <a href="<?= e($orgSite) ?>" target="_blank" rel="noopener" style="text-decoration:none">
                <?php if ($logoUrl !== ''): ?>
                  <img class="em-logo" src="<?= e($logoUrl) ?>" alt="<?= e($orgName) ?>" width="170"
                       style="display:block;border:0;width:170px;max-width:170px;height:auto;margin:0 auto">
                <?php else: ?>
                  <span style="font-family:<?= e($serif) ?>;font-size:24px;line-height:30px;color:#a92d63;font-weight:bold"><?= e($orgName) ?></span>
                <?php endif; ?>
              </a>
            </td>
          </tr>

          <!-- hero + body rows -->
          <?= $content ?>

          <!-- breathing room before the footer -->
          <tr>
            <td height="34" style="font-size:0;line-height:0;height:34px">&nbsp;</td>
          </tr>

          <!-- footer -->
          <tr>
            <td class="em-foot" bgcolor="#8A2050" style="background-color:#8a2050;padding:36px 30px 10px 30px">

              <p style="margin:0 0 24px 0;text-align:center;font-family:<?= e($serif) ?>;font-size:18px;line-height:26px;color:#ffd740">
                Need help? We&rsquo;re here for you.
              </p>

              <?php foreach ($offices as $office): ?>
                <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 14px 0">
                  <tr>
                    <td bgcolor="#9A3A62" style="background-color:#9a3a62;border:1px solid #b25a7e;border-radius:12px;padding:20px 22px" valign="top">
                      <?php if (!empty($office['region'])): ?>
                        <div style="font-family:<?= e($sans) ?>;font-size:11px;line-height:16px;letter-spacing:2px;font-weight:bold;color:#ffd740;text-transform:uppercase;margin-bottom:10px"><?= e((string) $office['region']) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($office['name'])): ?>
                        <div style="font-family:<?= e($sans) ?>;font-size:14px;line-height:22px;color:#ffffff;font-weight:bold"><?= e((string) $office['name']) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($office['address'])): ?>
                        <div style="margin-top:4px;font-family:<?= e($sans) ?>;font-size:13px;line-height:21px;color:#f5d5e3">
                          <?= implode('<br>', array_map(static fn ($l): string => e((string) $l), (array) $office['address'])) ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($office['phones'])): ?>
                        <div style="margin-top:12px;font-family:<?= e($sans) ?>;font-size:13px;line-height:22px;color:#f5d5e3">
                          <?php foreach ((array) $office['phones'] as $phone): ?>
                            <?php if (is_array($phone) && count($phone) >= 2): ?>
                              <strong style="color:#ffffff"><?= e((string) $phone[0]) ?>:</strong>
                              <a href="<?= e($telHref((string) $phone[1])) ?>" style="color:#ffd740;text-decoration:none"><?= e((string) $phone[1]) ?></a><br>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                </table>
              <?php endforeach; ?>

              <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="center" style="padding:22px 0 0 0;border-top:1px solid #b8486f">

                    <table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" style="margin-bottom:14px">
                      <tr>
                        <td bgcolor="#F2C200" align="center" style="background-color:#f2c200;border-radius:6px">
                          <a href="<?= e($contactUrl) ?>" target="_blank" rel="noopener"
                             style="display:inline-block;padding:11px 24px;font-family:<?= e($sans) ?>;font-size:13px;line-height:18px;font-weight:bold;color:#1a0810;text-decoration:none;border-radius:6px">
                            Contact Us &mdash; <?= e(preg_replace('#^https?://(www\.)?#', '', $contactUrl)) ?>
                          </a>
                        </td>
                      </tr>
                    </table>

                    <div style="font-family:<?= e($sans) ?>;font-size:13px;line-height:22px;color:#f5d5e3">
                      <a href="<?= e($orgSite) ?>" target="_blank" rel="noopener" style="color:#ffffff;text-decoration:none;font-weight:bold"><?= e(preg_replace('#^https?://#', '', $orgSite)) ?></a>
                      &nbsp;&nbsp;|&nbsp;&nbsp;
                      <a href="mailto:<?= e($orgEmail) ?>" style="color:#ffffff;text-decoration:none;font-weight:bold"><?= e($orgEmail) ?></a>
                    </div>

                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding:16px 0 24px 0;font-family:<?= e($sans) ?>;font-size:11px;line-height:18px;color:#e0a8c0">
                    You are receiving this message because of your activity on Almarah Foundation&rsquo;s fundraising platform.
                    If it was not intended for you, you can safely ignore it.<br>
                    &copy; <?= e((string) ($year ?? gmdate('Y'))) ?> <?= e($orgName) ?>. All rights reserved.
                  </td>
                </tr>
              </table>

            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>
</body>
</html>
