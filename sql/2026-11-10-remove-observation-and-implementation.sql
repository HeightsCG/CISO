-- Removes the per-control observation (notes) and implementation description,
-- which nothing reads any more. DESTRUCTIVE: their text is deleted. Deploy the
-- code first:
--
--     mysql <database> < sql/2026-11-10-remove-observation-and-implementation.sql
--
-- Safe to run more than once: each column is dropped only if it still exists.

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessment_items DROP COLUMN notes', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessment_items' AND column_name = 'notes');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessment_items DROP COLUMN implementation_description', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessment_items' AND column_name = 'implementation_description');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
