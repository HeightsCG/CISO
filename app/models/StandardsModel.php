<?php
class StandardsModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function load_standards($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    s.id,
                    s.standard_name,
                    s.short_code,
                    s.version,
                    s.standard_status,
                    s.date_created,
                    s.date_updated,
                    DATE_FORMAT(s.date_created, df.sql_format) AS date_created_display,
                    DATE_FORMAT(s.date_updated, df.sql_format) AS date_updated_display,
                    COUNT(sc.id) AS control_count
                FROM
                    standards s
                    JOIN companies co ON co.id = s.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN standard_controls sc ON sc.standard_id = s.id and sc.deleted = 0
                WHERE
                    s.company_id = :company_id
                    and
                    s.deleted = 0
                GROUP BY
                    s.id,
                    s.standard_name,
                    s.short_code,
                    s.version,
                    s.standard_status,
                    s.date_created,
                    s.date_updated,
                    df.sql_format
                ORDER BY
                    s.standard_name";
        return parent::select($sql, $where);
    }

    /**
     * Archived standards are deliberately excluded: anywhere in the app that asks a
     * user to pick a standard must only offer the ones still in force.
     */
    public function active_standards($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    s.id,
                    s.standard_name,
                    s.short_code,
                    s.version
                FROM
                    standards s
                WHERE
                    s.company_id = :company_id
                    and
                    s.standard_status = 'Active'
                    and
                    s.deleted = 0
                ORDER BY
                    s.standard_name";
        return parent::select($sql, $where);
    }

    public function get_standard($standard_id, $company_id)
    {
        $where = array(
            'id' => $standard_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    s.*,
                    DATE_FORMAT(s.date_created, df.sql_format) AS date_created_display,
                    DATE_FORMAT(s.date_updated, df.sql_format) AS date_updated_display,
                    (
                        SELECT COUNT(sc.id)
                        FROM standard_controls sc
                        WHERE sc.standard_id = s.id and sc.deleted = 0
                    ) AS control_count
                FROM
                    standards s
                    JOIN companies co ON co.id = s.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                WHERE
                    s.id = :id
                    and
                    s.company_id = :company_id
                    and
                    s.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_standard(
        $company_id,
        $standard_name,
        $short_code,
        $version,
        $description,
        $standard_status,
        $created_by
    )
    {
        $data = array(
            'company_id' => $company_id,
            'standard_name' => $standard_name,
            'short_code' => $short_code,
            'version' => $version,
            'description' => $description,
            'standard_status' => $standard_status,
            'created_by' => $created_by,
            'updated_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('standards', $data);
    }

    public function update_standard(
        $standard_id,
        $company_id,
        $standard_name,
        $short_code,
        $version,
        $description,
        $standard_status,
        $updated_by
    )
    {
        $where = array(
            'id' => $standard_id,
            'company_id' => $company_id
        );
        $data = array(
            'standard_name' => $standard_name,
            'short_code' => $short_code,
            'version' => $version,
            'description' => $description,
            'standard_status' => $standard_status,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('standards', $data, 'id = :id and company_id = :company_id', $where);
    }

    public function delete_standard($standard_id, $company_id, $updated_by)
    {
        $where = array(
            'id' => $standard_id,
            'company_id' => $company_id
        );
        $data = array(
            'deleted' => 1,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        $rows = parent::update('standards', $data, 'id = :id and company_id = :company_id', $where);

        if ($rows > 0) {
            $this->delete_controls_for_standard($standard_id, $updated_by);
        }

        return $rows;
    }

    /**
     * The standard and every control it owns are copied together or not at all —
     * a half-copied framework is worse than no copy, because nothing on screen
     * distinguishes it from a complete one.
     */
    public function duplicate_standard(
        $standard_id,
        $company_id,
        $standard_name,
        $short_code,
        $version,
        $created_by
    )
    {
        $rows = $this->get_standard($standard_id, $company_id);

        if (!is_array($rows) || count($rows) !== 1) {
            return 0;
        }

        $source = $rows[0];

        $this->db->beginTransaction();

        try {

            $new_id = $this->add_standard(
                $company_id,
                $standard_name,
                $short_code,
                $version,
                $source['description'],
                'Active',
                $created_by
            );

            $controls = $this->load_controls($standard_id, $company_id);

            foreach ($controls as $control) {
                $this->add_control(
                    $new_id,
                    $control['control_identifier'],
                    $control['control_title'],
                    $control['description'],
                    $control['family'],
                    $control['sort_order'],
                    $created_by
                );
            }

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $new_id;
    }

    public function load_controls($standard_id, $company_id)
    {
        $where = array(
            'standard_id' => $standard_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    sc.id,
                    sc.standard_id,
                    sc.control_identifier,
                    sc.control_title,
                    sc.description,
                    sc.family,
                    sc.sort_order
                FROM
                    standard_controls sc
                    JOIN standards s ON s.id = sc.standard_id
                WHERE
                    sc.standard_id = :standard_id
                    and
                    s.company_id = :company_id
                    and
                    sc.deleted = 0
                    and
                    s.deleted = 0
                ORDER BY
                    sc.family,
                    sc.sort_order,
                    sc.control_identifier";
        return parent::select($sql, $where);
    }

    public function get_control($control_id, $company_id)
    {
        $where = array(
            'id' => $control_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    sc.*
                FROM
                    standard_controls sc
                    JOIN standards s ON s.id = sc.standard_id
                WHERE
                    sc.id = :id
                    and
                    s.company_id = :company_id
                    and
                    sc.deleted = 0
                    and
                    s.deleted = 0";
        return parent::select($sql, $where);
    }

    /**
     * Live identifiers for a standard, used to reject duplicates on save and to
     * skip them on import. Keyed lowercase so `AC.L2-3.1.1` and `ac.l2-3.1.1`
     * cannot both be added.
     */
    public function control_identifiers($standard_id, $exclude_control_id = 0)
    {
        $where = array(
            'standard_id' => $standard_id,
            'exclude_id' => $exclude_control_id
        );
        $sql = "SELECT
                    sc.control_identifier
                FROM
                    standard_controls sc
                WHERE
                    sc.standard_id = :standard_id
                    and
                    sc.id <> :exclude_id
                    and
                    sc.deleted = 0";
        $rows = parent::select($sql, $where);

        $identifiers = array();

        foreach ($rows as $row) {
            $identifiers[mb_strtolower($row['control_identifier'])] = 1;
        }

        return $identifiers;
    }

    public function max_sort_order($standard_id)
    {
        $where = array(
            'standard_id' => $standard_id
        );
        $sql = "SELECT
                    COALESCE(MAX(sc.sort_order), 0) AS max_sort_order
                FROM
                    standard_controls sc
                WHERE
                    sc.standard_id = :standard_id
                    and
                    sc.deleted = 0";
        $rows = parent::select($sql, $where);

        return (int) $rows[0]['max_sort_order'];
    }

    public function add_control(
        $standard_id,
        $control_identifier,
        $control_title,
        $description,
        $family,
        $sort_order,
        $created_by
    )
    {
        $data = array(
            'standard_id' => $standard_id,
            'control_identifier' => $control_identifier,
            'control_title' => $control_title,
            'description' => $description,
            'family' => $family,
            'sort_order' => $sort_order,
            'created_by' => $created_by,
            'updated_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('standard_controls', $data);
    }

    public function update_control(
        $control_id,
        $standard_id,
        $control_identifier,
        $control_title,
        $description,
        $family,
        $sort_order,
        $updated_by
    )
    {
        $where = array(
            'id' => $control_id,
            'standard_id' => $standard_id
        );
        $data = array(
            'control_identifier' => $control_identifier,
            'control_title' => $control_title,
            'description' => $description,
            'family' => $family,
            'sort_order' => $sort_order,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('standard_controls', $data, 'id = :id and standard_id = :standard_id', $where);
    }

    public function delete_control($control_id, $standard_id, $updated_by)
    {
        $where = array(
            'id' => $control_id,
            'standard_id' => $standard_id
        );
        $data = array(
            'deleted' => 1,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('standard_controls', $data, 'id = :id and standard_id = :standard_id', $where);
    }

    private function delete_controls_for_standard($standard_id, $updated_by)
    {
        $where = array(
            'standard_id' => $standard_id
        );
        $data = array(
            'deleted' => 1,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('standard_controls', $data, 'standard_id = :standard_id', $where);
    }

    /**
     * Bulk insert for CSV import. Every row lands or none do, so a file that fails
     * partway through leaves the standard exactly as it was.
     */
    public function import_controls($standard_id, $controls, $created_by)
    {
        $sort_order = $this->max_sort_order($standard_id);

        $this->db->beginTransaction();

        try {

            foreach ($controls as $control) {
                $sort_order++;
                $this->add_control(
                    $standard_id,
                    $control['control_identifier'],
                    $control['control_title'],
                    $control['description'],
                    $control['family'],
                    $sort_order,
                    $created_by
                );
            }

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return count($controls);
    }

}
