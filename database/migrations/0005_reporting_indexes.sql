-- =============================================================================
-- 0005 — Reporting views and helper indexes
--
-- The views are the single source of truth for "amount raised": only verified
-- completed donations are counted. Application code and dashboards read these
-- rather than re-deriving totals with slightly different filters.
-- =============================================================================

CREATE OR REPLACE VIEW `v_fundraiser_totals` AS
SELECT
  f.`id`                                                          AS `fundraiser_id`,
  COALESCE(SUM(CASE WHEN d.`status` = 'completed' THEN d.`amount_minor` ELSE 0 END), 0)         AS `raised_minor`,
  COUNT(DISTINCT CASE WHEN d.`status` = 'completed' THEN d.`id` END)                            AS `donor_count`,
  COALESCE(SUM(CASE WHEN d.`status` IN ('pending','processing') THEN d.`amount_minor` ELSE 0 END), 0) AS `pending_minor`,
  COUNT(DISTINCT CASE WHEN d.`status` IN ('pending','processing') THEN d.`id` END)              AS `pending_count`
FROM `fundraisers` f
LEFT JOIN `donations` d ON d.`fundraiser_id` = f.`id`
GROUP BY f.`id`;

CREATE OR REPLACE VIEW `v_team_totals` AS
SELECT
  t.`id`                                                          AS `team_id`,
  COALESCE(SUM(CASE WHEN d.`status` = 'completed' THEN d.`amount_minor` ELSE 0 END), 0) AS `raised_minor`,
  COUNT(DISTINCT CASE WHEN d.`status` = 'completed' THEN d.`id` END)                    AS `donor_count`
FROM `teams` t
LEFT JOIN `donations` d ON d.`team_id` = t.`id`
GROUP BY t.`id`;

CREATE OR REPLACE VIEW `v_campaign_totals` AS
SELECT
  c.`id`                                                          AS `campaign_id`,
  COALESCE(SUM(CASE WHEN d.`status` = 'completed' THEN d.`amount_minor` ELSE 0 END), 0) AS `raised_minor`,
  COUNT(DISTINCT CASE WHEN d.`status` = 'completed' THEN d.`id` END)                    AS `donor_count`
FROM `campaigns` c
LEFT JOIN `donations` d ON d.`campaign_id` = c.`id`
GROUP BY c.`id`;

CREATE OR REPLACE VIEW `v_donation_status_summary` AS
SELECT
  `status`,
  COUNT(*)                AS `donation_count`,
  COALESCE(SUM(`amount_minor`), 0) AS `amount_minor`
FROM `donations`
GROUP BY `status`;

-- Speed up the public listing filters.
CREATE INDEX `idx_fundraisers_public` ON `fundraisers` (`status`, `approval_status`, `featured`, `end_at`);
CREATE INDEX `idx_donations_donor_listing` ON `donations` (`fundraiser_id`, `status`, `created_at`);
