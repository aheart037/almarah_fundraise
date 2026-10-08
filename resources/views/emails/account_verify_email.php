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
<?= email_heading($heading, 'One step left') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_button((string) ($cta_label ?? 'Verify my email'), (string) ($cta_url ?? '')) ?>
<?= email_fallback_url((string) ($cta_url ?? '')) ?>
<?= email_note('This link expires in ' . (string) ($expires ?? '48 hours') . '. If it has expired you can request a new one from the sign-in page.', 'warning') ?>
<?= email_paragraph('If you did not create an account, you can ignore this message — no account will be activated without this confirmation.') ?>
<?= email_signoff($orgName) ?>
