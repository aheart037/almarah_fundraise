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
<?= email_heading($heading, 'Welcome back') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_button((string) ($cta_label ?? 'Go to your dashboard'), (string) ($cta_url ?? '')) ?>
<?= email_fallback_url((string) ($cta_url ?? '')) ?>
<?= email_note('If you would like your fundraiser re-published, open it from your dashboard and submit it for review — approval is usually quick.', 'success') ?>
<?= email_signoff($orgName, 'Good to have you back,') ?>
