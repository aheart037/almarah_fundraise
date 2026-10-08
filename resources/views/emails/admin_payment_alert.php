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
<?= email_heading($heading, 'Needs attention') ?>
<?= email_paragraph('A payment on the platform needs a human decision.') ?>
<?= email_rows([
    'Reference' => (string) ($reference ?? ''),
    'Amount'    => (string) ($amount ?? ''),
]) ?>
<?= email_paragraph($body) ?>
<?= email_button((string) ($cta_label ?? 'Open the admin dashboard'), (string) ($cta_url ?? '')) ?>
<?= email_note('Open the donation, check what the gateway reports, and reconcile. Do not mark a payment completed unless the provider confirms it.', 'danger') ?>
<?= email_signoff($orgName, 'Automated alert from the payment monitor.') ?>
