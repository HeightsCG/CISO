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

    /**
     * Every tenant, for the global admin's company picker. No company_id predicate
     * is possible here by definition, so this must only ever be reached through a
     * global admin check.
     */
    public function get_companies()
    {
        $sql = "SELECT
                    c.id,
                    c.company_name
                FROM
                    companies c
                WHERE
                    c.deleted = 0
                ORDER BY
                    c.company_name";
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

    /**
     * Claim the platform customer for this company. The WHERE is conditional on the
     * column still being empty, so two administrators opening Billing at the same
     * moment cannot both write one: the loser gets rowCount() 0 and re-reads. The
     * idempotency key on the Stripe call means that even if both requests reached
     * Stripe they were handed the same customer.
     */
    public function set_platform_customer($company_id, $platform_customer_id, $platform_livemode)
    {
        $where = array(
            'id' => $company_id
        );
        $data = array(
            'platform_customer_id' => $platform_customer_id,
            'platform_livemode'    => $platform_livemode,
            'date_updated'         => date('Y-m-d H:i:s')
        );
        return parent::update('companies', $data, 'id = :id and platform_customer_id is null', $where);
    }

    /** As set_platform_customer, for the company's own connected account. */
    public function set_connect_account($company_id, $stripe_connect_account_id, $stripe_livemode)
    {
        $where = array(
            'id' => $company_id
        );
        $data = array(
            'stripe_connect_account_id' => $stripe_connect_account_id,
            'stripe_livemode'           => $stripe_livemode,
            'stripe_connect_status'     => 'Onboarding',
            'stripe_connected_at'       => date('Y-m-d H:i:s'),
            'date_updated'              => date('Y-m-d H:i:s')
        );
        return parent::update('companies', $data, 'id = :id and stripe_connect_account_id is null', $where);
    }

    /**
     * Mirror the capability state Stripe reports. Currency is taken from the
     * account rather than chosen here: it is what the account actually settles in,
     * so an invoice raised in it can never be one the account cannot take.
     */
    public function set_connect_state($company_id, $connect_status, $charges_enabled, $details_submitted, $payouts_enabled, $requirements, $default_currency)
    {
        $where = array(
            'id' => $company_id
        );
        $data = array(
            'stripe_connect_status'    => $connect_status,
            'stripe_charges_enabled'   => $charges_enabled,
            'stripe_details_submitted' => $details_submitted,
            'stripe_payouts_enabled'   => $payouts_enabled,
            'stripe_requirements'      => $requirements,
            'default_currency'         => $default_currency,
            'stripe_synced_at'         => date('Y-m-d H:i:s'),
            'date_updated'             => date('Y-m-d H:i:s')
        );
        return parent::update('companies', $data, 'id = :id', $where);
    }

    /** Stripe's own words for why charges are off, when the requirement list alone would not explain it. */
    public function set_connect_reason($company_id, $stripe_disabled_reason)
    {
        return parent::update(
            'companies',
            array('stripe_disabled_reason' => $stripe_disabled_reason, 'date_updated' => date('Y-m-d H:i:s')),
            'id = :id',
            array('id' => $company_id)
        );
    }

    /**
     * Forget the connection without touching Stripe. A Standard account belongs to
     * the company, so it is never deleted from here; this only stops the
     * application acting on it. Invoices keep the account id they were stamped
     * with, so a reconnection to a different account cannot reinterpret them.
     */
    public function clear_connect_account($company_id)
    {
        $where = array(
            'id' => $company_id
        );
        $data = array(
            'stripe_connect_account_id' => null,
            'stripe_connect_status'     => 'Disconnected',
            'stripe_charges_enabled'    => 0,
            'stripe_details_submitted'  => 0,
            'stripe_payouts_enabled'    => 0,
            'stripe_requirements'       => '',
            'date_updated'              => date('Y-m-d H:i:s')
        );
        return parent::update('companies', $data, 'id = :id', $where);
    }

    /** Reached by connected account id alone; safe because the column is unique and Stripe supplies the value. */
    public function by_connect_account_id($stripe_connect_account_id)
    {
        $where = array(
            'stripe_connect_account_id' => $stripe_connect_account_id
        );
        $sql = "SELECT
                    id,
                    company_name,
                    stripe_connect_account_id,
                    stripe_connect_status,
                    default_currency
                FROM
                    companies
                WHERE
                    stripe_connect_account_id = :stripe_connect_account_id
                    and
                    deleted = 0";
        return parent::select($sql, $where);
    }

    public function update_company(
        $company_id,
        $company_name,
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
