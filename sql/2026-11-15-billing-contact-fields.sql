-- Gives the billing contact the same fields as the primary contact: job title
-- and phone, alongside the existing name and email. Additive, no data changes.
--
--     mysql <database> < sql/2026-11-15-billing-contact-fields.sql

ALTER TABLE clients
    ADD COLUMN billing_title varchar(200) NOT NULL DEFAULT '' AFTER billing_name,
    ADD COLUMN billing_phone varchar(60) NOT NULL DEFAULT '' AFTER billing_email;
