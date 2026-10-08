-- =============================================================================
-- 0003 — Fundraisers, updates, teams, donors
-- =============================================================================

CREATE TABLE IF NOT EXISTS `fundraisers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_user_id` INT UNSIGNED NOT NULL,
  `campaign_id` INT UNSIGNED NULL,
  `category_id` INT UNSIGNED NULL,
  `title` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `story` MEDIUMTEXT NOT NULL,
  `impact_statement` VARCHAR(180) NULL,
  `cover_image_path` VARCHAR(255) NULL,
  `goal_minor` BIGINT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `start_at` DATE NULL,
  `end_at` DATE NULL,
  `status` ENUM('draft','pending_review','changes_requested','approved','published','paused','rejected','archived') NOT NULL DEFAULT 'draft',
  `approval_status` ENUM('pending','approved','rejected','changes_requested') NOT NULL DEFAULT 'pending',
  `rejection_reason` TEXT NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fundraisers_slug_unique` (`slug`),
  KEY `fundraisers_owner_idx` (`owner_user_id`),
  KEY `fundraisers_status_idx` (`status`),
  KEY `fundraisers_approval_idx` (`approval_status`),
  KEY `fundraisers_featured_idx` (`featured`),
  KEY `fundraisers_end_idx` (`end_at`),
  KEY `fundraisers_campaign_idx` (`campaign_id`),
  KEY `fundraisers_category_idx` (`category_id`),
  CONSTRAINT `fk_fundraisers_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fundraisers_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fundraisers_category` FOREIGN KEY (`category_id`) REFERENCES `fundraiser_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_fundraisers_goal` CHECK (`goal_minor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fundraiser_updates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fundraiser_id` INT UNSIGNED NOT NULL,
  `author_user_id` INT UNSIGNED NULL,
  `title` VARCHAR(180) NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `image_path` VARCHAR(255) NULL,
  `status` ENUM('published','hidden') NOT NULL DEFAULT 'published',
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fu_fundraiser_idx` (`fundraiser_id`,`published_at`),
  CONSTRAINT `fk_fu_fundraiser` FOREIGN KEY (`fundraiser_id`) REFERENCES `fundraisers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fu_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teams` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_user_id` INT UNSIGNED NOT NULL,
  `campaign_id` INT UNSIGNED NULL,
  `name` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  `goal_minor` BIGINT UNSIGNED NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `status` ENUM('active','archived') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_slug_unique` (`slug`),
  KEY `teams_owner_idx` (`owner_user_id`),
  KEY `teams_status_idx` (`status`),
  CONSTRAINT `fk_teams_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teams_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_members` (
  `team_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('owner','member') NOT NULL DEFAULT 'member',
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`,`user_id`),
  KEY `team_members_user_idx` (`user_id`),
  CONSTRAINT `fk_tm_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_invitations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `invited_by` INT UNSIGNED NULL,
  `status` ENUM('pending','accepted','revoked','expired') NOT NULL DEFAULT 'pending',
  `expires_at` TIMESTAMP NOT NULL,
  `accepted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_inv_token_unique` (`token_hash`),
  KEY `team_inv_team_idx` (`team_id`,`status`),
  CONSTRAINT `fk_ti_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ti_inviter` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Donor identity is kept separate from user accounts: guests can donate.
CREATE TABLE IF NOT EXISTS `donors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(40) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `donors_email_idx` (`email`),
  KEY `donors_user_idx` (`user_id`),
  CONSTRAINT `fk_donors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
