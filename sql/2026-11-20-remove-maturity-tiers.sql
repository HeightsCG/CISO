-- Maturity tiers were never shown or used anywhere, so they are removed.
--
--     mysql <database> < sql/2026-11-20-remove-maturity-tiers.sql

ALTER TABLE assessments
    DROP COLUMN tier_governance,
    DROP COLUMN tier_management,
    DROP COLUMN target_tier_governance,
    DROP COLUMN target_tier_management;
