<?php
class ItemCommentsModel extends Model {

    /** The longest comment accepted, in characters. */
    const MAX_LENGTH = 4000;

    public function __construct(){
        parent::__construct();
    }

    /**
     * The live comments on one control, oldest first. is_own marks the caller's
     * comments so the view can label them and offer removal; is_client marks
     * comments written from the portal. created_by itself is not returned.
     */
    public function load_comments($item_id, $company_id, $user_id)
    {
        $where = array(
            'item_id' => $item_id,
            'company_id' => $company_id,
            'user_id' => $user_id
        );
        $sql = "SELECT
                    c.id,
                    c.comment_text,
                    DATE_FORMAT(c.date_created, df.sql_format) AS date_created_display,
                    TRIM(CONCAT(IFNULL(u.first_name, ''), ' ', IFNULL(u.last_name, ''))) AS author_name,
                    IF(u.user_type = 'portal', 1, 0) AS is_client,
                    IF(c.created_by = :user_id, 1, 0) AS is_own
                FROM
                    item_comments c
                    JOIN companies co ON co.id = c.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN user_accounts u ON u.user_id = c.created_by
                WHERE
                    c.assessment_item_id = :item_id
                    and
                    c.company_id = :company_id
                    and
                    c.deleted = 0
                ORDER BY
                    c.id";
        return parent::select($sql, $where);
    }

    /** One live comment within the company, or no rows. */
    public function get_comment($comment_id, $company_id)
    {
        $where = array(
            'id' => $comment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    c.id,
                    c.company_id,
                    c.assessment_id,
                    c.assessment_item_id,
                    c.created_by
                FROM
                    item_comments c
                WHERE
                    c.id = :id
                    and
                    c.company_id = :company_id
                    and
                    c.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_comment($company_id, $assessment_id, $item_id, $comment_text, $user_id)
    {
        $data = array(
            'company_id' => $company_id,
            'assessment_id' => $assessment_id,
            'assessment_item_id' => $item_id,
            'comment_text' => $comment_text,
            'created_by' => $user_id,
            'date_created' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('item_comments', $data);
    }

    /**
     * Soft-deletes a comment, and only for its author: created_by is part of
     * the WHERE, so another user's id matches no row whatever the caller checked.
     */
    public function delete_comment($comment_id, $company_id, $user_id)
    {
        $data = array(
            'deleted' => 1
        );
        $where = array(
            'id' => $comment_id,
            'company_id' => $company_id,
            'created_by' => $user_id
        );
        return parent::update('item_comments', $data, 'id = :id and company_id = :company_id and created_by = :created_by and deleted = 0', $where);
    }

    /** Why a comment cannot be saved, or '' when it can. Expects trimmed text. */
    public static function text_error(string $comment_text): string
    {
        if ($comment_text === '') {
            return 'Enter a comment';
        }

        if (mb_strlen($comment_text, 'UTF-8') > self::MAX_LENGTH) {
            return 'Comments can be up to '.self::MAX_LENGTH.' characters';
        }

        return '';
    }

    public static function may_delete(array $comment, $user_id): bool
    {
        if ((int) $user_id <= 0) {
            return false;
        }

        return (int) $comment['created_by'] === (int) $user_id;
    }

}
