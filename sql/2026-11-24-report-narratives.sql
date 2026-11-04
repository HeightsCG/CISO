-- AI-written report narratives, one row per assessment and report section
-- ('executive', 'domain:<family>', 'remediation', 'patterns', 'review').
-- input_hash identifies the facts, model and prompt version a narrative was
-- written from; when the current inputs hash differently the stored text is
-- stale and reports fall back to the calculated text. Additive:
--
--     mysql <database> < sql/2026-11-24-report-narratives.sql

CREATE TABLE report_narratives (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    company_id bigint unsigned NOT NULL,
    assessment_id bigint unsigned NOT NULL,
    section_key varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
    input_hash char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    content mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
    edited_content mediumtext COLLATE utf8mb4_unicode_ci NULL,
    rejected_fields text COLLATE utf8mb4_unicode_ci NULL,
    narrative_status enum('Draft','Approved','Rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
    model varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    input_tokens int unsigned NOT NULL DEFAULT 0,
    output_tokens int unsigned NOT NULL DEFAULT 0,
    generated_by bigint unsigned NOT NULL,
    edited_by bigint unsigned NULL,
    reviewed_by bigint unsigned NULL,
    date_created datetime NOT NULL,
    date_updated datetime NOT NULL,
    deleted tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_report_narratives_section (assessment_id, section_key),
    KEY idx_report_narratives_company (company_id, assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
