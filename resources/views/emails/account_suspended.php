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
<?= email_heading($heading, 'Account notice') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_note('While an account is suspended, fundraisers owned by it stop accepting donations and cannot be edited or shared.', 'danger') ?>
<?= email_paragraph('If you believe this is a mistake, reply to this message with any evidence that helps us review the decision.') ?>
<?= email_button((string) ($cta_label ?? 'Contact support'), (string) ($cta_url ?? '')) ?>
<?= email_signoff($orgName) ?>
