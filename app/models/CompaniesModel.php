<?php
class CompaniesModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function get_company($company_id)
    {
        $where = array(
            'id' => $company_id
        );
        $sql = "SELECT
                    c.*
                FROM
                    companies c
                WHERE
                    c.id = :id";
        return parent::select($sql, $where);
    }

    public function update_company(
        $company_id,
        $company_name,
        $address_1,
        $address_2,
        $city,
        $state_region,
        $postal_code,
        $country,
        $website,
        $timezone,
        $brand_color,
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
            'state_region' => $state_region,
            'postal_code' => $postal_code,
            'country' => $country,
            'website' => $website,
            'timezone' => $timezone,
            'brand_color' => $brand_color,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('companies', $data, 'id = :id', $where);
    }

    public function update_logo($company_id, $logo_path, $updated_by)
    {
        $where = array(
            'id' => $company_id
        );
        $data = array(
            'logo_path' => $logo_path,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('companies', $data, 'id = :id', $where);
    }

}
