-- Projects no longer carry a status. It was only ever set from the state of
-- their assessments, which does not describe a project with several of them.
-- "Open" now means ongoing or not yet past its end date.
--
--     mysql <database> < sql/2026-11-17-remove-project-status.sql
--
-- Safe to run more than once: each column is dropped only if it still exists.

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE projects DROP COLUMN project_status', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'projects' AND column_name = 'project_status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
