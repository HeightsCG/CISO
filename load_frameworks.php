<?php
/**
 * Loads framework definitions (frameworks/*.json) into the shared framework library
 * (standards with company_id 0, read by every organisation), then replaces the shared
 * crosswalk. Safe to re-run: standards and controls are updated in place.
 *
 *     APPLICATION_ENV=production php load_frameworks.php [framework_key ...]
 *
 * With no keys every framework is loaded. A leading "all" is accepted and ignored.
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

$keys = array_slice($argv, 1);

if (($keys[0] ?? '') === 'all') {
    array_shift($keys);
}

if (count($keys) > 0 && ctype_digit($keys[0])) {
    exit("Frameworks are shared by every organisation; give framework keys only.\n");
}

if (count($keys) === 0) {
    $keys = array_keys(FrameworkLibrary::all());
}

foreach ($keys as $key) {
    $standard_id = FrameworkLibrary::sync(FrameworkLibrary::SHARED, $key);
    echo $key.' -> standard '.$standard_id."\n";
}

echo 'crosswalk: '.FrameworkLibrary::load_crosswalk()." links\n";
