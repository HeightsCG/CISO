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
                    p.date_created,
                    p.date_updated
                FROM
                    projects p
                    JOIN companies co ON co.id = p.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
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
                    DATE_FORMAT(p.start_date, df.sql_format) AS start_date_display,
                    DATE_FORMAT(p.end_date, df.sql_format) AS end_date_display
                FROM
                    projects p
                    JOIN companies co ON co.id = p.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                WHERE
                    p.id = :id
                    and
                    p.company_id = :company_id
                    and
                    p.deleted = 0";
        return parent::select($sql, $where);
    }

}
