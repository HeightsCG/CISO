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
                    p.status,
                    p.date_created,
                    p.date_updated
                FROM
                    projects p
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
                    p.*
                FROM
                    projects p
                WHERE
                    p.id = :id
                    and
                    p.company_id = :company_id
                    and
                    p.deleted = 0";
        return parent::select($sql, $where);
    }

}
