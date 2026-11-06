-- Removes the per-control finding fields (gap, business impact, risk, recommendation,
-- owner, target date), which nothing reads any more. DESTRUCTIVE: their text is
-- deleted. Deploy the code first:
--
--     mysql <database> < sql/2026-11-13-remove-control-findings.sql
--
-- Safe to run more than once: each column is dropped only if it still exists.

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessment_items DROP COLUMN gap_description', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessment_items' AND column_name = 'gap_description');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessment_items DROP COLUMN business_impact', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessment_items' AND column_name = 'business_impact');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessment_items DROP COLUMN risk_rating', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessment_items' AND column_name = 'risk_rating');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessment_items DROP COLUMN recommendation', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessment_items' AND column_name = 'recommendation');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessment_items DROP COLUMN owner_name', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessment_items' AND column_name = 'owner_name');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessment_items DROP COLUMN target_date', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessment_items' AND column_name = 'target_date');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
