<?php
/**
 * Background report jobs. A web request never generates narratives or
 * packages itself: dispatch() stores a Queued row in report_jobs and spawns a
 * detached CLI worker (bin/report-worker.php --job=<id>), then returns. A cron
 * entry (--drain) runs anything a spawn missed and recovers jobs whose worker
 * stopped beating for five minutes.
 *
 * A job is a list of steps with persistent progress. Each step is tried up to
 * four times (backoff 5s, 20s, 60s) when its failure is retryable; a step
 * that still fails is marked Failed and the job carries on, ending Failed
 * with the partial results kept. Cancel is checked between steps.
 *
 * Narratives: one step per AI section (sections already current are Skipped
 * without an API call unless the mode forces them), then one quality
 * revision cycle when the review was written in this job (or on request from
 * the stored review): each section with High or Medium issues, from the AI
 * review or from ReportQuality's deterministic checks, is rewritten once with
 * those notes, the executive summary is refreshed when the sections it reads
 * changed, and the review is written again.
 *
 * Package: every report rendered (status-aware) into a ZIP built in
 * sys_get_temp_dir(), stored privately in S3 and the temp files removed;
 * the download is a short-lived presigned URL from report_job_status.
 */
class ReportJobs
{
    const TYPES = array(
        'Narratives',
        'Package'
    );

    const MODES = array(
        'missing_or_stale',
        'all',
        'sections'
    );

    const REVISE = array(
        'auto',
        'stored',
        'none'
    );

    const DETAILS = array(
        'executive',
        'standard',
        'comprehensive'
    );

    const FORMATS = array(
        'pdf',
        'docx',
        'xlsx'
    );

    const PACKAGE_ORDER = array(
        'executive_summary',
        'risk_assessment',
        'gap_analysis',
        'remediation_plan',
        'assessment_results',
        'statement_of_applicability',
        'evidence_register'
    );

    const REVISABLE = array(
        'domain',
        'remediation',
        'patterns',
        'executive'
    );

    /** Sections whose input reads other sections' text: executive reads remediation and patterns. */
    const READS = array(
        'executive' => array(
            'remediation',
            'patterns'
        )
    );

    const BACKOFF = array(
        5,
        20,
        60
    );

    const STALE_SECONDS = 300;

    const MAX_NOTES = 12;

    const MAX_JOB_ATTEMPTS = 3;

    const BEAT_SECONDS = 20;

    const DRAIN_LIMIT = 10;

    const DOWNLOAD_TTL = 300;

    private $model;
    private $generator;
    private $sleep;
    private $job;
    private $steps = array();
    private $last_beat = 0;

    /**
     * $generator is anything with ReportAI's generate() signature (tests pass
     * a fake); $sleep replaces sleep() for the backoff.
     */
    public function __construct($generator = null, ?callable $sleep = null)
    {
        $this->model = new ReportJobsModel();
        $this->generator = $generator;
        $this->sleep = $sleep ?? function (int $seconds) {
            sleep($seconds);
        };
    }

    /* ------------------------------------------------------------ dispatch */

    /** Queue a job and start a worker for it. Returns success with job_id, or a message. */
    public static function dispatch(int $company_id, int $assessment_id, string $job_type, array $options, int $user_id): array
    {
        if (!in_array($job_type, self::TYPES, true)) {
            return self::refuse('Choose Narratives or Package');
        }

        if ($job_type === 'Narratives' && !AiAssistant::configured()) {
            return self::refuse('The AI service is not configured');
        }

        $analysis = (new ReportAnalysis($assessment_id, $company_id))->build();

        if ($analysis === null) {
            return self::refuse('That assessment could not be found');
        }

        $options = self::normalize_options($job_type, $options);

        if (isset($options['error'])) {
            return self::refuse($options['error']);
        }

        $steps = self::plan($job_type, $options, ReportAI::section_keys($analysis));

        if (isset($steps['error'])) {
            return self::refuse($steps['error']);
        }

        $model = new ReportJobsModel();

        if (count($model->active_job($assessment_id, $company_id, $job_type)) > 0) {
            return self::refuse('A '.strtolower($job_type).' job is already running for this assessment');
        }

        $job_id = (int) $model->create_job(
            $company_id,
            $assessment_id,
            $job_type,
            json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            count($steps),
            $user_id
        );

        if (!self::spawn($job_id)) {
            error_log('[report-jobs] job '.$job_id.' queued without a worker; the --drain cron entry will run it');
        }

        return array(
            'success' => true,
            'message' => 'ok',
            'job_id' => $job_id
        );
    }

    /** Options with defaults filled in and everything unknown dropped, or array('error' => ...). */
    public static function normalize_options(string $job_type, array $options): array
    {
        if ($job_type === 'Package') {
            $formats = array_values(array_intersect(self::FORMATS, array_map('strval', (array) ($options['formats'] ?? self::FORMATS))));
            if (count($formats) === 0) {
                return array(
                    'error' => 'Choose at least one format'
                );
            }
            return array(
                'detail' => in_array($options['detail'] ?? '', self::DETAILS, true) ? $options['detail'] : 'standard',
                'appendices' => !in_array($options['appendices'] ?? true, array(
                    false,
                    0,
                    '0',
                    'false'
                ), true),
                'formats' => $formats
            );
        }

        $mode = in_array($options['mode'] ?? '', self::MODES, true) ? $options['mode'] : 'missing_or_stale';
        $sections = array_values(array_unique(array_filter(array_map('strval', (array) ($options['sections'] ?? array())), function ($key) {
            return $key !== '';
        })));

        return array(
            'mode' => $mode,
            'sections' => $mode === 'sections' ? $sections : array(),
            'revise' => in_array($options['revise'] ?? '', self::REVISE, true) ? $options['revise'] : 'auto'
        );
    }

    /** The job's steps before it runs; revision steps are added when the review is in. */
    public static function plan(string $job_type, array $options, array $section_keys): array
    {
        $steps = array();

        if ($job_type === 'Package') {
            foreach (self::PACKAGE_ORDER as $type) {
                $spec = ReadyReports::spec($type);
                if ($spec === null || count(array_intersect($spec['formats'], $options['formats'])) === 0) {
                    continue;
                }
                $steps[] = self::step($type, $spec['name']);
            }
            $steps[] = self::step('package', 'Build and store the package');
            return $steps;
        }

        if ($options['mode'] === 'sections') {
            $unknown = array_diff($options['sections'], $section_keys);
            if (count($unknown) > 0) {
                return array(
                    'error' => 'These sections do not apply to this assessment: '.implode(', ', $unknown)
                );
            }
            if (count($options['sections']) === 0 && $options['revise'] !== 'stored') {
                return array(
                    'error' => 'Choose at least one section'
                );
            }
        }

        foreach ($section_keys as $key) {
            if ($options['mode'] === 'sections' && !in_array($key, $options['sections'], true)) {
                continue;
            }
            $steps[] = self::step($key, ReportAI::label($key));
        }

        return $steps;
    }

    public static function step(string $key, string $label, array $notes = array()): array
    {
        $step = array(
            'key' => $key,
            'label' => $label,
            'state' => 'Pending',
            'attempts' => 0,
            'error' => null,
            'tokens_in' => 0,
            'tokens_out' => 0
        );

        if (count($notes) > 0) {
            $step['notes'] = $notes;
        }

        return $step;
    }

    /**
     * The sections to revise from review and rules issues, in generation
     * order: each section with High or Medium issues gets its notes (at most
     * MAX_NOTES, most severe first); the executive summary is refreshed (no
     * notes) when a section it reads was revised.
     */
    public static function revision_targets(array $issues, array $section_keys): array
    {
        $notes = array();

        foreach ($issues as $issue) {
            $section = (string) ($issue['section'] ?? '');
            $severity = (string) ($issue['severity'] ?? '');
            $description = trim((string) ($issue['description'] ?? ''));
            if (!in_array($severity, array(
                'High',
                'Medium'
            ), true) || $description === '' || !in_array($section, $section_keys, true) || !in_array(ReportAI::kind($section), self::REVISABLE, true)) {
                continue;
            }
            if (count($notes[$section] ?? array()) >= self::MAX_NOTES) {
                continue;
            }
            $tags = array_filter(array(
                $severity,
                ($issue['source'] ?? '') === ReportQuality::SOURCE ? 'automated check' : '',
                str_replace('_', ' ', (string) ($issue['category'] ?? ''))
            ));
            $notes[$section][] = '['.implode(' · ', $tags).'] '.$description;
        }

        $targets = array();
        $revised = array();

        foreach ($section_keys as $key) {
            if (isset($notes[$key])) {
                $targets[] = array(
                    'key' => $key,
                    'notes' => $notes[$key]
                );
                $revised[] = $key;
                continue;
            }
            if (count(array_intersect(self::READS[$key] ?? array(), $revised)) > 0) {
                $targets[] = array(
                    'key' => $key,
                    'notes' => array()
                );
                $revised[] = $key;
            }
        }

        return $targets;
    }

    /** Review issues and rules issues in one list, High before Medium before Low, review first within a severity. */
    public static function revision_issues(array $review_issues, array $rules_issues): array
    {
        $all = array_merge(array_values($review_issues), array_values($rules_issues));
        $order = array_flip(array_keys($all));

        uksort($all, function ($x, $y) use ($all, $order) {
            $rank = (ReportQuality::SEVERITY_RANK[$all[$y]['severity'] ?? 'Low'] ?? 0) <=> (ReportQuality::SEVERITY_RANK[$all[$x]['severity'] ?? 'Low'] ?? 0);
            return $rank !== 0 ? $rank : $order[$x] <=> $order[$y];
        });

        return array_values($all);
    }

    /* ------------------------------------------------------------- workers */

    /**
     * Start a detached worker for one job and return at once. The worker's
     * stdin, stdout and stderr are all redirected, so the shell exits right
     * away and nothing ties the web request to the worker; setsid (when the
     * system has it) puts it in its own session. False when no PHP CLI or
     * exec() is available.
     */
    public static function spawn(int $job_id): bool
    {
        $php = self::php_cli();

        if ($php === null) {
            return false;
        }

        if (!function_exists('exec')) {
            error_log('[report-jobs] exec() is disabled, so no worker can be started for job '.$job_id);
            return false;
        }

        $detach = is_executable('/usr/bin/setsid') ? '/usr/bin/setsid ' : (is_executable('/usr/bin/nohup') ? '/usr/bin/nohup ' : '');
        $command = 'APPLICATION_ENV='.escapeshellarg((string) Main::get_environment())
            .' '.$detach.escapeshellarg($php).' '.escapeshellarg(self::worker_script()).' --job='.$job_id
            .' < /dev/null >> '.escapeshellarg(self::log_file()).' 2>&1 &';

        $output = array();
        $code = 0;
        exec($command, $output, $code);

        if ($code !== 0) {
            error_log('[report-jobs] could not start a worker for job '.$job_id.' (exit '.$code.')');
            return false;
        }

        return true;
    }

    public static function worker_script(): string
    {
        return Main::app_path().'/bin/report-worker.php';
    }

    public static function log_file(): string
    {
        return sys_get_temp_dir().'/ciso-report-worker.log';
    }

    /**
     * The PHP CLI binary: app.ini `php_cli` (environment section, then
     * [global]) when set, else PHP_BINDIR/php, else the running binary when
     * this already is the CLI. Each candidate must be an executable file.
     */
    public static function php_cli(): ?string
    {
        $config = Main::get_config();
        $env = (string) Main::get_environment();
        $configured = (string) ($config[$env]['php_cli'] ?? ($config['global']['php_cli'] ?? ''));
        $candidates = array();

        if ($configured !== '') {
            $candidates[] = $configured;
        }
        $candidates[] = PHP_BINDIR.'/php';
        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            $candidates[] = PHP_BINARY;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
            error_log('[report-jobs] PHP CLI not usable at '.$candidate.' (missing or not executable)'.($candidate === $configured ? '; check php_cli in app.ini' : ''));
        }

        error_log('[report-jobs] no PHP CLI binary found; set php_cli in app.ini. Jobs stay queued until the --drain cron entry runs them');

        return null;
    }

    /** When a job looks abandoned (queued with no worker, or silent past the stale limit), start a worker. */
    public static function nudge(array $job): void
    {
        $queued_since = strtotime((string) $job['date_created']);
        $beat = strtotime((string) ($job['heartbeat'] ?? ''));

        $abandoned = ($job['job_status'] === 'Queued' && $queued_since !== false && time() - $queued_since > 30)
            || ($job['job_status'] === 'Running' && ($beat === false || time() - $beat > self::STALE_SECONDS));

        if ($abandoned) {
            self::spawn((int) $job['id']);
        }
    }

    /** CLI entry: --job=<id> runs one job, --drain runs every runnable job. Returns the exit code. */
    public static function main(array $options): int
    {
        if (isset($options['job'])) {
            $status = (new self())->run((int) $options['job']);
            echo '[report-worker] job '.(int) $options['job'].': '.$status."\n";
            return in_array($status, array(
                'Completed',
                'Cancelled',
                'Busy'
            ), true) ? 0 : 1;
        }

        if (isset($options['drain'])) {
            $count = 0;
            foreach ((new ReportJobsModel())->runnable_jobs(self::stale_before(), self::DRAIN_LIMIT) as $row) {
                $status = (new self())->run((int) $row['id']);
                echo '[report-worker] job '.(int) $row['id'].': '.$status."\n";
                $count++;
            }
            echo '[report-worker] drain finished, '.$count.' job(s)'."\n";
            return 0;
        }

        fwrite(STDERR, 'Usage: php bin/report-worker.php --job=<id> | --drain'."\n");

        return 2;
    }

    public static function stale_before(): string
    {
        return date('Y-m-d H:i:s', time() - self::STALE_SECONDS);
    }

    /* ----------------------------------------------------------------- run */

    /**
     * Claim and run one job. Returns its final status, or 'Busy' when another
     * worker holds it and 'Missing' when there is no such job.
     */
    public function run(int $job_id): string
    {
        $rows = $this->model->get_job_for_worker($job_id);

        if (count($rows) !== 1) {
            return 'Missing';
        }

        $job = $rows[0];

        if (!in_array($job['job_status'], array(
            'Queued',
            'Running'
        ), true)) {
            return $job['job_status'];
        }

        if ((int) $job['attempts'] >= self::MAX_JOB_ATTEMPTS) {
            if ($job['job_status'] === 'Queued' || strtotime((string) $job['heartbeat']) < time() - self::STALE_SECONDS) {
                $this->model->fail_job($job_id, 'The job stopped responding '.self::MAX_JOB_ATTEMPTS.' times and was given up');
                return 'Failed';
            }
            return 'Busy';
        }

        if ($this->model->claim_job($job_id, (int) $job['attempts'] + 1, self::stale_before()) !== 1) {
            return 'Busy';
        }

        set_time_limit(0);
        ignore_user_abort(true);

        $this->job = $job;
        $this->last_beat = time();
        $this->steps = json_decode((string) $job['steps'], true) ?: array();

        foreach ($this->steps as $index => $step) {
            if ($step['state'] === 'Running') {
                $this->steps[$index]['state'] = 'Pending';
            }
        }

        error_log('[report-jobs] job '.$job_id.' ('.$job['job_type'].', assessment '.$job['assessment_id'].') started, attempt '.((int) $job['attempts'] + 1));

        try {
            if ($job['job_type'] === 'Package') {
                $this->run_package();
            } else {
                $this->run_narratives();
            }
        } catch (Throwable $e) {
            error_log('[report-jobs] job '.$job_id.' stopped: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
            $this->finish('Failed', 'The job stopped unexpectedly: '.$e->getMessage(), null, null);
        }

        $status = (string) $this->model->get_job_status($job_id);
        error_log('[report-jobs] job '.$job_id.' finished: '.$status);

        return $status;
    }

    private function options(): array
    {
        return json_decode((string) $this->job['options'], true) ?: array();
    }

    /* ---------------------------------------------------------- narratives */

    private function run_narratives(): void
    {
        $options = self::normalize_options('Narratives', $this->options());
        $assessment_id = (int) $this->job['assessment_id'];
        $company_id = (int) $this->job['company_id'];
        $user_id = (int) $this->job['created_by'];
        $force = $options['mode'] !== 'missing_or_stale';

        if ($this->generator === null) {
            $this->generator = new ReportAI(ReportAI::client(function () {
                $this->beat();
            }));
        }

        $planned_revision = false;
        $review_written = false;

        foreach ($this->steps as $step) {
            if (self::phase($step['key']) === 'revision') {
                $planned_revision = true;
            }
            if ($step['key'] === 'review' && $step['state'] === 'Done') {
                $review_written = true;
            }
        }

        foreach (array_keys($this->steps) as $index) {
            if ($this->steps[$index]['state'] !== 'Pending' || self::phase($this->steps[$index]['key']) !== 'sections') {
                continue;
            }
            if ($this->cancelled()) {
                return;
            }
            $key = $this->steps[$index]['key'];
            $this->run_section($index, $assessment_id, $company_id, $key, $user_id, $force, array());
            if ($key === 'review' && $this->steps[$index]['state'] === 'Done') {
                $review_written = true;
            }
        }

        $revise = ($options['revise'] === 'auto' && $review_written) || $options['revise'] === 'stored';

        if (!$planned_revision && $revise) {
            $this->plan_revision($assessment_id, $company_id);
        }

        foreach (array_keys($this->steps) as $index) {
            if ($this->steps[$index]['state'] !== 'Pending' || self::phase($this->steps[$index]['key']) !== 'revision') {
                continue;
            }
            if ($this->cancelled()) {
                return;
            }
            $step = $this->steps[$index];
            $key = $step['key'] === 'final_review' ? 'review' : substr($step['key'], strpos($step['key'], ':') + 1);
            $this->run_section($index, $assessment_id, $company_id, $key, $user_id, true, $step['notes'] ?? array());
        }

        $this->finish_from_steps(null, null);
    }

    private static function phase(string $key): string
    {
        if ($key === 'final_review' || strpos($key, 'revise:') === 0 || strpos($key, 'update:') === 0) {
            return 'revision';
        }

        return 'sections';
    }

    /** Add the revision steps from the stored, current review. */
    private function plan_revision(int $assessment_id, int $company_id): void
    {
        $analysis = (new ReportAnalysis($assessment_id, $company_id))->build();
        $states = ReportStates::narrative_states($analysis, $company_id);
        $review = $states['review'] ?? null;

        if ($review === null || $review['state'] !== 'current' || !is_array($review['content'])) {
            error_log('[report-jobs] job '.$this->job['id'].': no current review to revise from');
            return;
        }

        $issues = self::revision_issues($review['content']['issues'] ?? array(), ReportQuality::check($analysis, ReportQuality::usable_sections($states)));
        $targets = self::revision_targets($issues, array_keys($states));

        if (count($targets) === 0) {
            error_log('[report-jobs] job '.$this->job['id'].': neither the review nor the automated checks raised High or Medium issues to revise');
            return;
        }

        foreach ($targets as $target) {
            $prefix = count($target['notes']) > 0 ? 'revise' : 'update';
            $this->steps[] = self::step($prefix.':'.$target['key'], ucfirst($prefix).': '.ReportAI::label($target['key']), $target['notes']);
        }
        $this->steps[] = self::step('final_review', 'Final review');

        $this->save(null);
    }

    private function run_section(int $index, int $assessment_id, int $company_id, string $key, int $user_id, bool $force, array $notes): void
    {
        $result = $this->run_step($index, function () use ($assessment_id, $company_id, $key, $user_id, $force, $notes) {
            return $this->generator->generate($assessment_id, $company_id, $key, $user_id, $force, $notes);
        });

        if ($result === null) {
            return;
        }

        if (!empty($result['success'])) {
            $this->steps[$index]['state'] = !empty($result['cached']) ? 'Skipped' : 'Done';
            $this->steps[$index]['tokens_in'] += (int) ($result['input_tokens'] ?? 0);
            $this->steps[$index]['tokens_out'] += (int) ($result['output_tokens'] ?? 0);
            $this->steps[$index]['error'] = null;
        } elseif (!empty($result['not_applicable'])) {
            $this->steps[$index]['state'] = 'Skipped';
            $this->steps[$index]['error'] = $result['message'];
        } else {
            $this->steps[$index]['state'] = 'Failed';
            $this->steps[$index]['error'] = $result['message'] ?? 'The step failed';
        }

        $this->save(null);
    }

    /* ------------------------------------------------------------- package */

    private function run_package(): void
    {
        $options = self::normalize_options('Package', $this->options());
        $assessment_id = (int) $this->job['assessment_id'];
        $company_id = (int) $this->job['company_id'];
        $dir = sys_get_temp_dir().'/ciso-report-package-'.(int) $this->job['id'].'-'.bin2hex(random_bytes(6));
        $zip_path = $dir.'.zip';
        $entries = array();
        $result_key = null;
        $result_name = null;

        foreach (array_keys($this->steps) as $index) {
            $this->steps[$index]['state'] = 'Pending';
            $this->steps[$index]['error'] = null;
        }

        if (!mkdir($dir, 0700)) {
            $this->finish('Failed', 'A temporary folder for the package could not be created', null, null);
            return;
        }

        try {
            $render_options = array_merge(ReportStates::document_options($assessment_id, $company_id), array(
                'detail' => $options['detail'],
                'appendices' => $options['appendices']
            ));
            $reports = new ReadyReports($company_id);
            $number = 0;

            foreach (array_keys($this->steps) as $index) {
                $key = $this->steps[$index]['key'];
                if ($key === 'package') {
                    continue;
                }
                $number++;
                if ($this->steps[$index]['state'] !== 'Pending') {
                    continue;
                }
                if ($this->cancelled()) {
                    return;
                }
                $position = $number;
                $result = $this->run_step($index, function () use ($reports, $key, $assessment_id, $options, $render_options, $dir, $position) {
                    return self::render_report($reports, $key, $assessment_id, $options['formats'], $render_options, $dir, $position);
                });
                if ($result === null) {
                    return;
                }
                if (!empty($result['success'])) {
                    $entries = array_merge($entries, $result['entries']);
                    $this->steps[$index]['state'] = 'Done';
                    $this->steps[$index]['error'] = null;
                } else {
                    $this->steps[$index]['state'] = 'Failed';
                    $this->steps[$index]['error'] = $result['message'];
                }
                $this->save(null);
            }

            foreach (array_keys($this->steps) as $index) {
                if ($this->steps[$index]['key'] !== 'package') {
                    continue;
                }
                if ($this->cancelled()) {
                    return;
                }
                $result_name = self::package_name($assessment_id, $company_id);
                $result = $this->run_step($index, function () use ($entries, $zip_path, $company_id) {
                    return self::store_package($entries, $zip_path, $company_id, (int) $this->job['id']);
                });
                if ($result === null) {
                    return;
                }
                if (!empty($result['success'])) {
                    $result_key = $result['key'];
                    $this->steps[$index]['state'] = 'Done';
                    $this->steps[$index]['error'] = null;
                } else {
                    $this->steps[$index]['state'] = 'Failed';
                    $this->steps[$index]['error'] = $result['message'];
                }
                $this->save(null);
            }
        } finally {
            foreach ($entries as $entry) {
                if (is_file($entry['path'])) {
                    unlink($entry['path']);
                }
            }
            foreach (glob($dir.'/*') ?: array() as $left) {
                unlink($left);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
            if (is_file($zip_path)) {
                unlink($zip_path);
            }
        }

        $this->finish_from_steps($result_key, $result_key !== null ? $result_name : null);
    }

    /** One report in every requested format it offers, written into the job's temp folder. */
    private static function render_report(ReadyReports $reports, string $type, int $assessment_id, array $formats, array $render_options, string $dir, int $position): array
    {
        $spec = ReadyReports::spec($type);
        $entries = array();

        foreach (array_intersect($formats, $spec['formats']) as $format) {
            $file = $reports->render($type, $assessment_id, $format, $render_options);
            set_time_limit(0);

            if (isset($file['error'])) {
                return array(
                    'success' => false,
                    'message' => $file['error'],
                    'retryable' => false
                );
            }

            $name = sprintf('%02d %s', $position, $file['file_name']);
            $path = $dir.'/'.$position.'-'.$format.'.'.$file['extension'];

            if (file_put_contents($path, $file['bytes']) === false) {
                return array(
                    'success' => false,
                    'message' => 'The '.$spec['name'].' could not be written to the temporary folder',
                    'retryable' => true
                );
            }

            $entries[] = array(
                'path' => $path,
                'name' => $format === 'xlsx' ? 'Excel workbooks/'.$name : $name
            );
        }

        return array(
            'success' => true,
            'entries' => $entries
        );
    }

    /** Zip the rendered files and store the archive privately; the key is returned on success. */
    private static function store_package(array $entries, string $zip_path, int $company_id, int $job_id): array
    {
        if (count($entries) === 0) {
            return array(
                'success' => false,
                'message' => 'No report could be rendered, so there is nothing to package',
                'retryable' => false
            );
        }

        if (!S3Service::configured()) {
            return array(
                'success' => false,
                'message' => 'File storage is not configured, so the package cannot be stored',
                'retryable' => false
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return array(
                'success' => false,
                'message' => 'The package could not be created',
                'retryable' => true
            );
        }

        foreach ($entries as $entry) {
            $zip->addFile($entry['path'], $entry['name']);
        }
        $zip->close();

        $key = 'reports/'.$company_id.'/packages/'.$job_id.'-'.bin2hex(random_bytes(10)).'.zip';
        $bytes = file_get_contents($zip_path);

        if (!is_string($bytes) || !S3Service::put_private_bytes($key, $bytes, 'application/zip')) {
            return array(
                'success' => false,
                'message' => 'The package could not be stored',
                'retryable' => true
            );
        }

        return array(
            'success' => true,
            'key' => $key
        );
    }

    private static function package_name(int $assessment_id, int $company_id): string
    {
        $rows = (new AssessmentsModel())->get_assessment($assessment_id, $company_id);
        $client = ReportAnalysis::plain($rows[0]['client_name'] ?? '');
        $name = ReportAnalysis::plain($rows[0]['assessment_name'] ?? 'Assessment');
        $base = ($client !== '' ? $client.' - ' : '').'Assessment reports - '.$name;

        return preg_replace('/[^A-Za-z0-9 ,.&()\'-]/', '', $base).'.zip';
    }

    /* --------------------------------------------------------------- steps */

    /**
     * Run one step with retries. Returns the step's last result, or null when
     * the job was cancelled while waiting to retry.
     */
    private function run_step(int $index, callable $work): ?array
    {
        $this->steps[$index]['state'] = 'Running';
        $this->steps[$index]['error'] = null;
        $this->save($this->steps[$index]['label']);

        $result = self::attempt($work, $this->sleep, function (int $attempt, array $result) use ($index) {
            $this->steps[$index]['attempts'] = $attempt;
            $this->steps[$index]['error'] = ($result['message'] ?? 'Failed').' (retrying)';
            $this->save($this->steps[$index]['label']);
            return !$this->cancelled();
        });

        $this->steps[$index]['attempts'] = $result['attempts'];

        if (!empty($result['cancelled'])) {
            $this->steps[$index]['state'] = 'Pending';
            $this->save(null);
            return null;
        }

        return $result;
    }

    /**
     * Call $work until it succeeds, fails without 'retryable', or has been
     * tried 1 + count(BACKOFF) times, sleeping BACKOFF[n] between tries.
     * $before_retry runs before each wait and stops the loop by returning
     * false. An exception counts as a non-retryable failure.
     */
    public static function attempt(callable $work, callable $sleep, ?callable $before_retry = null): array
    {
        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                $result = $work();
            } catch (Throwable $e) {
                error_log('[report-jobs] step error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
                $result = array(
                    'success' => false,
                    'message' => $e->getMessage(),
                    'retryable' => false
                );
            }

            $result['attempts'] = $attempts;

            if (!empty($result['success']) || empty($result['retryable']) || $attempts > count(self::BACKOFF)) {
                return $result;
            }

            if ($before_retry !== null && $before_retry($attempts, $result) === false) {
                $result['cancelled'] = true;
                return $result;
            }

            $sleep(self::BACKOFF[$attempts - 1]);
        }
    }

    private function cancelled(): bool
    {
        $this->beat();

        if ($this->model->get_job_status((int) $this->job['id']) === 'Cancelled') {
            error_log('[report-jobs] job '.$this->job['id'].' cancelled');
            return true;
        }

        return false;
    }

    /** Keep the heartbeat fresh; cheap to call often. */
    public function beat(): void
    {
        if ($this->job === null || time() - $this->last_beat < self::BEAT_SECONDS) {
            return;
        }

        $this->last_beat = time();
        $this->model->beat((int) $this->job['id']);
    }

    private function save(?string $current_step): void
    {
        $done = count(array_filter($this->steps, function ($step) {
            return in_array($step['state'], array(
                'Done',
                'Skipped',
                'Failed'
            ), true);
        }));

        $this->last_beat = time();
        $this->model->save_progress(
            (int) $this->job['id'],
            json_encode($this->steps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            count($this->steps),
            $done,
            $current_step
        );
    }

    private function finish_from_steps(?string $result_key, ?string $result_name): void
    {
        $failed = array_values(array_filter($this->steps, function ($step) {
            return $step['state'] === 'Failed';
        }));

        if (count($failed) > 0) {
            $this->finish('Failed', count($failed).' of '.count($this->steps).' steps failed: '.implode('; ', array_map(function ($step) {
                return $step['label'].' ('.$step['error'].')';
            }, array_slice($failed, 0, 3))), $result_key, $result_name);
            return;
        }

        $this->finish('Completed', null, $result_key, $result_name);
    }

    private function finish(string $job_status, ?string $last_error, ?string $result_key, ?string $result_name): void
    {
        $this->save(null);
        $this->model->finish_job((int) $this->job['id'], $job_status, $last_error, $result_key, $result_name);
    }

    /* ----------------------------------------------------------------- api */

    /** A job row as the Reports tab reads it. */
    public static function payload(array $job): array
    {
        return array(
            'id' => (int) $job['id'],
            'job_type' => $job['job_type'],
            'job_status' => $job['job_status'],
            'done_steps' => (int) $job['done_steps'],
            'total_steps' => (int) $job['total_steps'],
            'current_step' => $job['current_step'],
            'steps' => json_decode((string) $job['steps'], true) ?: array(),
            'last_error' => $job['last_error'],
            'date_created' => $job['date_created'],
            'date_started' => $job['date_started'],
            'date_finished' => $job['date_finished']
        );
    }

    private static function refuse(string $message): array
    {
        return array(
            'success' => false,
            'message' => $message
        );
    }
}
