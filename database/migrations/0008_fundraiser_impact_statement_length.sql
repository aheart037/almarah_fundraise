-- ---------------------------------------------------------------------------
-- 0008: fundraiser impact line head-room.
--
-- fundraisers.impact_statement shipped as VARCHAR(180) while the create/edit
-- form and validator allowed 500 characters, so a longer line was rejected
-- by MySQL strict mode at insert time and the fundraiser was never created.
-- The form and validator now enforce the 180 characters the column has
-- always held; this migration widens the column so the limit can be raised
-- safely later. Existing values are preserved.
-- ---------------------------------------------------------------------------

ALTER TABLE `fundraisers`
  MODIFY COLUMN `impact_statement` VARCHAR(500) NULL DEFAULT NULL;
