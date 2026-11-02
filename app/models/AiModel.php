<?php
class AiModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function load_conversations($user_id, $company_id)
    {
        $where = array(
            'user_id' => $user_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    c.id,
                    c.conversation_title,
                    c.date_updated
                FROM
                    ai_conversations c
                WHERE
                    c.user_id = :user_id
                    and
                    c.company_id = :company_id
                    and
                    c.deleted = 0
                ORDER BY
                    c.date_updated DESC,
                    c.id DESC
                LIMIT 30";
        return parent::select($sql, $where);
    }

    public function get_conversation($conversation_id, $user_id, $company_id)
    {
        $where = array(
            'id' => $conversation_id,
            'user_id' => $user_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    c.*
                FROM
                    ai_conversations c
                WHERE
                    c.id = :id
                    and
                    c.user_id = :user_id
                    and
                    c.company_id = :company_id
                    and
                    c.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_conversation($company_id, $user_id, $conversation_title)
    {
        $data = array(
            'company_id' => $company_id,
            'user_id' => $user_id,
            'conversation_title' => $conversation_title,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('ai_conversations', $data);
    }

    /** Store the actions waiting on the person, or null once none are. */
    public function set_pending($conversation_id, $pending_json)
    {
        $where = array(
            'id' => $conversation_id
        );
        $data = array(
            'pending_json' => $pending_json,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('ai_conversations', $data, 'id = :id', $where);
    }

    public function load_messages($conversation_id)
    {
        $where = array(
            'conversation_id' => $conversation_id
        );
        $sql = "SELECT
                    m.id,
                    m.message_role,
                    m.message_content,
                    m.display_text,
                    m.attachment_names,
                    m.attachment_id
                FROM
                    ai_messages m
                WHERE
                    m.conversation_id = :conversation_id
                ORDER BY
                    m.id";
        return parent::select($sql, $where);
    }

    public function add_message($conversation_id, $message_role, $message_content, $display_text, $attachment_names, $attachment_id = null)
    {
        $data = array(
            'conversation_id' => $conversation_id,
            'message_role' => $message_role,
            'message_content' => $message_content,
            'display_text' => $display_text,
            'attachment_names' => $attachment_names,
            'attachment_id' => $attachment_id,
            'date_created' => date('Y-m-d H:i:s')
        );
        return parent::insert('ai_messages', $data);
    }

    public function add_attachment($company_id, $user_id, $conversation_id, $file_name, $file_type, $file_size, $file_key, $file_source, $anthropic_file_id, $extracted_text)
    {
        $data = array(
            'company_id' => $company_id,
            'user_id' => $user_id,
            'conversation_id' => $conversation_id,
            'file_name' => $file_name,
            'file_type' => $file_type,
            'file_size' => $file_size,
            'file_key' => $file_key,
            'file_source' => $file_source,
            'anthropic_file_id' => $anthropic_file_id,
            'extracted_text' => $extracted_text,
            'date_created' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('ai_attachments', $data);
    }

    /** Uploads this person made that are not yet part of any message. */
    public function get_unsent_attachment($attachment_id, $user_id, $company_id)
    {
        $where = array(
            'id' => $attachment_id,
            'user_id' => $user_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.*
                FROM
                    ai_attachments a
                WHERE
                    a.id = :id
                    and
                    a.user_id = :user_id
                    and
                    a.company_id = :company_id
                    and
                    a.conversation_id IS NULL
                    and
                    a.deleted = 0";
        return parent::select($sql, $where);
    }

    public function set_container($conversation_id, $container_id)
    {
        $where = array(
            'id' => $conversation_id
        );
        $data = array(
            'container_id' => $container_id
        );
        return parent::update('ai_conversations', $data, 'id = :id', $where);
    }

    /** Any file in one of this person's conversations, uploaded or generated. */
    public function get_attachment($attachment_id, $user_id, $company_id)
    {
        $where = array(
            'id' => $attachment_id,
            'user_id' => $user_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.*
                FROM
                    ai_attachments a
                WHERE
                    a.id = :id
                    and
                    a.user_id = :user_id
                    and
                    a.company_id = :company_id
                    and
                    a.deleted = 0";
        return parent::select($sql, $where);
    }

    /** The newest file with this name in a conversation. */
    public function find_conversation_file($conversation_id, $file_name)
    {
        $where = array(
            'conversation_id' => $conversation_id,
            'file_name' => $file_name
        );
        $sql = "SELECT
                    a.*
                FROM
                    ai_attachments a
                WHERE
                    a.conversation_id = :conversation_id
                    and
                    a.file_name = :file_name
                    and
                    a.deleted = 0
                ORDER BY
                    a.id DESC
                LIMIT 1";
        return parent::select($sql, $where);
    }

    public function has_generated_file($conversation_id, $anthropic_file_id)
    {
        $where = array(
            'conversation_id' => $conversation_id,
            'anthropic_file_id' => $anthropic_file_id
        );
        $sql = "SELECT
                    COUNT(*) AS file_count
                FROM
                    ai_attachments a
                WHERE
                    a.conversation_id = :conversation_id
                    and
                    a.anthropic_file_id = :anthropic_file_id";
        $rows = parent::select($sql, $where);

        return (int) ($rows[0]['file_count'] ?? 0) > 0;
    }

    public function bind_attachment($attachment_id, $conversation_id)
    {
        $where = array(
            'id' => $attachment_id
        );
        $data = array(
            'conversation_id' => $conversation_id
        );
        return parent::update('ai_attachments', $data, 'id = :id', $where);
    }

}
