<?php
/**
 * The document state of an assessment's reports: Draft, In Review or Final,
 * its version and, for Final, the digest of what was approved. One row per
 * assessment; no row means Draft 0.1.
 */
class ReportStatesModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function get_state($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    rs.assessment_id,
                    rs.company_id,
                    rs.report_status,
                    rs.report_version,
                    rs.final_hash,
                    rs.finalized_by,
                    rs.finalized_date,
                    rs.updated_by,
                    rs.date_updated,
                    TRIM(CONCAT(IFNULL(ua.first_name, ''), ' ', IFNULL(ua.last_name, ''))) AS finalized_by_name
                FROM
                    report_states rs
                    LEFT JOIN user_accounts ua ON ua.user_id = rs.finalized_by
                WHERE
                    rs.assessment_id = :assessment_id
                    and
                    rs.company_id = :company_id";
        return parent::select($sql, $where);
    }

    public function save_state(
        $assessment_id,
        $company_id,
        $report_status,
        $report_version,
        $final_hash,
        $finalized_by,
        $finalized_date,
        $updated_by
    )
    {
        $data = array(
            'report_status' => $report_status,
            'report_version' => $report_version,
            'final_hash' => $final_hash,
            'finalized_by' => $finalized_by,
            'finalized_date' => $finalized_date,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );

        if (count($this->get_state($assessment_id, $company_id)) === 1) {
            $where = array(
                'assessment_id' => $assessment_id,
                'company_id' => $company_id
            );
            return parent::update('report_states', $data, 'assessment_id = :assessment_id and company_id = :company_id', $where);
        }

        $data['assessment_id'] = $assessment_id;
        $data['company_id'] = $company_id;

        return parent::insert('report_states', $data);
    }
}
