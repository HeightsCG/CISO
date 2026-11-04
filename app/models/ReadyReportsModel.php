<?php
/**
 * The joins the ready-made reports need that no screen does: evidence titles
 * per control for an assessment, and controls per evidence file. All are
 * scoped to the company.
 */
class ReadyReportsModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function item_evidence($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    IFNULL(c.id, ai.id) AS assessment_item_id,
                    e.evidence_title
                FROM
                    evidence_links el
                    JOIN evidence e ON e.id = el.evidence_id and e.deleted = 0
                    JOIN assessment_items ai ON ai.id = el.assessment_item_id and ai.deleted = 0
                    JOIN assessments a ON a.id = ai.assessment_id and a.deleted = 0
                    LEFT JOIN assessment_items c ON ai.control_kind = 'objective'
                        and c.assessment_id = ai.assessment_id
                        and c.control_identifier = ai.parent_identifier
                        and c.control_kind = 'control'
                        and c.deleted = 0
                WHERE
                    a.id = :assessment_id
                    and
                    a.company_id = :company_id
                ORDER BY
                    e.evidence_title";
        return parent::select($sql, $where);
    }

    /** Every evidence link of one assessment, against the control or objective it was attached to. */
    public function evidence_by_item($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    el.assessment_item_id,
                    e.id AS evidence_id,
                    e.evidence_title,
                    e.description,
                    e.file_name,
                    e.file_type,
                    e.date_created,
                    CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name
                FROM
                    evidence_links el
                    JOIN evidence e ON e.id = el.evidence_id and e.deleted = 0
                    JOIN assessment_items ai ON ai.id = el.assessment_item_id and ai.deleted = 0
                    JOIN assessments a ON a.id = ai.assessment_id and a.deleted = 0
                    LEFT JOIN user_accounts u ON u.user_id = e.uploaded_by
                WHERE
                    a.id = :assessment_id
                    and
                    a.company_id = :company_id
                ORDER BY
                    e.evidence_title";
        return parent::select($sql, $where);
    }

    /** Controls and objectives of one assessment that each evidence file supports. */
    public function assessment_evidence_controls($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    el.evidence_id,
                    ai.control_identifier
                FROM
                    evidence_links el
                    JOIN assessment_items ai ON ai.id = el.assessment_item_id and ai.deleted = 0
                    JOIN assessments a ON a.id = ai.assessment_id and a.deleted = 0
                WHERE
                    a.id = :assessment_id
                    and
                    a.company_id = :company_id
                ORDER BY
                    ai.sort_order,
                    ai.control_identifier";
        return parent::select($sql, $where);
    }

    public function evidence_controls($client_id, $company_id)
    {
        $where = array(
            'client_id' => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    el.evidence_id,
                    ai.control_identifier,
                    a.assessment_name
                FROM
                    evidence_links el
                    JOIN assessment_items ai ON ai.id = el.assessment_item_id and ai.deleted = 0
                    JOIN assessments a ON a.id = ai.assessment_id and a.deleted = 0
                WHERE
                    a.client_id = :client_id
                    and
                    a.company_id = :company_id
                ORDER BY
                    ai.sort_order,
                    ai.control_identifier";
        return parent::select($sql, $where);
    }


}
