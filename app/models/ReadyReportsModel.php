<?php
/**
 * The joins the ready-made reports need that no screen does: evidence titles
 * per control for an assessment, and controls per evidence file for a client.
 * Both are scoped to the company.
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
