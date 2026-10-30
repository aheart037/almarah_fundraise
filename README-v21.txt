ALMARAH FUNDRAISE — VERSION 21 PAYMENT-ONLY PATCH

Base repository commit: 01c784f495ff4e3d7203b25fcf35a79b7b043800
Scope: UBL (internal gateway code: etisalat) and Meezan payments only.
No database migrations, credentials, settings changes, styling, layout changes,
or unrelated feature changes are included. The application continues to use
its existing internal status name "completed" for Complete.

INVESTIGATION / ROOT-CAUSE PATHS

UBL:
- Finalization extracted ResponseCode case-insensitively but read approval,
  identity, amount and currency case-sensitively. A success-shaped response
  with differently cased fields was therefore left unverified/Processing.
- Verification required OrderID AND Amount echoes even when an authenticated
  Finalization returned the exact TransactionID saved at registration and an
  ApprovalCode. Compact responses without those echoes could not complete.
- A signed-state return without posted TransactionID was rejected before the
  server could finalize using the TransactionID already stored in the database.

Meezan:
- getOrderStatus.do amounts are minor units, like registration amounts. The old
  parser interpreted a decimal-formatted minor-unit amount (e.g. "1050.00") as
  major units, producing 105000 instead of 1050 and rejecting a paid response.
- Status/amount/currency parsing accepted only selected field casings.
- API error codes were not checked before accepting an accompanying paid
  status; echoed order references were also not checked.
- Hosted OrderStatus 6 (declined) was mapped to Cancelled rather than Failed.

Shared:
- Expired callback state stopped verification even when the bank returned the
  matching bank-issued identifier for the existing payment.
- Donation/payment status writes were separate, without a surrounding database
  transaction. A write failure could leave inconsistent statuses.
- Reconciliation read donation state before acquiring the payment lock and
  did not refresh it after acquiring the lock.

These are code-level findings reproduced with controlled offline responses.
No production database, gateway logs, merchant specification or bank credentials
were supplied. The exact response variant causing the reported live symptom
must still be confirmed in bank sandbox/live acceptance testing.

CHANGES

- UBL Finalization fields are read case-insensitively. Completion still requires
  an authenticated bank call, success ResponseCode and ApprovalCode. A compact
  response must echo the saved TransactionID. Without an echoed TransactionID,
  both matching OrderID and Amount are required. Conflicting/malformed echoed
  identifiers, amount or currency remain unverified; browser success flags are
  never used as payment evidence.
- UBL ReturnPath includes signed state when it fits the existing 256-character
  limit. Existing bank-ID fallback remains available if the bank strips state.
  A valid signed-state return may finalize with the stored TransactionID when
  the POST field is absent; a present contradictory ID is still rejected.
- Meezan reads status fields case-insensitively and preserves minor-unit amounts,
  including zero-only decimal fractions. Paid amounts must be present and match;
  conflicting amounts, currencies, references or API errors cannot complete.
- Meezan decline status 6 becomes Failed; reversed status 3 becomes Cancelled.
  Pending/authorized/unknown statuses remain non-complete.
- Non-2xx HTTP responses from either verification API are not payment proof.
- Late returns are verified only when carrying the saved bank-issued reference;
  expired signed state or a merchant order number alone is not sufficient.
- Donation/payment status changes are committed atomically. Existing callback
  locks, unique payment keys and terminal-state guards are retained; callbacks
  create no new donation/payment rows and never repeat registration.
- Reconciliation re-reads current records inside its payment lock.
- The existing payment result component displays Payment Successful or Payment
  Failed. Failed results include the saved bank error, HTML-escaped. No design
  or stylesheet changes were made.

MODIFIED FILES (overwrite at the same relative paths)

app/Http/Controllers/Public/PaymentController.php
app/Payments/Etisalat/EtisalatApiClient.php
app/Payments/Etisalat/EtisalatGateway.php
app/Payments/Meezan/MeezanApiClient.php
app/Payments/Meezan/MeezanGateway.php
app/Payments/PaymentStatusMapper.php
app/Services/DonationService.php
resources/views/partials/payment-result.php
resources/views/public/donate/response.php

NEW FILES

README-v21.txt
tests/payments/v21.php

DEPLOYMENT

1. Back up the nine existing files above and the production database.
2. Extract this patch over the website root, preserving all paths. This ZIP is
   not a complete application; it intentionally contains only changed/new files.
3. Do not reinstall, reseed, change APP_KEY, or run a database migration.
4. If PHP OPcache does not automatically detect file changes, refresh it using
   the hosting control panel or restart PHP through your normal hosting process.
5. Check bank endpoints, credentials, environment and registered return URLs
   against the merchant account. Existing return scripts and routes are retained.
6. Existing Processing payments can be checked using the existing admin reconcile
   action or the existing CLI worker: php bin/console payments:reconcile
   This performs real bank calls with configured credentials: first validate in
   staging, then reconcile production with the correct merchant environment.
   Do not re-submit a donation form to repair an existing payment.

OFFLINE TESTING COMPLETED

Runtime: PHP CLI 8.4.27; application code retains existing PHP 8.2+ syntax.
- PHP syntax checked across all 224 PHP files in the patched repository: passed.
- git diff --check: passed.
- php tests/payments/v21.php: 69 assertions passed.

The standalone regression suite uses fake HTTP responses, real gateway classes,
return handlers, repositories, status service and in-memory SQLite. It does not
boot the production application, read .env, contact a bank or move money.
Requires pdo_sqlite and mbstring in addition to PHP 8.2+.

Coverage: registration/handoff compatibility; rejected registration; success,
decline and pending updates to both records; return destinations; duplicate
callbacks; lock contention; timeout; forged browser success; late returns;
expired state; HTTP authentication errors; amount/currency/identity mismatches;
field casing; compact UBL Finalization; signed UBL GET returns; missing approval;
contradictory callback IDs; insufficient merchant-only reference; Meezan API
errors/missing amount; scheduled/admin reconciliation; database failure rollback
and retry; no duplicate donation/payment rows; visible result messages and HTML
escaping of bank error descriptions.

TESTING STILL REQUIRED WITH BANK CREDENTIALS

Before production sign-off, test BOTH merchant integrations in the bank sandbox,
then perform bank-approved small live transactions if required:
- successful capture/deposit: bank confirms payment; both stored statuses become
  completed; return page displays Payment Successful;
- actual decline/cancellation: stored payment fails/cancels as appropriate,
  donation is not completed, and Payment Failed plus the bank error is shown;
- pending/authorization/3DS interrupted or API timeout: donation stays Processing
  until reconciliation receives a final verified result;
- duplicate/late bank return, browser refresh and concurrent worker/return:
  same original records, no duplicate donation/payment or repeated registration;
- confirm actual merchant-specific Finalization fields/ApprovalCode, Meezan
  getOrderStatus.do units and OrderStatus values, and HTTPS return routing under
  the deployed domain/subfolder;
- confirm network/TLS access to UBL's configured port (normally 2443), MySQL
  transaction/locking behavior, receipt queueing and existing cron scheduling.

Live TLS, bank enrollment/return URL allowlisting, 3DS, real captures, merchant
response variants, MySQL concurrency and donor email delivery cannot be proven
by offline fixtures. No live credentials were requested, included or used.
