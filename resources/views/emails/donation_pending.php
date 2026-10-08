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
<?= email_heading($heading, 'In progress') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Reference'      => (string) ($reference ?? ''),
    'Amount'         => (string) ($amount ?? ''),
    'Payment method' => (string) ($gateway ?? ''),
    'Status'         => (string) ($status ?? 'Processing'),
]) ?>
<?= email_button((string) ($cta_label ?? ''), (string) ($cta_url ?? '')) ?>
<?= email_note('No money will be treated as received until the payment provider confirms it. If the payment does not complete, any amount on hold is released by your bank on its own schedule.', 'warning') ?>
<?= email_paragraph('You will receive one more email when the payment is either confirmed or declined. You do not need to give again.') ?>
<?= email_signoff($orgName) ?>
