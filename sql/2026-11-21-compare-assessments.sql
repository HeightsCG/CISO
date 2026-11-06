-- An assessment can be compared against any other assessment of the same
-- client and standard. The Baseline/Interim/Final type and the "no baseline"
-- flag are gone; the chosen assessment moves to compare_assessment_id.
--
--     mysql <database> < sql/2026-11-21-compare-assessments.sql
--
-- Safe to run more than once: each column is dropped only if it still exists.

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments CHANGE COLUMN baseline_assessment_id compare_assessment_id bigint unsigned NOT NULL DEFAULT 0', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'baseline_assessment_id');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN assessment_type', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'assessment_type');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN no_baseline', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'no_baseline');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
