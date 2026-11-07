<?php
class LeadsModel extends Model {

    const ORGANIZATION_TYPES = array(
        'Airline or operator',
        'Airport',
        'Maintenance (MRO)',
        'Design or production',
        'Aviation supplier',
        'Consultancy or assessor',
        'Other'
    );

    public function __construct(){
        parent::__construct();
    }

    public function add_lead($lead_type, $first_name, $last_name, $lead_email, $lead_phone, $company_name, $organization_type, $lead_message, $ip_address, $user_agent)
    {
        $data = array(
            'lead_type'         => $lead_type,
            'first_name'        => $first_name,
            'last_name'         => $last_name,
            'lead_email'        => $lead_email,
            'lead_phone'        => $lead_phone,
            'company_name'      => $company_name,
            'organization_type' => $organization_type,
            'lead_message'      => $lead_message,
            'ip_address'        => $ip_address,
            'user_agent'        => substr($user_agent, 0, 255),
            'date_created'      => date('Y-m-d H:i:s')
        );

        return parent::insert('leads', $data);
    }

    /** Requests from one address in the last hour, for throttling the public form. */
    public function count_recent_from_ip($ip_address)
    {
        $where = array(
            'ip_address' => $ip_address
        );
        $sql = "SELECT
                    COUNT(*) AS lead_count
                FROM
                    leads l
                WHERE
                    l.ip_address = :ip_address
                    AND l.date_created > (NOW() - INTERVAL 1 HOUR)";
        $rows = parent::select($sql, $where);

        return (is_array($rows) && count($rows) === 1) ? (int) $rows[0]['lead_count'] : 0;
    }

    public function load_leads($sql_date_format)
    {
        $where = array(
            'date_format' => $sql_date_format
        );
        $sql = "SELECT
                    l.id,
                    l.lead_type,
                    l.first_name,
                    l.last_name,
                    l.lead_email,
                    l.lead_phone,
                    l.company_name,
                    l.organization_type,
                    l.lead_message,
                    DATE_FORMAT(l.date_created, :date_format) AS received_display
                FROM
                    leads l
                ORDER BY
                    l.date_created DESC,
                    l.id DESC";
        return parent::select($sql, $where);
    }

}
