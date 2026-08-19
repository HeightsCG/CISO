-- Login security controls: per-account lockout tracking and password-age
-- tracking, so the account_lockout_* and password_expiry_* policies already
-- stored per company can actually be enforced at sign-in.
--
-- Additive and safe to run on a live table. Run once before deploying the
-- matching application code:
--
--     mysql <database> < sql/2026-08-18-login-security-controls.sql

ALTER TABLE user_accounts
    ADD COLUMN failed_login_count INT NOT NULL DEFAULT 0,
    ADD COLUMN last_failed_login DATETIME NULL DEFAULT NULL,
    ADD COLUMN locked_until DATETIME NULL DEFAULT NULL,
    ADD COLUMN password_changed_at DATETIME NULL DEFAULT NULL;

-- Start every existing account's expiry clock at migration time rather than at
-- account creation, so turning on password expiry does not force every user to
-- reset on the first sign-in after deploy.
UPDATE user_accounts SET password_changed_at = NOW() WHERE password_changed_at IS NULL;
