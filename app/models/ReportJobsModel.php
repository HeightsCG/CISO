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

    public function save_progress($job_id, $steps, $total_steps, $done_steps, $current_step)
    {
        $data = array(
            'steps' => $steps,
            'total_steps' => $total_steps,
            'done_steps' => $done_steps,
            'current_step' => $current_step,
            'heartbeat' => date('Y-m-d H:i:s')
        );
        $where = array(
            'id' => $job_id
        );
        return parent::update('report_jobs', $data, 'id = :id', $where);
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

    /** Close a running job; a job cancelled meanwhile keeps its Cancelled status. */
    public function finish_job($job_id, $job_status, $last_error, $result_key, $result_name)
    {
        $data = array(
            'job_status' => $job_status,
            'last_error' => $last_error,
            'result_key' => $result_key,
            'result_name' => $result_name,
            'current_step' => null,
            'heartbeat' => date('Y-m-d H:i:s'),
            'date_finished' => date('Y-m-d H:i:s')
        );
        $where = array(
            'id' => $job_id
        );
        return parent::update('report_jobs', $data, "id = :id and job_status = 'Running'", $where);
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
