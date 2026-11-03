-- Removes the per-control finding fields (gap, business impact, risk, recommendation,
-- owner, target date), which nothing reads any more. DESTRUCTIVE: their text is
-- deleted. Deploy the code first:
--
--     mysql <database> < sql/2026-11-13-remove-control-findings.sql

ALTER TABLE assessment_items
    DROP COLUMN gap_description,
    DROP COLUMN business_impact,
    DROP COLUMN risk_rating,
    DROP COLUMN recommendation,
    DROP COLUMN owner_name,
    DROP COLUMN target_date;
