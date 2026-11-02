-- AI assistant, part two: keep the original of every file so it can be filed
-- into an evidence vault, record reports the assistant generates, and remember
-- the code-execution container each conversation works in. Additive. Run once
-- after 2026-11-02-ai-assistant.sql:
--
--     mysql <database> < sql/2026-11-03-ai-assistant-files.sql

ALTER TABLE ai_attachments
    ADD COLUMN file_key VARCHAR(255) NULL DEFAULT NULL AFTER file_size,
    ADD COLUMN file_source ENUM('upload', 'generated') NOT NULL DEFAULT 'upload' AFTER file_key;

ALTER TABLE ai_messages
    MODIFY COLUMN message_role ENUM('user', 'assistant', 'action', 'file') NOT NULL,
    ADD COLUMN attachment_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER attachment_names;

ALTER TABLE ai_conversations
    ADD COLUMN container_id VARCHAR(100) NULL DEFAULT NULL AFTER pending_json;
