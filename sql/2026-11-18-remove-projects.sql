-- Projects are gone. Assessments, evidence and evidence folders now belong to a
-- client directly; reports belong to an assessment. Each assessment takes over
-- its project's client and dates, evidence and folders take their project's
-- client, and a project report moves to that project's first assessment.
-- Invoices and subscriptions keep their client and lose the project link.
--
--     mysql <database> < sql/2026-11-18-remove-projects.sql

ALTER TABLE assessments
    ADD COLUMN client_id bigint unsigned NOT NULL DEFAULT 0 AFTER company_id,
    ADD COLUMN start_date date NULL DEFAULT NULL AFTER assessment_type,
    ADD COLUMN end_date date NULL DEFAULT NULL AFTER start_date;

UPDATE assessments a
    JOIN projects p ON p.id = a.project_id
SET
    a.client_id = p.client_id,
    a.start_date = p.start_date,
    a.end_date = p.end_date;

ALTER TABLE evidence
    ADD COLUMN client_id bigint unsigned NOT NULL DEFAULT 0 AFTER company_id;

UPDATE evidence e
    JOIN projects p ON p.id = e.project_id
SET
    e.client_id = p.client_id;

ALTER TABLE evidence_folders
    ADD COLUMN client_id bigint unsigned NOT NULL DEFAULT 0 AFTER company_id;

UPDATE evidence_folders f
    JOIN projects p ON p.id = f.project_id
SET
    f.client_id = p.client_id;

ALTER TABLE reports
    ADD COLUMN assessment_id bigint unsigned NOT NULL DEFAULT 0 AFTER company_id;

UPDATE reports r
    JOIN (
        SELECT project_id, MIN(id) AS assessment_id
        FROM assessments
        WHERE deleted = 0
        GROUP BY project_id
    ) first_assessment ON first_assessment.project_id = r.project_id
SET
    r.assessment_id = first_assessment.assessment_id;

ALTER TABLE assessments
    DROP INDEX idx_assessments_project,
    DROP COLUMN project_id,
    ADD INDEX idx_assessments_client (client_id, deleted);

ALTER TABLE evidence
    DROP INDEX idx_evidence_client,
    DROP INDEX idx_project_folder,
    DROP COLUMN project_id,
    ADD INDEX idx_evidence_client (company_id, client_id, deleted),
    ADD INDEX idx_client_folder (client_id, folder_id, deleted);

ALTER TABLE evidence_folders
    DROP INDEX idx_company_project,
    DROP INDEX idx_parent,
    DROP COLUMN project_id,
    ADD INDEX idx_company_client (company_id, client_id, deleted),
    ADD INDEX idx_parent (client_id, parent_id, deleted);

ALTER TABLE reports
    DROP INDEX idx_reports_project,
    DROP COLUMN project_id,
    ADD INDEX idx_reports_assessment (assessment_id, deleted);

ALTER TABLE invoices
    DROP INDEX idx_invoices_project,
    DROP COLUMN project_id;

ALTER TABLE subscriptions
    DROP COLUMN project_id;

DROP TABLE project_users;
DROP TABLE project_standards;
DROP TABLE projects;
