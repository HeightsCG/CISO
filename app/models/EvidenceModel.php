<?php
class EvidenceModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /** The client's vault: every piece of evidence with how widely it is reused. */
    public function load_evidence($client_id, $company_id)
    {
        $where = array(
            'client_id' => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    e.id,
                    e.evidence_title,
                    e.description,
                    e.file_name,
                    e.file_size,
                    e.file_type,
                    e.expiry_date,
                    e.date_created,
                    DATE_FORMAT(e.date_created, df.sql_format) AS date_created_display,
                    DATE_FORMAT(e.expiry_date, df.sql_format) AS expiry_date_display,
                    CASE WHEN e.expiry_date IS NOT NULL and e.expiry_date < CURDATE() THEN 1 ELSE 0 END AS expired,
                    CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name,
                    COUNT(el.id) AS link_count
                FROM
                    evidence e
                    JOIN companies co ON co.id = e.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN user_accounts u ON u.user_id = e.uploaded_by
                    LEFT JOIN evidence_links el ON el.evidence_id = e.id
                WHERE
                    e.client_id = :client_id
                    and
                    e.company_id = :company_id
                    and
                    e.deleted = 0
                GROUP BY
                    e.id,
                    e.evidence_title,
                    e.description,
                    e.file_name,
                    e.file_size,
                    e.file_type,
                    e.expiry_date,
                    e.date_created,
                    df.sql_format,
                    u.first_name,
                    u.last_name
                ORDER BY
                    e.date_created DESC,
                    e.id DESC";
        return parent::select($sql, $where);
    }

    public function get_evidence($evidence_id, $company_id)
    {
        $where = array(
            'id' => $evidence_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    e.*,
                    DATE_FORMAT(e.date_created, df.sql_format) AS date_created_display,
                    DATE_FORMAT(e.expiry_date, df.sql_format) AS expiry_date_display,
                    c.company_name AS client_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name
                FROM
                    evidence e
                    JOIN companies co ON co.id = e.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN clients c ON c.id = e.client_id
                    LEFT JOIN user_accounts u ON u.user_id = e.uploaded_by
                WHERE
                    e.id = :id
                    and
                    e.company_id = :company_id
                    and
                    e.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_evidence(
        $company_id,
        $client_id,
        $evidence_title,
        $description,
        $file_key,
        $file_name,
        $file_size,
        $file_type,
        $expiry_date,
        $uploaded_by
    )
    {
        $data = array(
            'company_id' => $company_id,
            'client_id' => $client_id,
            'evidence_title' => $evidence_title,
            'description' => $description,
            'file_key' => $file_key,
            'file_name' => $file_name,
            'file_size' => $file_size,
            'file_type' => $file_type,
            'expiry_date' => ($expiry_date === '' ? null : $expiry_date),
            'uploaded_by' => $uploaded_by,
            'updated_by' => $uploaded_by,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('evidence', $data);
    }

    public function update_evidence($evidence_id, $company_id, $evidence_title, $description, $expiry_date, $updated_by)
    {
        $where = array(
            'id' => $evidence_id,
            'company_id' => $company_id
        );
        $data = array(
            'evidence_title' => $evidence_title,
            'description' => $description,
            'expiry_date' => ($expiry_date === '' ? null : $expiry_date),
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('evidence', $data, 'id = :id and company_id = :company_id', $where);
    }

    /**
     * Removing evidence drops every link with it: a link pointing at a file that no
     * longer exists would show an assessment item as evidenced when it is not.
     */
    public function delete_evidence($evidence_id, $company_id, $updated_by)
    {
        $where = array(
            'id' => $evidence_id,
            'company_id' => $company_id
        );
        $data = array(
            'deleted' => 1,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        $rows = parent::update('evidence', $data, 'id = :id and company_id = :company_id', $where);

        if ($rows > 0) {
            parent::delete_all('evidence_links', 'evidence_id = :evidence_id', array('evidence_id' => $evidence_id));
        }

        return $rows;
    }

    /** Evidence attached to one assessment item. */
    public function load_item_evidence($item_id, $company_id)
    {
        // Native prepares cannot reuse a named placeholder, so each occurrence
        // gets its own name.
        $where = array(
            'item_id' => $item_id,
            'company_id' => $company_id,
            'company_id2' => $company_id
        );
        $sql = "SELECT
                    e.id,
                    e.evidence_title,
                    e.file_name,
                    e.file_size,
                    e.expiry_date,
                    DATE_FORMAT(e.expiry_date, df.sql_format) AS expiry_date_display,
                    CASE WHEN e.expiry_date IS NOT NULL and e.expiry_date < CURDATE() THEN 1 ELSE 0 END AS expired
                FROM
                    evidence_links el
                    JOIN evidence e ON e.id = el.evidence_id
                    JOIN companies co ON co.id = e.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    JOIN assessment_items ai ON ai.id = el.assessment_item_id
                    JOIN assessments a ON a.id = ai.assessment_id
                WHERE
                    el.assessment_item_id = :item_id
                    and
                    e.company_id = :company_id
                    and
                    a.company_id = :company_id2
                    and
                    e.deleted = 0
                ORDER BY
                    e.evidence_title";
        return parent::select($sql, $where);
    }

    /** Everywhere one piece of evidence is used - the other side of the link. */
    public function load_evidence_links($evidence_id, $company_id)
    {
        $where = array(
            'evidence_id' => $evidence_id,
            'company_id' => $company_id,
            'company_id2' => $company_id
        );
        $sql = "SELECT
                    el.id AS link_id,
                    ai.id AS item_id,
                    ai.control_identifier,
                    ai.control_title,
                    ai.family,
                    ai.item_result,
                    a.id AS assessment_id,
                    a.assessment_name,
                    a.standard_name,
                    a.short_code,
                    p.id AS project_id,
                    p.project_name
                FROM
                    evidence_links el
                    JOIN evidence e ON e.id = el.evidence_id
                    JOIN assessment_items ai ON ai.id = el.assessment_item_id
                    JOIN assessments a ON a.id = ai.assessment_id
                    JOIN projects p ON p.id = a.project_id
                WHERE
                    el.evidence_id = :evidence_id
                    and
                    e.company_id = :company_id
                    and
                    a.company_id = :company_id2
                    and
                    e.deleted = 0
                    and
                    ai.deleted = 0
                    and
                    a.deleted = 0
                ORDER BY
                    p.project_name,
                    a.assessment_name,
                    ai.family,
                    ai.sort_order";
        return parent::select($sql, $where);
    }

    /**
     * Both sides are re-checked against the session's org before a link is made:
     * the evidence and the item must belong to the same company, and the evidence
     * to the same client as the item's project.
     */
    public function link_evidence($evidence_id, $item_id, $company_id, $created_by)
    {
        $check = parent::select(
            "SELECT e.id
             FROM evidence e
                JOIN assessment_items ai ON ai.id = :item_id
                JOIN assessments a ON a.id = ai.assessment_id
                JOIN projects p ON p.id = a.project_id
             WHERE e.id = :evidence_id
                and e.company_id = :company_id
                and a.company_id = :company_id2
                and e.client_id = p.client_id
                and e.deleted = 0
                and ai.deleted = 0
                and a.deleted = 0",
            array(
                'item_id' => $item_id,
                'evidence_id' => $evidence_id,
                'company_id' => $company_id,
                'company_id2' => $company_id
            )
        );

        if (count($check) !== 1) {
            return 0;
        }

        $existing = parent::select(
            "SELECT id FROM evidence_links WHERE evidence_id = :evidence_id and assessment_item_id = :item_id",
            array('evidence_id' => $evidence_id, 'item_id' => $item_id)
        );

        if (count($existing) > 0) {
            return (int) $existing[0]['id'];
        }

        return parent::insert('evidence_links', array(
            'evidence_id' => $evidence_id,
            'assessment_item_id' => $item_id,
            'created_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s')
        ));
    }

    public function unlink_evidence($evidence_id, $item_id, $company_id)
    {
        $check = parent::select(
            "SELECT el.id
             FROM evidence_links el
                JOIN evidence e ON e.id = el.evidence_id
                JOIN assessment_items ai ON ai.id = el.assessment_item_id
                JOIN assessments a ON a.id = ai.assessment_id
             WHERE el.evidence_id = :evidence_id
                and el.assessment_item_id = :item_id
                and e.company_id = :company_id
                and a.company_id = :company_id2",
            array(
                'evidence_id' => $evidence_id,
                'item_id' => $item_id,
                'company_id' => $company_id,
                'company_id2' => $company_id
            )
        );

        if (count($check) !== 1) {
            return 0;
        }

        return parent::delete_all(
            'evidence_links',
            'evidence_id = :evidence_id and assessment_item_id = :item_id',
            array('evidence_id' => $evidence_id, 'item_id' => $item_id)
        );
    }

}
