-- ---------------------------------------------------------------------------
-- 0006: remember the provider-hosted payment page for the Etisalat/UBL EPG
-- flow.
--
-- The EPG returns a payment portal URL together with a TransactionID at
-- registration time. The payer must POST that TransactionID to the portal, so
-- we store the portal URL and re-validate its host against the gateway
-- allowlist every time we use it. Nothing here is a credential: the URL is a
-- public EPG endpoint and the TransactionID is only meaningful for this one
-- transaction, alongside the merchant credentials we hold.
-- ---------------------------------------------------------------------------

ALTER TABLE `payment_transactions`
  ADD COLUMN `provider_payment_page_url` VARCHAR(255) NULL DEFAULT NULL
  AFTER `provider_unique_id`;
