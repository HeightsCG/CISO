<?php
class ProjectsModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function load_projects($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    p.id,
                    p.project_name,
                    p.description,
                    p.start_date,
                    p.end_date,
                    DATE_FORMAT(p.start_date, df.sql_format) AS start_date_display,
                    DATE_FORMAT(p.end_date, df.sql_format) AS end_date_display,
                    p.project_status,
                    p.client_id,
                    cl.company_name AS client_name,
                    p.date_created,
                    p.date_updated
                FROM
                    projects p
                    JOIN companies co ON co.id = p.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN clients cl ON cl.id = p.client_id and cl.deleted = 0
                WHERE
                    p.company_id = :company_id
                    and
                    p.deleted = 0
                ORDER BY
                    p.date_created DESC,
                    p.id DESC";
        return parent::select($sql, $where);
    }

    public function get_project($project_id, $company_id)
    {
        $where = array(
            'id' => $project_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    p.*,
                    cl.company_name AS client_name,
                    DATE_FORMAT(p.start_date, df.sql_format) AS start_date_display,
                    DATE_FORMAT(p.end_date, df.sql_format) AS end_date_display
                FROM
                    projects p
                    JOIN companies co ON co.id = p.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN clients cl ON cl.id = p.client_id and cl.deleted = 0
                WHERE
                    p.id = :id
                    and
                    p.company_id = :company_id
                    and
                    p.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_project(
        $company_id,
        $client_id,
        $project_name,
        $description,
        $start_date,
        $end_date,
        $project_status,
        $created_by
    )
    {
        $data = array(
            'company_id' => $company_id,
            'client_id' => $client_id,
            'project_name' => $project_name,
            'description' => $description,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'project_status' => $project_status,
            'created_by' => $created_by,
            'updated_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('projects', $data);
    }

    /**
     * The client is set here rather than from the project page: it decides which
     * evidence vault every assessment on the project reads from, so it belongs
     * behind the edit form rather than one stray click away on a read view.
     */
    public function update_project(
        $project_id,
        $company_id,
        $client_id,
        $project_name,
        $description,
        $start_date,
        $end_date,
        $project_status,
        $updated_by
    )
    {
        $where = array(
            'id' => $project_id,
            'company_id' => $company_id
        );
        $data = array(
            'client_id' => $client_id,
            'project_name' => $project_name,
            'description' => $description,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'project_status' => $project_status,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('projects', $data, 'id = :id and company_id = :company_id', $where);
    }

    /**
     * Deleting a project takes its assessments and their items with it, and drops
     * the evidence links those items held. The evidence itself belongs to the
     * client's vault and is left alone - it is very likely in use elsewhere.
     */
    public function delete_project($project_id, $company_id, $updated_by)
    {
        $where = array(
            'id' => $project_id,
            'company_id' => $company_id
        );

        $rows = parent::update(
            'projects',
            array('deleted' => 1, 'updated_by' => $updated_by, 'date_updated' => date('Y-m-d H:i:s')),
            'id = :id and company_id = :company_id',
            $where
        );

        if ($rows === 0) {
            return 0;
        }

        $assessments = parent::select(
            "SELECT a.id FROM assessments a WHERE a.project_id = :project_id and a.company_id = :company_id",
            array('project_id' => $project_id, 'company_id' => $company_id)
        );

        foreach ($assessments as $assessment) {

            // A link pointing at a removed item would keep inflating the "used on
            // N controls" count shown against the evidence in the vault.
            parent::delete_all(
                'evidence_links',
                'assessment_item_id IN (SELECT id FROM assessment_items WHERE assessment_id = :assessment_id)',
                array('assessment_id' => $assessment['id'])
            );

            parent::update(
                'assessment_items',
                array('deleted' => 1, 'date_updated' => date('Y-m-d H:i:s')),
                'assessment_id = :assessment_id',
                array('assessment_id' => $assessment['id'])
            );
        }

        parent::update(
            'assessments',
            array('deleted' => 1, 'updated_by' => $updated_by, 'date_updated' => date('Y-m-d H:i:s')),
            'project_id = :project_id and company_id = :company_id',
            array('project_id' => $project_id, 'company_id' => $company_id)
        );

        return $rows;
    }

}
