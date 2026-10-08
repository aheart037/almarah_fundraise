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
<?= email_heading($heading, 'Password reset') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_button((string) ($cta_label ?? 'Choose a new password'), (string) ($cta_url ?? '')) ?>
<?= email_fallback_url((string) ($cta_url ?? '')) ?>
<?= email_note('This link can be used once and expires in ' . (string) ($expires ?? '2 hours') . '.', 'warning') ?>
<?= email_paragraph('Nobody at Almarah Foundation will ever ask you for this link or for your password. If you did not request a reset, no action is needed.') ?>
<?= email_signoff($orgName) ?>
