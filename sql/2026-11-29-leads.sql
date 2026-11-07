-- Leads: demo and pricing requests sent from the public /demo page. Additive;
-- run after the earlier pending migrations:
--
--     mysql <database> < sql/2026-11-29-leads.sql
--
-- lead_type is 'demo' or 'pricing'. ip_address is kept so the public form can
-- be throttled per address.

CREATE TABLE IF NOT EXISTS leads (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    lead_type varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
    first_name varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    last_name varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    lead_email varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    lead_phone varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    company_name varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
    organization_type varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
    lead_message text COLLATE utf8mb4_unicode_ci NOT NULL,
    ip_address varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    user_agent varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    date_created datetime NOT NULL,
    PRIMARY KEY (id),
    KEY idx_leads_created (date_created),
    KEY idx_leads_ip (ip_address, date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
