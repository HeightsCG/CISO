<?php
/**
 * Preview and Download through background Report jobs: no web request
 * renders a report, a stored report is reused only for the exact state it
 * was rendered from, matching jobs are shared, the number of jobs under way
 * per assessment is capped, and the Reports tab waits on the job through
 * ApiDataSvc and shows the job's own failure message.
 */
require_once ROOT.'/libs/Classes/Main.php';
require_once ROOT.'/libs/Classes/S3Service.php';
require_once ROOT.'/libs/Classes/AiAssistant.php';
require_once ROOT.'/libs/Classes/ReportAI.php';
require_once ROOT.'/libs/Classes/ReportQuality.php';
require_once ROOT.'/libs/Classes/ReportNarratives.php';
require_once ROOT.'/libs/Classes/ReportStates.php';
require_once ROOT.'/libs/Classes/ReportJobs.php';

$source = function (string $relative) {
    return (string) file_get_contents(ROOT.'/'.$relative);
};

$job = function (int $id, string $job_status, ?string $fingerprint, ?string $result_key = null) {
    $options = array(
        'report_type' => 'risk_assessment',
        'format' => 'pdf',
        'detail' => null,
        'appendices' => null
    );
    if ($fingerprint !== null) {
        $options['fingerprint'] = $fingerprint;
    }
    return array(
        'id' => $id,
        'job_status' => $job_status,
        'options' => json_encode($options),
        'result_key' => $result_key,
        'result_name' => $result_key !== null ? 'Report.pdf' : null,
        'date_created' => date('Y-m-d H:i:s')
    );
};

$parts = function () {
    return array(
        'options' => array(
            'report_type' => 'poam',
            'format' => 'pdf',
            'detail' => 'standard',
            'appendices' => true
        ),
        'document' => array(
            'report_status' => 'Draft',
            'report_version' => '0.3',
            'changed_since_final' => false,
            'approved_by' => '',
            'approved_date' => null,
            'narrative_mode' => 'draft'
        ),
        'analysis' => hash('sha256', 'analysis'),
        'narratives' => hash('sha256', 'narratives'),
        'records' => array(
            array(
                'assessment_updated' => '2026-11-05 10:00:00',
                'items_updated' => '2026-11-05 10:00:00',
                'items_count' => 120
            )
        ),
        'poam' => array(
            array(
                'history_id' => 44,
                'items_count' => 9
            )
        ),
        'day' => '2026-11-05',
        'code' => '1762300000:120'
    );
};

return array(

    'a completed job with the same fingerprint is reused' => function () use ($job) {
        $match = ReportJobs::reusable_report(array(
            $job(9, 'Completed', 'bbb', 'reports/1/ready/9-x.pdf'),
            $job(8, 'Completed', 'aaa', 'reports/1/ready/8-x.pdf'),
            $job(7, 'Completed', 'aaa', 'reports/1/ready/7-x.pdf')
        ), 'aaa');
        Assert::same(8, $match['cached']['id'], 'the newest matching stored report');
        Assert::same(null, $match['active'], 'nothing under way');
    },

    'a report rendered while the state moved is never reused' => function () use ($job) {
        $match = ReportJobs::reusable_report(array(
            $job(5, 'Completed', null, 'reports/1/ready/5-x.pdf')
        ), 'aaa');
        Assert::same(null, $match['cached'], 'a job recorded without a fingerprint never matches');
    },

    'a completed job without a stored file is not a cache hit' => function () use ($job) {
        $match = ReportJobs::reusable_report(array(
            $job(5, 'Completed', 'aaa', null)
        ), 'aaa');
        Assert::same(null, $match['cached'], 'no result_key, no reuse');
    },

    'a matching job under way is shared instead of queuing another' => function () use ($job) {
        $match = ReportJobs::reusable_report(array(
            $job(12, 'Running', 'aaa'),
            $job(11, 'Queued', 'bbb')
        ), 'aaa');
        Assert::same(12, $match['active']['id'], 'the running job for the same state');
        Assert::same(2, $match['active_count'], 'both unfinished jobs count toward the limit');
    },

    'only unfinished jobs count toward the limit' => function () use ($job) {
        $match = ReportJobs::reusable_report(array(
            $job(3, 'Queued', 'x'),
            $job(2, 'Running', 'y'),
            $job(1, 'Completed', 'z', 'k')
        ), 'aaa');
        Assert::same(2, $match['active_count'], 'two under way');
        Assert::true(ReportJobs::REPORT_ACTIVE_LIMIT >= 2, 'the limit allows a preview and a download together');
    },

    'report options keep known details and leave the rest to the report defaults' => function () {
        $options = ReportJobs::report_options('risk_assessment', 'pdf', 'comprehensive', false);
        Assert::same('comprehensive', $options['detail'], 'a known detail level is kept');
        Assert::same(false, $options['appendices'], 'appendices off is kept');
        $options = ReportJobs::report_options('risk_assessment', 'pdf', 'everything', null);
        Assert::same(null, $options['detail'], 'an unknown detail level falls back to the default');
        Assert::same(null, $options['appendices'], 'appendices not asked for stay unset');
    },

    'the fingerprint changes with every part a report depends on' => function () use ($parts) {
        $base = ReportJobs::fingerprint($parts());
        Assert::same($base, ReportJobs::fingerprint($parts()), 'the same state gives the same fingerprint');

        $changes = array(
            'format' => function (array $p) {
                $p['options']['format'] = 'docx';
                return $p;
            },
            'detail' => function (array $p) {
                $p['options']['detail'] = 'executive';
                return $p;
            },
            'appendices' => function (array $p) {
                $p['options']['appendices'] = false;
                return $p;
            },
            'document status' => function (array $p) {
                $p['document']['report_status'] = 'Final';
                return $p;
            },
            'document version' => function (array $p) {
                $p['document']['report_version'] = '0.4';
                return $p;
            },
            'assessment analysis' => function (array $p) {
                $p['analysis'] = hash('sha256', 'one control result changed');
                return $p;
            },
            'narratives' => function (array $p) {
                $p['narratives'] = hash('sha256', 'a section was edited');
                return $p;
            },
            'records' => function (array $p) {
                $p['records'][0]['items_updated'] = '2026-11-05 10:00:01';
                return $p;
            },
            'POA&M state' => function (array $p) {
                $p['poam'][0]['history_id'] = 45;
                return $p;
            },
            'day' => function (array $p) {
                $p['day'] = '2026-11-06';
                return $p;
            },
            'report code' => function (array $p) {
                $p['code'] = '1762300001:120';
                return $p;
            }
        );

        foreach ($changes as $label => $change) {
            Assert::true(ReportJobs::fingerprint($change($parts())) !== $base, 'a change of '.$label.' gives a new fingerprint');
        }
    },

    'the fingerprint never includes the generation time' => function () use ($source) {
        $code = $source('libs/Classes/ReportJobs.php');
        Assert::true(strpos($code, "unset(\$document['generated']);") !== false, 'the document time is dropped before hashing');
        Assert::true(strpos($code, "unset(\$analysis['periods']['cutoff']);") !== false, 'the analysis cutoff clock is dropped before hashing');
    },

    'the report code version covers the report classes and framework definitions' => function () {
        $version = ReportJobs::code_version();
        Assert::true((bool) preg_match('/^\d+:\d+$/', $version), 'newest mtime and file count');
        Assert::true((int) explode(':', $version)[1] > 20, 'the report classes and frameworks are counted');
        Assert::true(in_array('frameworks/*.json', ReportJobs::REPORT_CODE, true), 'framework definitions are part of it');
    },

    'the preview request queues a job and never renders' => function () use ($source) {
        $api = $source('app/controllers/ApiController.php');
        $start = strpos($api, 'public function ready_report_downloadAction()');
        $end = strpos($api, 'public function', $start + 10);
        $action = substr($api, $start, $end - $start);
        Assert::true(strpos($action, 'ReportJobs::dispatch_report(') !== false, 'the action dispatches a Report job');
        Assert::true(strpos($action, '->render(') === false, 'the action does not render');
        Assert::true(strpos($action, 'owns_assessment($target_id)') !== false, 'the assessment must belong to the signed-in company');
        Assert::true(strpos($action, "Session::get('user_type') !== 'staff'") !== false, 'staff only');
    },

    'a completed Report job hands back a short-lived link only through the authorized status action' => function () use ($source) {
        $api = $source('app/controllers/ApiController.php');
        $start = strpos($api, 'public function report_job_statusAction()');
        $end = strpos($api, 'public function', $start + 10);
        $action = substr($api, $start, $end - $start);
        Assert::true(strpos($action, '$this->report_job(') !== false, 'the job is read through the company and assessment check');
        Assert::true(strpos($action, 'ReportJobs::REPORT') !== false, 'Report jobs get a download link');
        Assert::true(strpos($action, 'ReportJobs::DOWNLOAD_TTL') !== false, 'with the short TTL');
    },

    'the worker records no fingerprint when the state moved during rendering' => function () use ($source) {
        $code = $source('libs/Classes/ReportJobs.php');
        Assert::true(strpos($code, "'fingerprint' => \$after !== null && hash_equals(\$before, \$after) ? \$before : null") !== false, 'before and after must agree');
    },

    'a long render keeps its heartbeat so no second worker claims the job' => function () use ($source) {
        $code = $source('libs/Classes/ReportJobs.php');
        $run = substr($code, strpos($code, 'public function run(int $job_id)'));
        Assert::true(strpos($run, 'self::start_beater($job_id);') !== false, 'the worker starts the heartbeat helper once it holds the job');
        Assert::true(strpos($code, "posix_kill(\$pid, 0) && \$model->get_job_status(\$job_id) === 'Running'") !== false, 'the helper stops when the worker dies or the job ends');
        Assert::same(2, ReportJobs::keep_beating(0, 0), 'the helper refuses without a job and a worker');
        Assert::true(strpos($source('bin/report-worker.php'), "'beat:'") !== false, 'the worker script accepts --beat');
    },

    'the Reports tab waits on the job through ApiDataSvc and shows its failure' => function () use ($source) {
        $view = $source('app/views/assessments/detail.php');
        Assert::true(strpos($view, "ApiDataSvc.apiCall('post', 'ready_report_download'") !== false, 'the request goes through ApiDataSvc');
        Assert::true(strpos($view, "ApiDataSvc.apiCall('post', 'report_job_status'") !== false, 'the job is polled through ApiDataSvc');
        Assert::true(strpos($view, 'failed(obj.job.last_error') !== false, 'the job message reaches toastr');
        Assert::true(strpos($view, '$_GET') === false, 'no query-string reads');
    }
);
