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
<?= email_heading($heading, 'Account ready') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_list([
    'Build your page: add a title, a photo and the story of why this cause matters to you.',
    'Set a goal and a date — a clear target raises more than an open-ended page.',
    'Share the link with family, friends and colleagues, then post updates as you go.',
]) ?>
<?= email_button((string) ($cta_label ?? 'Open your dashboard'), (string) ($cta_url ?? '')) ?>
<?= email_fallback_url((string) ($cta_url ?? '')) ?>
<?= email_note('Almarah Foundation never asks for your password, card details or one-time codes by email.') ?>
<?= email_signoff($orgName, 'Welcome aboard,') ?>
