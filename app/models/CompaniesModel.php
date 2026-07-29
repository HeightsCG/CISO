<?php
class CompaniesModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /** Selectable date formats, ordered for the Settings dropdown. */
    public function get_date_formats()
    {
        $sql = "SELECT
                    d.id,
                    d.php_format,
                    d.label
                FROM
                    date_formats d
                WHERE
                    d.deleted = 0
                ORDER BY
                    d.sort_order";
        return parent::select($sql);
    }

    public function get_company($company_id)
    {
        $where = array(
            'id' => $company_id
        );
        $sql = "SELECT
                    c.*,
                    d.php_format AS date_format,
                    d.sql_format AS date_format_sql
                FROM
                    companies c
                    JOIN date_formats d ON d.id = c.date_format_id
                WHERE
                    c.id = :id";
        return parent::select($sql, $where);
    }

    public function update_company(
        $company_id,
        $company_name,
        $trading_name,
        $address_1,
        $address_2,
        $city,
        $state,
        $postal_code,
        $country,
        $website,
        $email_domain,
        $timezone,
        $date_format_id,
        $time_format,
        $brand_color,
        $brand_color_secondary,
        $brand_color_accent,
        $updated_by
    )
    {
        $where = array(
            'id' => $company_id
        );
        $data = array(
            'company_name' => $company_name,
            'trading_name' => $trading_name,
            'address_1' => $address_1,
            'address_2' => $address_2,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postal_code,
            'country' => $country,
            'website' => $website,
            'email_domain' => $email_domain,
            'timezone' => $timezone,
            'date_format_id' => $date_format_id,
            'time_format' => $time_format,
            'brand_color' => $brand_color,
            'brand_color_secondary' => $brand_color_secondary,
            'brand_color_accent' => $brand_color_accent,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('companies', $data, 'id = :id', $where);
    }

    public function update_security(
        $company_id,
        $session_timeout_enabled,
        $session_timeout_minutes,
        $password_expiry_enabled,
        $password_expiry_days,
        $account_lockout_enabled,
        $lockout_attempts,
        $lockout_minutes,
        $mfa_enabled,
        $mfa_methods,
        $updated_by
    )
    {
        $where = array(
            'id' => $company_id
        );
        $data = array(
            'session_timeout_enabled' => $session_timeout_enabled,
            'session_timeout_minutes' => $session_timeout_minutes,
            'password_expiry_enabled' => $password_expiry_enabled,
            'password_expiry_days' => $password_expiry_days,
            'account_lockout_enabled' => $account_lockout_enabled,
            'lockout_attempts' => $lockout_attempts,
            'lockout_minutes' => $lockout_minutes,
            'mfa_enabled' => $mfa_enabled,
            'mfa_methods' => $mfa_methods,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('companies', $data, 'id = :id', $where);
    }

    public function update_logo($company_id, $logo_path, $logo_filename, $logo_size, $updated_by)
    {
        $where = array(
            'id' => $company_id
        );
        $data = array(
            'logo_path' => $logo_path,
            'logo_filename' => $logo_filename,
            'logo_size' => $logo_size,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('companies', $data, 'id = :id', $where);
    }

}
