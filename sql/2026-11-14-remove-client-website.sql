-- Removes the client website, which is no longer collected or shown. The
-- organisation's own website (companies.website) is unaffected. DESTRUCTIVE:
-- stored client websites are deleted. Deploy the code first:
--
--     mysql <database> < sql/2026-11-14-remove-client-website.sql
--
-- Safe to run more than once: each column is dropped only if it still exists.

SET @sql = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE clients DROP COLUMN website', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'clients' AND column_name = 'website');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
