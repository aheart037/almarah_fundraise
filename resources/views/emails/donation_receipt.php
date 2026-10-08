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
<?= email_heading($heading, 'Official receipt') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Receipt number' => (string) ($reference ?? ''),
    'Date'           => (string) ($receipt_date ?? gmdate('j F Y')),
    'Amount'         => (string) ($amount ?? ''),
    'Currency'       => (string) ($currency ?? 'PKR'),
    'Payment method' => (string) ($gateway ?? ''),
    'Status'         => (string) ($status ?? 'Completed'),
    'Designated to'  => (string) ($fundraiser_title ?? ''),
]) ?>
<?= email_note('This email is your receipt. Please keep it for your records — Almarah Foundation can reissue it from our records if it is misplaced.') ?>
<?= email_button((string) ($cta_label ?? 'View the fundraiser'), (string) ($cta_url ?? '')) ?>
<?= email_paragraph('Every rupee given here is spent on the programme your fundraiser supports. If you would like to see how these funds were used, reply to this email and we will send you the latest report.') ?>
<?= email_signoff($orgName, 'Thank you for your generosity,') ?>
