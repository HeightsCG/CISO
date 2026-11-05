<?php
/**
 * Background worker for report jobs (see ReportJobs). Command line only,
 * outside public/, with APPLICATION_ENV set as for the web app:
 *   php bin/report-worker.php --job=<id>   run one job (spawned when a job starts)
 *   php bin/report-worker.php --drain      run queued and stalled jobs (cron, every minute)
 *   php bin/report-worker.php --beat=<id> --pid=<worker pid>
 *                                          keep a running job's heartbeat (started by the worker)
 */
if (php_sapi_name() !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);

if (is_file($root.'/vendor/autoload.php')) {
    require_once $root.'/vendor/autoload.php';
}

spl_autoload_register(function ($class) use ($root) {
    $sources = array(
        $root.'/app/controllers/'.$class.'.php',
        $root.'/app/models/'.$class.'.php',
        $root.'/app/services/'.$class.'.php',
        $root.'/app/'.$class.'.php',
        $root.'/libs/Classes/'.$class.'.php'
    );

    foreach ($sources as $source) {
        if (file_exists($source)) {
            require_once $source;
            return;
        }
    }
});

if ((string) getenv('APPLICATION_ENV') === '') {
    fwrite(STDERR, '[report-worker] APPLICATION_ENV is not set'."\n");
    exit(2);
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$config = Main::get_config();
date_default_timezone_set($config['global']['timezone'] ?? 'UTC');

exit(ReportJobs::main(getopt('', array(
    'job:',
    'drain',
    'beat:',
    'pid:'
))));
