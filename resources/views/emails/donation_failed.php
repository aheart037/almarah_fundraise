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
<?= email_heading($heading, 'Not completed') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Reference'      => (string) ($reference ?? ''),
    'Amount'         => (string) ($amount ?? ''),
    'Payment method' => (string) ($gateway ?? ''),
    'Status'         => (string) ($status ?? 'Failed'),
]) ?>
<?= email_list([
    'Check that the card has enough available balance and that online payments are enabled.',
    'If you selected a bank account debit, confirm the account supports online transactions.',
    'Try a different payment method, or contact your bank if it is declined again.',
]) ?>
<?= email_button((string) ($cta_label ?? 'Try again'), (string) ($cta_url ?? '')) ?>
<?= email_paragraph('If money appears to have left your account for a failed payment, send us this reference and we will investigate with the bank straight away.') ?>
<?= email_signoff($orgName) ?>
