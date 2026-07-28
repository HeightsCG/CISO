<?php
class ClientsModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function load_clients($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    c.id,
                    c.company_name,
                    c.contact_name,
                    c.contact_title,
                    c.contact_email,
                    c.contact_phone,
                    c.country,
                    c.website,
                    c.address_1,
                    c.address_2,
                    c.city,
                    c.state,
                    c.postal_code,
                    c.date_created,
                    c.date_updated
                FROM
                    clients c
                WHERE
                    c.company_id = :company_id
                    and
                    c.deleted = 0
                ORDER BY
                    c.date_created DESC,
                    c.id DESC";
        return parent::select($sql, $where);
    }

    public function get_client($client_id, $company_id)
    {
        $where = array(
            'id' => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    c.*
                FROM
                    clients c
                WHERE
                    c.id = :id
                    and
                    c.company_id = :company_id
                    and
                    c.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_client(
        $company_id,
        $company_name,
        $contact_name,
        $contact_title,
        $contact_email,
        $contact_phone,
        $website,
        $address_1,
        $address_2,
        $city,
        $state,
        $postal_code,
        $country,
        $created_by
    )
    {
        $data = array(
            'company_id' => $company_id,
            'company_name' => $company_name,
            'contact_name' => $contact_name,
            'contact_title' => $contact_title,
            'contact_email' => $contact_email,
            'contact_phone' => $contact_phone,
            'website' => $website,
            'address_1' => $address_1,
            'address_2' => $address_2,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postal_code,
            'country' => $country,
            'created_by' => $created_by,
            'updated_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('clients', $data);
    }

    public function update_client(
        $client_id,
        $company_id,
        $company_name,
        $contact_name,
        $contact_title,
        $contact_email,
        $contact_phone,
        $website,
        $address_1,
        $address_2,
        $city,
        $state,
        $postal_code,
        $country,
        $updated_by
    )
    {
        $where = array(
            'id' => $client_id,
            'company_id' => $company_id
        );
        $data = array(
            'company_name' => $company_name,
            'contact_name' => $contact_name,
            'contact_title' => $contact_title,
            'contact_email' => $contact_email,
            'contact_phone' => $contact_phone,
            'website' => $website,
            'address_1' => $address_1,
            'address_2' => $address_2,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postal_code,
            'country' => $country,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('clients', $data, 'id = :id and company_id = :company_id', $where);
    }

    public function delete_client($client_id, $company_id, $updated_by)
    {
        $where = array(
            'id' => $client_id,
            'company_id' => $company_id
        );
        $data = array(
            'deleted' => 1,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('clients', $data, 'id = :id and company_id = :company_id', $where);
    }

}
