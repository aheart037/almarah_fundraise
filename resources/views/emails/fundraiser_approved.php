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
<?= email_heading($heading, 'You are live') ?>
<?= email_paragraph($greeting . ' ' . $body) ?>
<?= email_rows([
    'Fundraiser' => (string) ($fundraiser_title ?? ''),
    'Status'     => 'Live and accepting donations',
]) ?>
<?= email_button((string) ($cta_label ?? 'Share your fundraiser'), (string) ($cta_url ?? '')) ?>
<?= email_fallback_url((string) ($cta_url ?? '')) ?>
<?= email_list([
    'Share the link by WhatsApp first — it raises the most for most fundraisers.',
    'Ask five people personally rather than posting to a large group.',
    'Post an update every week or two so supporters can see what their money is doing.',
]) ?>
<?= email_signoff($orgName) ?>
