-- Removes fields nothing reads any more: the assessment write-up (scope,
-- method, consultant narrative), governing document sections, and the
-- frameworks chosen per project. DESTRUCTIVE: the text in these columns and
-- tables is deleted. Back up first if any of it should be kept:
--
--     mysql <database> < sql/2026-11-09-remove-writeup-and-project-frameworks.sql

ALTER TABLE assessments
    DROP COLUMN assessment_scope,
    DROP COLUMN assessment_method,
    DROP COLUMN posture_text,
    DROP COLUMN strengths_text,
    DROP COLUMN concerns_text,
    DROP COLUMN readiness_text,
    DROP COLUMN decisions_text;

DROP TABLE IF EXISTS assessment_documents;

DROP TABLE IF EXISTS project_standards;
