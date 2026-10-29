<?php
/**
 * Donation result / receipt page (/donation/{reference}).
 *
 * The status shown is the status in our database, which is only ever changed
 * by a verified server-to-server response from the payment provider. The page
 * itself is the shared component partials/payment-result.php (v19 redesign),
 * so it matches the page the banks return the donor to.
 *
 * @var array|null $donation
 * @var array $transactions
 * @var string|null $donorMessage
 * @var string|null $expected
 * @var string|null $emptyHeading
 */

$status = (string) ($donation['status'] ?? '');

if ($donation === null) {
    $prOutcome = 'notfound';
    $prReason = '';
    $prTitle = (string) ($emptyHeading ?? 'We could not find that donation');
    $prLead = (string) ($donorMessage ?? 'Check the link in your receipt email, or contact our team with the reference from your bank statement.');
} else {
    // Map the stored status onto the four things a donor needs to know.
    $prOutcome = match ($status) {
        'completed'            => 'completed',
        'processing', 'pending' => 'processing',
        'refunded'             => 'refunded',
        default                => 'failed',
    };
    $prReason = match ($status) {
        'cancelled' => 'cancelled',
        'abandoned' => 'expired',
        default     => 'failed',
    };
}

$prDonation     = $donation ?? null;
$prReference    = (string) ($donation['public_reference'] ?? '');
$prTransactions = $transactions ?? [];
$prStatusUrl    = $prReference !== '' ? base_url('donation/' . rawurlencode($prReference)) : base_url('fundraisers');
$prRetryUrl     = !empty($donation['fundraiser_slug'])
    ? base_url('donate/' . rawurlencode((string) $donation['fundraiser_slug']))
    : base_url('fundraisers');
$prNotice       = ($donation !== null && ($expected ?? null) !== null && $expected !== $status)
    ? 'We checked this donation against our records rather than the page you landed on. This is its real, verified status.'
    : '';

require __DIR__ . '/../../partials/payment-result.php';
