-- The frameworks each project covers. An assessment whose framework is not
-- one of its project's frameworks raises a mismatch warning. Additive:
--
--     mysql <database> < sql/2026-11-08-project-frameworks.sql

CREATE TABLE project_standards (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    project_id bigint unsigned NOT NULL,
    standard_id bigint unsigned NOT NULL,
    created_by bigint unsigned NOT NULL,
    date_created datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_standard (project_id, standard_id),
    KEY idx_project_standards_standard (standard_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
