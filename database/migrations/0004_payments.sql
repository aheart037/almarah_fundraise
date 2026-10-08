-- =============================================================================
-- 0004 — Donations and payment transactions
--
-- Money is stored exclusively as integer minor units (paisa for PKR).
-- Only rows with status='completed' contribute to fundraiser totals.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `donations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_reference` VARCHAR(40) NOT NULL,
  `donor_id` INT UNSIGNED NULL,
  `fundraiser_id` INT UNSIGNED NULL,
  `campaign_id` INT UNSIGNED NULL,
  `team_id` INT UNSIGNED NULL,
  `gateway_id` INT UNSIGNED NOT NULL,
  `amount_minor` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `donor_message` VARCHAR(255) NULL,
  `anonymous` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('pending','processing','completed','failed','cancelled','refunded','abandoned') NOT NULL DEFAULT 'pending',
  `receipt_email_status` ENUM('not_sent','queued','sent','failed') NOT NULL DEFAULT 'not_sent',
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `donations_reference_unique` (`public_reference`),
  KEY `donations_fundraiser_status_idx` (`fundraiser_id`,`status`),
  KEY `donations_campaign_status_idx` (`campaign_id`,`status`),
  KEY `donations_team_status_idx` (`team_id`,`status`),
  KEY `donations_status_idx` (`status`),
  KEY `donations_gateway_idx` (`gateway_id`),
  KEY `donations_created_idx` (`created_at`),
  KEY `donations_donor_idx` (`donor_id`),
  CONSTRAINT `fk_donations_donor` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_donations_fundraiser` FOREIGN KEY (`fundraiser_id`) REFERENCES `fundraisers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_donations_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_donations_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_donations_gateway` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`),
  CONSTRAINT `chk_donations_amount` CHECK (`amount_minor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per gateway registration attempt. The callback state validates the
-- return trip; the provider transaction id is unique per gateway.
CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `donation_id` BIGINT UNSIGNED NOT NULL,
  `gateway_id` INT UNSIGNED NOT NULL,
  `environment` ENUM('sandbox','live') NOT NULL,
  `merchant_order_id` VARCHAR(64) NOT NULL,
  `provider_transaction_id` VARCHAR(128) NULL,
  `provider_unique_id` VARCHAR(128) NULL,
  `provider_order_id` VARCHAR(128) NULL,
  `callback_state_hash` CHAR(64) NOT NULL,
  `callback_state_expires_at` TIMESTAMP NOT NULL,
  `provider_response_code` VARCHAR(32) NULL,
  `provider_response_description` VARCHAR(255) NULL,
  `amount_minor` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL,
  `status` ENUM('pending','processing','completed','failed','cancelled','refunded','abandoned') NOT NULL DEFAULT 'pending',
  `request_reference` VARCHAR(64) NOT NULL,
  `idempotency_key` VARCHAR(128) NOT NULL,
  `registration_payload_hash` CHAR(64) NULL,
  `registered_at` TIMESTAMP NULL DEFAULT NULL,
  `callback_received_at` TIMESTAMP NULL DEFAULT NULL,
  `finalized_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `refunded_at` TIMESTAMP NULL DEFAULT NULL,
  `last_reconciled_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pt_merchant_order_unique` (`merchant_order_id`),
  UNIQUE KEY `pt_idempotency_unique` (`idempotency_key`),
  UNIQUE KEY `pt_provider_txn_unique` (`gateway_id`,`provider_transaction_id`),
  KEY `pt_donation_idx` (`donation_id`),
  KEY `pt_status_idx` (`status`),
  KEY `pt_provider_order_idx` (`gateway_id`,`provider_order_id`),
  CONSTRAINT `fk_pt_donation` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pt_gateway` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raw callback bodies are stored with secrets removed, for auditability.
CREATE TABLE IF NOT EXISTS `payment_callbacks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` BIGINT UNSIGNED NULL,
  `gateway_id` INT UNSIGNED NOT NULL,
  `callback_method` ENUM('GET','POST') NOT NULL,
  `raw_safe_payload_json` JSON NULL,
  `received_transaction_id` VARCHAR(128) NULL,
  `validation_result` ENUM('accepted','rejected','duplicate','ignored','error') NOT NULL,
  `reason` VARCHAR(255) NULL,
  `processed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pc_transaction_idx` (`payment_transaction_id`),
  KEY `pc_result_idx` (`validation_result`),
  KEY `pc_created_idx` (`created_at`),
  CONSTRAINT `fk_pc_transaction` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pc_gateway` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refunds are idempotent per transaction via a unique idempotency key, so a
-- retried refund request can never charge the merchant twice.
CREATE TABLE IF NOT EXISTS `payment_refunds` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` BIGINT UNSIGNED NOT NULL,
  `donation_id` BIGINT UNSIGNED NOT NULL,
  `gateway_id` INT UNSIGNED NOT NULL,
  `amount_minor` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL,
  `status` ENUM('pending','completed','failed','rejected') NOT NULL DEFAULT 'pending',
  `idempotency_key` VARCHAR(128) NOT NULL,
  `provider_response_code` VARCHAR(32) NULL,
  `provider_response_description` VARCHAR(255) NULL,
  `requested_by` INT UNSIGNED NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pr_idempotency_unique` (`idempotency_key`),
  KEY `pr_transaction_idx` (`payment_transaction_id`),
  KEY `pr_status_idx` (`status`),
  CONSTRAINT `fk_pr_transaction` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_donation` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_gateway` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Short-lived advisory locks so two callbacks cannot finalize one transaction
-- concurrently, and so a refusal cannot be followed by a refund attempt.
CREATE TABLE IF NOT EXISTS `payment_locks` (
  `lock_key` VARCHAR(150) NOT NULL,
  `owner_token` VARCHAR(64) NOT NULL,
  `acquired_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  PRIMARY KEY (`lock_key`),
  KEY `payment_locks_expiry_idx` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
