-- Every category of a standard is always in scope, so the per-assessment
-- category choice is removed.
--
--     mysql <database> < sql/2026-11-22-remove-scope-categories.sql
--
-- Safe to run more than once: each column is dropped only if it still exists.

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN scope_categories', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'scope_categories');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
