<?php
class EvidenceModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /** The project's vault: every piece of evidence with how widely it is reused. */
    public function load_evidence($project_id, $company_id)
    {
        $where = array(
            'project_id' => $project_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    e.id,
                    e.folder_id,
                    e.evidence_title,
                    e.description,
                    e.file_name,
                    e.file_size,
                    e.file_type,
                    e.date_created,
                    DATE_FORMAT(e.date_created, df.sql_format) AS date_created_display,
                    CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name,
                    COUNT(el.id) AS link_count
                FROM
                    evidence e
                    JOIN companies co ON co.id = e.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN user_accounts u ON u.user_id = e.uploaded_by
                    LEFT JOIN evidence_links el ON el.evidence_id = e.id
                WHERE
                    e.project_id = :project_id
                    and
                    e.company_id = :company_id
                    and
                    e.deleted = 0
                GROUP BY
                    e.id,
                    e.folder_id,
                    e.evidence_title,
                    e.description,
                    e.file_name,
                    e.file_size,
                    e.file_type,
                    e.date_created,
                    df.sql_format,
                    u.first_name,
                    u.last_name
                ORDER BY
                    e.date_created DESC,
                    e.id DESC";
        return parent::select($sql, $where);
    }

    /**
     * Paged, filtered vault lookup for the evidence picker.
     *
     * The vault is per project but can still run to thousands of files, so the
     * filtering and the slicing both happen in SQL - the browser never receives
     * more than one page.
     */
    public function search_evidence($project_id, $company_id, $term, $limit, $offset, $sort = 'date_created', $dir = 'desc')
    {
        $limit = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);

        // Whitelisted: the sort column and direction are the only parts of this
        // query built from a string, so they can never come straight from input.
        $sortable = array(
            'file_name' => 'e.file_name',
            'file_size' => 'e.file_size',
            'date_created' => 'e.date_created',
            'link_count' => 'link_count'
        );

        $order = $sortable[$sort] ?? 'e.date_created';
        $direction = (strtolower($dir) === 'asc') ? 'ASC' : 'DESC';

        $where = array(
            'project_id' => $project_id,
            'company_id' => $company_id
        );

        $filter = '';

        if ($term !== '') {
            $filter = " and (e.file_name LIKE :term_a or e.evidence_title LIKE :term_b or e.description LIKE :term_c)";
            $where['term_a'] = '%'.$term.'%';
            $where['term_b'] = '%'.$term.'%';
            $where['term_c'] = '%'.$term.'%';
        }

        // LIMIT and OFFSET are cast to int above; they cannot be bound as
        // parameters with native prepares on this driver.
        $sql = "SELECT
                    e.id,
                    e.evidence_title,
                    e.file_name,
                    e.file_size,
                    DATE_FORMAT(e.date_created, df.sql_format) AS date_created_display,
                    COUNT(el.id) AS link_count
                FROM
                    evidence e
                    JOIN companies co ON co.id = e.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN evidence_links el ON el.evidence_id = e.id
                WHERE
                    e.project_id = :project_id
                    and
                    e.company_id = :company_id
                    and
                    e.deleted = 0
                    ".$filter."
                GROUP BY
                    e.id,
                    e.evidence_title,
                    e.file_name,
                    e.file_size,
                    e.date_created,
                    df.sql_format
                ORDER BY
                    ".$order." ".$direction.",
                    e.id DESC
                LIMIT ".$limit." OFFSET ".$offset;

        return parent::select($sql, $where);
    }

    public function count_evidence($project_id, $company_id, $term)
    {
        $where = array(
            'project_id' => $project_id,
            'company_id' => $company_id
        );

        $filter = '';

        if ($term !== '') {
            $filter = " and (e.file_name LIKE :term_a or e.evidence_title LIKE :term_b or e.description LIKE :term_c)";
            $where['term_a'] = '%'.$term.'%';
            $where['term_b'] = '%'.$term.'%';
            $where['term_c'] = '%'.$term.'%';
        }

        $sql = "SELECT COUNT(*) AS total
                FROM evidence e
                WHERE e.project_id = :project_id
                    and e.company_id = :company_id
                    and e.deleted = 0
                    ".$filter;

        $rows = parent::select($sql, $where);

        return (int) $rows[0]['total'];
    }

    public function get_evidence($evidence_id, $company_id)
    {
        $where = array(
            'id' => $evidence_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    e.*,
                    DATE_FORMAT(e.date_created, df.sql_format) AS date_created_display,
                    pr.project_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS uploaded_by_name
                FROM
                    evidence e
                    JOIN companies co ON co.id = e.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN projects pr ON pr.id = e.project_id
                    LEFT JOIN user_accounts u ON u.user_id = e.uploaded_by
                WHERE
                    e.id = :id
                    and
                    e.company_id = :company_id
                    and
                    e.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_evidence(
        $company_id,
        $project_id,
        $folder_id,
        $evidence_title,
        $description,
        $file_key,
        $file_name,
        $file_size,
        $file_type,
        $uploaded_by
    )
    {
        $data = array(
            'company_id' => $company_id,
            'project_id' => $project_id,
            'folder_id' => $folder_id,
            'evidence_title' => $evidence_title,
            'description' => $description,
            'file_key' => $file_key,
            'file_name' => $file_name,
            'file_size' => $file_size,
            'file_type' => $file_type,
            'uploaded_by' => $uploaded_by,
            'updated_by' => $uploaded_by,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('evidence', $data);
    }

    public function update_evidence($evidence_id, $company_id, $evidence_title, $description, $updated_by)
    {
        $where = array(
            'id' => $evidence_id,
            'company_id' => $company_id
        );
        $data = array(
            'evidence_title' => $evidence_title,
            'description' => $description,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('evidence', $data, 'id = :id and company_id = :company_id', $where);
    }

    /**
     * Removing evidence drops every link with it: a link pointing at a file that no
     * longer exists would show an assessment item as evidenced when it is not.
     */
    public function delete_evidence($evidence_id, $company_id, $updated_by)
    {
        $where = array(
            'id' => $evidence_id,
            'company_id' => $company_id
        );
        $data = array(
            'deleted' => 1,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        $rows = parent::update('evidence', $data, 'id = :id and company_id = :company_id', $where);

        if ($rows > 0) {
            parent::delete_all('evidence_links', 'evidence_id = :evidence_id', array('evidence_id' => $evidence_id));
        }

        return $rows;
    }

    /** Evidence attached to one assessment item. */
    public function load_item_evidence($item_id, $company_id)
    {
        // Native prepares cannot reuse a named placeholder, so each occurrence
        // gets its own name.
        $where = array(
            'item_id' => $item_id,
            'company_id' => $company_id,
            'company_id2' => $company_id
        );
        $sql = "SELECT
                    e.id,
                    e.evidence_title,
                    e.file_name,
                    e.file_size
                FROM
                    evidence_links el
                    JOIN evidence e ON e.id = el.evidence_id
                    JOIN assessment_items ai ON ai.id = el.assessment_item_id
                    JOIN assessments a ON a.id = ai.assessment_id
                WHERE
                    el.assessment_item_id = :item_id
                    and
                    e.company_id = :company_id
                    and
                    a.company_id = :company_id2
                    and
                    e.deleted = 0
                ORDER BY
                    e.evidence_title";
        return parent::select($sql, $where);
    }

    /** Everywhere one piece of evidence is used - the other side of the link. */
    public function load_evidence_links($evidence_id, $company_id)
    {
        $where = array(
            'evidence_id' => $evidence_id,
            'company_id' => $company_id,
            'company_id2' => $company_id
        );
        $sql = "SELECT
                    el.id AS link_id,
                    ai.id AS item_id,
                    ai.control_identifier,
                    ai.control_title,
                    ai.family,
                    ai.item_result,
                    a.id AS assessment_id,
                    a.assessment_name,
                    a.standard_name,
                    a.short_code,
                    p.id AS project_id,
                    p.project_name
                FROM
                    evidence_links el
                    JOIN evidence e ON e.id = el.evidence_id
                    JOIN assessment_items ai ON ai.id = el.assessment_item_id
                    JOIN assessments a ON a.id = ai.assessment_id
                    JOIN projects p ON p.id = a.project_id
                WHERE
                    el.evidence_id = :evidence_id
                    and
                    e.company_id = :company_id
                    and
                    a.company_id = :company_id2
                    and
                    e.deleted = 0
                    and
                    ai.deleted = 0
                    and
                    a.deleted = 0
                ORDER BY
                    p.project_name,
                    a.assessment_name,
                    ai.family,
                    ai.sort_order";
        return parent::select($sql, $where);
    }

    /**
     * Both sides are re-checked against the session's org before a link is made:
     * the evidence and the item must belong to the same company, and the evidence
     * to the same project as the item's assessment.
     */
    public function link_evidence($evidence_id, $item_id, $company_id, $created_by)
    {
        $check = parent::select(
            "SELECT e.id
             FROM evidence e
                JOIN assessment_items ai ON ai.id = :item_id
                JOIN assessments a ON a.id = ai.assessment_id
                JOIN projects p ON p.id = a.project_id
             WHERE e.id = :evidence_id
                and e.company_id = :company_id
                and a.company_id = :company_id2
                and e.project_id = p.id
                and e.deleted = 0
                and ai.deleted = 0
                and a.deleted = 0",
            array(
                'item_id' => $item_id,
                'evidence_id' => $evidence_id,
                'company_id' => $company_id,
                'company_id2' => $company_id
            )
        );

        if (count($check) !== 1) {
            return 0;
        }

        $existing = parent::select(
            "SELECT id FROM evidence_links WHERE evidence_id = :evidence_id and assessment_item_id = :item_id",
            array('evidence_id' => $evidence_id, 'item_id' => $item_id)
        );

        if (count($existing) > 0) {
            return (int) $existing[0]['id'];
        }

        return parent::insert('evidence_links', array(
            'evidence_id' => $evidence_id,
            'assessment_item_id' => $item_id,
            'created_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s')
        ));
    }

    public function unlink_evidence($evidence_id, $item_id, $company_id)
    {
        $check = parent::select(
            "SELECT el.id
             FROM evidence_links el
                JOIN evidence e ON e.id = el.evidence_id
                JOIN assessment_items ai ON ai.id = el.assessment_item_id
                JOIN assessments a ON a.id = ai.assessment_id
             WHERE el.evidence_id = :evidence_id
                and el.assessment_item_id = :item_id
                and e.company_id = :company_id
                and a.company_id = :company_id2",
            array(
                'evidence_id' => $evidence_id,
                'item_id' => $item_id,
                'company_id' => $company_id,
                'company_id2' => $company_id
            )
        );

        if (count($check) !== 1) {
            return 0;
        }

        return parent::delete_all(
            'evidence_links',
            'evidence_id = :evidence_id and assessment_item_id = :item_id',
            array('evidence_id' => $evidence_id, 'item_id' => $item_id)
        );
    }


    /* ------------------------------------------------------------- folders */

    /** The whole tree for one project, with a file count against each folder. */
    public function load_folders($project_id, $company_id)
    {
        $where = array(
            'project_id' => $project_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    f.id,
                    f.parent_id,
                    f.folder_name,
                    (SELECT COUNT(*) FROM evidence e WHERE e.folder_id = f.id and e.deleted = 0) AS file_count
                FROM
                    evidence_folders f
                WHERE
                    f.project_id = :project_id
                    and
                    f.company_id = :company_id
                    and
                    f.deleted = 0
                ORDER BY
                    f.folder_name";
        return parent::select($sql, $where);
    }

    public function get_folder($folder_id, $company_id)
    {
        $where = array(
            'id' => $folder_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    f.id,
                    f.company_id,
                    f.project_id,
                    f.parent_id,
                    f.folder_name
                FROM
                    evidence_folders f
                WHERE
                    f.id = :id
                    and
                    f.company_id = :company_id
                    and
                    f.deleted = 0";
        return parent::select($sql, $where);
    }

    /** Two folders cannot share a name under the same parent. */
    public function check_folder_name($project_id, $parent_id, $folder_name, $exclude_folder_id = 0)
    {
        $where_text = '';

        $where = array(
            'project_id' => $project_id,
            'parent_id' => $parent_id,
            'folder_name' => $folder_name
        );

        if ($exclude_folder_id > 0) {
            $where['exclude_folder_id'] = $exclude_folder_id;
            $where_text = ' and f.id != :exclude_folder_id';
        }

        $sql = "SELECT
                    f.id
                FROM
                    evidence_folders f
                WHERE
                    f.project_id = :project_id
                    and
                    f.parent_id = :parent_id
                    and
                    f.folder_name = :folder_name
                    and
                    f.deleted = 0" . $where_text;
        return parent::select($sql, $where);
    }

    public function add_folder($company_id, $project_id, $parent_id, $folder_name, $created_by)
    {
        $data = array(
            'company_id' => $company_id,
            'project_id' => $project_id,
            'parent_id' => $parent_id,
            'folder_name' => $folder_name,
            'created_by' => $created_by,
            'updated_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s'),
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('evidence_folders', $data);
    }

    public function update_folder($folder_id, $company_id, $parent_id, $folder_name, $updated_by)
    {
        $where = array(
            'id' => $folder_id,
            'company_id' => $company_id
        );
        $data = array(
            'parent_id' => $parent_id,
            'folder_name' => $folder_name,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('evidence_folders', $data, 'id = :id and company_id = :company_id', $where);
    }

    /**
     * Every folder beneath one, itself included. Stops a folder being moved
     * inside its own subtree, which would cut that branch off the root, and
     * gathers a subtree for deletion.
     */
    public function folder_subtree($folder_id, $company_id)
    {
        $folder = $this->get_folder($folder_id, $company_id);

        if (count($folder) !== 1) {
            return array();
        }

        $ids = array((int) $folder_id);
        $frontier = array((int) $folder_id);
        $all = $this->load_folders($folder[0]['project_id'], $company_id);

        while (count($frontier) > 0) {

            $next = array();

            foreach ($all as $row) {
                if (in_array((int) $row['parent_id'], $frontier, true) && !in_array((int) $row['id'], $ids, true)) {
                    $ids[] = (int) $row['id'];
                    $next[] = (int) $row['id'];
                }
            }

            $frontier = $next;
        }

        return $ids;
    }

    /**
     * Deleting a folder takes its subfolders with it. The files inside are not
     * destroyed - they surface at the parent, because a folder is a filing
     * decision and losing the evidence with it would be indefensible.
     */
    public function delete_folder($folder_id, $company_id, $updated_by)
    {
        $folder = $this->get_folder($folder_id, $company_id);

        if (count($folder) !== 1) {
            return 0;
        }

        $ids = $this->folder_subtree($folder_id, $company_id);
        $parent_id = (int) $folder[0]['parent_id'];

        foreach ($ids as $id) {

            parent::update(
                'evidence',
                array('folder_id' => $parent_id, 'updated_by' => $updated_by, 'date_updated' => date('Y-m-d H:i:s')),
                'folder_id = :from_folder_id and company_id = :company_id',
                array('from_folder_id' => $id, 'company_id' => $company_id)
            );

            parent::update(
                'evidence_folders',
                array('deleted' => 1, 'updated_by' => $updated_by, 'date_updated' => date('Y-m-d H:i:s')),
                'id = :id and company_id = :company_id',
                array('id' => $id, 'company_id' => $company_id)
            );
        }

        return count($ids);
    }

    public function move_evidence($evidence_id, $company_id, $folder_id, $updated_by)
    {
        $where = array(
            'id' => $evidence_id,
            'company_id' => $company_id
        );
        $data = array(
            'folder_id' => $folder_id,
            'updated_by' => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('evidence', $data, 'id = :id and company_id = :company_id', $where);
    }

}
