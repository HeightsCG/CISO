-- Maturity tiers were never shown or used anywhere, so they are removed.
--
--     mysql <database> < sql/2026-11-20-remove-maturity-tiers.sql
--
-- Safe to run more than once: each column is dropped only if it still exists.

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN tier_governance', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'tier_governance');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN tier_management', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'tier_management');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN target_tier_governance', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'target_tier_governance');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN target_tier_management', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'target_tier_management');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
