<?php
/**
 * Builds frameworks/<key>.json from frameworks/meta/<key>.json plus the
 * structure its parser derives from the official sources.
 *
 *   php frameworks/build.php            build every framework, then the crosswalk
 *   php frameworks/build.php cmmc-l2    build one
 */
require __DIR__.'/lib/Source.php';
require __DIR__.'/lib/FrameworkBuilder.php';
require __DIR__.'/lib/Crosswalk.php';

$keys = array_slice($argv, 1);

if (count($keys) === 0) {
    $keys = FrameworkBuilder::keys();
}

foreach ($keys as $key) {
    $definition = FrameworkBuilder::build($key);
    file_put_contents(__DIR__.'/'.$key.'.json', FrameworkBuilder::encode($definition));
    echo $key.': '.$definition['counts']['families'].' groups, '.$definition['counts']['controls'].' controls, '.$definition['counts']['objectives']." objectives\n";
}

if (count(array_slice($argv, 1)) === 0) {
    $crosswalk = Crosswalk::build();
    file_put_contents(__DIR__.'/crosswalk/crosswalk.json', FrameworkBuilder::encode($crosswalk));
    echo 'crosswalk: '.count($crosswalk['links']).' links in '.count($crosswalk['counts'])." sets\n";
}
