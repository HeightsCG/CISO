-- Single-report jobs: Preview and Download in the Reports tab render one
-- report in the background worker (job_type Report) instead of the web
-- request, store it privately and hand back a short-lived link. A completed
-- Report job is reused while the fingerprint in its options (assessment
-- state, narratives, document state, report options, POA&M state for the
-- POA&M, the day and the report code) still matches.
-- Run on the local development database only:
--
--     mysql <database> < sql/2026-11-27-report-preview-jobs.sql

ALTER TABLE report_jobs
    MODIFY job_type enum('Narratives','Package','Report') COLLATE utf8mb4_unicode_ci NOT NULL;
