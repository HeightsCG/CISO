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
                    c.company_name,
                    r.role_name
                FROM
                    user_accounts u
                    LEFT JOIN companies c ON c.id = u.company_id and c.deleted = 0
                    LEFT JOIN user_roles r ON r.id = u.role_id and r.deleted = 0
                WHERE
                    u.user_id = :user_id
                    and
                    u.deleted = 0";
        return parent::select($sql, $where);
    }

    public function get_email_owner($user_email, $exclude_user_id)
    {
        $where = array(
            'user_email' => $user_email,
            'exclude_user_id' => $exclude_user_id
        );
        $sql = "SELECT
                    u.user_id
                FROM
                    user_accounts u
                WHERE
                    u.user_email = :user_email
                    and
                    u.user_id != :exclude_user_id
                    and
                    u.deleted = 0";
        return parent::select($sql, $where);
    }

    public function update_profile($user_id, $first_name, $last_name, $user_email)
    {
        $where = array(
            'user_id' => $user_id
        );
        $data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'user_email' => $user_email,
            'updated_by' => $user_id,
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
            'date_updated'        => date('Y-m-d H:i:s')
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
            'updated_by'          => $user_id,
            'date_updated'        => date('Y-m-d H:i:s')
        );
        parent::update('user_accounts', $data, 'user_id = :user_id', $where);
    }

    public function add_user($first_name, $last_name, $u_name, $user_email, $enc_pw){
        $data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'u_name' => $u_name,
            'user_email' => $user_email,
            'p_word' => $enc_pw,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('user_accounts', $data);
    }

}
