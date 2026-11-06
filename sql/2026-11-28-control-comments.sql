-- Control comments: a running thread on each assessment control, written by
-- the client in the portal and by staff on the Controls tab. Additive; run
-- after the earlier pending migrations:
--
--     mysql <database> < sql/2026-11-28-control-comments.sql
--
-- item_comments holds one row per comment. company_id and assessment_id are
-- copied from the control at the time of writing so every read can be scoped
-- without a join. created_by is the user_accounts.user_id of the author; only
-- that user can remove the comment, and removal sets deleted = 1 rather than
-- deleting the row.

CREATE TABLE IF NOT EXISTS item_comments (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    company_id bigint unsigned NOT NULL,
    assessment_id bigint unsigned NOT NULL,
    assessment_item_id bigint unsigned NOT NULL,
    comment_text text COLLATE utf8mb4_unicode_ci NOT NULL,
    created_by bigint unsigned NOT NULL DEFAULT 0,
    date_created datetime NOT NULL,
    deleted tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_item_comments_item (assessment_item_id, id),
    KEY idx_item_comments_company (company_id, assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
