<?php
/**
 * The joins the ready-made reports need that no screen does: evidence titles
 * per control for an assessment, and controls per evidence file for a project.
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
                    el.assessment_item_id,
                    e.evidence_title
                FROM
                    evidence_links el
                    JOIN evidence e ON e.id = el.evidence_id and e.deleted = 0
                    JOIN assessment_items ai ON ai.id = el.assessment_item_id and ai.deleted = 0
                    JOIN assessments a ON a.id = ai.assessment_id and a.deleted = 0
                WHERE
                    a.id = :assessment_id
                    and
                    a.company_id = :company_id
                ORDER BY
                    e.evidence_title";
        return parent::select($sql, $where);
    }

    public function evidence_controls($project_id, $company_id)
    {
        $where = array(
            'project_id' => $project_id,
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
                    a.project_id = :project_id
                    and
                    a.company_id = :company_id
                ORDER BY
                    ai.sort_order,
                    ai.control_identifier";
        return parent::select($sql, $where);
    }


}
