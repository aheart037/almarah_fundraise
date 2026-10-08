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
<?= email_heading($heading, 'Security notice') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_list([
    'All other signed-in sessions are still active — sign out of them from Account security if you are unsure.',
    'Choose a password you do not use anywhere else, and store it in a password manager.',
    'Check your account activity for anything you do not recognise.',
]) ?>
<?= email_button((string) ($cta_label ?? 'Contact support'), (string) ($cta_url ?? '')) ?>
<?= email_signoff($orgName) ?>
