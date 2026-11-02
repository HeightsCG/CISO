-- Client deliverable reports: one report per project, built from structured
-- sections and rendered to Word and PDF. Additive. Run once before deploying
-- the matching application code:
--
--     mysql <database> < sql/2026-11-04-reports.sql

CREATE TABLE reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    report_title VARCHAR(255) NOT NULL DEFAULT 'Cybersecurity Risk Assessment',
    report_date DATE NULL DEFAULT NULL,
    period_start DATE NULL DEFAULT NULL,
    period_end DATE NULL DEFAULT NULL,
    regulatory_reference VARCHAR(255) NOT NULL DEFAULT '',
    report_version VARCHAR(20) NOT NULL DEFAULT '0.1',
    report_stage ENUM('Draft', 'Issued for response', 'Final') NOT NULL DEFAULT 'Draft',
    purpose_text MEDIUMTEXT NULL DEFAULT NULL,
    conclusion_text MEDIUMTEXT NULL DEFAULT NULL,
    background_text MEDIUMTEXT NULL DEFAULT NULL,
    scope_text MEDIUMTEXT NULL DEFAULT NULL,
    out_of_scope_text MEDIUMTEXT NULL DEFAULT NULL,
    approach_text MEDIUMTEXT NULL DEFAULT NULL,
    methodology_text MEDIUMTEXT NULL DEFAULT NULL,
    limitations_text MEDIUMTEXT NULL DEFAULT NULL,
    review_text MEDIUMTEXT NULL DEFAULT NULL,
    scan_text MEDIUMTEXT NULL DEFAULT NULL,
    signoff_name VARCHAR(255) NOT NULL DEFAULT '',
    signoff_title VARCHAR(255) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    date_created DATETIME NOT NULL,
    date_updated DATETIME NOT NULL,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_reports_project (project_id, deleted),
    KEY idx_reports_company (company_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document control: each issued version of the report.
CREATE TABLE report_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    version_label VARCHAR(20) NOT NULL,
    version_date DATE NOT NULL,
    version_note VARCHAR(500) NOT NULL DEFAULT '',
    version_author VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_report_versions (report_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The distribution list and the assessment team.
CREATE TABLE report_people (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    person_kind ENUM('distribution', 'team') NOT NULL,
    person_name VARCHAR(255) NOT NULL DEFAULT '',
    person_title VARCHAR(255) NOT NULL DEFAULT '',
    organisation VARCHAR(255) NOT NULL DEFAULT '',
    responsibility VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_report_people (report_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_domains (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    domain_name VARCHAR(255) NOT NULL,
    areas_reviewed VARCHAR(1000) NOT NULL DEFAULT '',
    csf_refs VARCHAR(255) NOT NULL DEFAULT '',
    domain_rating ENUM('Satisfactory', 'Satisfactory with exceptions', 'Needs improvement', 'Unsatisfactory') NULL DEFAULT NULL,
    domain_summary MEDIUMTEXT NULL DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_report_domains (report_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Findings. Likelihood and impact are each 1 to 5; their product is the score.
-- The response fields are filled by the client in the portal once the report
-- is issued for response, or by the consultant.
CREATE TABLE report_findings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    domain_id BIGINT UNSIGNED NULL DEFAULT NULL,
    finding_ref VARCHAR(20) NOT NULL DEFAULT '',
    finding_title VARCHAR(500) NOT NULL DEFAULT '',
    observation MEDIUMTEXT NULL DEFAULT NULL,
    risk_text MEDIUMTEXT NULL DEFAULT NULL,
    recommendation MEDIUMTEXT NULL DEFAULT NULL,
    likelihood TINYINT UNSIGNED NOT NULL DEFAULT 1,
    impact TINYINT UNSIGNED NOT NULL DEFAULT 1,
    csf_refs VARCHAR(500) NOT NULL DEFAULT '',
    response_status ENUM('Pending', 'Agreed', 'Partially agreed', 'Not agreed') NOT NULL DEFAULT 'Pending',
    management_response MEDIUMTEXT NULL DEFAULT NULL,
    response_owner VARCHAR(255) NOT NULL DEFAULT '',
    target_date DATE NULL DEFAULT NULL,
    responded_by BIGINT UNSIGNED NULL DEFAULT NULL,
    responded_at DATETIME NULL DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NOT NULL,
    date_created DATETIME NOT NULL,
    date_updated DATETIME NOT NULL,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_report_findings (report_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_risks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    domain_id BIGINT UNSIGNED NULL DEFAULT NULL,
    finding_id BIGINT UNSIGNED NULL DEFAULT NULL,
    risk_ref VARCHAR(20) NOT NULL DEFAULT '',
    risk_text VARCHAR(1000) NOT NULL DEFAULT '',
    threat_refs VARCHAR(255) NOT NULL DEFAULT '',
    likelihood TINYINT UNSIGNED NOT NULL DEFAULT 1,
    impact TINYINT UNSIGNED NOT NULL DEFAULT 1,
    treatment VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_report_risks (report_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_threats (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    threat_ref VARCHAR(20) NOT NULL DEFAULT '',
    threat_name VARCHAR(255) NOT NULL DEFAULT '',
    threat_description VARCHAR(1000) NOT NULL DEFAULT '',
    parties_affected VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_report_threats (report_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appendix B: systems and data inventory.
CREATE TABLE report_assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    asset_name VARCHAR(255) NOT NULL DEFAULT '',
    asset_kind VARCHAR(100) NOT NULL DEFAULT '',
    asset_owner VARCHAR(255) NOT NULL DEFAULT '',
    data_held VARCHAR(1000) NOT NULL DEFAULT '',
    sensitivity ENUM('High', 'Medium', 'Low') NOT NULL DEFAULT 'Medium',
    sort_order INT NOT NULL DEFAULT 0,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_report_assets (report_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NIST CSF 2.0 profile: one row per function, tiers 1 to 4.
CREATE TABLE report_csf (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    csf_function ENUM('Govern', 'Identify', 'Protect', 'Detect', 'Respond', 'Recover') NOT NULL,
    current_tier TINYINT UNSIGNED NULL DEFAULT NULL,
    target_tier TINYINT UNSIGNED NULL DEFAULT NULL,
    main_gaps VARCHAR(1000) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_report_csf (report_id, csf_function)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appendix D: external vulnerability scan summary.
CREATE TABLE report_scan_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    scan_rating ENUM('Critical', 'High', 'Medium', 'Low') NOT NULL,
    issue_count INT UNSIGNED NOT NULL DEFAULT 0,
    scan_summary VARCHAR(1000) NOT NULL DEFAULT '',
    disposition VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_report_scan_rows (report_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appendix A: regulatory cross-reference.
CREATE TABLE report_xrefs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id BIGINT UNSIGNED NOT NULL,
    xref_section VARCHAR(100) NOT NULL DEFAULT '',
    requirement VARCHAR(1000) NOT NULL DEFAULT '',
    where_addressed VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_report_xrefs (report_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
