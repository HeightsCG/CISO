<?php
/**
 * Report delivery safety and the report workflow's pure logic, without the
 * network or the database: no generated report can sit in the web root,
 * report code never writes there, report bytes are stored privately and
 * reached only through short-lived links, the worker refuses web SAPIs,
 * and the Final gate, versioning, revision planning and step retries.
 */
require_once ROOT.'/libs/Classes/Main.php';
require_once ROOT.'/libs/Classes/S3Service.php';
require_once ROOT.'/libs/Classes/AiAssistant.php';
require_once ROOT.'/libs/Classes/ReportAI.php';
require_once ROOT.'/libs/Classes/ReportQuality.php';
require_once ROOT.'/libs/Classes/ReportNarratives.php';
require_once ROOT.'/libs/Classes/ReportStates.php';
require_once ROOT.'/libs/Classes/ReportJobs.php';
require_once ROOT.'/libs/Classes/ReportRenderer.php';

$report_extensions = array(
    'pdf',
    'docx',
    'xlsx',
    'zip',
    'csv',
    'doc',
    'xls',
    'pptx'
);

$source = function (string $relative) {
    return (string) file_get_contents(ROOT.'/'.$relative);
};

$report_api = function () use ($source) {
    $api = $source('app/controllers/ApiController.php');
    $start = strpos($api, '/* --------------------------------------------------------------- reports */');
    $end = strpos($api, '/* ------------------------------------------------------------ assistant */');
    Assert::true($start !== false && $end !== false && $end > $start, 'the report section of ApiController can be found');
    return substr($api, $start, $end - $start);
};

$report_sources = function () use ($source, $report_api) {
    return array(
        'ReadyReports.php' => $source('libs/Classes/ReadyReports.php'),
        'ReportJobs.php' => $source('libs/Classes/ReportJobs.php'),
        'ReportRenderer.php' => $source('libs/Classes/ReportRenderer.php'),
        'ReportStates.php' => $source('libs/Classes/ReportStates.php'),
        'bin/report-worker.php' => $source('bin/report-worker.php'),
        'ApiController.php (reports)' => $report_api()
    );
};

$state = function (string $key, string $state, ?string $narrative_status) {
    return array(
        'section_key' => $key,
        'label' => $key,
        'state' => $state,
        'narrative_status' => $narrative_status,
        'usable' => $state === 'current' && $narrative_status !== 'Rejected',
        'hash' => hash('sha256', $key),
        'content' => array(
            'text' => $key
        )
    );
};

return array(

    'no report-like file anywhere under public/' => function () use ($report_extensions) {
        $found = array();
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT.'/public', FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (in_array(strtolower($file->getExtension()), $report_extensions, true)) {
                $found[] = substr($file->getPathname(), strlen(ROOT) + 1);
            }
        }
        Assert::same(array(), $found, 'report-like files must never be served from the web root');
    },

    'report code never writes under public/' => function () use ($report_sources) {
        foreach ($report_sources() as $name => $code) {
            Assert::true(preg_match('/[\'"]\/?public\//', $code) === 0, $name.' builds no path into public/');
            Assert::true(strpos($code, "app_path().'/public") === false, $name.' builds no path from app_path() into public');
            preg_match_all('/(file_put_contents|fopen|tempnam|mkdir|->open)\s*\(([^;]*)/', $code, $calls, PREG_SET_ORDER);
            foreach ($calls as $call) {
                Assert::true(stripos($call[2], 'public') === false, $name.': '.$call[1].' must not target public/');
            }
        }
    },

    'package and temporary files live in the system temp folder' => function () use ($source) {
        $jobs = $source('libs/Classes/ReportJobs.php');
        Assert::true(preg_match('/\$dir = sys_get_temp_dir\(\)\./', $jobs) === 1, 'the package folder is under sys_get_temp_dir()');
        Assert::true(strpos($jobs, '$zip_path = $dir.\'.zip\'') !== false, 'the zip sits beside the package folder');
        Assert::true(strpos($jobs, 'unlink($zip_path)') !== false && strpos($jobs, 'rmdir($dir)') !== false, 'the zip and folder are removed');
        preg_match_all('/tempnam\(([^,]+),/', $source('libs/Classes/ReportRenderer.php'), $temps);
        foreach ($temps[1] as $dir) {
            Assert::same('sys_get_temp_dir()', trim($dir), 'renderer temp files use sys_get_temp_dir()');
        }
    },

    'report bytes are stored privately, never with a public ACL' => function () use ($report_sources, $source) {
        foreach ($report_sources() as $name => $code) {
            Assert::true(stripos($code, 'public-read') === false && stripos($code, "'ACL'") === false, $name.' sets no ACL');
            Assert::true(strpos($code, 'S3Service::upload_file(') === false && strpos($code, 'S3Service::put_private(') === false, $name.' stores reports only with put_private_bytes');
        }
        Assert::true(strpos($source('libs/Classes/ReportJobs.php'), 'S3Service::put_private_bytes($key, $bytes, \'application/zip\')') !== false, 'packages use put_private_bytes');
        Assert::true(strpos($source('libs/Classes/ReportJobs.php'), 'S3Service::put_private_bytes($key, $file[\'bytes\']') !== false, 'previews and downloads use put_private_bytes');
        Assert::true(stripos($source('libs/Classes/S3Service.php'), 'public-read') === false, 'S3Service never asks for public-read');
    },

    'presigned report links live ten minutes at most' => function () use ($report_sources) {
        Assert::true(ReportJobs::DOWNLOAD_TTL <= 600, 'DOWNLOAD_TTL is at most 600 seconds');
        $calls = 0;
        foreach ($report_sources() as $name => $code) {
            preg_match_all('/presigned_get_url\(([^,]+),\s*([^,\)]+)/', $code, $found, PREG_SET_ORDER);
            foreach ($found as $call) {
                $calls++;
                $ttl = trim($call[2]);
                Assert::true($ttl === 'ReportJobs::DOWNLOAD_TTL' || $ttl === 'self::DOWNLOAD_TTL' || (ctype_digit($ttl) && (int) $ttl <= 600), $name.': presigned TTL '.$ttl.' must be a constant or number of at most 600');
            }
        }
        Assert::true($calls >= 2, 'the download and package links are presigned');
    },

    'the worker sits outside public/ and refuses web SAPIs' => function () use ($source) {
        $worker = ROOT.'/bin/report-worker.php';
        Assert::true(is_file($worker), 'bin/report-worker.php exists');
        Assert::true(strpos(realpath($worker), realpath(ROOT.'/public')) !== 0, 'the worker is not under public/');
        $code = $source('bin/report-worker.php');
        $guard = strpos($code, "if (php_sapi_name() !== 'cli') {");
        Assert::true($guard !== false, 'the worker checks php_sapi_name()');
        Assert::true($guard < strpos($code, 'require_once') && $guard < strpos($code, 'ReportJobs::'), 'the SAPI check runs before anything else');
        Assert::true(preg_match('/php_sapi_name\(\) !== \'cli\'\) \{\s*exit\(1\);/', $code) === 1, 'a web SAPI exits at once');

        $cgi = PHP_BINDIR.'/php-cgi';
        if (is_file($cgi) && is_executable($cgi)) {
            $output = (string) shell_exec('APPLICATION_ENV=testing '.escapeshellarg($cgi).' -q '.escapeshellarg($worker).' 2>&1');
            Assert::true(strpos($output, 'report-worker') === false && strpos($output, 'Usage') === false, 'under php-cgi the worker does nothing');
        }

        $output = (string) shell_exec('env -u APPLICATION_ENV '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($worker).' --drain 2>&1');
        Assert::true(strpos($output, 'APPLICATION_ENV is not set') !== false, 'the worker refuses to run without APPLICATION_ENV');
    },

    'S3 serves PDFs inline and Office files and archives as attachments' => function () {
        $method = new ReflectionMethod('S3Service', 'get_object_params');
        $method->setAccessible(true);
        $pdf = $method->invoke(null, 'reports/1/ready/abc.pdf', 'Report.pdf');
        Assert::true(strpos($pdf['ResponseContentDisposition'], 'inline;') === 0, 'a PDF opens inline');
        Assert::same('application/pdf', $pdf['ResponseContentType'], 'a PDF is served as a PDF');
        foreach (array(
            'docx',
            'xlsx',
            'zip',
            'csv',
            'html',
            'svg'
        ) as $extension) {
            $params = $method->invoke(null, 'reports/1/x.'.$extension, 'x.'.$extension);
            Assert::true(strpos($params['ResponseContentDisposition'], 'attachment;') === 0, $extension.' downloads as an attachment');
            Assert::same('application/octet-stream', $params['ResponseContentType'], $extension.' is never served as a renderable type');
        }
    },

    'Final is blocked until every section is current and approved' => function () use ($state) {
        $blockers = ReportStates::gate(array(
            'executive' => $state('executive', 'current', 'Draft'),
            'remediation' => $state('remediation', 'stale', 'Approved'),
            'patterns' => $state('patterns', 'missing', null),
            'review' => $state('review', 'current', 'Rejected'),
            'domain:Privacy' => $state('domain:Privacy', 'current', 'Approved')
        ));
        Assert::same(array(
            'executive: not approved',
            'remediation: out of date with the assessment, regenerate and approve it',
            'patterns: not generated',
            'review: rejected'
        ), $blockers, 'drafts, stale approvals, missing and rejected sections all block Final');

        Assert::same(array(), ReportStates::gate(array(
            'executive' => $state('executive', 'current', 'Approved'),
            'review' => $state('review', 'current', 'Approved')
        )), 'all current and approved: Final allowed');

        Assert::same(1, count(ReportStates::gate(array())), 'no sections at all blocks Final');
    },

    'Final documents use approved text only, drafts use any current unrejected text' => function () use ($state) {
        Assert::true(ReportNarratives::usable_in($state('executive', 'current', 'Draft'), 'draft'), 'a draft section is used in a Draft document');
        Assert::true(!ReportNarratives::usable_in($state('executive', 'current', 'Draft'), 'final'), 'a draft section is left out of a Final document');
        Assert::true(ReportNarratives::usable_in($state('executive', 'current', 'Approved'), 'final'), 'an approved section is used in a Final document');
        Assert::true(!ReportNarratives::usable_in($state('executive', 'stale', 'Approved'), 'final'), 'a stale approval is not used');
        Assert::true(!ReportNarratives::usable_in($state('executive', 'current', 'Rejected'), 'draft'), 'a rejected section is never used');
    },

    'versions bump minor into Draft or In Review and major into Final' => function () {
        Assert::same('0.2', ReportStates::next_version(null, 'In Review'), 'first review is 0.2');
        Assert::same('0.3', ReportStates::next_version('0.2', 'Draft'), 'back to draft bumps the minor');
        Assert::same('1.0', ReportStates::next_version('0.3', 'Final'), 'first Final is 1.0');
        Assert::same('1.1', ReportStates::next_version('1.0', 'Draft'), 'a draft after Final is 1.1');
        Assert::same('2.0', ReportStates::next_version('1.1', 'Final'), 'the next Final is 2.0');
    },

    'the Final digest changes when results or approved text change' => function () use ($state) {
        $analysis = array(
            'name' => 'A',
            'controls' => array(
                array(
                    'ref' => 'CC1.1',
                    'result' => 'Implemented',
                    'applicability' => 'Applicable',
                    'justification' => '',
                    'assessor' => array(),
                    'risk_rating' => null,
                    'evidence' => array(),
                    'objectives' => array()
                )
            )
        );
        $states = array(
            'executive' => $state('executive', 'current', 'Approved')
        );
        $digest = ReportStates::digest($states, $analysis);
        $changed = $analysis;
        $changed['controls'][0]['result'] = 'Not Implemented';
        Assert::true($digest !== ReportStates::digest($states, $changed), 'a changed result changes the digest');
        $edited = $states;
        $edited['executive']['content'] = array(
            'text' => 'edited'
        );
        Assert::true($digest !== ReportStates::digest($edited, $analysis), 'edited text changes the digest');
        Assert::same($digest, ReportStates::digest($states, $analysis), 'the digest is stable');
    },

    'revision targets come from High and Medium review issues in generation order' => function () {
        $keys = array(
            'domain:Access',
            'domain:Privacy',
            'remediation',
            'patterns',
            'executive',
            'review'
        );
        $targets = ReportJobs::revision_targets(array(
            array(
                'severity' => 'Medium',
                'section' => 'remediation',
                'description' => 'Fix the phase.'
            ),
            array(
                'severity' => 'High',
                'section' => 'domain:Access',
                'description' => 'Unsupported claim.'
            ),
            array(
                'severity' => 'Low',
                'section' => 'domain:Privacy',
                'description' => 'Style.'
            ),
            array(
                'severity' => 'High',
                'section' => 'review',
                'description' => 'Not revisable.'
            ),
            array(
                'severity' => 'High',
                'section' => 'domain:Unknown',
                'description' => 'No such section.'
            ),
            array(
                'severity' => 'High',
                'section' => 'remediation',
                'description' => 'Wrong dependency.'
            )
        ), $keys);
        Assert::same(array(
            array(
                'key' => 'domain:Access',
                'notes' => array(
                    '[High] Unsupported claim.'
                )
            ),
            array(
                'key' => 'remediation',
                'notes' => array(
                    '[Medium] Fix the phase.',
                    '[High] Wrong dependency.'
                )
            ),
            array(
                'key' => 'executive',
                'notes' => array()
            )
        ), $targets, 'Low, review and unknown sections are ignored; the executive summary is refreshed after remediation changes');

        Assert::same(array(
            array(
                'key' => 'domain:Access',
                'notes' => array(
                    '[Medium] x'
                )
            )
        ), ReportJobs::revision_targets(array(
            array(
                'severity' => 'Medium',
                'section' => 'domain:Access',
                'description' => 'x'
            )
        ), $keys), 'a domain revision alone does not refresh the executive summary');
    },

    'reviewer notes reach the model but not the input hash' => function () {
        $input = array(
            'section' => 'executive',
            'facts' => 1
        );
        $plain = ReportAI::user_message('executive', $input);
        $revised = ReportAI::user_message('executive', $input, array(
            '[High] Fix it.'
        ));
        Assert::true(strpos($plain, 'REVIEWER NOTES') === false, 'no notes block without notes');
        Assert::true(strpos($revised, "REVIEWER NOTES TO RESOLVE:") !== false && strpos($revised, '- [High] Fix it.') !== false, 'the notes are listed');
        Assert::true(strpos($revised, $plain) === 0, 'the facts message is unchanged');
    },

    'a step retries retryable failures with 5s, 20s, 60s backoff' => function () {
        $slept = array();
        $sleep = function (int $seconds) use (&$slept) {
            $slept[] = $seconds;
        };
        $calls = 0;
        $result = ReportJobs::attempt(function () use (&$calls) {
            $calls++;
            return array(
                'success' => false,
                'message' => 'busy',
                'retryable' => true
            );
        }, $sleep);
        Assert::same(4, $calls, 'one try and three retries');
        Assert::same(array(
            5,
            20,
            60
        ), $slept, 'the backoff waits');
        Assert::same(false, $result['success'], 'still failing after the retries');
        Assert::same(4, $result['attempts'], 'attempts are counted');

        $slept = array();
        $calls = 0;
        $result = ReportJobs::attempt(function () use (&$calls) {
            $calls++;
            return $calls < 3 ? array(
                'success' => false,
                'message' => 'connection',
                'retryable' => true
            ) : array(
                'success' => true
            );
        }, $sleep);
        Assert::true($result['success'] && $result['attempts'] === 3, 'succeeds on the third try');
        Assert::same(array(
            5,
            20
        ), $slept, 'two waits before the success');

        $slept = array();
        $result = ReportJobs::attempt(function () {
            return array(
                'success' => false,
                'message' => 'bad request',
                'retryable' => false
            );
        }, $sleep);
        Assert::true($result['attempts'] === 1 && count($slept) === 0, 'a non-retryable failure is not retried');

        $log = ini_set('error_log', '/dev/null');
        $result = ReportJobs::attempt(function () {
            throw new RuntimeException('boom');
        }, $sleep);
        ini_set('error_log', (string) $log);
        Assert::true($result['attempts'] === 1 && $result['message'] === 'boom' && !$result['success'], 'an exception fails the step without retrying');

        $result = ReportJobs::attempt(function () {
            return array(
                'success' => false,
                'message' => 'busy',
                'retryable' => true
            );
        }, $sleep, function () {
            return false;
        });
        Assert::true(!empty($result['cancelled']) && $result['attempts'] === 1, 'a cancel before the retry stops the step');
    },

    'only transient API statuses are retried' => function () {
        foreach (array(
            429,
            500,
            529,
            408,
            null
        ) as $status) {
            Assert::true(ReportAI::retryable_status($status), var_export($status, true).' is retried');
        }
        foreach (array(
            400,
            401,
            403,
            404,
            422
        ) as $status) {
            Assert::true(!ReportAI::retryable_status($status), $status.' is not retried');
        }
    },

    'job options are normalized and package steps follow the report order' => function () {
        $narratives = ReportJobs::normalize_options('Narratives', array(
            'mode' => 'everything',
            'sections' => array(
                'executive'
            )
        ));
        Assert::same(array(
            'mode' => 'missing_or_stale',
            'sections' => array(),
            'revise' => 'auto'
        ), $narratives, 'unknown modes fall back to missing_or_stale');

        $package = ReportJobs::normalize_options('Package', array(
            'formats' => array(
                'exe',
                'pdf'
            ),
            'appendices' => '0'
        ));
        Assert::same(array(
            'pdf'
        ), $package['formats'], 'only known formats are kept');
        Assert::same(false, $package['appendices'], 'appendices can be turned off');
        Assert::true(isset(ReportJobs::normalize_options('Package', array(
            'formats' => array()
        ))['error']), 'a package needs a format');
    },

    'the footer state line reads Draft or Final with the version' => function () {
        $zone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        Assert::same('Draft v0.3 · Generated 4 Nov 2026 15:42 UTC', ReportRenderer::status_line(array(
            'report_status' => 'Draft',
            'report_version' => '0.3',
            'generated' => '2026-11-04 15:42:00',
            'changed_since_final' => false
        )), 'draft line');
        Assert::same('Draft · changed since Final v1.0 · Generated 4 Nov 2026 15:42 UTC', ReportRenderer::status_line(array(
            'report_status' => 'Draft',
            'report_version' => '1.0',
            'generated' => '2026-11-04 15:42:00',
            'changed_since_final' => true
        )), 'a changed Final reads as Draft');
        date_default_timezone_set($zone);
    }
);
