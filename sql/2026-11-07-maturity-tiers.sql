-- Maturity frameworks (NIST CSF 2.0): the consultant's current and target
-- Tiers for governance and for management. 0 means not set. Additive:
--
--     mysql <database> < sql/2026-11-07-maturity-tiers.sql

ALTER TABLE assessments
    ADD COLUMN tier_governance tinyint unsigned NOT NULL DEFAULT 0 AFTER ssi_note,
    ADD COLUMN tier_management tinyint unsigned NOT NULL DEFAULT 0 AFTER tier_governance,
    ADD COLUMN target_tier_governance tinyint unsigned NOT NULL DEFAULT 0 AFTER tier_management,
    ADD COLUMN target_tier_management tinyint unsigned NOT NULL DEFAULT 0 AFTER target_tier_governance;
