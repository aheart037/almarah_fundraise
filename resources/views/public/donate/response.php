<?php
/**
 * Payment result page shown when the bank returns the donor to
 * fundraise-response.php (Meezan and UBL/Etisalat alike): success, being
 * confirmed, or not completed.
 *
 * The outcome is never taken from the browser: it comes from the server-to-
 * server verification done in PaymentController::fundraiseResult(). The page
 * itself is the shared component partials/payment-result.php (v19 redesign).
 *
 * @var string     $outcome    completed|processing|failed
 * @var string     $reason     cancelled|expired|failed|... (failed only)
 * @var string     $reference  public donation reference
 * @var array|null $donation
 * @var string     $retryUrl
 * @var string     $statusUrl
 */

$prOutcome   = (string) $outcome;
$prReason    = (string) ($reason ?? '');
$prDonation  = $donation ?? null;
$prReference = (string) ($reference ?? '');
$prRetryUrl  = (string) $retryUrl;
$prStatusUrl = (string) $statusUrl;

require __DIR__ . '/../../partials/payment-result.php';
