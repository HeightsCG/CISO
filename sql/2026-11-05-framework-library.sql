-- Framework library: standards and controls loaded from the framework
-- definitions in frameworks/*.json carry their source document and version on
-- every row; assessment objectives are child rows (control_kind = 'objective').
-- framework_crosswalk links controls across frameworks (reference data shared by
-- all companies, replaced in full by load_frameworks.php).
-- Additive. Run once before deploying the matching code, then load the library:
--
--     mysql <database> < sql/2026-11-05-framework-library.sql
--     APPLICATION_ENV=production php load_frameworks.php all

ALTER TABLE standards
    ADD COLUMN framework_key varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER version,
    ADD COLUMN full_name varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER framework_key,
    ADD COLUMN issuing_body varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER full_name,
    ADD COLUMN source_document varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER issuing_body,
    ADD COLUMN source_version varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER source_document,
    ADD KEY idx_standards_framework (company_id, framework_key, deleted);

ALTER TABLE standard_controls
    ADD COLUMN control_kind enum('control','objective') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'control' AFTER parent_identifier,
    ADD COLUMN guidance mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER description,
    ADD COLUMN control_weight smallint NULL AFTER family,
    ADD COLUMN partial_weight smallint NULL AFTER control_weight,
    ADD COLUMN control_flags varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER partial_weight,
    ADD COLUMN source_document varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER control_flags,
    ADD COLUMN source_version varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER source_document;

ALTER TABLE assessment_items
    ADD COLUMN control_kind enum('control','objective') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'control' AFTER parent_identifier,
    ADD COLUMN source_document varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER family,
    ADD COLUMN source_version varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER source_document;

ALTER TABLE assessments
    ADD COLUMN framework_key varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER version;

UPDATE standards
SET standard_status = 'Archived', date_updated = UTC_TIMESTAMP()
WHERE short_code = 'NIST-800-171A' and deleted = 0;

CREATE TABLE framework_crosswalk (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    crosswalk_set varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    from_framework varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    from_identifier varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    to_framework varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    to_identifier varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    link_relationship varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_document varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_version varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_crosswalk_from (from_framework, from_identifier),
    KEY idx_crosswalk_to (to_framework, to_identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE standards
SET version = 'TSC 2017, revised 2022', date_updated = UTC_TIMESTAMP()
WHERE short_code = 'SOC2' and version = 'TSC 2017 (rev 2022)' and deleted = 0;

UPDATE assessments
SET version = 'TSC 2017, revised 2022'
WHERE short_code = 'SOC2' and version = 'TSC 2017 (rev 2022)';
