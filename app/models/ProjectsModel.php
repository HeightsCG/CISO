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

    /**
     * The client portal's project list. Scoped on client_id as well as company_id:
     * a client id is only unique within an organisation, so both are required.
     */
    public function client_projects($client_id, $company_id)
    {
        $where = array(
            'client_id' => $client_id,
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
                    (SELECT COUNT(*)
                        FROM assessments a
                        WHERE a.project_id = p.id
                        and a.deleted = 0) AS assessment_count,
                    (SELECT COUNT(*)
                        FROM assessment_items ai
                        JOIN assessments a ON a.id = ai.assessment_id
                        WHERE a.project_id = p.id
                        and a.deleted = 0
                        and ai.deleted = 0) AS item_count,
                    (SELECT COUNT(*)
                        FROM assessment_items ai
                        JOIN assessments a ON a.id = ai.assessment_id
                        WHERE a.project_id = p.id
                        and a.deleted = 0
                        and ai.deleted = 0
                        and ai.item_result <> 'Not Assessed') AS assessed_count,
                    (SELECT COUNT(*)
                        FROM evidence e
                        WHERE e.project_id = p.id
                        and e.deleted = 0
                        and e.evidence_private = 0) AS evidence_count
                FROM
                    projects p
                    JOIN companies co ON co.id = p.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                WHERE
                    p.client_id = :client_id
                    and
                    p.company_id = :company_id
                    and
                    p.deleted = 0
                ORDER BY
                    p.date_created DESC,
                    p.id DESC";
        return parent::select($sql, $where);
    }

    /** One project, but only if it belongs to the calling client. */
    public function client_project($project_id, $client_id, $company_id)
    {
        $where = array(
            'id' => $project_id,
            'client_id' => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    p.id,
                    p.project_name,
                    p.description,
                    DATE_FORMAT(p.start_date, df.sql_format) AS start_date_display,
                    DATE_FORMAT(p.end_date, df.sql_format) AS end_date_display,
                    p.project_status
                FROM
                    projects p
                    JOIN companies co ON co.id = p.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                WHERE
                    p.id = :id
                    and
                    p.client_id = :client_id
                    and
                    p.company_id = :company_id
                    and
                    p.deleted = 0";
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
            'project_status' => 'Active',
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

    /**
     * A project is only complete once every assessment on it is. Answering a
     * control anywhere therefore reopens it, which is what stops a project
     * reading Complete while work is still going on inside it.
     */
    public function sync_project_status($project_id, $company_id, $updated_by)
    {
        $where = array(
            'project_id' => $project_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    COUNT(*) AS assessment_count,
                    SUM(CASE WHEN a.assessment_status <> 'Complete' THEN 1 ELSE 0 END) AS open_count
                FROM
                    assessments a
                WHERE
                    a.project_id = :project_id
                    and
                    a.company_id = :company_id
                    and
                    a.deleted = 0";
        $rows = parent::select($sql, $where);

        $complete = ((int) $rows[0]['assessment_count'] > 0 && (int) $rows[0]['open_count'] === 0);
        $project_status = ($complete ? 'Complete' : 'Active');

        $data = array(
            'project_status' => $project_status,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        parent::update('projects', $data, 'id = :id and company_id = :company_id', array('id' => $project_id, 'company_id' => $company_id));

        return $project_status;
    }

    /* ------------------------------------------------------------ project team */

    public function load_project_users($project_id, $company_id)
    {
        $where = array(
            'project_id' => $project_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    pu.id,
                    pu.user_id,
                    pu.project_role,
                    u.first_name,
                    u.last_name,
                    u.u_name,
                    u.user_email,
                    u.user_status,
                    r.role_name,
                    pu.date_created,
                    DATE_FORMAT(pu.date_created, df.sql_format) AS date_created_display
                FROM
                    project_users pu
                    JOIN projects p ON p.id = pu.project_id and p.deleted = 0
                    JOIN user_accounts u ON u.user_id = pu.user_id and u.deleted = 0
                    JOIN companies co ON co.id = pu.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN user_roles r ON r.id = u.role_id and r.deleted = 0
                WHERE
                    pu.project_id = :project_id
                    and
                    pu.company_id = :company_id
                    and
                    pu.deleted = 0
                ORDER BY
                    u.first_name,
                    u.last_name";
        return parent::select($sql, $where);
    }

    /**
     * Only accounts that can actually sign in and are not already on the project
     * are offered, so the picker cannot create a duplicate or seat a disabled user.
     */
    public function available_users($project_id, $company_id)
    {
        $where = array(
            'project_id' => $project_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    u.user_id,
                    u.first_name,
                    u.last_name,
                    u.user_email
                FROM
                    user_accounts u
                    LEFT JOIN project_users pu ON pu.user_id = u.user_id and pu.project_id = :project_id and pu.deleted = 0
                WHERE
                    u.company_id = :company_id
                    and
                    u.user_status = 'Active'
                    and
                    u.deleted = 0
                    and
                    pu.id IS NULL
                ORDER BY
                    u.first_name,
                    u.last_name";
        return parent::select($sql, $where);
    }

    public function get_project_user($id, $company_id)
    {
        $where = array(
            'id' => $id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    pu.id,
                    pu.project_id,
                    pu.user_id,
                    pu.project_role
                FROM
                    project_users pu
                WHERE
                    pu.id = :id
                    and
                    pu.company_id = :company_id
                    and
                    pu.deleted = 0";
        return parent::select($sql, $where);
    }

    public function check_project_user($project_id, $user_id)
    {
        $where = array(
            'project_id' => $project_id,
            'user_id' => $user_id
        );
        $sql = "SELECT
                    pu.id
                FROM
                    project_users pu
                WHERE
                    pu.project_id = :project_id
                    and
                    pu.user_id = :user_id
                    and
                    pu.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_project_user($company_id, $project_id, $user_id, $project_role, $created_by)
    {
        $data = array(
            'company_id' => $company_id,
            'project_id' => $project_id,
            'user_id' => $user_id,
            'project_role' => $project_role,
            'created_by' => $created_by,
            'updated_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('project_users', $data);
    }

    public function update_project_user($id, $company_id, $project_role, $updated_by)
    {
        $where = array(
            'id' => $id,
            'company_id' => $company_id
        );
        $data = array(
            'project_role' => $project_role,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('project_users', $data, 'id = :id and company_id = :company_id', $where);
    }

    public function delete_project_user($id, $company_id, $updated_by)
    {
        $where = array(
            'id' => $id,
            'company_id' => $company_id
        );
        $data = array(
            'deleted' => 1,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('project_users', $data, 'id = :id and company_id = :company_id', $where);
    }

}
