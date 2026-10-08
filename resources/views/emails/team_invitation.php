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
<?= email_heading($heading, 'Team invitation') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Team'    => (string) ($team_name ?? ''),
    'Invited by' => (string) ($inviter ?? ''),
]) ?>
<?= email_button((string) ($cta_label ?? 'Join the team'), (string) ($cta_url ?? '')) ?>
<?= email_fallback_url((string) ($cta_url ?? '')) ?>
<?= email_paragraph('The invitation is tied to this email address, so please sign in or register with the same address to accept it. If you were not expecting it, you can ignore this message.') ?>
<?= email_signoff($orgName) ?>
