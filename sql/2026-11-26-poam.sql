-- Plan of Action and Milestones (POA&M): persistent, editable tracking records
-- for an assessment's deficient controls. Additive; run after the earlier
-- pending migrations:
--
--     mysql <database> < sql/2026-11-26-poam.sql
--
-- poam_items holds one row per deficient control per remediation cycle. Its
-- reference POAM-{assessment_id}-{poam_seq, three digits} is allocated inside
-- a transaction holding the assessment row lock and is never reused; rows are
-- never deleted. The source_* columns are the snapshot the sync
-- (libs/Classes/Poam.php) takes from the assessment analysis and rewrites only
-- while the item is open; every other column is a management field the sync
-- never writes, and an empty value means "Not assigned" / "Not set". A
-- deficient control whose last item was closed gets a new item linked through
-- reopened_from_id. poam_status never becomes Closed through an edit: only the
-- closure confirmation (staff) sets it, recording the note, the evidence or the
-- stated reason, and the override acknowledgement when the control was not
-- recorded Implemented.
--
-- poam_history is append-only: one row per field change, from the sync, a
-- user edit, a closure or a reopen. Nothing updates or deletes its rows.
--
-- assessments.poam_clock_date is the CMMC Conditional status date the 180-day
-- POA&M closeout runs from. It is entered by staff and never inferred.

CREATE TABLE IF NOT EXISTS poam_items (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    company_id bigint unsigned NOT NULL,
    assessment_id bigint unsigned NOT NULL,
    poam_seq int unsigned NOT NULL,
    poam_ref varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
    reopened_from_id bigint unsigned NULL,
    source_item_id bigint unsigned NOT NULL DEFAULT 0,
    source_ref varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
    source_title varchar(400) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_family varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_result varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_open_objectives text COLLATE utf8mb4_unicode_ci NULL,
    source_weakness text COLLATE utf8mb4_unicode_ci NULL,
    source_action text COLLATE utf8mb4_unicode_ci NULL,
    source_priority enum('','High','Medium','Low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_priority_basis varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_initiative_ref varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_initiative_title varchar(400) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_eligibility varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_eligibility_note varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_points int NULL,
    source_resolved tinyint(1) NOT NULL DEFAULT 0,
    source_hash char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    source_date_updated datetime NULL,
    weakness_override text COLLATE utf8mb4_unicode_ci NULL,
    action_override text COLLATE utf8mb4_unicode_ci NULL,
    priority_override enum('','High','Medium','Low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    owner_name varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    resources text COLLATE utf8mb4_unicode_ci NULL,
    detection_date date NULL,
    scheduled_completion_date date NULL,
    completion_date date NULL,
    poam_status enum('Open','In Progress','Ready for Validation','Closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open',
    comments text COLLATE utf8mb4_unicode_ci NULL,
    risk_rating enum('','Low','Moderate','High','Critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    vendor_dependency tinyint(1) NOT NULL DEFAULT 0,
    vendor_product varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    vendor_checkin_date date NULL,
    deviation_type enum('','Risk Adjustment','False Positive','Operational Requirement') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    deviation_rationale text COLLATE utf8mb4_unicode_ci NULL,
    closure_note text COLLATE utf8mb4_unicode_ci NULL,
    closure_reason text COLLATE utf8mb4_unicode_ci NULL,
    closure_result varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    closure_override tinyint(1) NOT NULL DEFAULT 0,
    closed_by bigint unsigned NULL,
    date_closed datetime NULL,
    created_by bigint unsigned NOT NULL DEFAULT 0,
    updated_by bigint unsigned NOT NULL DEFAULT 0,
    date_created datetime NOT NULL,
    date_updated datetime NOT NULL,
    deleted tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_poam_items_seq (assessment_id, poam_seq),
    UNIQUE KEY uq_poam_items_ref (poam_ref),
    KEY idx_poam_items_company (company_id, assessment_id, deleted),
    KEY idx_poam_items_source (assessment_id, source_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poam_milestones (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    company_id bigint unsigned NOT NULL,
    poam_item_id bigint unsigned NOT NULL,
    milestone_text text COLLATE utf8mb4_unicode_ci NOT NULL,
    due_date date NULL,
    completed_date date NULL,
    milestone_status enum('Not Started','In Progress','Completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Not Started',
    sort_order int unsigned NOT NULL DEFAULT 0,
    created_by bigint unsigned NOT NULL DEFAULT 0,
    updated_by bigint unsigned NOT NULL DEFAULT 0,
    date_created datetime NOT NULL,
    date_updated datetime NOT NULL,
    deleted tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_poam_milestones_item (poam_item_id, deleted),
    KEY idx_poam_milestones_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poam_evidence (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    company_id bigint unsigned NOT NULL,
    poam_item_id bigint unsigned NOT NULL,
    evidence_id bigint unsigned NOT NULL,
    evidence_role enum('Progress','Closure') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Progress',
    linked_by bigint unsigned NOT NULL DEFAULT 0,
    date_created datetime NOT NULL,
    deleted tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_poam_evidence_item (poam_item_id, deleted),
    KEY idx_poam_evidence_company (company_id, evidence_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poam_history (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    company_id bigint unsigned NOT NULL,
    assessment_id bigint unsigned NOT NULL,
    poam_item_id bigint unsigned NOT NULL,
    poam_milestone_id bigint unsigned NULL,
    change_source enum('Sync','User','Closure','Reopen') COLLATE utf8mb4_unicode_ci NOT NULL,
    field_name varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
    old_value text COLLATE utf8mb4_unicode_ci NULL,
    new_value text COLLATE utf8mb4_unicode_ci NULL,
    change_note varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    changed_by bigint unsigned NOT NULL DEFAULT 0,
    date_created datetime NOT NULL,
    PRIMARY KEY (id),
    KEY idx_poam_history_item (poam_item_id, id),
    KEY idx_poam_history_assessment (company_id, assessment_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE assessments
    ADD COLUMN poam_clock_date date NULL;
