-- Ongoing projects have no start or end date. Both columns become optional;
-- existing projects keep their dates.
--
--     mysql <database> < sql/2026-11-16-ongoing-projects.sql

ALTER TABLE projects
    MODIFY start_date date NULL DEFAULT NULL,
    MODIFY end_date date NULL DEFAULT NULL;
