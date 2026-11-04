-- Projects no longer carry a status. It was only ever set from the state of
-- their assessments, which does not describe a project with several of them.
-- "Open" now means ongoing or not yet past its end date.
--
--     mysql <database> < sql/2026-11-17-remove-project-status.sql

ALTER TABLE projects DROP COLUMN project_status;
