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
<?= email_heading($heading, 'Finishing strong') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Fundraiser'  => (string) ($fundraiser_title ?? ''),
    'Days left'   => (string) ($days_left ?? ''),
]) ?>
<?= email_list([
    'Post an update with a photo and a specific number — "Rs 40,000 to go".',
    'Message your most engaged supporters directly; they are the ones who share.',
    'Ask your team members to do the same from their own pages.',
]) ?>
<?= email_button((string) ($cta_label ?? 'Post an update'), (string) ($cta_url ?? '')) ?>
<?= email_signoff($orgName) ?>
