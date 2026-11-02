<?php
/**
 * Client deliverable reports and their sections. Every section row belongs to
 * a report, and every report to a company: callers check the report against
 * the session's company first, then work on rows by report id.
 */
class ReportsModel extends Model {

    const SECTION_TABLES = array(
        'version' => 'report_versions',
        'person' => 'report_people',
        'domain' => 'report_domains',
        'finding' => 'report_findings',
        'risk' => 'report_risks',
        'threat' => 'report_threats',
        'asset' => 'report_assets',
        'scan_row' => 'report_scan_rows',
        'xref' => 'report_xrefs'
    );

    public function __construct(){
        parent::__construct();
    }

    public function get_report_by_project($project_id, $company_id)
    {
        $where = array(
            'project_id' => $project_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    r.*
                FROM
                    reports r
                WHERE
                    r.project_id = :project_id
                    and
                    r.company_id = :company_id
                    and
                    r.deleted = 0
                ORDER BY
                    r.id DESC
                LIMIT 1";
        return parent::select($sql, $where);
    }

    public function get_report($report_id, $company_id)
    {
        $where = array(
            'id' => $report_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    r.*
                FROM
                    reports r
                WHERE
                    r.id = :id
                    and
                    r.company_id = :company_id
                    and
                    r.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_report($company_id, $project_id, $report_title, $methodology_text, $limitations_text, $review_text, $created_by)
    {
        $data = array(
            'company_id' => $company_id,
            'project_id' => $project_id,
            'report_title' => $report_title,
            'methodology_text' => $methodology_text,
            'limitations_text' => $limitations_text,
            'review_text' => $review_text,
            'created_by' => $created_by,
            'updated_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('reports', $data);
    }

    public function update_report_details($report_id, $report_title, $report_date, $period_start, $period_end, $regulatory_reference, $purpose_text, $conclusion_text, $background_text, $scope_text, $out_of_scope_text, $approach_text, $methodology_text, $limitations_text, $review_text, $scan_text, $signoff_name, $signoff_title, $updated_by)
    {
        $where = array(
            'id' => $report_id
        );
        $data = array(
            'report_title' => $report_title,
            'report_date' => $report_date,
            'period_start' => $period_start,
            'period_end' => $period_end,
            'regulatory_reference' => $regulatory_reference,
            'purpose_text' => $purpose_text,
            'conclusion_text' => $conclusion_text,
            'background_text' => $background_text,
            'scope_text' => $scope_text,
            'out_of_scope_text' => $out_of_scope_text,
            'approach_text' => $approach_text,
            'methodology_text' => $methodology_text,
            'limitations_text' => $limitations_text,
            'review_text' => $review_text,
            'scan_text' => $scan_text,
            'signoff_name' => $signoff_name,
            'signoff_title' => $signoff_title,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('reports', $data, 'id = :id', $where);
    }

    public function set_stage($report_id, $report_stage, $report_version, $updated_by)
    {
        $where = array(
            'id' => $report_id
        );
        $data = array(
            'report_stage' => $report_stage,
            'report_version' => $report_version,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('reports', $data, 'id = :id', $where);
    }

    public function load_versions($report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_versions t
                WHERE
                    t.report_id = :report_id
                    and
                    t.deleted = 0
                ORDER BY
                    sort_order, id";
        return parent::select($sql, $where);
    }

    public function get_version($id, $report_id)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_versions t
                WHERE
                    t.id = :id
                    and
                    t.report_id = :report_id
                    and
                    t.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_version($report_id, $version_label, $version_date, $version_note, $version_author, $sort_order)
    {
        $data = array(
            'report_id' => $report_id,
            'version_label' => $version_label,
            'version_date' => $version_date,
            'version_note' => $version_note,
            'version_author' => $version_author,
            'sort_order' => $sort_order,
            'deleted' => 0
        );
        return parent::insert('report_versions', $data);
    }

    public function update_version($id, $report_id, $version_label, $version_date, $version_note, $version_author)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'version_label' => $version_label,
            'version_date' => $version_date,
            'version_note' => $version_note,
            'version_author' => $version_author
        );
        return parent::update('report_versions', $data, 'id = :id and report_id = :report_id', $where);
    }

    public function load_persons($report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_people t
                WHERE
                    t.report_id = :report_id
                    and
                    t.deleted = 0
                ORDER BY
                    person_kind, sort_order, id";
        return parent::select($sql, $where);
    }

    public function get_person($id, $report_id)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_people t
                WHERE
                    t.id = :id
                    and
                    t.report_id = :report_id
                    and
                    t.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_person($report_id, $person_kind, $person_name, $person_title, $organisation, $responsibility, $sort_order)
    {
        $data = array(
            'report_id' => $report_id,
            'person_kind' => $person_kind,
            'person_name' => $person_name,
            'person_title' => $person_title,
            'organisation' => $organisation,
            'responsibility' => $responsibility,
            'sort_order' => $sort_order,
            'deleted' => 0
        );
        return parent::insert('report_people', $data);
    }

    public function update_person($id, $report_id, $person_kind, $person_name, $person_title, $organisation, $responsibility)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'person_kind' => $person_kind,
            'person_name' => $person_name,
            'person_title' => $person_title,
            'organisation' => $organisation,
            'responsibility' => $responsibility
        );
        return parent::update('report_people', $data, 'id = :id and report_id = :report_id', $where);
    }

    public function load_domains($report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_domains t
                WHERE
                    t.report_id = :report_id
                    and
                    t.deleted = 0
                ORDER BY
                    sort_order, id";
        return parent::select($sql, $where);
    }

    public function get_domain($id, $report_id)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_domains t
                WHERE
                    t.id = :id
                    and
                    t.report_id = :report_id
                    and
                    t.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_domain($report_id, $domain_name, $areas_reviewed, $csf_refs, $domain_rating, $domain_summary, $sort_order)
    {
        $data = array(
            'report_id' => $report_id,
            'domain_name' => $domain_name,
            'areas_reviewed' => $areas_reviewed,
            'csf_refs' => $csf_refs,
            'domain_rating' => $domain_rating,
            'domain_summary' => $domain_summary,
            'sort_order' => $sort_order,
            'deleted' => 0
        );
        return parent::insert('report_domains', $data);
    }

    public function update_domain($id, $report_id, $domain_name, $areas_reviewed, $csf_refs, $domain_rating, $domain_summary)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'domain_name' => $domain_name,
            'areas_reviewed' => $areas_reviewed,
            'csf_refs' => $csf_refs,
            'domain_rating' => $domain_rating,
            'domain_summary' => $domain_summary
        );
        return parent::update('report_domains', $data, 'id = :id and report_id = :report_id', $where);
    }

    public function load_findings($report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_findings t
                WHERE
                    t.report_id = :report_id
                    and
                    t.deleted = 0
                ORDER BY
                    sort_order, id";
        return parent::select($sql, $where);
    }

    public function get_finding($id, $report_id)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_findings t
                WHERE
                    t.id = :id
                    and
                    t.report_id = :report_id
                    and
                    t.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_finding($report_id, $domain_id, $finding_ref, $finding_title, $observation, $risk_text, $recommendation, $likelihood, $impact, $csf_refs, $response_status, $management_response, $response_owner, $target_date, $sort_order)
    {
        $data = array(
            'report_id' => $report_id,
            'domain_id' => $domain_id,
            'finding_ref' => $finding_ref,
            'finding_title' => $finding_title,
            'observation' => $observation,
            'risk_text' => $risk_text,
            'recommendation' => $recommendation,
            'likelihood' => $likelihood,
            'impact' => $impact,
            'csf_refs' => $csf_refs,
            'response_status' => $response_status,
            'management_response' => $management_response,
            'response_owner' => $response_owner,
            'target_date' => $target_date,
            'sort_order' => $sort_order,
            'updated_by' => Session::get('user_id'),
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('report_findings', $data);
    }

    public function update_finding($id, $report_id, $domain_id, $finding_ref, $finding_title, $observation, $risk_text, $recommendation, $likelihood, $impact, $csf_refs, $response_status, $management_response, $response_owner, $target_date)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'domain_id' => $domain_id,
            'finding_ref' => $finding_ref,
            'finding_title' => $finding_title,
            'observation' => $observation,
            'risk_text' => $risk_text,
            'recommendation' => $recommendation,
            'likelihood' => $likelihood,
            'impact' => $impact,
            'csf_refs' => $csf_refs,
            'response_status' => $response_status,
            'management_response' => $management_response,
            'response_owner' => $response_owner,
            'target_date' => $target_date,
            'updated_by' => Session::get('user_id'),
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('report_findings', $data, 'id = :id and report_id = :report_id', $where);
    }

    public function load_risks($report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_risks t
                WHERE
                    t.report_id = :report_id
                    and
                    t.deleted = 0
                ORDER BY
                    sort_order, id";
        return parent::select($sql, $where);
    }

    public function get_risk($id, $report_id)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_risks t
                WHERE
                    t.id = :id
                    and
                    t.report_id = :report_id
                    and
                    t.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_risk($report_id, $domain_id, $finding_id, $risk_ref, $risk_text, $threat_refs, $likelihood, $impact, $treatment, $sort_order)
    {
        $data = array(
            'report_id' => $report_id,
            'domain_id' => $domain_id,
            'finding_id' => $finding_id,
            'risk_ref' => $risk_ref,
            'risk_text' => $risk_text,
            'threat_refs' => $threat_refs,
            'likelihood' => $likelihood,
            'impact' => $impact,
            'treatment' => $treatment,
            'sort_order' => $sort_order,
            'deleted' => 0
        );
        return parent::insert('report_risks', $data);
    }

    public function update_risk($id, $report_id, $domain_id, $finding_id, $risk_ref, $risk_text, $threat_refs, $likelihood, $impact, $treatment)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'domain_id' => $domain_id,
            'finding_id' => $finding_id,
            'risk_ref' => $risk_ref,
            'risk_text' => $risk_text,
            'threat_refs' => $threat_refs,
            'likelihood' => $likelihood,
            'impact' => $impact,
            'treatment' => $treatment
        );
        return parent::update('report_risks', $data, 'id = :id and report_id = :report_id', $where);
    }

    public function load_threats($report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_threats t
                WHERE
                    t.report_id = :report_id
                    and
                    t.deleted = 0
                ORDER BY
                    sort_order, id";
        return parent::select($sql, $where);
    }

    public function get_threat($id, $report_id)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_threats t
                WHERE
                    t.id = :id
                    and
                    t.report_id = :report_id
                    and
                    t.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_threat($report_id, $threat_ref, $threat_name, $threat_description, $parties_affected, $sort_order)
    {
        $data = array(
            'report_id' => $report_id,
            'threat_ref' => $threat_ref,
            'threat_name' => $threat_name,
            'threat_description' => $threat_description,
            'parties_affected' => $parties_affected,
            'sort_order' => $sort_order,
            'deleted' => 0
        );
        return parent::insert('report_threats', $data);
    }

    public function update_threat($id, $report_id, $threat_ref, $threat_name, $threat_description, $parties_affected)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'threat_ref' => $threat_ref,
            'threat_name' => $threat_name,
            'threat_description' => $threat_description,
            'parties_affected' => $parties_affected
        );
        return parent::update('report_threats', $data, 'id = :id and report_id = :report_id', $where);
    }

    public function load_assets($report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_assets t
                WHERE
                    t.report_id = :report_id
                    and
                    t.deleted = 0
                ORDER BY
                    sort_order, id";
        return parent::select($sql, $where);
    }

    public function get_asset($id, $report_id)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_assets t
                WHERE
                    t.id = :id
                    and
                    t.report_id = :report_id
                    and
                    t.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_asset($report_id, $asset_name, $asset_kind, $asset_owner, $data_held, $sensitivity, $sort_order)
    {
        $data = array(
            'report_id' => $report_id,
            'asset_name' => $asset_name,
            'asset_kind' => $asset_kind,
            'asset_owner' => $asset_owner,
            'data_held' => $data_held,
            'sensitivity' => $sensitivity,
            'sort_order' => $sort_order,
            'deleted' => 0
        );
        return parent::insert('report_assets', $data);
    }

    public function update_asset($id, $report_id, $asset_name, $asset_kind, $asset_owner, $data_held, $sensitivity)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'asset_name' => $asset_name,
            'asset_kind' => $asset_kind,
            'asset_owner' => $asset_owner,
            'data_held' => $data_held,
            'sensitivity' => $sensitivity
        );
        return parent::update('report_assets', $data, 'id = :id and report_id = :report_id', $where);
    }

    public function load_scan_rows($report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_scan_rows t
                WHERE
                    t.report_id = :report_id
                    and
                    t.deleted = 0
                ORDER BY
                    sort_order, id";
        return parent::select($sql, $where);
    }

    public function get_scan_row($id, $report_id)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_scan_rows t
                WHERE
                    t.id = :id
                    and
                    t.report_id = :report_id
                    and
                    t.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_scan_row($report_id, $scan_rating, $issue_count, $scan_summary, $disposition, $sort_order)
    {
        $data = array(
            'report_id' => $report_id,
            'scan_rating' => $scan_rating,
            'issue_count' => $issue_count,
            'scan_summary' => $scan_summary,
            'disposition' => $disposition,
            'sort_order' => $sort_order,
            'deleted' => 0
        );
        return parent::insert('report_scan_rows', $data);
    }

    public function update_scan_row($id, $report_id, $scan_rating, $issue_count, $scan_summary, $disposition)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'scan_rating' => $scan_rating,
            'issue_count' => $issue_count,
            'scan_summary' => $scan_summary,
            'disposition' => $disposition
        );
        return parent::update('report_scan_rows', $data, 'id = :id and report_id = :report_id', $where);
    }

    public function load_xrefs($report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_xrefs t
                WHERE
                    t.report_id = :report_id
                    and
                    t.deleted = 0
                ORDER BY
                    sort_order, id";
        return parent::select($sql, $where);
    }

    public function get_xref($id, $report_id)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $sql = "SELECT
                    t.*
                FROM
                    report_xrefs t
                WHERE
                    t.id = :id
                    and
                    t.report_id = :report_id
                    and
                    t.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_xref($report_id, $xref_section, $requirement, $where_addressed, $sort_order)
    {
        $data = array(
            'report_id' => $report_id,
            'xref_section' => $xref_section,
            'requirement' => $requirement,
            'where_addressed' => $where_addressed,
            'sort_order' => $sort_order,
            'deleted' => 0
        );
        return parent::insert('report_xrefs', $data);
    }

    public function update_xref($id, $report_id, $xref_section, $requirement, $where_addressed)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'xref_section' => $xref_section,
            'requirement' => $requirement,
            'where_addressed' => $where_addressed
        );
        return parent::update('report_xrefs', $data, 'id = :id and report_id = :report_id', $where);
    }

    /** The client's answer to one finding, recorded with who gave it and when. */
    public function respond_to_finding($id, $report_id, $response_status, $management_response, $response_owner, $target_date, $responded_by)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'response_status' => $response_status,
            'management_response' => $management_response,
            'response_owner' => $response_owner,
            'target_date' => $target_date,
            'responded_by' => $responded_by,
            'responded_at' => date('Y-m-d H:i:s'),
            'updated_by' => $responded_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('report_findings', $data, 'id = :id and report_id = :report_id', $where);
    }

    public function delete_section_row($section, $id, $report_id)
    {
        $where = array(
            'id' => $id,
            'report_id' => $report_id
        );
        $data = array(
            'deleted' => 1
        );
        return parent::update(self::SECTION_TABLES[$section], $data, 'id = :id and report_id = :report_id', $where);
    }

    public function next_sort_order($section, $report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $rows = parent::select("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM ".self::SECTION_TABLES[$section]." WHERE report_id = :report_id", $where);

        return (int) ($rows[0]['next_order'] ?? 1);
    }

    public function load_csf($report_id)
    {
        $where = array(
            'report_id' => $report_id
        );
        $sql = "SELECT
                    c.*
                FROM
                    report_csf c
                WHERE
                    c.report_id = :report_id
                ORDER BY
                    FIELD(c.csf_function, 'Govern', 'Identify', 'Protect', 'Detect', 'Respond', 'Recover')";
        return parent::select($sql, $where);
    }

    public function save_csf($report_id, $csf_function, $current_tier, $target_tier, $main_gaps)
    {
        $where = array(
            'report_id' => $report_id,
            'csf_function' => $csf_function
        );
        $rows = parent::select("SELECT id FROM report_csf WHERE report_id = :report_id and csf_function = :csf_function", $where);

        if (count($rows) === 1) {
            $data = array(
                'current_tier' => $current_tier,
                'target_tier' => $target_tier,
                'main_gaps' => $main_gaps
            );
            return parent::update('report_csf', $data, 'report_id = :report_id and csf_function = :csf_function', $where);
        }

        $data = array(
            'report_id' => $report_id,
            'csf_function' => $csf_function,
            'current_tier' => $current_tier,
            'target_tier' => $target_tier,
            'main_gaps' => $main_gaps
        );
        return parent::insert('report_csf', $data);
    }

}
