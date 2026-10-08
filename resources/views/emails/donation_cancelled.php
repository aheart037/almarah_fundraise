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
<?= email_heading($heading, 'Cancelled') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Reference'      => (string) ($reference ?? ''),
    'Amount'         => (string) ($amount ?? ''),
    'Payment method' => (string) ($gateway ?? ''),
    'Status'         => (string) ($status ?? 'Cancelled'),
]) ?>
<?= email_button((string) ($cta_label ?? ''), (string) ($cta_url ?? '')) ?>
<?= email_note('No money has left your account for this attempt.', 'success') ?>
<?= email_paragraph('You can start again whenever you are ready — the reference above will not be reused, so a new reference will be issued for your next attempt.') ?>
<?= email_signoff($orgName) ?>
