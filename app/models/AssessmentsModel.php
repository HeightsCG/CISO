<?php
class AssessmentsModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /**
     * How many assessments this project already carries, for the plan gate. The
     * limit is per project, so it is counted within the project rather than
     * across the company.
     */
    public function count_assessments($project_id, $company_id)
    {
        $where = array(
            'project_id' => $project_id,
            'company_id' => $company_id
        );
        $sql = "SELECT COUNT(*) AS total FROM assessments WHERE project_id = :project_id and company_id = :company_id and deleted = 0";
        $rows = parent::select($sql, $where);

        return (int) ($rows[0]['total'] ?? 0);
    }

    public function load_assessments($project_id, $company_id)
    {
        $where = array(
            'project_id' => $project_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.id,
                    a.assessment_name,
                    a.standard_name,
                    a.short_code,
                    a.version,
                    a.assessment_status,
                    a.date_created,
                    DATE_FORMAT(a.date_created, df.sql_format) AS date_created_display,
                    COUNT(ai.id) AS item_count,
                    SUM(CASE WHEN ai.item_result <> 'Not Assessed' THEN 1 ELSE 0 END) AS assessed_count
                FROM
                    assessments a
                    JOIN companies co ON co.id = a.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0
                WHERE
                    a.project_id = :project_id
                    and
                    a.company_id = :company_id
                    and
                    a.deleted = 0
                GROUP BY
                    a.id,
                    a.assessment_name,
                    a.standard_name,
                    a.short_code,
                    a.version,
                    a.assessment_status,
                    a.date_created,
                    df.sql_format
                ORDER BY
                    a.date_created DESC,
                    a.id DESC";
        return parent::select($sql, $where);
    }

    /**
     * Every assessment across one client's projects, in a single query so the
     * client record does not fan out into one request per project.
     */
    public function client_assessments($client_id, $company_id)
    {
        $where = array(
            'client_id' => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.id,
                    a.project_id,
                    a.assessment_name,
                    a.short_code,
                    a.version,
                    a.assessment_status,
                    COUNT(ai.id) AS item_count,
                    SUM(CASE WHEN ai.item_result <> 'Not Assessed' THEN 1 ELSE 0 END) AS assessed_count
                FROM
                    assessments a
                    JOIN projects p ON p.id = a.project_id
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0
                WHERE
                    p.client_id = :client_id
                    and
                    a.company_id = :company_id
                    and
                    a.deleted = 0
                    and
                    p.deleted = 0
                GROUP BY
                    a.id,
                    a.project_id,
                    a.assessment_name,
                    a.short_code,
                    a.version,
                    a.assessment_status
                ORDER BY
                    a.date_created DESC,
                    a.id DESC";
        return parent::select($sql, $where);
    }

    public function client_assessment($assessment_id, $client_id, $company_id)
    {
        $where = array(
            'id' => $assessment_id,
            'client_id' => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.id,
                    a.project_id,
                    a.assessment_name,
                    a.standard_name,
                    a.short_code,
                    a.version,
                    a.assessment_status,
                    p.project_name
                FROM
                    assessments a
                    JOIN projects p ON p.id = a.project_id
                WHERE
                    a.id = :id
                    and
                    p.client_id = :client_id
                    and
                    a.company_id = :company_id
                    and
                    a.deleted = 0
                    and
                    p.deleted = 0";
        return parent::select($sql, $where);
    }

    public function portal_items($assessment_id, $client_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'client_id' => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    ai.id,
                    ai.control_identifier,
                    ai.parent_identifier,
                    ai.control_title,
                    ai.description,
                    ai.family,
                    ai.sort_order,
                    ai.item_result,
                    (SELECT COUNT(*)
                        FROM evidence_links el
                        JOIN evidence e ON e.id = el.evidence_id
                        WHERE el.assessment_item_id = ai.id
                        and e.deleted = 0
                        and e.evidence_private = 0) AS evidence_count
                FROM
                    assessment_items ai
                    JOIN assessments a ON a.id = ai.assessment_id
                    JOIN projects p ON p.id = a.project_id
                WHERE
                    ai.assessment_id = :assessment_id
                    and
                    p.client_id = :client_id
                    and
                    a.company_id = :company_id
                    and
                    ai.deleted = 0
                    and
                    a.deleted = 0
                    and
                    p.deleted = 0
                ORDER BY
                    ai.family,
                    ai.sort_order,
                    ai.control_identifier";
        return parent::select($sql, $where);
    }

    public function get_assessment($assessment_id, $company_id)
    {
        $where = array(
            'id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.*,
                    p.project_name,
                    p.client_id,
                    c.company_name AS client_name,
                    (
                        SELECT COUNT(ai.id)
                        FROM assessment_items ai
                        WHERE ai.assessment_id = a.id and ai.deleted = 0
                    ) AS item_count
                FROM
                    assessments a
                    JOIN projects p ON p.id = a.project_id
                    LEFT JOIN clients c ON c.id = p.client_id and c.deleted = 0
                WHERE
                    a.id = :id
                    and
                    a.company_id = :company_id
                    and
                    a.deleted = 0";
        return parent::select($sql, $where);
    }

    public function create_assessment($project_id, $company_id, $standard_id, $assessment_name, $created_by)
    {
        $standard = $this->assessment_standard($standard_id, $company_id);

        if (count($standard) !== 1) {
            return 0;
        }

        $controls = $this->assessment_controls($standard_id);

        if (count($controls) === 0) {
            return 0;
        }

        $data = array(
            'company_id' => $company_id,
            'project_id' => $project_id,
            'standard_id' => $standard_id,
            'standard_name' => $standard[0]['standard_name'],
            'short_code' => $standard[0]['short_code'],
            'version' => $standard[0]['version'],
            'assessment_name' => $assessment_name,
            'assessment_status' => 'Planned',
            'created_by' => $created_by,
            'updated_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        $assessment_id = parent::insert('assessments', $data);

        foreach ($controls as $control) {
            $this->add_item(
                $assessment_id,
                $control['control_identifier'],
                $control['parent_identifier'],
                $control['control_title'],
                $control['description'],
                $control['family'],
                $control['sort_order']
            );
        }

        return $assessment_id;
    }

    /** The standard an assessment is being cut from, active and in this org only. */
    public function assessment_standard($standard_id, $company_id)
    {
        $where = array(
            'id' => $standard_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    s.id,
                    s.standard_name,
                    s.short_code,
                    s.version
                FROM
                    standards s
                WHERE
                    s.id = :id
                    and
                    s.company_id = :company_id
                    and
                    s.standard_status = 'Active'
                    and
                    s.deleted = 0";
        return parent::select($sql, $where);
    }

    public function assessment_controls($standard_id)
    {
        $where = array(
            'standard_id' => $standard_id
        );
        $sql = "SELECT
                    sc.control_identifier,
                    sc.parent_identifier,
                    sc.control_title,
                    sc.description,
                    sc.family,
                    sc.sort_order
                FROM
                    standard_controls sc
                WHERE
                    sc.standard_id = :standard_id
                    and
                    sc.deleted = 0
                ORDER BY
                    sc.family,
                    sc.sort_order,
                    sc.control_identifier";
        return parent::select($sql, $where);
    }

    public function add_item(
        $assessment_id,
        $control_identifier,
        $parent_identifier,
        $control_title,
        $description,
        $family,
        $sort_order
    )
    {
        $data = array(
            'assessment_id' => $assessment_id,
            'control_identifier' => $control_identifier,
            'parent_identifier' => $parent_identifier,
            'control_title' => $control_title,
            'description' => $description,
            'family' => $family,
            'sort_order' => $sort_order,
            'item_result' => 'Not Assessed',
            'notes' => '',
            'updated_by' => 0,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('assessment_items', $data);
    }

    public function update_assessment($assessment_id, $company_id, $assessment_name, $updated_by)
    {
        $where = array(
            'id' => $assessment_id,
            'company_id' => $company_id
        );
        $data = array(
            'assessment_name' => $assessment_name,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('assessments', $data, 'id = :id and company_id = :company_id', $where);
    }

    public function delete_assessment($assessment_id, $company_id, $updated_by)
    {
        $where = array(
            'id' => $assessment_id,
            'company_id' => $company_id
        );
        $data = array(
            'deleted' => 1,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        $rows = parent::update('assessments', $data, 'id = :id and company_id = :company_id', $where);

        if ($rows > 0) {

            parent::delete_all(
                'evidence_links',
                'assessment_item_id IN (SELECT id FROM assessment_items WHERE assessment_id = :assessment_id)',
                array('assessment_id' => $assessment_id)
            );

            parent::update(
                'assessment_items',
                array('deleted' => 1, 'date_updated' => date('Y-m-d H:i:s')),
                'assessment_id = :assessment_id',
                array('assessment_id' => $assessment_id)
            );
        }

        return $rows;
    }

    public function load_items($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    ai.id,
                    ai.control_identifier,
                    ai.parent_identifier,
                    ai.control_title,
                    ai.description,
                    ai.family,
                    ai.sort_order,
                    ai.item_result,
                    ai.notes,
                    COUNT(el.id) AS evidence_count
                FROM
                    assessment_items ai
                    JOIN assessments a ON a.id = ai.assessment_id
                    LEFT JOIN evidence_links el ON el.assessment_item_id = ai.id
                WHERE
                    ai.assessment_id = :assessment_id
                    and
                    a.company_id = :company_id
                    and
                    ai.deleted = 0
                    and
                    a.deleted = 0
                GROUP BY
                    ai.id,
                    ai.control_identifier,
                    ai.parent_identifier,
                    ai.control_title,
                    ai.description,
                    ai.family,
                    ai.sort_order,
                    ai.item_result,
                    ai.notes
                ORDER BY
                    ai.family,
                    ai.sort_order,
                    ai.control_identifier";
        return parent::select($sql, $where);
    }

    public function get_item($item_id, $company_id)
    {
        $where = array(
            'id' => $item_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    ai.*,
                    a.company_id,
                    a.assessment_name,
                    p.client_id
                FROM
                    assessment_items ai
                    JOIN assessments a ON a.id = ai.assessment_id
                    JOIN projects p ON p.id = a.project_id
                WHERE
                    ai.id = :id
                    and
                    a.company_id = :company_id
                    and
                    ai.deleted = 0
                    and
                    a.deleted = 0";
        return parent::select($sql, $where);
    }

    public function save_item($item_id, $company_id, $item_result, $notes, $updated_by)
    {
        $item = $this->get_item($item_id, $company_id);

        if (count($item) !== 1) {
            return 0;
        }

        $where = array(
            'id' => $item_id
        );
        $data = array(
            'item_result' => $item_result,
            'notes' => $notes,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('assessment_items', $data, 'id = :id', $where);
    }

    public function unassessed_count($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    COUNT(*) AS unassessed
                FROM
                    assessment_items ai
                    JOIN assessments a ON a.id = ai.assessment_id
                WHERE
                    ai.assessment_id = :assessment_id
                    and
                    a.company_id = :company_id
                    and
                    ai.item_result = 'Not Assessed'
                    and
                    ai.deleted = 0";
        $rows = parent::select($sql, $where);
        return (int) $rows[0]['unassessed'];
    }

    public function set_assessment_status($assessment_id, $company_id, $assessment_status, $updated_by)
    {
        $where = array(
            'id' => $assessment_id,
            'company_id' => $company_id
        );
        $data = array(
            'assessment_status' => $assessment_status,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('assessments', $data, 'id = :id and company_id = :company_id', $where);
    }

    public function sync_assessment_status($assessment_id, $company_id, $updated_by)
    {
        $assessment = $this->get_assessment($assessment_id, $company_id);

        if (count($assessment) !== 1) {
            return '';
        }

        $current = $assessment[0]['assessment_status'];
        $unassessed = $this->unassessed_count($assessment_id, $company_id);
        $status = ($unassessed === (int) $assessment[0]['item_count'] ? 'Planned' : 'In Progress');

        if ($current === 'Complete' && $unassessed === 0) {
            return $current;
        }

        if ($current !== $status) {
            $this->set_assessment_status($assessment_id, $company_id, $status, $updated_by);
        }

        return $status;
    }

    public function result_totals($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    ai.item_result,
                    COUNT(*) AS item_count
                FROM
                    assessment_items ai
                    JOIN assessments a ON a.id = ai.assessment_id
                WHERE
                    ai.assessment_id = :assessment_id
                    and
                    a.company_id = :company_id
                    and
                    ai.deleted = 0
                GROUP BY
                    ai.item_result";
        return parent::select($sql, $where);
    }

    public function results_by_family($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    ai.family,
                    COUNT(*) AS item_count,
                    SUM(CASE WHEN ai.item_result = 'Implemented' THEN 1 ELSE 0 END) AS implemented,
                    SUM(CASE WHEN ai.item_result = 'Partially Implemented' THEN 1 ELSE 0 END) AS partially_implemented,
                    SUM(CASE WHEN ai.item_result = 'Not Implemented' THEN 1 ELSE 0 END) AS not_implemented,
                    SUM(CASE WHEN ai.item_result = 'Not Applicable' THEN 1 ELSE 0 END) AS not_applicable,
                    SUM(CASE WHEN ai.item_result = 'Not Assessed' THEN 1 ELSE 0 END) AS not_assessed,
                    SUM(CASE WHEN ev.link_count > 0 THEN 1 ELSE 0 END) AS with_evidence
                FROM
                    assessment_items ai
                    JOIN assessments a ON a.id = ai.assessment_id
                    LEFT JOIN (
                        SELECT el.assessment_item_id, COUNT(*) AS link_count
                        FROM evidence_links el
                        GROUP BY el.assessment_item_id
                    ) ev ON ev.assessment_item_id = ai.id
                WHERE
                    ai.assessment_id = :assessment_id
                    and
                    a.company_id = :company_id
                    and
                    ai.deleted = 0
                GROUP BY
                    ai.family
                ORDER BY
                    ai.family";
        return parent::select($sql, $where);
    }

    public function project_progress($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.project_id,
                    COUNT(DISTINCT a.id) AS assessment_count,
                    COUNT(ai.id) AS item_count,
                    SUM(CASE WHEN ai.item_result <> 'Not Assessed' THEN 1 ELSE 0 END) AS assessed_count
                FROM
                    assessments a
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0
                WHERE
                    a.company_id = :company_id
                    and
                    a.deleted = 0
                GROUP BY
                    a.project_id";
        return parent::select($sql, $where);
    }

}
