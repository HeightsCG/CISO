-- Removes the per-control observation (notes) and implementation description,
-- which nothing reads any more. DESTRUCTIVE: their text is deleted. Deploy the
-- code first:
--
--     mysql <database> < sql/2026-11-10-remove-observation-and-implementation.sql

ALTER TABLE assessment_items
    DROP COLUMN notes,
    DROP COLUMN implementation_description;
