<?php
/**
 * Links between controls of different frameworks (framework_crosswalk), shared
 * by every company. Loaded in full from frameworks/crosswalk/crosswalk.json.
 */
class CrosswalkModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function clear_links()
    {
        return parent::delete_all('framework_crosswalk', '1 = 1');
    }

    public function add_link(
        $crosswalk_set,
        $from_framework,
        $from_identifier,
        $to_framework,
        $to_identifier,
        $link_relationship,
        $source_document,
        $source_version
    )
    {
        $data = array(
            'crosswalk_set' => $crosswalk_set,
            'from_framework' => $from_framework,
            'from_identifier' => $from_identifier,
            'to_framework' => $to_framework,
            'to_identifier' => $to_identifier,
            'link_relationship' => $link_relationship,
            'source_document' => $source_document,
            'source_version' => $source_version
        );
        return parent::insert('framework_crosswalk', $data);
    }

    /** Controls in other frameworks linked to one control, in either direction. */
    public function linked_controls($framework_key, $control_identifier)
    {
        $where = array(
            'from_framework' => $framework_key,
            'from_identifier' => $control_identifier,
            'to_framework' => $framework_key,
            'to_identifier' => $control_identifier
        );
        $sql = "SELECT
                    fc.to_framework framework_key,
                    fc.to_identifier control_identifier,
                    fc.link_relationship,
                    fc.source_document
                FROM
                    framework_crosswalk fc
                WHERE
                    fc.from_framework = :from_framework
                    and
                    fc.from_identifier = :from_identifier
                UNION
                SELECT
                    fc.from_framework framework_key,
                    fc.from_identifier control_identifier,
                    fc.link_relationship,
                    fc.source_document
                FROM
                    framework_crosswalk fc
                WHERE
                    fc.to_framework = :to_framework
                    and
                    fc.to_identifier = :to_identifier";
        return parent::select($sql, $where);
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
