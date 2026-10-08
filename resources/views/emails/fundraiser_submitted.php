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
<?= email_heading($heading, 'Under review') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Fundraiser' => (string) ($fundraiser_title ?? ''),
    'Status'     => 'Submitted for review',
    'Next step'  => 'Our team reviews your page and emails you the decision',
]) ?>
<?= email_list([
    'Review usually takes less than two working days.',
    'You will receive an email the moment your page goes live, with a link to share.',
    'In the meantime you can keep editing the page — changes are saved on your draft.',
]) ?>
<?= email_button((string) ($cta_label ?? 'View your fundraiser'), (string) ($cta_url ?? '')) ?>
<?= email_signoff($orgName, 'Thank you,') ?>
