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
<?= email_heading($heading, 'Update published') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Fundraiser' => (string) ($fundraiser_title ?? ''),
]) ?>
<?= email_button((string) ($cta_label ?? 'View your fundraiser'), (string) ($cta_url ?? '')) ?>
<?= email_signoff($orgName) ?>
