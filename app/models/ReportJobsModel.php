<?php
/**
 * Background report jobs (AI narratives, report packages). The web request
 * only queues a row; bin/report-worker.php claims and runs it. Every query
 * the web side makes is scoped to the company; the worker reads by id.
 */
class ReportJobsModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function create_job($company_id, $assessment_id, $job_type, $options, $steps, $total_steps, $created_by)
    {
        $data = array(
            'company_id' => $company_id,
            'assessment_id' => $assessment_id,
            'job_type' => $job_type,
            'job_status' => 'Queued',
            'options' => $options,
            'steps' => $steps,
            'total_steps' => $total_steps,
            'done_steps' => 0,
            'attempts' => 0,
            'created_by' => $created_by,
            'date_created' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );
        return parent::insert('report_jobs', $data);
    }

    public function get_job($job_id, $company_id)
    {
        $where = array(
            'id' => $job_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    *
                FROM
                    report_jobs
                WHERE
                    id = :id
                    and
                    company_id = :company_id
                    and
                    deleted = 0";
        return parent::select($sql, $where);
    }

    /** The worker's read: by id alone, since it runs outside any session. */
    public function get_job_for_worker($job_id)
    {
        $where = array(
            'id' => $job_id
        );
        $sql = "SELECT
                    *
                FROM
                    report_jobs
                WHERE
                    id = :id
                    and
                    deleted = 0";
        return parent::select($sql, $where);
    }

    public function get_job_status($job_id)
    {
        $where = array(
            'id' => $job_id
        );
        $sql = "SELECT
                    job_status
                FROM
                    report_jobs
                WHERE
                    id = :id";
        $rows = parent::select($sql, $where);
        return count($rows) === 1 ? $rows[0]['job_status'] : null;
    }

    /** The Queued or Running job of this type for the assessment, if any. */
    public function active_job($assessment_id, $company_id, $job_type)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id,
            'job_type' => $job_type
        );
        $sql = "SELECT
                    id,
                    job_status
                FROM
                    report_jobs
                WHERE
                    assessment_id = :assessment_id
                    and
                    company_id = :company_id
                    and
                    job_type = :job_type
                    and
                    job_status IN ('Queued', 'Running')
                    and
                    deleted = 0
                ORDER BY
                    id DESC
                LIMIT 1";
        return parent::select($sql, $where);
    }

    public function latest_job($assessment_id, $company_id, $job_type)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id,
            'job_type' => $job_type
        );
        $sql = "SELECT
                    *
                FROM
                    report_jobs
                WHERE
                    assessment_id = :assessment_id
                    and
                    company_id = :company_id
                    and
                    job_type = :job_type
                    and
                    deleted = 0
                ORDER BY
                    id DESC
                LIMIT 1";
        return parent::select($sql, $where);
    }

    /** Today's single-report jobs of an assessment that may still be reused: queued, running or completed. */
    public function report_jobs_since($assessment_id, $company_id, $since)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id,
            'since' => $since
        );
        $sql = "SELECT
                    id,
                    job_status,
                    options,
                    result_key,
                    result_name,
                    date_created
                FROM
                    report_jobs
                WHERE
                    assessment_id = :assessment_id
                    and
                    company_id = :company_id
                    and
                    job_type = 'Report'
                    and
                    job_status IN ('Queued', 'Running', 'Completed')
                    and
                    date_created >= :since
                    and
                    deleted = 0
                ORDER BY
                    id DESC
                LIMIT 200";
        return parent::select($sql, $where);
    }

    /**
     * The record state a report stands on beyond the assessment analysis:
     * the assessment, client and company rows and the client's evidence
     * library. Any edit moves a date or a count.
     */
    public function report_watermark($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.date_updated AS assessment_updated,
                    c.date_updated AS client_updated,
                    co.date_updated AS company_updated,
                    (SELECT MAX(ai.date_updated) FROM assessment_items ai WHERE ai.assessment_id = a.id) AS items_updated,
                    (SELECT COUNT(*) FROM assessment_items ai WHERE ai.assessment_id = a.id and ai.deleted = 0) AS items_count,
                    (SELECT MAX(e.date_updated) FROM evidence e WHERE e.client_id = a.client_id and e.company_id = a.company_id) AS evidence_updated,
                    (SELECT COUNT(*) FROM evidence e WHERE e.client_id = a.client_id and e.company_id = a.company_id and e.deleted = 0) AS evidence_count
                FROM
                    assessments a
                    LEFT JOIN clients c ON c.id = a.client_id
                    LEFT JOIN companies co ON co.id = a.company_id
                WHERE
                    a.id = :assessment_id
                    and
                    a.company_id = :company_id";
        return parent::select($sql, $where);
    }

    /** The POA&M register's state: every change writes history, and items, milestones and evidence links carry dates and ids. */
    public function poam_watermark($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.poam_clock_date,
                    (SELECT MAX(h.id) FROM poam_history h WHERE h.assessment_id = a.id and h.company_id = a.company_id) AS history_id,
                    (SELECT COUNT(*) FROM poam_history h WHERE h.assessment_id = a.id and h.company_id = a.company_id) AS history_count,
                    (SELECT MAX(i.date_updated) FROM poam_items i WHERE i.assessment_id = a.id and i.company_id = a.company_id) AS items_updated,
                    (SELECT COUNT(*) FROM poam_items i WHERE i.assessment_id = a.id and i.company_id = a.company_id and i.deleted = 0) AS items_count,
                    (SELECT MAX(m.date_updated) FROM poam_milestones m JOIN poam_items i ON i.id = m.poam_item_id WHERE i.assessment_id = a.id and m.company_id = a.company_id) AS milestones_updated,
                    (SELECT COUNT(*) FROM poam_milestones m JOIN poam_items i ON i.id = m.poam_item_id WHERE i.assessment_id = a.id and m.company_id = a.company_id and m.deleted = 0) AS milestones_count,
                    (SELECT MAX(pe.id) FROM poam_evidence pe JOIN poam_items i ON i.id = pe.poam_item_id WHERE i.assessment_id = a.id and pe.company_id = a.company_id) AS evidence_id,
                    (SELECT COUNT(*) FROM poam_evidence pe JOIN poam_items i ON i.id = pe.poam_item_id WHERE i.assessment_id = a.id and pe.company_id = a.company_id and pe.deleted = 0) AS evidence_count
                FROM
                    assessments a
                WHERE
                    a.id = :assessment_id
                    and
                    a.company_id = :company_id";
        return parent::select($sql, $where);
    }

    /** Replace a job's options; the worker records the state its report was rendered from. */
    public function set_options($job_id, $options)
    {
        $data = array(
            'options' => $options
        );
        $where = array(
            'id' => $job_id
        );
        return parent::update('report_jobs', $data, "id = :id", $where);
    }

    public function recent_jobs($company_id, $job_type, $limit)
    {
        $where = array(
            'company_id' => $company_id,
            'job_type' => $job_type
        );
        $sql = "SELECT
                    steps
                FROM
                    report_jobs
                WHERE
                    company_id = :company_id
                    and
                    job_type = :job_type
                    and
                    deleted = 0
                ORDER BY
                    id DESC
                LIMIT ".(int) $limit;
        return parent::select($sql, $where);
    }

    /** Jobs a drain run should take: Queued ones, and Running ones that stopped beating. */
    public function runnable_jobs($stale_before, $limit)
    {
        $where = array(
            'stale_before' => $stale_before
        );
        $sql = "SELECT
                    id
                FROM
                    report_jobs
                WHERE
                    deleted = 0
                    and
                    (
                        job_status = 'Queued'
                        or
                        (job_status = 'Running' and (heartbeat IS NULL or heartbeat < :stale_before))
                    )
                ORDER BY
                    id
                LIMIT ".(int) $limit;
        return parent::select($sql, $where);
    }

    /**
     * Take a job for this worker. Only one claimant can win: the row must still
     * be Queued, or Running with a heartbeat older than $stale_before.
     */
    public function claim_job($job_id, $attempts, $stale_before)
    {
        $now = date('Y-m-d H:i:s');
        $data = array(
            'job_status' => 'Running',
            'attempts' => $attempts,
            'heartbeat' => $now,
            'date_started' => $now
        );
        $where = array(
            'id' => $job_id,
            'stale_before' => $stale_before
        );
        return parent::update('report_jobs', $data, "id = :id and deleted = 0 and (job_status = 'Queued' or (job_status = 'Running' and (heartbeat IS NULL or heartbeat < :stale_before)))", $where);
    }

    /**
     * Save the worker's steps. Steps another request appended since the worker
     * last read them are kept and returned, so the worker can run them too.
     */
    public function save_progress($job_id, $steps, $current_step)
    {
        $this->db->beginTransaction();

        try {
            $lock = $this->db->prepare("SELECT steps FROM report_jobs WHERE id = :id FOR UPDATE");
            $lock->execute(array(
                'id' => $job_id
            ));
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            $stored = $row ? (json_decode((string) $row['steps'], true) ?: array()) : array();

            for ($i = count($steps); $i < count($stored); $i++) {
                $steps[] = $stored[$i];
            }

            $done = count(array_filter($steps, function ($step) {
                return in_array($step['state'], array(
                    'Done',
                    'Skipped',
                    'Failed'
                ), true);
            }));

            $update = $this->db->prepare("UPDATE report_jobs SET steps = :steps, total_steps = :total_steps, done_steps = :done_steps, current_step = :current_step, heartbeat = :heartbeat WHERE id = :id");
            $update->execute(array(
                'steps' => json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'total_steps' => count($steps),
                'done_steps' => $done,
                'current_step' => $current_step,
                'heartbeat' => date('Y-m-d H:i:s'),
                'id' => $job_id
            ));

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $steps;
    }

    /**
     * Add steps to a queued or running job. Returns false when the job has
     * already finished, so the caller starts a new one instead.
     */
    public function append_steps($job_id, $new_steps)
    {
        $this->db->beginTransaction();

        try {
            $lock = $this->db->prepare("SELECT steps FROM report_jobs WHERE id = :id and job_status IN ('Queued', 'Running') and deleted = 0 FOR UPDATE");
            $lock->execute(array(
                'id' => $job_id
            ));
            $row = $lock->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                $this->db->rollBack();
                return false;
            }

            $steps = json_decode((string) $row['steps'], true) ?: array();

            foreach ($new_steps as $step) {
                $steps[] = $step;
            }

            $update = $this->db->prepare("UPDATE report_jobs SET steps = :steps, total_steps = :total_steps WHERE id = :id");
            $update->execute(array(
                'steps' => json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'total_steps' => count($steps),
                'id' => $job_id
            ));

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return true;
    }

    public function beat($job_id)
    {
        $data = array(
            'heartbeat' => date('Y-m-d H:i:s')
        );
        $where = array(
            'id' => $job_id
        );
        return parent::update('report_jobs', $data, "id = :id and job_status = 'Running'", $where);
    }

    /**
     * Close a running job; a job cancelled meanwhile keeps its Cancelled status.
     * Returns false without closing when steps were appended after the worker
     * last saved, so the worker runs them first.
     */
    public function finish_job($job_id, $job_status, $last_error, $result_key, $result_name, $total_steps)
    {
        $this->db->beginTransaction();

        try {
            $lock = $this->db->prepare("SELECT steps FROM report_jobs WHERE id = :id FOR UPDATE");
            $lock->execute(array(
                'id' => $job_id
            ));
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            $stored = $row ? (json_decode((string) $row['steps'], true) ?: array()) : array();

            if (count($stored) > $total_steps) {
                $this->db->rollBack();
                return false;
            }

            $update = $this->db->prepare("UPDATE report_jobs SET job_status = :job_status, last_error = :last_error, result_key = :result_key, result_name = :result_name, current_step = NULL, heartbeat = :heartbeat, date_finished = :date_finished WHERE id = :id and job_status = 'Running'");
            $update->execute(array(
                'job_status' => $job_status,
                'last_error' => $last_error,
                'result_key' => $result_key,
                'result_name' => $result_name,
                'heartbeat' => date('Y-m-d H:i:s'),
                'date_finished' => date('Y-m-d H:i:s'),
                'id' => $job_id
            ));

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return true;
    }

    /** Fail a job that never started properly (no worker could run it, or it kept dying). */
    public function fail_job($job_id, $last_error)
    {
        $data = array(
            'job_status' => 'Failed',
            'last_error' => $last_error,
            'current_step' => null,
            'date_finished' => date('Y-m-d H:i:s')
        );
        $where = array(
            'id' => $job_id
        );
        return parent::update('report_jobs', $data, "id = :id and job_status IN ('Queued', 'Running')", $where);
    }

    public function cancel_job($job_id, $company_id)
    {
        $data = array(
            'job_status' => 'Cancelled',
            'current_step' => null,
            'date_finished' => date('Y-m-d H:i:s')
        );
        $where = array(
            'id' => $job_id,
            'company_id' => $company_id
        );
        return parent::update('report_jobs', $data, "id = :id and company_id = :company_id and job_status IN ('Queued', 'Running')", $where);
    }
}
