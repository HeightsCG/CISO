-- Report workflow: background jobs for AI narratives and report packages,
-- and the document state (Draft / In Review / Final) of each assessment's
-- reports. Additive; run on the local development database only:
--
--     mysql <database> < sql/2026-11-25-report-workflow.sql
--
-- Jobs run outside the web request in bin/report-worker.php. Starting a job
-- inserts a Queued row and spawns a detached worker for it
-- (php bin/report-worker.php --job=<id>) with the PHP CLI binary from
-- app.ini `php_cli` (environment section, then [global]) or PHP_BINDIR/php.
-- A cron entry picks up anything a spawn missed and recovers jobs whose
-- worker stopped (heartbeat older than five minutes):
--
--     * * * * * cd /path/to/www && APPLICATION_ENV=production php bin/report-worker.php --drain
--
-- report_narratives.revision_notes holds the reviewer notes a section was
-- last rewritten to resolve (JSON list), or NULL for a plain generation.

CREATE TABLE report_jobs (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    company_id bigint unsigned NOT NULL,
    assessment_id bigint unsigned NOT NULL,
    job_type enum('Narratives','Package') COLLATE utf8mb4_unicode_ci NOT NULL,
    job_status enum('Queued','Running','Completed','Failed','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Queued',
    options text COLLATE utf8mb4_unicode_ci NULL,
    steps mediumtext COLLATE utf8mb4_unicode_ci NULL,
    total_steps int unsigned NOT NULL DEFAULT 0,
    done_steps int unsigned NOT NULL DEFAULT 0,
    current_step varchar(255) COLLATE utf8mb4_unicode_ci NULL,
    attempts tinyint unsigned NOT NULL DEFAULT 0,
    last_error text COLLATE utf8mb4_unicode_ci NULL,
    result_key varchar(500) COLLATE utf8mb4_unicode_ci NULL,
    result_name varchar(255) COLLATE utf8mb4_unicode_ci NULL,
    heartbeat datetime NULL,
    created_by bigint unsigned NOT NULL,
    date_created datetime NOT NULL,
    date_started datetime NULL,
    date_finished datetime NULL,
    deleted tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_report_jobs_assessment (company_id, assessment_id, job_type),
    KEY idx_report_jobs_status (job_status, heartbeat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_states (
    assessment_id bigint unsigned NOT NULL,
    company_id bigint unsigned NOT NULL,
    report_status enum('Draft','In Review','Final') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
    report_version varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0.1',
    final_hash char(64) COLLATE utf8mb4_unicode_ci NULL,
    finalized_by bigint unsigned NULL,
    finalized_date datetime NULL,
    updated_by bigint unsigned NULL,
    date_updated datetime NOT NULL,
    PRIMARY KEY (assessment_id),
    KEY idx_report_states_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE report_narratives
    ADD COLUMN revision_notes text COLLATE utf8mb4_unicode_ci NULL AFTER rejected_fields;
