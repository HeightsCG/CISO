-- Removes fields nothing reads any more: the assessment write-up (scope,
-- method, consultant narrative), governing document sections, and the
-- frameworks chosen per project. DESTRUCTIVE: the text in these columns and
-- tables is deleted. Back up first if any of it should be kept:
--
--     mysql <database> < sql/2026-11-09-remove-writeup-and-project-frameworks.sql
--
-- Safe to run more than once: each column is dropped only if it still exists.

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN assessment_scope', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'assessment_scope');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN assessment_method', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'assessment_method');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN posture_text', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'posture_text');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN strengths_text', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'strengths_text');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN concerns_text', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'concerns_text');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN readiness_text', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'readiness_text');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE assessments DROP COLUMN decisions_text', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assessments' AND column_name = 'decisions_text');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS assessment_documents;

DROP TABLE IF EXISTS project_standards;
