-- Removes per-control milestones, which nothing reads any more. DESTRUCTIVE:
-- the milestones are deleted. Deploy the code first:
--
--     mysql <database> < sql/2026-11-11-remove-milestones.sql

DROP TABLE item_milestones;
