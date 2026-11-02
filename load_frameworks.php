<?php
/**
 * Loads framework definitions (frameworks/*.json) into companies' standards
 * libraries, then replaces the shared crosswalk. Safe to re-run: standards and
 * controls are updated in place.
 *
 *     APPLICATION_ENV=production php load_frameworks.php all
 *     APPLICATION_ENV=production php load_frameworks.php <company_id> [framework_key ...]
 */
if (PHP_SAPI !== 'cli') {
    exit("This loader runs from the command line only.\n");
}

require_once __DIR__.'/libs/Classes/Main.php';
require_once __DIR__.'/libs/Classes/Database.php';
require_once __DIR__.'/libs/Classes/Model.php';
require_once __DIR__.'/app/models/StandardsModel.php';
require_once __DIR__.'/app/models/CompaniesModel.php';
require_once __DIR__.'/app/models/CrosswalkModel.php';
require_once __DIR__.'/libs/Classes/FrameworkLibrary.php';

$target = $argv[1] ?? '';
$keys = array_slice($argv, 2);

if ($target === '') {
    exit("Usage: php load_frameworks.php all|<company_id> [framework_key ...]\n");
}

if (count($keys) === 0) {
    $keys = array_keys(FrameworkLibrary::all());
}

if ($target === 'all') {
    $companies = array();

    foreach ((new Model())->select('SELECT id FROM companies WHERE deleted = 0 ORDER BY id') as $row) {
        $companies[] = (int) $row['id'];
    }
} else {
    $companies = array(
        (int) $target
    );
}

foreach ($companies as $company_id) {
    foreach ($keys as $key) {
        $standard_id = FrameworkLibrary::sync($company_id, $key);
        echo 'company '.$company_id.': '.$key.' -> standard '.$standard_id."\n";
    }
}

echo 'crosswalk: '.FrameworkLibrary::load_crosswalk()." links\n";
