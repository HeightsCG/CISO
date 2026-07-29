<?php
class UsersModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function get_profile($user_id)
    {
        $where = array(
            'user_id' => $user_id
        );
        $sql = "SELECT
                    u.user_id,
                    u.u_name,
                    u.user_email,
                    u.first_name,
                    u.last_name,
                    u.user_status,
                    u.date_created,
                    DATE_FORMAT(u.date_created, df.sql_format) AS date_created_display,
                    c.company_name,
                    r.role_name
                FROM
                    user_accounts u
                    LEFT JOIN companies c ON c.id = u.company_id and c.deleted = 0
                    LEFT JOIN date_formats df ON df.id = c.date_format_id
                    LEFT JOIN user_roles r ON r.id = u.role_id and r.deleted = 0
                WHERE
                    u.user_id = :user_id
                    and
                    u.deleted = 0";
        return parent::select($sql, $where);
    }

    public function get_users_by_company($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    u.user_id,
                    u.u_name,
                    u.user_email,
                    u.first_name,
                    u.last_name,
                    u.user_status,
                    u.reset_pw,
                    u.role_id,
                    u.date_created,
                    DATE_FORMAT(u.date_created, df.sql_format) AS date_created_display,
                    r.role_name
                FROM
                    user_accounts u
                    JOIN companies c ON c.id = u.company_id
                    JOIN date_formats df ON df.id = c.date_format_id
                    LEFT JOIN user_roles r ON r.id = u.role_id and r.deleted = 0
                WHERE
                    u.company_id = :company_id
                    and
                    u.deleted = 0
                ORDER BY
                    u.first_name,
                    u.last_name";
        return parent::select($sql, $where);
    }

    public function check_email($user_email, $exclude_user_id = 0)
    {

        $where_text = '';

        $where = array(
            'user_email' => $user_email,
        );

        if ($exclude_user_id > 0) {
            $where['exclude_user_id'] = $exclude_user_id;
            $where_text = ' and u.user_id != :exclude_user_id';
        }

        $sql = "SELECT
                    u.user_id
                FROM
                    user_accounts u
                WHERE
                    u.user_email = :user_email
                    and
                    u.deleted = 0" . $where_text;
        return parent::select($sql, $where);
    }

    public function check_username($u_name, $exclude_user_id = 0)
    {

        $where_text = '';

        $where = array(
            'u_name' => $u_name,
        );

        if ($exclude_user_id > 0) {
            $where['exclude_user_id'] = $exclude_user_id;
            $where_text = ' and u.user_id != :exclude_user_id';
        }

        $sql = "SELECT
                    u.user_id
                FROM
                    user_accounts u
                WHERE
                    u.u_name = :u_name
                    and
                    u.deleted = 0" . $where_text;
        return parent::select($sql, $where);
    }

    public function update_profile($user_id, $first_name, $last_name, $u_name, $user_email)
    {
        $where = array(
            'user_id' => $user_id
        );
        $data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'u_name' => $u_name,
            'user_email' => $user_email,
            'updated_by' => Session::get('user_id'),
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('user_accounts', $data, 'user_id = :user_id', $where);
    }

    public function get_user_by_username($u_name)
    {
        $where = array(
            'u_name' => $u_name
        );
        $sql = "SELECT
                    u.*
                FROM
                    user_accounts u
                WHERE
                    u.u_name = :u_name
                    and
                    u.deleted = 0";
        return parent::select($sql, $where);
    }

    public function get_user_by_email($user_email)
    {
        $where = array(
            'user_email' => $user_email
        );
        $sql = "SELECT
                    u.*
                FROM
                    user_accounts u
                WHERE
                    u.user_email = :user_email
                    and
                    u.deleted = 0";
        return parent::select($sql, $where);
    }

    public function get_user_by_id($user_id)
    {
        $where = array(
            'user_id' => $user_id
        );
        $sql = "SELECT
                    u.*
                FROM
                    user_accounts u
                WHERE
                    u.user_id = :user_id
                    and
                    u.deleted = 0";
        return parent::select($sql, $where);
    }

    public function get_user_by_reset_token($raw_token)
    {
        $where = array(
            'reset_token' => hash('sha256', (string) $raw_token),
            'reset_token_expires' => date('Y-m-d H:i:s')
        );
        $sql = "SELECT
                    u.*
                FROM
                    user_accounts u
                WHERE
                    u.reset_token = :reset_token
                    and
                    u.reset_token_expires > :reset_token_expires
                    and
                    u.deleted = 0";
        return parent::select($sql, $where);
    }

    public function set_reset_token($user_id)
    {
        $where = array(
            'user_id' => $user_id
        );
        $raw_token = bin2hex(random_bytes(32));
        $data = array(
            'reset_token'         => hash('sha256', $raw_token),
            'reset_token_expires' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'date_updated'        => date('Y-m-d H:i:s'),
            'updated_by'          => Session::get('user_id')
        );
        parent::update('user_accounts', $data, 'user_id = :user_id', $where);
        return $raw_token;
    }

    public function change_password($user_id, $p_word){
        $where = array(
            'user_id' => $user_id
        );
        $data = array(
            'p_word'              => password_hash($p_word, PASSWORD_DEFAULT),
            'reset_pw'            => 0,
            'reset_token'         => null,
            'reset_token_expires' => null,
            'updated_by'          => Session::get('user_id'),
            'date_updated'        => date('Y-m-d H:i:s')
        );
        parent::update('user_accounts', $data, 'user_id = :user_id', $where);
    }

    public function get_roles()
    {
        $sql = "SELECT 
                    r.id, 
                    r.role_name
                FROM 
                    user_roles r
                WHERE 
                    r.deleted = 0
                ORDER BY 
                    r.role_name";
        return parent::select($sql);
    }

    public function get_company_user($user_id, $company_id)
    {
        $where = array(
            'user_id' => $user_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    u.user_id,
                    u.company_id,
                    u.role_id,
                    u.u_name,
                    u.user_email,
                    u.first_name,
                    u.last_name,
                    u.user_status,
                    u.reset_pw,
                    DATE_FORMAT(u.date_created, df.sql_format) AS date_created_display
                FROM
                    user_accounts u
                    JOIN companies c ON c.id = u.company_id
                    JOIN date_formats df ON df.id = c.date_format_id
                WHERE
                    u.user_id = :user_id
                    and
                    u.company_id = :company_id
                    and
                    u.deleted = 0";
        return parent::select($sql, $where);
    }

    public function update_user($user_id, $company_id, $role_id, $first_name, $last_name, $u_name, $user_email, $user_status)
    {
        $where = array(
            'user_id' => $user_id,
            'company_id' => $company_id
        );
        $data = array(
            'role_id' => $role_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'u_name' => $u_name,
            'user_email' => $user_email,
            'user_status' => $user_status,
            'updated_by' => Session::get('user_id'),
            'date_updated' => date('Y-m-d H:i:s'),
        );
        return parent::update('user_accounts', $data, 'user_id = :user_id and company_id = :company_id', $where);
    }

    public function delete_user($user_id, $company_id)
    {
        $where = array(
            'user_id' => $user_id,
            'company_id' => $company_id
        );
        $data = array(
            'deleted' => 1,
            'user_status' => 'Disabled',
            'updated_by' => Session::get('user_id'),
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('user_accounts', $data, 'user_id = :user_id and company_id = :company_id', $where);
    }

    public function add_user($company_id, $role_id, $first_name, $last_name, $u_name, $user_email, $user_status)
    {
        $data = array(
            'company_id' => $company_id,
            'role_id' => $role_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'u_name' => $u_name,
            'user_email' => $user_email,
            'user_status' => $user_status,
            'p_word' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'reset_pw' => 1,
            'created_by' => Session::get('user_id'),
            'updated_by' => Session::get('user_id'),
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('user_accounts', $data);
    }

}
