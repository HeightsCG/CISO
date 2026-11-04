<?php
class AssessmentsModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /**
     * How many assessments this client already carries, for the plan gate. The
     * limit is per client, so it is counted within the client rather than across
     * the company.
     */
    public function count_assessments($client_id, $company_id)
    {
        $where = array(
            'client_id' => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT COUNT(*) AS total FROM assessments WHERE client_id = :client_id and company_id = :company_id and deleted = 0";
        $rows = parent::select($sql, $where);

        return (int) ($rows[0]['total'] ?? 0);
    }

    /** Every live assessment in the organisation, newest first, for the Assessments list. */
    public function load_all_assessments($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        return parent::select($this->list_sql(''), $where);
    }

    /** One client's live assessments, in the same row shape as load_all_assessments. */
    public function client_assessments($client_id, $company_id)
    {
        $where = array(
            'client_id' => $client_id,
            'company_id' => $company_id
        );
        return parent::select($this->list_sql('and a.client_id = :client_id'), $where);
    }

    public function load_client_assessments($client_id, $company_id)
    {
        return $this->client_assessments($client_id, $company_id);
    }

    private function list_sql($client_filter)
    {
        return "SELECT
                    a.id,
                    a.assessment_name,
                    a.assessment_status,
                    a.client_id,
                    cl.company_name AS client_name,
                    a.standard_name,
                    a.short_code,
                    a.version,
                    a.start_date,
                    a.end_date,
                    IFNULL(DATE_FORMAT(a.start_date, df.sql_format), '') AS start_date_display,
                    IFNULL(DATE_FORMAT(a.end_date, df.sql_format), '') AS end_date_display,
                    a.date_created,
                    DATE_FORMAT(a.date_created, df.sql_format) AS date_created_display,
                    COUNT(ai.id) AS item_count,
                    IFNULL(SUM(CASE WHEN ai.item_result <> 'Not Assessed' THEN 1 ELSE 0 END), 0) AS assessed_count
                FROM
                    assessments a
                    JOIN clients cl ON cl.id = a.client_id and cl.company_id = a.company_id and cl.deleted = 0
                    JOIN companies co ON co.id = a.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0 and ai.control_kind = 'control'
                WHERE
                    a.company_id = :company_id
                    and
                    a.deleted = 0
                    " . $client_filter . "
                GROUP BY
                    a.id,
                    a.assessment_name,
                    a.assessment_status,
                    a.client_id,
                    cl.company_name,
                    a.standard_name,
                    a.short_code,
                    a.version,
                    a.start_date,
                    a.end_date,
                    a.date_created,
                    df.sql_format
                ORDER BY
                    a.date_created DESC,
                    a.id DESC";
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
                    a.client_id,
                    a.assessment_name,
                    a.standard_name,
                    a.short_code,
                    a.version,
                    a.assessment_status,
                    a.start_date,
                    a.end_date,
                    IFNULL(DATE_FORMAT(a.start_date, df.sql_format), '') AS start_date_display,
                    IFNULL(DATE_FORMAT(a.end_date, df.sql_format), '') AS end_date_display
                FROM
                    assessments a
                    JOIN companies co ON co.id = a.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                WHERE
                    a.id = :id
                    and
                    a.client_id = :client_id
                    and
                    a.company_id = :company_id
                    and
                    a.deleted = 0";
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
                WHERE
                    ai.assessment_id = :assessment_id
                    and
                    a.client_id = :client_id
                    and
                    a.company_id = :company_id
                    and
                    ai.deleted = 0
                    and
                    ai.control_kind = 'control'
                    and
                    a.deleted = 0
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
                    c.company_name AS client_name,
                    IFNULL(DATE_FORMAT(a.start_date, df.sql_format), '') AS start_date_display,
                    IFNULL(DATE_FORMAT(a.end_date, df.sql_format), '') AS end_date_display,
                    (
                        SELECT COUNT(ai.id)
                        FROM assessment_items ai
                        WHERE ai.assessment_id = a.id and ai.deleted = 0 and ai.control_kind = 'control'
                    ) AS item_count
                FROM
                    assessments a
                    JOIN companies co ON co.id = a.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN clients c ON c.id = a.client_id and c.deleted = 0
                WHERE
                    a.id = :id
                    and
                    a.company_id = :company_id
                    and
                    a.deleted = 0";
        return parent::select($sql, $where);
    }

    public function create_assessment(
        $client_id,
        $company_id,
        $standard_id,
        $assessment_name,
        $start_date,
        $end_date,
        $created_by
    )
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
            'client_id' => $client_id,
            'standard_id' => $standard_id,
            'standard_name' => $standard[0]['standard_name'],
            'short_code' => $standard[0]['short_code'],
            'version' => $standard[0]['version'],
            'framework_key' => $standard[0]['framework_key'],
            'assessment_name' => $assessment_name,
            'assessment_status' => 'Planned',
            'start_date' => $start_date,
            'end_date' => $end_date,
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

    /** Sets the assessment's dates; both null means it runs ongoing. */
    public function set_schedule($assessment_id, $company_id, $start_date, $end_date, $updated_by)
    {
        $where = array(
            'id' => $assessment_id,
            'company_id' => $company_id
        );
        $data = array(
            'start_date' => $start_date,
            'end_date' => $end_date,
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
                    ai.partial_credit,
                    ai.applicability,
                    ai.applicability_justification,
                    ai.notes,
                    ai.gap_description,
                    ai.business_impact,
                    ai.risk_rating,
                    ai.recommendation,
                    ai.owner_name,
                    ai.target_date,
                    ai.implementation_description,
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

    /**
     * Control rows, each with open_objectives (how many of its objectives are
     * still unanswered) and evidence_total (evidence on the control plus on
     * its objectives).
     */
    public function load_controls($assessment_id, $company_id)
    {
        $items = $this->load_items($assessment_id, $company_id);
        $open = array();
        $evidence = array();

        foreach ($items as $item) {
            if ($item['control_kind'] !== 'objective') {
                continue;
            }

            if ($item['item_result'] === 'Not Assessed') {
                $open[$item['parent_identifier']] = ($open[$item['parent_identifier']] ?? 0) + 1;
            }

            $evidence[$item['parent_identifier']] = ($evidence[$item['parent_identifier']] ?? 0) + (int) $item['evidence_count'];
        }

        $controls = array();

        foreach ($items as $item) {
            if ($item['control_kind'] === 'control') {
                $item['open_objectives'] = $open[$item['control_identifier']] ?? 0;
                $item['evidence_total'] = (int) $item['evidence_count'] + ($evidence[$item['control_identifier']] ?? 0);
                $controls[] = $item;
            }
        }

        return $controls;
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
                    a.client_id
                FROM
                    assessment_items ai
                    JOIN assessments a ON a.id = ai.assessment_id
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

    /** Controls not yet fully answered: still Not Assessed, or with an objective left unanswered. */
    public function incomplete_count($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    COUNT(*) AS incomplete
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
                    and
                    (
                        ai.item_result = 'Not Assessed'
                        or
                        EXISTS (
                            SELECT 1
                            FROM assessment_items o
                            WHERE o.assessment_id = ai.assessment_id
                                and o.parent_identifier = ai.control_identifier
                                and o.control_kind = 'objective'
                                and o.deleted = 0
                                and o.item_result = 'Not Assessed'
                        )
                    )";
        $rows = parent::select($sql, $where);
        return (int) $rows[0]['incomplete'];
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

        if ($current === 'Complete' && $this->incomplete_count($assessment_id, $company_id) === 0) {
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
                        SELECT
                            x.assessment_id,
                            IF(x.control_kind = 'objective', x.parent_identifier, x.control_identifier) AS control_identifier,
                            COUNT(*) AS link_count
                        FROM evidence_links el
                        JOIN assessment_items x ON x.id = el.assessment_item_id and x.deleted = 0
                        GROUP BY
                            x.assessment_id,
                            IF(x.control_kind = 'objective', x.parent_identifier, x.control_identifier)
                    ) ev ON ev.assessment_id = ai.assessment_id and ev.control_identifier = ai.control_identifier
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

    /** Saves the finding fields of one control. Returns 0 when the item is not this company's. */
    public function save_control(
        $item_id,
        $company_id,
        $item_result,
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
     * A control with objectives is Not Applicable when every objective is; among
     * the rest it is Implemented when all are met, Not Implemented when none is
     * met or partially met, and Partially Implemented otherwise. With none
     * answered it is Not Assessed; with only some answered it is Partially
     * Implemented until the rest are.
     */
    public static function result_from_objectives(array $results): string
    {
        $counts = array_count_values($results);

        if (count($results) === 0 || ($counts['Not Assessed'] ?? 0) === count($results)) {
            return 'Not Assessed';
        }

        if (($counts['Not Assessed'] ?? 0) > 0) {
            return 'Partially Implemented';
        }

        $applicable = count($results) - ($counts['Not Applicable'] ?? 0);

        if ($applicable === 0) {
            return 'Not Applicable';
        }

        if (($counts['Implemented'] ?? 0) === $applicable) {
            return 'Implemented';
        }

        return ($counts['Not Implemented'] ?? 0) === $applicable ? 'Not Implemented' : 'Partially Implemented';
    }

    public function save_details(
        $assessment_id,
        $company_id,
        $compare_assessment_id,
        $ssi_reviewed,
        $ssi_note,
        $updated_by
    )
    {
        $data = array(
            'compare_assessment_id' => $compare_assessment_id,
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

    /** Other assessments of the same client and standard that this one can be compared against, oldest first. */
    public function compare_options($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'self_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    b.id,
                    b.assessment_name,
                    DATE_FORMAT(b.date_created, df.sql_format) AS created_on
                FROM
                    assessments a
                    JOIN assessments b ON b.client_id = a.client_id and b.company_id = a.company_id and b.deleted = 0
                    JOIN companies co ON co.id = a.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                WHERE
                    a.id = :assessment_id
                    and
                    a.company_id = :company_id
                    and
                    b.id <> :self_id
                    and
                    b.framework_key = a.framework_key
                    and
                    a.framework_key <> ''
                ORDER BY
                    b.date_created asc,
                    b.id asc";
        return parent::select($sql, $where);
    }

    /** Control results of one assessment, keyed for comparison by control identifier. */
    public function control_results($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    ai.control_identifier,
                    ai.item_result
                FROM
                    assessment_items ai
                    JOIN assessments a ON a.id = ai.assessment_id
                WHERE
                    ai.assessment_id = :assessment_id
                    and
                    a.company_id = :company_id
                    and
                    ai.control_kind = 'control'
                    and
                    ai.deleted = 0
                    and
                    a.deleted = 0";
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
