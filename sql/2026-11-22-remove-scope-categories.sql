-- Every category of a standard is always in scope, so the per-assessment
-- category choice is removed.
--
--     mysql <database> < sql/2026-11-22-remove-scope-categories.sql

ALTER TABLE assessments
    DROP COLUMN scope_categories;
