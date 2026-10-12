-- ---------------------------------------------------------------------------
-- 0007: preserve the complete EPG PaymentPage URL returned by Registration.
--
-- Some EPG instances include query parameters on the hosted page URL. The old
-- 255-character column could truncate it and make the POST hand-off invalid.
-- Existing values and donations are preserved.
-- ---------------------------------------------------------------------------

ALTER TABLE `payment_transactions`
  MODIFY COLUMN `provider_payment_page_url` VARCHAR(2048) NULL DEFAULT NULL;
