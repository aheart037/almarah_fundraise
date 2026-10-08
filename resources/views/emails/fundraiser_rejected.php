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
<?= email_heading($heading, 'Changes requested') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Fundraiser' => (string) ($fundraiser_title ?? ''),
    'Status'     => 'Changes requested',
]) ?>
<?= email_list([
    'Make the change in your fundraiser page.',
    'Send it back for review — you will not lose your draft or any donations already collected.',
    'Reply to this email if anything in the request is unclear.',
]) ?>
<?= email_button((string) ($cta_label ?? 'Edit your fundraiser'), (string) ($cta_url ?? '')) ?>
<?= email_signoff($orgName) ?>
