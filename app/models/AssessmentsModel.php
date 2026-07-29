<?php
class AssessmentsModel extends Model {

    public function __construct(){
        parent::__construct();
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

    /**
     * Creating an assessment copies the standard's controls in rather than
     * referencing them, so later edits to the library can never rewrite an
     * assessment that is already under way.
     */
    public function create_assessment($project_id, $company_id, $standard_id, $assessment_name, $created_by)
    {
        $standard = parent::select(
            "SELECT s.id, s.standard_name, s.short_code, s.version
             FROM standards s
             WHERE s.id = :id and s.company_id = :company_id and s.standard_status = 'Active' and s.deleted = 0",
            array('id' => $standard_id, 'company_id' => $company_id)
        );

        if (count($standard) !== 1) {
            return 0;
        }

        $controls = parent::select(
            "SELECT sc.control_identifier, sc.parent_identifier, sc.control_title, sc.description, sc.family, sc.sort_order
             FROM standard_controls sc
             WHERE sc.standard_id = :standard_id and sc.deleted = 0
             ORDER BY sc.family, sc.sort_order, sc.control_identifier",
            array('standard_id' => $standard_id)
        );

        if (count($controls) === 0) {
            return 0;
        }

        $this->db->beginTransaction();

        try {

            $assessment_id = parent::insert('assessments', array(
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
            ));

            foreach ($controls as $control) {
                parent::insert('assessment_items', array(
                    'assessment_id' => $assessment_id,
                    'control_identifier' => $control['control_identifier'],
                    'parent_identifier' => $control['parent_identifier'],
                    'control_title' => $control['control_title'],
                    'description' => $control['description'],
                    'family' => $control['family'],
                    'sort_order' => $control['sort_order'],
                    'item_result' => 'Not Assessed',
                    'notes' => '',
                    'updated_by' => 0,
                    'date_created' => date('Y-m-d H:i:s'),
                    'date_updated' => date('Y-m-d H:i:s'),
                    'deleted' => 0
                ));
            }

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $assessment_id;
    }

    public function update_assessment($assessment_id, $company_id, $assessment_name, $assessment_status, $updated_by)
    {
        $where = array(
            'id' => $assessment_id,
            'company_id' => $company_id
        );
        $data = array(
            'assessment_name' => $assessment_name,
            'assessment_status' => $assessment_status,
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

    /* ------------------------------------------------------- reporting hooks */

    /** Result totals for one assessment, for the header and the project roll-up. */
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

    /** Results broken down by family - the shape a report consumes. */
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

    /** Every item with its result, notes and attached evidence - the report body. */
    public function report_detail($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    ai.control_identifier,
                    ai.control_title,
                    ai.family,
                    ai.sort_order,
                    ai.item_result,
                    ai.notes,
                    e.id AS evidence_id,
                    e.evidence_title,
                    e.file_name,
                    e.expiry_date
                FROM
                    assessment_items ai
                    JOIN assessments a ON a.id = ai.assessment_id
                    LEFT JOIN evidence_links el ON el.assessment_item_id = ai.id
                    LEFT JOIN evidence e ON e.id = el.evidence_id and e.deleted = 0
                WHERE
                    ai.assessment_id = :assessment_id
                    and
                    a.company_id = :company_id
                    and
                    ai.deleted = 0
                ORDER BY
                    ai.family,
                    ai.sort_order,
                    ai.control_identifier,
                    e.evidence_title";
        return parent::select($sql, $where);
    }

    /** Assessment progress for every project in one call, for the projects list. */
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
