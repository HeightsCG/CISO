-- Removes the report builder: the editable per-assessment client report, its
-- portal view where clients responded to findings, and the assistant's report
-- tools. The ready-made reports are unaffected and build the client risk
-- assessment report from the client's assessments. No other table references
-- these. DESTRUCTIVE: every edited report, its versions, people, domains,
-- findings, client responses, risks, threats, assets, CSF profile, scan rows
-- and cross-references are deleted. Deploy the code first:
--
--     mysql <database> < sql/2026-11-19-remove-report-builder.sql

DROP TABLE IF EXISTS report_versions;
DROP TABLE IF EXISTS report_people;
DROP TABLE IF EXISTS report_domains;
DROP TABLE IF EXISTS report_findings;
DROP TABLE IF EXISTS report_risks;
DROP TABLE IF EXISTS report_threats;
DROP TABLE IF EXISTS report_assets;
DROP TABLE IF EXISTS report_csf;
DROP TABLE IF EXISTS report_scan_rows;
DROP TABLE IF EXISTS report_xrefs;
DROP TABLE IF EXISTS reports;
