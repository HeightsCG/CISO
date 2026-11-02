<?php
$shared = require __DIR__.'/easa-part-is-shared.php';

// Points (objectives) per requirement, counted by hand from Annex II to Regulation (EU) 2023/203.
$points = array(
    '100' => 1,
    '200' => 18,
    '205' => 13,
    '210' => 5,
    '215' => 9,
    '220' => 10,
    '225' => 5,
    '230' => 8,
    '235' => 2,
    '240' => 12,
    '245' => 17,
    '250' => 17,
    '255' => 6,
    '260' => 2
);

$tests = $shared('easa-part-is-i', 'I', 'easa/reg-2023-203-consolidated-2026-02-22.html', $points, 'ANNEX II — ORGANISATION REQUIREMENTS (PART-IS.I.OR)');

$tests['125 points in total, including the ATM/ANS derogation IS.I.OR.205(e)'] = function () {
    $d = json_decode(file_get_contents(ROOT.'/frameworks/easa-part-is-i.json'), true);
    Assert::same(125, $d['counts']['objectives'], 'point count');
    Assert::true(strpos(json_encode($d['families']), '"IS.I.OR.205(e)"') !== false, 'IS.I.OR.205(e) is loaded');
};

$tests['ISMM and change rules follow the 2025/2293 amendment for declared organisations'] = function () {
    $html = Source::normalise(strip_tags(file_get_contents(Source::path('easa/reg-2023-203-consolidated-2026-02-22.html'))));
    Assert::true(strpos($html, 'COMMISSION IMPLEMENTING REGULATION (EU) 2025/2293') !== false, 'consolidated text includes 2025/2293');
    Assert::true(strpos($html, 'It shall apply from 22 February 2026.') !== false, 'Article 16 application date');
};

return $tests;
