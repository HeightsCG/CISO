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
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0 and ai.control_kind = 'control'
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
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0 and ai.control_kind = 'control'
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
                    ai.control_kind = 'control'
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
                        WHERE ai.assessment_id = a.id and ai.deleted = 0 and ai.control_kind = 'control'
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
            'framework_key' => $standard[0]['framework_key'],
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
                $control['control_kind'],
                $control['control_title'],
                $control['description'],
                $control['guidance'],
                $control['family'],
                $control['control_weight'],
                $control['partial_weight'],
                $control['control_flags'],
                $control['source_document'],
                $control['source_version'],
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
                    s.version,
                    s.framework_key
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
                    sc.control_kind,
                    sc.control_title,
                    sc.description,
                    sc.guidance,
                    sc.family,
                    sc.control_weight,
                    sc.partial_weight,
                    sc.control_flags,
                    sc.source_document,
                    sc.source_version,
                    sc.sort_order
                FROM
                    standard_controls sc
                    JOIN standards s ON s.id = sc.standard_id
                WHERE
                    sc.standard_id = :standard_id
                    and
                    sc.deleted = 0
                ORDER BY
                    CASE WHEN s.framework_key = '' THEN sc.family ELSE '' END,
                    sc.sort_order,
                    sc.control_identifier";
        return parent::select($sql, $where);
    }

    public function add_item(
        $assessment_id,
        $control_identifier,
        $parent_identifier,
        $control_kind,
        $control_title,
        $description,
        $guidance,
        $family,
        $control_weight,
        $partial_weight,
        $control_flags,
        $source_document,
        $source_version,
        $sort_order
    )
    {
        $data = array(
            'assessment_id' => $assessment_id,
            'control_identifier' => $control_identifier,
            'parent_identifier' => $parent_identifier,
            'control_kind' => $control_kind,
            'control_title' => $control_title,
            'description' => $description,
            'guidance' => $guidance,
            'family' => $family,
            'control_weight' => $control_weight,
            'partial_weight' => $partial_weight,
            'control_flags' => $control_flags,
            'source_document' => $source_document,
            'source_version' => $source_version,
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
                    ai.control_kind,
                    ai.control_title,
                    ai.description,
                    ai.guidance,
                    ai.family,
                    ai.control_weight,
                    ai.partial_weight,
                    ai.control_flags,
                    ai.sort_order,
                    ai.item_result,
                    ai.notes,
                    ai.gap_description,
                    ai.business_impact,
                    ai.risk_rating,
                    ai.recommendation,
                    ai.owner_name,
                    ai.target_date,
                    ai.implementation_description,
                    ai.partial_credit,
                    ai.applicability,
                    ai.applicability_justification,
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
                    ai.id
                ORDER BY
                    CASE WHEN a.framework_key = '' THEN ai.family ELSE '' END,
                    ai.sort_order,
                    ai.control_identifier";
        return parent::select($sql, $where);
    }

    /** Control rows only (no assessment objectives), for summaries and exports. */
    public function load_controls($assessment_id, $company_id)
    {
        return array_values(array_filter($this->load_items($assessment_id, $company_id), function ($item) {
            return $item['control_kind'] === 'control';
        }));
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
                    ai.deleted = 0
                    and
                    ai.control_kind = 'control'";
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
                    and
                    ai.control_kind = 'control'
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
                    and
                    ai.control_kind = 'control'
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
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0 and ai.control_kind = 'control'
                WHERE
                    a.company_id = :company_id
                    and
                    a.deleted = 0
                GROUP BY
                    a.project_id";
        return parent::select($sql, $where);
    }

    /** Saves the finding fields of one control. Returns 0 when the item is not this company's. */
    public function save_control(
        $item_id,
        $company_id,
        $item_result,
        $notes,
        $gap_description,
        $business_impact,
        $risk_rating,
        $recommendation,
        $owner_name,
        $target_date,
        $implementation_description,
        $partial_credit,
        $applicability,
        $applicability_justification,
        $updated_by
    )
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
            'gap_description' => $gap_description,
            'business_impact' => $business_impact,
            'risk_rating' => $risk_rating,
            'recommendation' => $recommendation,
            'owner_name' => $owner_name,
            'target_date' => $target_date === '' ? null : $target_date,
            'implementation_description' => $implementation_description,
            'partial_credit' => $partial_credit,
            'applicability' => $applicability,
            'applicability_justification' => $applicability_justification,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        parent::update('assessment_items', $data, 'id = :id', $where);
        return 1;
    }

    /** Objective rows of one control, in order. */
    public function control_objectives($assessment_id, $control_identifier)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'parent_identifier' => $control_identifier
        );
        $sql = "SELECT
                    ai.id,
                    ai.item_result
                FROM
                    assessment_items ai
                WHERE
                    ai.assessment_id = :assessment_id
                    and
                    ai.parent_identifier = :parent_identifier
                    and
                    ai.control_kind = 'objective'
                    and
                    ai.deleted = 0
                ORDER BY
                    ai.sort_order";
        return parent::select($sql, $where);
    }

    public function set_objective_result($objective_id, $assessment_id, $item_result, $updated_by)
    {
        $where = array(
            'id' => $objective_id,
            'assessment_id' => $assessment_id
        );
        $data = array(
            'item_result' => $item_result,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('assessment_items', $data, "id = :id and assessment_id = :assessment_id and control_kind = 'objective'", $where);
    }

    public function set_control_result($control_id, $assessment_id, $item_result, $updated_by)
    {
        $where = array(
            'id' => $control_id,
            'assessment_id' => $assessment_id
        );
        $data = array(
            'item_result' => $item_result,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('assessment_items', $data, "id = :id and assessment_id = :assessment_id and control_kind = 'control'", $where);
    }

    /**
     * A control with objectives is Implemented only when every objective is met
     * (objectives marked not applicable count as met); Not Applicable when all
     * are not applicable; Not Implemented when none is met; Partially
     * Implemented otherwise. While any objective is unanswered the control is
     * Not Assessed.
     */
    public static function result_from_objectives(array $results): string
    {
        if (count($results) === 0 || in_array('Not Assessed', $results, true)) {
            return 'Not Assessed';
        }

        $counts = array_count_values($results);
        $met = $counts['Implemented'] ?? 0;
        $not_met = $counts['Not Implemented'] ?? 0;

        if ($met === 0 && $not_met === 0) {
            return 'Not Applicable';
        }

        if ($not_met === 0) {
            return 'Implemented';
        }

        return $met === 0 ? 'Not Implemented' : 'Partially Implemented';
    }

    public function item_milestones($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    m.id,
                    m.assessment_item_id,
                    m.milestone_text,
                    m.due_date,
                    m.milestone_status,
                    m.sort_order
                FROM
                    item_milestones m
                    JOIN assessment_items ai ON ai.id = m.assessment_item_id and ai.deleted = 0
                    JOIN assessments a ON a.id = ai.assessment_id and a.deleted = 0
                WHERE
                    ai.assessment_id = :assessment_id
                    and
                    a.company_id = :company_id
                    and
                    m.deleted = 0
                ORDER BY
                    m.assessment_item_id,
                    m.sort_order,
                    m.id";
        return parent::select($sql, $where);
    }

    public function save_milestone($milestone_id, $item_id, $milestone_text, $due_date, $milestone_status, $sort_order, $user_id)
    {
        $data = array(
            'milestone_text' => $milestone_text,
            'due_date' => $due_date === '' ? null : $due_date,
            'milestone_status' => $milestone_status,
            'sort_order' => $sort_order,
            'updated_by' => $user_id,
            'date_updated' => date('Y-m-d H:i:s')
        );

        if ($milestone_id === 0) {
            $data['assessment_item_id'] = $item_id;
            $data['created_by'] = $user_id;
            $data['date_created'] = date('Y-m-d H:i:s');
            $data['deleted'] = 0;
            return (int) parent::insert('item_milestones', $data);
        }

        $where = array(
            'id' => $milestone_id,
            'assessment_item_id' => $item_id
        );
        parent::update('item_milestones', $data, 'id = :id and assessment_item_id = :assessment_item_id', $where);
        return $milestone_id;
    }

    public function milestone_ids($item_id)
    {
        $where = array(
            'item_id' => $item_id
        );
        $sql = "SELECT
                    m.id
                FROM
                    item_milestones m
                WHERE
                    m.assessment_item_id = :item_id
                    and
                    m.deleted = 0";
        return array_map('intval', array_column(parent::select($sql, $where), 'id'));
    }

    public function delete_milestone($milestone_id, $item_id, $user_id)
    {
        $data = array(
            'deleted' => 1,
            'updated_by' => $user_id,
            'date_updated' => date('Y-m-d H:i:s')
        );
        $where = array(
            'id' => $milestone_id,
            'assessment_item_id' => $item_id
        );
        return parent::update('item_milestones', $data, 'id = :id and assessment_item_id = :assessment_item_id', $where);
    }

    public function save_details(
        $assessment_id,
        $company_id,
        $assessment_type,
        $baseline_assessment_id,
        $no_baseline,
        $scope_categories,
        $ssi_reviewed,
        $ssi_note,
        $tier_governance,
        $tier_management,
        $target_tier_governance,
        $target_tier_management,
        $updated_by
    )
    {
        $data = array(
            'tier_governance' => $tier_governance,
            'tier_management' => $tier_management,
            'target_tier_governance' => $target_tier_governance,
            'target_tier_management' => $target_tier_management,
            'assessment_type' => $assessment_type,
            'baseline_assessment_id' => $baseline_assessment_id,
            'no_baseline' => $no_baseline,
            'scope_categories' => $scope_categories,
            'ssi_reviewed' => $ssi_reviewed,
            'ssi_note' => $ssi_note,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        $where = array(
            'id' => $assessment_id,
            'company_id' => $company_id
        );
        return parent::update('assessments', $data, 'id = :id and company_id = :company_id', $where);
    }

    /** Baseline assessments for the same client and framework that this one can be compared against. */
    public function baseline_options($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'self_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    b.id,
                    b.assessment_name,
                    bp.project_name,
                    DATE_FORMAT(b.date_created, '%Y-%m-%d') AS created_on
                FROM
                    assessments a
                    JOIN projects p ON p.id = a.project_id
                    JOIN projects bp ON bp.client_id = p.client_id and bp.company_id = p.company_id and bp.deleted = 0
                    JOIN assessments b ON b.project_id = bp.id and b.deleted = 0
                WHERE
                    a.id = :assessment_id
                    and
                    a.company_id = :company_id
                    and
                    b.id <> :self_id
                    and
                    b.assessment_type = 'Baseline'
                    and
                    b.framework_key = a.framework_key
                    and
                    a.framework_key <> ''
                ORDER BY
                    b.date_created desc";
        return parent::select($sql, $where);
    }

    public function begin()
    {
        $this->db->beginTransaction();
    }

    public function commit()
    {
        $this->db->commit();
    }

    public function roll_back()
    {
        $this->db->rollBack();
    }

}
