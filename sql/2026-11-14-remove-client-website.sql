-- Removes the client website, which is no longer collected or shown. The
-- organisation's own website (companies.website) is unaffected. DESTRUCTIVE:
-- stored client websites are deleted. Deploy the code first:
--
--     mysql <database> < sql/2026-11-14-remove-client-website.sql

ALTER TABLE clients
    DROP COLUMN website;
