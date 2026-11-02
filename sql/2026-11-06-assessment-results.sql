-- Assessment results: finding fields per control, objective-level results,
-- milestones, assessment details (type, baseline, scope, method), consultant
-- narrative and governing document sections. Additive; run after
-- 2026-11-05-framework-library.sql:
--
--     mysql <database> < sql/2026-11-06-assessment-results.sql

ALTER TABLE assessment_items
    ADD COLUMN guidance mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER description,
    ADD COLUMN control_weight smallint NULL AFTER family,
    ADD COLUMN partial_weight smallint NULL AFTER control_weight,
    ADD COLUMN control_flags varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER partial_weight,
    ADD COLUMN gap_description text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER notes,
    ADD COLUMN business_impact text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER gap_description,
    ADD COLUMN risk_rating enum('','Low','Moderate','High','Critical') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER business_impact,
    ADD COLUMN recommendation text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER risk_rating,
    ADD COLUMN owner_name varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER recommendation,
    ADD COLUMN target_date date NULL AFTER owner_name,
    ADD COLUMN implementation_description text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER target_date,
    ADD COLUMN partial_credit tinyint(1) NOT NULL DEFAULT 0 AFTER implementation_description,
    ADD COLUMN applicability enum('Applicable','Excluded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Applicable' AFTER partial_credit,
    ADD COLUMN applicability_justification text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER applicability,
    ADD KEY idx_items_parent (assessment_id, parent_identifier);

CREATE TABLE item_milestones (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    assessment_item_id bigint unsigned NOT NULL,
    milestone_text varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    due_date date NULL,
    milestone_status enum('Open','Done') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open',
    sort_order int unsigned NOT NULL DEFAULT 0,
    created_by bigint unsigned NOT NULL,
    updated_by bigint unsigned NOT NULL,
    date_created datetime NOT NULL,
    date_updated datetime NOT NULL,
    deleted tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_milestones_item (assessment_item_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE assessments
    ADD COLUMN assessment_type enum('Baseline','Interim','Final') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Baseline' AFTER assessment_status,
    ADD COLUMN baseline_assessment_id bigint unsigned NOT NULL DEFAULT 0 AFTER assessment_type,
    ADD COLUMN no_baseline tinyint(1) NOT NULL DEFAULT 0 AFTER baseline_assessment_id,
    ADD COLUMN assessment_scope text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER no_baseline,
    ADD COLUMN assessment_method text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER assessment_scope,
    ADD COLUMN scope_categories varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER assessment_method,
    ADD COLUMN ssi_reviewed tinyint(1) NOT NULL DEFAULT 0 AFTER scope_categories,
    ADD COLUMN ssi_note text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER ssi_reviewed,
    ADD COLUMN posture_text mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER ssi_note,
    ADD COLUMN strengths_text mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER posture_text,
    ADD COLUMN concerns_text mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER strengths_text,
    ADD COLUMN readiness_text mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER concerns_text,
    ADD COLUMN decisions_text mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER readiness_text;

CREATE TABLE assessment_documents (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    assessment_id bigint unsigned NOT NULL,
    section_key varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    section_title varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    section_body mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    sort_order int unsigned NOT NULL DEFAULT 0,
    updated_by bigint unsigned NOT NULL DEFAULT 0,
    date_created datetime NOT NULL,
    date_updated datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_section (assessment_id, section_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
