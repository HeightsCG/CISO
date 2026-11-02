-- AI assistant: saved conversations, their messages, and files attached to
-- them. Additive. Run once before deploying the matching application code:
--
--     mysql <database> < sql/2026-11-02-ai-assistant.sql

CREATE TABLE ai_conversations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    conversation_title VARCHAR(200) NOT NULL DEFAULT '',
    pending_json MEDIUMTEXT NULL DEFAULT NULL,
    date_created DATETIME NOT NULL,
    date_updated DATETIME NOT NULL,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_ai_conversations_user (user_id, deleted, date_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- message_role 'user' and 'assistant' rows are replayed to the model exactly as
-- stored in message_content. 'action' rows are shown in the panel only.
-- display_text NULL means the row is never shown (tool results).
CREATE TABLE ai_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    message_role ENUM('user', 'assistant', 'action') NOT NULL,
    message_content MEDIUMTEXT NULL DEFAULT NULL,
    display_text MEDIUMTEXT NULL DEFAULT NULL,
    attachment_names TEXT NULL DEFAULT NULL,
    date_created DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ai_messages_conversation (conversation_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PDFs and images are held by the Anthropic Files API (anthropic_file_id);
-- Word, Excel and text files are reduced to extracted_text on upload.
CREATE TABLE ai_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NULL DEFAULT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    anthropic_file_id VARCHAR(100) NULL DEFAULT NULL,
    extracted_text MEDIUMTEXT NULL DEFAULT NULL,
    date_created DATETIME NOT NULL,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_ai_attachments_user (user_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
