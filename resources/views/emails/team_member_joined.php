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
<?= email_heading($heading, 'Team growing') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Team'   => (string) ($team_name ?? ''),
    'Member' => (string) ($member_name ?? ''),
]) ?>
<?= email_list([
    'Welcome them with a short message and a link to their page.',
    'Agree who is asking which part of your network, so you are not asking the same people twice.',
    'Cheer each other on publicly — teams that compare progress weekly raise more.',
]) ?>
<?= email_button((string) ($cta_label ?? 'Manage your team'), (string) ($cta_url ?? '')) ?>
<?= email_signoff($orgName) ?>
