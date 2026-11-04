-- An assessment can be compared against any other assessment of the same
-- client and standard. The Baseline/Interim/Final type and the "no baseline"
-- flag are gone; the chosen assessment moves to compare_assessment_id.
--
--     mysql <database> < sql/2026-11-21-compare-assessments.sql

ALTER TABLE assessments
    CHANGE COLUMN baseline_assessment_id compare_assessment_id bigint unsigned NOT NULL DEFAULT 0,
    DROP COLUMN assessment_type,
    DROP COLUMN no_baseline;
