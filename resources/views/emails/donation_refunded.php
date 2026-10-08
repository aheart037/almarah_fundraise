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
<?= email_heading($heading, 'Refund processed') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Reference'      => (string) ($reference ?? ''),
    'Amount'         => (string) ($amount ?? ''),
    'Payment method' => (string) ($gateway ?? ''),
    'Status'         => (string) ($status ?? 'Refunded'),
]) ?>
<?= email_button((string) ($cta_label ?? ''), (string) ($cta_url ?? '')) ?>
<?= email_note('Refunds are returned to the same card, wallet or account that was used for the donation. Banks typically take five to ten working days to show the credit.', 'warning') ?>
<?= email_paragraph('If the refund has not appeared after ten working days, reply to this email with the reference and we will chase it with the bank.') ?>
<?= email_signoff($orgName) ?>
