<?php
/**
 * POA&M records, milestones, evidence links and their append-only change
 * history. Every web-side query is scoped to the company. Writes that change
 * a record also write its history rows in the same transaction; nothing
 * here updates or deletes a history row, and records are never deleted.
 */
class PoamModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /* ----------------------------------------------------------------- sync */

    /**
     * Runs one sync inside a transaction that holds the assessment row lock,
     * so concurrent syncs of the same assessment queue behind each other and
     * sequence numbers are allocated once. $planner receives every stored
     * record and returns Poam::plan_sync() operations, which are applied here.
     */
    public function sync_items($assessment_id, $company_id, $user_id, callable $planner)
    {
        $counts = array(
            'created' => 0,
            'refreshed' => 0,
            'resolved' => 0,
            'reopened' => 0
        );

        $this->db->beginTransaction();

        try {

            if (!$this->lock_assessment($assessment_id, $company_id)) {
                $this->db->rollBack();
                return array(
                    'error' => 'That assessment could not be found'
                );
            }

            $records = $this->all_records($assessment_id, $company_id);
            $now = date('Y-m-d H:i:s');

            foreach ($planner($records) as $op) {

                if ($op['op'] === 'insert') {
                    $values = array();
                    foreach (Poam::SNAPSHOT_FIELDS as $field) {
                        $values[$field] = $op['snapshot'][$field];
                    }
                    $values['source_hash'] = Poam::snapshot_hash($op['snapshot']);
                    $values['source_date_updated'] = $op['snapshot']['updated'] !== '' ? $op['snapshot']['updated'] : null;
                    $from = $op['reopened_from'];
                    $item = $this->insert_record($assessment_id, $company_id, $user_id, $values, $from === null ? null : (int) $from['id'], $now);
                    $this->history($company_id, $assessment_id, $item['id'], null, $op['change_source'], 'poam_ref', null, $item['poam_ref'], $op['note'], $user_id, $now);
                    if ($from !== null) {
                        $this->history($company_id, $assessment_id, (int) $from['id'], null, 'Reopen', 'reopened_as', null, $item['poam_ref'], $op['note'], $user_id, $now);
                        $counts['reopened']++;
                    } else {
                        $counts['created']++;
                    }
                    continue;
                }

                $record = $op['record'];
                $this->update_columns((int) $record['id'], $op['set'], $user_id, $now);

                foreach ($op['set'] as $field => $value) {
                    if (in_array($field, array(
                        'source_hash',
                        'source_date_updated'
                    ), true)) {
                        continue;
                    }
                    $this->history($company_id, $assessment_id, (int) $record['id'], null, $op['change_source'], $field, $record[$field] ?? null, $value, $op['note'], $user_id, $now);
                }

                if (($op['set']['source_resolved'] ?? null) === 1) {
                    $counts['resolved']++;
                } else {
                    $counts['refreshed']++;
                }
            }

            $this->db->commit();

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $counts;
    }

    private function lock_assessment($assessment_id, $company_id): bool
    {
        $lock = $this->db->prepare("SELECT id FROM assessments WHERE id = :assessment_id and company_id = :company_id and deleted = 0 FOR UPDATE");
        $lock->execute(array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        ));

        return $lock->fetch() !== false;
    }

    /** Every record of the assessment, open or closed, for the sync's planner. */
    private function all_records($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    *
                FROM
                    poam_items
                WHERE
                    assessment_id = :assessment_id
                    and
                    company_id = :company_id
                    and
                    deleted = 0
                ORDER BY
                    poam_seq";
        return parent::select($sql, $where);
    }

    /**
     * Inserts one record with the next sequence number. The caller holds the
     * assessment row lock; MAX counts deleted rows too, so no number returns.
     */
    private function insert_record($assessment_id, $company_id, $user_id, $values, $reopened_from_id, $now)
    {
        $next = $this->db->prepare("SELECT COALESCE(MAX(poam_seq), 0) + 1 AS next_seq FROM poam_items WHERE assessment_id = :assessment_id");
        $next->execute(array(
            'assessment_id' => $assessment_id
        ));
        $seq = (int) $next->fetch()['next_seq'];
        $ref = Poam::ref((int) $assessment_id, $seq);

        $data = array_merge($values, array(
            'company_id' => $company_id,
            'assessment_id' => $assessment_id,
            'poam_seq' => $seq,
            'poam_ref' => $ref,
            'reopened_from_id' => $reopened_from_id,
            'source_resolved' => 0,
            'poam_status' => 'Open',
            'created_by' => $user_id,
            'updated_by' => $user_id,
            'date_created' => $now,
            'date_updated' => $now,
            'deleted' => 0
        ));

        return array(
            'id' => (int) parent::insert('poam_items', $data),
            'poam_ref' => $ref
        );
    }

    private function update_columns($item_id, $set, $user_id, $now)
    {
        $data = array_merge($set, array(
            'updated_by' => $user_id,
            'date_updated' => $now
        ));

        return parent::update('poam_items', $data, 'id = :item_id', array(
            'item_id' => $item_id
        ));
    }

    private function history($company_id, $assessment_id, $item_id, $milestone_id, $change_source, $field_name, $old_value, $new_value, $change_note, $user_id, $now)
    {
        $data = array(
            'company_id' => $company_id,
            'assessment_id' => $assessment_id,
            'poam_item_id' => $item_id,
            'poam_milestone_id' => $milestone_id,
            'change_source' => $change_source,
            'field_name' => $field_name,
            'old_value' => $old_value === null ? null : (string) $old_value,
            'new_value' => $new_value === null ? null : (string) $new_value,
            'change_note' => mb_substr((string) $change_note, 0, 500),
            'changed_by' => (int) $user_id,
            'date_created' => $now
        );

        return parent::insert('poam_history', $data);
    }

    /* ---------------------------------------------------------------- reads */

    /** The register: every record of the assessment with milestone and evidence counts. */
    public function load_items($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    pi.*,
                    TRIM(CONCAT(IFNULL(ua.first_name, ''), ' ', IFNULL(ua.last_name, ''))) AS closed_by_name,
                    pr.poam_ref AS reopened_from_ref,
                    (
                        SELECT COUNT(pm.id)
                        FROM poam_milestones pm
                        WHERE pm.poam_item_id = pi.id and pm.deleted = 0
                    ) AS milestone_count,
                    (
                        SELECT COUNT(pm.id)
                        FROM poam_milestones pm
                        WHERE pm.poam_item_id = pi.id and pm.deleted = 0 and pm.milestone_status = 'Completed'
                    ) AS milestones_done,
                    (
                        SELECT COUNT(pe.id)
                        FROM poam_evidence pe
                        WHERE pe.poam_item_id = pi.id and pe.deleted = 0
                    ) AS evidence_count
                FROM
                    poam_items pi
                    LEFT JOIN user_accounts ua ON ua.user_id = pi.closed_by
                    LEFT JOIN poam_items pr ON pr.id = pi.reopened_from_id
                WHERE
                    pi.assessment_id = :assessment_id
                    and
                    pi.company_id = :company_id
                    and
                    pi.deleted = 0
                ORDER BY
                    pi.poam_seq";
        return parent::select($sql, $where);
    }

    /** One record with its assessment's client and framework, for scoping and rules. */
    public function get_item($item_id, $company_id)
    {
        $where = array(
            'id' => $item_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    pi.*,
                    a.client_id,
                    a.framework_key,
                    a.poam_clock_date,
                    TRIM(CONCAT(IFNULL(ua.first_name, ''), ' ', IFNULL(ua.last_name, ''))) AS closed_by_name,
                    pr.poam_ref AS reopened_from_ref
                FROM
                    poam_items pi
                    JOIN assessments a ON a.id = pi.assessment_id
                    LEFT JOIN user_accounts ua ON ua.user_id = pi.closed_by
                    LEFT JOIN poam_items pr ON pr.id = pi.reopened_from_id
                WHERE
                    pi.id = :id
                    and
                    pi.company_id = :company_id
                    and
                    pi.deleted = 0
                    and
                    a.deleted = 0";
        return parent::select($sql, $where);
    }

    /** The control's current recorded result, read straight from the assessment. */
    public function control_result($assessment_id, $control_identifier)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'control_identifier' => $control_identifier
        );
        $sql = "SELECT
                    item_result
                FROM
                    assessment_items
                WHERE
                    assessment_id = :assessment_id
                    and
                    control_identifier = :control_identifier
                    and
                    control_kind = 'control'
                    and
                    deleted = 0
                LIMIT 1";
        $rows = parent::select($sql, $where);
        return count($rows) === 1 ? (string) $rows[0]['item_result'] : '';
    }

    public function clock_date($assessment_id, $company_id)
    {
        $where = array(
            'id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    poam_clock_date
                FROM
                    assessments
                WHERE
                    id = :id
                    and
                    company_id = :company_id
                    and
                    deleted = 0";
        $rows = parent::select($sql, $where);
        return count($rows) === 1 ? $rows[0]['poam_clock_date'] : null;
    }

    /** Milestones of one record ($item_id) or of every record of an assessment ($assessment_id). */
    public function load_milestones($item_id, $assessment_id, $company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $filter = 'pm.poam_item_id = :item_id';
        if ((int) $item_id > 0) {
            $where['item_id'] = $item_id;
        } else {
            $where['assessment_id'] = $assessment_id;
            $filter = 'pi.assessment_id = :assessment_id';
        }
        $sql = "SELECT
                    pm.*,
                    pi.poam_ref,
                    pi.source_ref
                FROM
                    poam_milestones pm
                    JOIN poam_items pi ON pi.id = pm.poam_item_id
                WHERE
                    ".$filter."
                    and
                    pm.company_id = :company_id
                    and
                    pm.deleted = 0
                    and
                    pi.deleted = 0
                ORDER BY
                    pi.poam_seq,
                    pm.sort_order,
                    pm.id";
        return parent::select($sql, $where);
    }

    public function get_milestone($milestone_id, $company_id)
    {
        $where = array(
            'id' => $milestone_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    pm.*
                FROM
                    poam_milestones pm
                WHERE
                    pm.id = :id
                    and
                    pm.company_id = :company_id
                    and
                    pm.deleted = 0";
        return parent::select($sql, $where);
    }

    /**
     * Evidence linked to one record ($item_id) or every record of an
     * assessment; $include_private false leaves out files marked private.
     */
    public function load_evidence($item_id, $assessment_id, $company_id, $include_private)
    {
        $where = array(
            'company_id' => $company_id
        );
        $filter = 'pe.poam_item_id = :item_id';
        if ((int) $item_id > 0) {
            $where['item_id'] = $item_id;
        } else {
            $where['assessment_id'] = $assessment_id;
            $filter = 'pi.assessment_id = :assessment_id';
        }
        $sql = "SELECT
                    pe.id,
                    pe.poam_item_id,
                    pe.evidence_id,
                    pe.evidence_role,
                    pe.date_created,
                    pi.poam_ref,
                    e.evidence_title,
                    e.file_name,
                    e.evidence_private,
                    TRIM(CONCAT(IFNULL(ua.first_name, ''), ' ', IFNULL(ua.last_name, ''))) AS linked_by_name
                FROM
                    poam_evidence pe
                    JOIN poam_items pi ON pi.id = pe.poam_item_id
                    JOIN evidence e ON e.id = pe.evidence_id and e.deleted = 0
                    LEFT JOIN user_accounts ua ON ua.user_id = pe.linked_by
                WHERE
                    ".$filter."
                    and
                    pe.company_id = :company_id
                    and
                    pe.deleted = 0
                    and
                    pi.deleted = 0
                    ".($include_private ? '' : 'and e.evidence_private = 0')."
                ORDER BY
                    pi.poam_seq,
                    pe.evidence_role,
                    e.evidence_title";
        return parent::select($sql, $where);
    }

    /** Closure evidence linked to a record that still exists in the vault. */
    public function closure_evidence_count($item_id, $company_id)
    {
        $where = array(
            'item_id' => $item_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    COUNT(pe.id) AS total
                FROM
                    poam_evidence pe
                    JOIN evidence e ON e.id = pe.evidence_id and e.deleted = 0
                WHERE
                    pe.poam_item_id = :item_id
                    and
                    pe.company_id = :company_id
                    and
                    pe.evidence_role = 'Closure'
                    and
                    pe.deleted = 0";
        $rows = parent::select($sql, $where);
        return (int) ($rows[0]['total'] ?? 0);
    }

    public function get_evidence_link($link_id, $company_id)
    {
        $where = array(
            'id' => $link_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    pe.*,
                    e.evidence_title,
                    e.evidence_private
                FROM
                    poam_evidence pe
                    JOIN evidence e ON e.id = pe.evidence_id
                WHERE
                    pe.id = :id
                    and
                    pe.company_id = :company_id
                    and
                    pe.deleted = 0";
        return parent::select($sql, $where);
    }

    /** A vault file of the given client, for linking to a record. */
    public function client_evidence($evidence_id, $client_id, $company_id)
    {
        $where = array(
            'id' => $evidence_id,
            'client_id' => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    id,
                    evidence_title,
                    evidence_private
                FROM
                    evidence
                WHERE
                    id = :id
                    and
                    client_id = :client_id
                    and
                    company_id = :company_id
                    and
                    deleted = 0";
        return parent::select($sql, $where);
    }

    /** Change history of one record ($item_id) or of a whole assessment, oldest first. */
    public function load_history($item_id, $assessment_id, $company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $filter = 'ph.poam_item_id = :item_id';
        if ((int) $item_id > 0) {
            $where['item_id'] = $item_id;
        } else {
            $where['assessment_id'] = $assessment_id;
            $filter = 'ph.assessment_id = :assessment_id';
        }
        $sql = "SELECT
                    ph.*,
                    pi.poam_ref,
                    TRIM(CONCAT(IFNULL(ua.first_name, ''), ' ', IFNULL(ua.last_name, ''))) AS changed_by_name
                FROM
                    poam_history ph
                    LEFT JOIN poam_items pi ON pi.id = ph.poam_item_id
                    LEFT JOIN user_accounts ua ON ua.user_id = ph.changed_by
                WHERE
                    ".$filter."
                    and
                    ph.company_id = :company_id
                ORDER BY
                    ph.id";
        return parent::select($sql, $where);
    }

    /* --------------------------------------------------------------- writes */

    /** The record as it stands, locked for the rest of the transaction. */
    private function locked_item($item_id, $company_id)
    {
        $lock = $this->db->prepare("SELECT * FROM poam_items WHERE id = :item_id and company_id = :company_id and deleted = 0 FOR UPDATE");
        $lock->execute(array(
            'item_id' => $item_id,
            'company_id' => $company_id
        ));
        $row = $lock->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Writes the management fields of an open record and one history row per
     * field that changed. Returns the number of fields changed, or false when
     * the record is gone or closed.
     */
    public function save_item($item_id, $company_id, $owner_name, $resources, $detection_date, $scheduled_completion_date, $completion_date, $poam_status, $comments, $weakness_override, $action_override, $priority_override, $risk_rating, $vendor_dependency, $vendor_product, $vendor_checkin_date, $deviation_type, $deviation_rationale, $change_source, $user_id)
    {
        $now = date('Y-m-d H:i:s');
        $data = array(
            'owner_name' => $owner_name,
            'resources' => $resources,
            'detection_date' => $detection_date,
            'scheduled_completion_date' => $scheduled_completion_date,
            'completion_date' => $completion_date,
            'poam_status' => $poam_status,
            'comments' => $comments,
            'weakness_override' => $weakness_override,
            'action_override' => $action_override,
            'priority_override' => $priority_override,
            'risk_rating' => $risk_rating,
            'vendor_dependency' => (int) $vendor_dependency,
            'vendor_product' => $vendor_product,
            'vendor_checkin_date' => $vendor_checkin_date,
            'deviation_type' => $deviation_type,
            'deviation_rationale' => $deviation_rationale
        );

        $this->db->beginTransaction();

        try {
            $item = $this->locked_item($item_id, $company_id);

            if ($item === null || $item['poam_status'] === 'Closed' || $poam_status === 'Closed') {
                $this->db->rollBack();
                return false;
            }

            $set = array();
            foreach ($data as $field => $value) {
                if ((string) ($item[$field] ?? '') !== (string) ($value ?? '')) {
                    $set[$field] = $value;
                }
            }

            if (count($set) > 0) {
                $this->update_columns((int) $item['id'], $set, $user_id, $now);
                foreach ($set as $field => $value) {
                    $this->history($item['company_id'], $item['assessment_id'], (int) $item['id'], null, $change_source, $field, $item[$field] ?? null, $value, '', $user_id, $now);
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return count($set);
    }

    /** Confirms closure; false when the record is gone or already closed. */
    public function close_item($item_id, $company_id, $closure_note, $closure_reason, $closure_result, $closure_override, $user_id)
    {
        $now = date('Y-m-d H:i:s');
        $data = array(
            'poam_status' => 'Closed',
            'closure_note' => $closure_note,
            'closure_reason' => $closure_reason === '' ? null : $closure_reason,
            'closure_result' => $closure_result,
            'closure_override' => $closure_override ? 1 : 0,
            'closed_by' => $user_id,
            'date_closed' => $now
        );
        $note = $closure_override ? 'Closed with override: the assessment records '.($closure_result !== '' ? $closure_result : 'no result').'.' : 'Closure confirmed.';

        $this->db->beginTransaction();

        try {
            $item = $this->locked_item($item_id, $company_id);

            if ($item === null || $item['poam_status'] === 'Closed') {
                $this->db->rollBack();
                return false;
            }

            $this->update_columns((int) $item['id'], $data, $user_id, $now);

            foreach ($data as $field => $value) {
                $this->history($item['company_id'], $item['assessment_id'], (int) $item['id'], null, 'Closure', $field, $item[$field] ?? null, $value, $note, $user_id, $now);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Reopens the latest closed record of a control as a new record (new
     * reference, linked through reopened_from_id) carrying over the snapshot
     * and the management fields; the closed record stays as it was. Returns
     * array(id, poam_ref) or null.
     */
    public function reopen_item($item_id, $company_id, $reopen_note, $user_id)
    {
        $now = date('Y-m-d H:i:s');
        $carried = array(
            'source_item_id',
            'source_ref',
            'source_title',
            'source_family',
            'source_result',
            'source_open_objectives',
            'source_weakness',
            'source_action',
            'source_priority',
            'source_priority_basis',
            'source_initiative_ref',
            'source_initiative_title',
            'source_eligibility',
            'source_eligibility_note',
            'source_points',
            'source_hash',
            'source_date_updated',
            'weakness_override',
            'action_override',
            'priority_override',
            'owner_name',
            'resources',
            'detection_date',
            'scheduled_completion_date',
            'comments',
            'risk_rating',
            'vendor_dependency',
            'vendor_product',
            'vendor_checkin_date',
            'deviation_type',
            'deviation_rationale'
        );

        $this->db->beginTransaction();

        try {
            $rows = $this->get_item($item_id, $company_id);

            if (count($rows) !== 1 || !$this->lock_assessment($rows[0]['assessment_id'], $company_id)) {
                $this->db->rollBack();
                return null;
            }

            $item = $this->locked_item($item_id, $company_id);
            $later = $this->db->prepare("SELECT id FROM poam_items WHERE assessment_id = :assessment_id and source_ref = :source_ref and poam_seq > :poam_seq and deleted = 0");
            $later->execute(array(
                'assessment_id' => $item['assessment_id'],
                'source_ref' => $item['source_ref'],
                'poam_seq' => $item['poam_seq']
            ));

            if ($item['poam_status'] !== 'Closed' || $later->fetch() !== false) {
                $this->db->rollBack();
                return null;
            }

            $values = array();
            foreach ($carried as $field) {
                $values[$field] = $item[$field];
            }

            $new = $this->insert_record($item['assessment_id'], $item['company_id'], $user_id, $values, (int) $item['id'], $now);
            $note = 'Reopened from '.$item['poam_ref'].': '.$reopen_note;
            $this->history($item['company_id'], $item['assessment_id'], $new['id'], null, 'Reopen', 'poam_ref', null, $new['poam_ref'], $note, $user_id, $now);
            $this->history($item['company_id'], $item['assessment_id'], (int) $item['id'], null, 'Reopen', 'reopened_as', null, $new['poam_ref'], $note, $user_id, $now);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $new;
    }

    /**
     * Adds ($milestone_id 0) or updates a milestone of an open record, with a
     * history row for each change. Returns the milestone id, or false.
     */
    public function save_milestone($item_id, $company_id, $milestone_id, $milestone_text, $due_date, $completed_date, $milestone_status, $change_source, $user_id)
    {
        $now = date('Y-m-d H:i:s');
        $data = array(
            'milestone_text' => $milestone_text,
            'due_date' => $due_date,
            'completed_date' => $completed_date,
            'milestone_status' => $milestone_status
        );

        $this->db->beginTransaction();

        try {
            $item = $this->locked_item($item_id, $company_id);

            if ($item === null || $item['poam_status'] === 'Closed') {
                $this->db->rollBack();
                return false;
            }

            if ((int) $milestone_id === 0) {
                $order = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM poam_milestones WHERE poam_item_id = :item_id");
                $order->execute(array(
                    'item_id' => $item['id']
                ));
                $milestone_id = (int) parent::insert('poam_milestones', array_merge($data, array(
                    'company_id' => $item['company_id'],
                    'poam_item_id' => $item['id'],
                    'sort_order' => (int) $order->fetch(PDO::FETCH_ASSOC)['next_order'],
                    'created_by' => $user_id,
                    'updated_by' => $user_id,
                    'date_created' => $now,
                    'date_updated' => $now,
                    'deleted' => 0
                )));
                $this->history($item['company_id'], $item['assessment_id'], (int) $item['id'], $milestone_id, $change_source, 'milestone_added', null, $milestone_text, 'Milestone added.', $user_id, $now);
            } else {
                $existing = parent::select("SELECT * FROM poam_milestones WHERE id = :id and poam_item_id = :item_id and deleted = 0", array(
                    'id' => $milestone_id,
                    'item_id' => $item['id']
                ));
                if (count($existing) !== 1) {
                    $this->db->rollBack();
                    return false;
                }
                $set = array();
                foreach ($data as $field => $value) {
                    if ((string) ($existing[0][$field] ?? '') !== (string) ($value ?? '')) {
                        $set[$field] = $value;
                    }
                }
                if (count($set) > 0) {
                    parent::update('poam_milestones', array_merge($set, array(
                        'updated_by' => $user_id,
                        'date_updated' => $now
                    )), 'id = :milestone_id', array(
                        'milestone_id' => $milestone_id
                    ));
                    foreach ($set as $field => $value) {
                        $this->history($item['company_id'], $item['assessment_id'], (int) $item['id'], (int) $milestone_id, $change_source, $field, $existing[0][$field] ?? null, $value, 'Milestone updated.', $user_id, $now);
                    }
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return (int) $milestone_id;
    }

    /** Removes a milestone of an open record (soft delete, logged); false when not possible. */
    public function delete_milestone($milestone_id, $company_id, $change_source, $user_id)
    {
        $now = date('Y-m-d H:i:s');
        $milestone = $this->get_milestone($milestone_id, $company_id);

        if (count($milestone) !== 1) {
            return false;
        }

        $this->db->beginTransaction();

        try {
            $item = $this->locked_item($milestone[0]['poam_item_id'], $company_id);

            if ($item === null || $item['poam_status'] === 'Closed') {
                $this->db->rollBack();
                return false;
            }

            $rows = parent::update('poam_milestones', array(
                'deleted' => 1,
                'updated_by' => $user_id,
                'date_updated' => $now
            ), 'id = :milestone_id and deleted = 0', array(
                'milestone_id' => $milestone_id
            ));

            if ($rows === 1) {
                $this->history($item['company_id'], $item['assessment_id'], (int) $item['id'], (int) $milestone_id, $change_source, 'milestone_removed', $milestone[0]['milestone_text'], null, 'Milestone removed.', $user_id, $now);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $rows === 1;
    }

    /** Links a vault file to an open record in a role; an identical link is kept. Returns the link id or false. */
    public function link_evidence($item_id, $company_id, $evidence_id, $evidence_title, $evidence_role, $user_id)
    {
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();

        try {
            $item = $this->locked_item($item_id, $company_id);

            if ($item === null || $item['poam_status'] === 'Closed') {
                $this->db->rollBack();
                return false;
            }

            $existing = parent::select("SELECT id FROM poam_evidence WHERE poam_item_id = :item_id and evidence_id = :evidence_id and evidence_role = :evidence_role and deleted = 0", array(
                'item_id' => $item['id'],
                'evidence_id' => $evidence_id,
                'evidence_role' => $evidence_role
            ));

            if (count($existing) > 0) {
                $this->db->commit();
                return (int) $existing[0]['id'];
            }

            $link_id = (int) parent::insert('poam_evidence', array(
                'company_id' => $item['company_id'],
                'poam_item_id' => $item['id'],
                'evidence_id' => $evidence_id,
                'evidence_role' => $evidence_role,
                'linked_by' => $user_id,
                'date_created' => $now,
                'deleted' => 0
            ));
            $this->history($item['company_id'], $item['assessment_id'], (int) $item['id'], null, 'User', 'evidence_linked', null, $evidence_role.': '.$evidence_title, $evidence_role.' evidence linked.', $user_id, $now);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $link_id;
    }

    /** Removes an evidence link of an open record (soft delete, logged); false when not possible. */
    public function unlink_evidence($link_id, $company_id, $user_id)
    {
        $now = date('Y-m-d H:i:s');
        $link = $this->get_evidence_link($link_id, $company_id);

        if (count($link) !== 1) {
            return false;
        }

        $this->db->beginTransaction();

        try {
            $item = $this->locked_item($link[0]['poam_item_id'], $company_id);

            if ($item === null || $item['poam_status'] === 'Closed') {
                $this->db->rollBack();
                return false;
            }

            $rows = parent::update('poam_evidence', array(
                'deleted' => 1
            ), 'id = :link_id and deleted = 0', array(
                'link_id' => $link_id
            ));

            if ($rows === 1) {
                $this->history($item['company_id'], $item['assessment_id'], (int) $item['id'], null, 'User', 'evidence_unlinked', $link[0]['evidence_role'].': '.$link[0]['evidence_title'], null, $link[0]['evidence_role'].' evidence removed.', $user_id, $now);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $rows === 1;
    }

    /** Sets the CMMC Conditional status date; logged against the assessment (record 0). */
    public function save_clock_date($assessment_id, $company_id, $poam_clock_date, $user_id)
    {
        $now = date('Y-m-d H:i:s');
        $old = $this->clock_date($assessment_id, $company_id);

        if ((string) ($old ?? '') === (string) ($poam_clock_date ?? '')) {
            return 0;
        }

        $this->db->beginTransaction();

        try {
            $rows = parent::update('assessments', array(
                'poam_clock_date' => $poam_clock_date,
                'updated_by' => $user_id,
                'date_updated' => $now
            ), 'id = :assessment_id and company_id = :company_id and deleted = 0', array(
                'assessment_id' => $assessment_id,
                'company_id' => $company_id
            ));
            $this->history($company_id, $assessment_id, 0, null, 'User', 'poam_clock_date', $old, $poam_clock_date, 'Conditional status date recorded.', $user_id, $now);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $rows;
    }

}
