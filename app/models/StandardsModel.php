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
                    $created_by,
                    $control['parent_identifier'] ?? ''
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
                    sc.parent_identifier,
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
        $created_by,
        $parent_identifier = ''
    )
    {
        $data = array(
            'standard_id' => $standard_id,
            'control_identifier' => $control_identifier,
            'parent_identifier' => $parent_identifier,
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
                    $created_by,
                    $control['parent_identifier'] ?? ''
                );
            }

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return count($controls);
    }

    public function framework_standard($company_id, $framework_key, $short_code)
    {
        $where = array(
            'company_id' => $company_id,
            'framework_key' => $framework_key,
            'framework_key_order' => $framework_key,
            'short_code' => $short_code
        );
        $sql = "SELECT
                    s.id,
                    s.framework_key
                FROM
                    standards s
                WHERE
                    s.company_id = :company_id
                    and
                    s.deleted = 0
                    and
                    (s.framework_key = :framework_key or (s.framework_key = '' and s.short_code = :short_code))
                ORDER BY
                    (s.framework_key = :framework_key_order) desc,
                    s.id
                LIMIT 1";
        $rows = parent::select($sql, $where);
        return count($rows) === 0 ? null : $rows[0];
    }

    public function save_framework_standard(
        $standard_id,
        $company_id,
        $framework_key,
        $standard_name,
        $short_code,
        $version,
        $full_name,
        $issuing_body,
        $source_document,
        $source_version,
        $user_id
    )
    {
        $data = array(
            'framework_key' => $framework_key,
            'standard_name' => $standard_name,
            'short_code' => $short_code,
            'version' => $version,
            'description' => $full_name,
            'full_name' => $full_name,
            'issuing_body' => $issuing_body,
            'source_document' => $source_document,
            'source_version' => $source_version,
            'updated_by' => $user_id,
            'date_updated' => date('Y-m-d H:i:s')
        );

        if ($standard_id === 0) {
            $data['company_id'] = $company_id;
            $data['standard_status'] = 'Active';
            $data['created_by'] = $user_id;
            $data['date_created'] = date('Y-m-d H:i:s');
            $data['deleted'] = 0;
            return (int) parent::insert('standards', $data);
        }

        $where = array(
            'id' => $standard_id,
            'company_id' => $company_id
        );
        parent::update('standards', $data, 'id = :id and company_id = :company_id', $where);
        return $standard_id;
    }

    public function framework_control_ids($standard_id)
    {
        $where = array(
            'standard_id' => $standard_id
        );
        $sql = "SELECT
                    sc.id,
                    sc.control_identifier,
                    sc.control_kind
                FROM
                    standard_controls sc
                WHERE
                    sc.standard_id = :standard_id
                    and
                    sc.deleted = 0";
        return parent::select($sql, $where);
    }

    public function save_framework_control(
        $control_id,
        $standard_id,
        $control_identifier,
        $parent_identifier,
        $control_kind,
        $control_title,
        $description,
        $guidance,
        $family,
        $control_weight,
        $partial_weight,
        $control_flags,
        $source_document,
        $source_version,
        $sort_order,
        $user_id
    )
    {
        $data = array(
            'control_identifier' => $control_identifier,
            'parent_identifier' => $parent_identifier,
            'control_kind' => $control_kind,
            'control_title' => $control_title,
            'description' => $description,
            'guidance' => $guidance,
            'family' => $family,
            'control_weight' => $control_weight,
            'partial_weight' => $partial_weight,
            'control_flags' => $control_flags,
            'source_document' => $source_document,
            'source_version' => $source_version,
            'sort_order' => $sort_order,
            'updated_by' => $user_id,
            'date_updated' => date('Y-m-d H:i:s')
        );

        if ($control_id === 0) {
            $data['standard_id'] = $standard_id;
            $data['created_by'] = $user_id;
            $data['date_created'] = date('Y-m-d H:i:s');
            $data['deleted'] = 0;
            return parent::insert('standard_controls', $data);
        }

        $where = array(
            'id' => $control_id,
            'standard_id' => $standard_id
        );
        return parent::update('standard_controls', $data, 'id = :id and standard_id = :standard_id', $where);
    }

    public function archive_standard($standard_id, $company_id, $user_id)
    {
        $data = array(
            'standard_status' => 'Archived',
            'updated_by' => $user_id,
            'date_updated' => date('Y-m-d H:i:s')
        );
        $where = array(
            'id' => $standard_id,
            'company_id' => $company_id
        );
        return parent::update('standards', $data, 'id = :id and company_id = :company_id', $where);
    }

    public function rename_assessments($standard_id, $company_id, $standard_name, $version, $framework_key)
    {
        $data = array(
            'standard_name' => $standard_name,
            'version' => $version,
            'framework_key' => $framework_key
        );
        $where = array(
            'standard_id' => $standard_id,
            'company_id' => $company_id
        );
        return parent::update('assessments', $data, 'standard_id = :standard_id and company_id = :company_id', $where);
    }

    public function begin()
    {
        $this->db->beginTransaction();
    }

    public function commit()
    {
        $this->db->commit();
    }

    public function roll_back()
    {
        $this->db->rollBack();
    }

}
