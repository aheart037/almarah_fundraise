<?php
/** @var array<string,mixed> $org */
require __DIR__ . '/_partials.php';

$orgName = (string) (is_array($org ?? null) ? ($org['legal_name'] ?? '') : '');
$orgName = $orgName !== '' ? $orgName : (string) config('app.name', 'Almarah Foundation');
$name     = trim((string) ($donor_name ?? ''));
$heading  = (string) ($heading ?? '');
$body     = (string) ($body ?? '');
$greeting = $name !== '' ? 'Dear ' . $name . ',' : 'Hello,';
?>
<?= email_heading($heading, 'Good news') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Amount'     => (string) ($amount ?? ''),
    'Supporter'  => (string) ($supporter_name ?? ''),
    'Fundraiser' => (string) ($fundraiser_title ?? ''),
]) ?>
<?= email_button((string) ($cta_label ?? 'View your donations'), (string) ($cta_url ?? '')) ?>
<?= email_note('Say thank you within a day. Donors who are thanked personally are far more likely to give again and to share your page.') ?>
<?= email_signoff($orgName) ?>
