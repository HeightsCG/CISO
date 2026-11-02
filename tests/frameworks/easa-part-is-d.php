<?php
$shared = require __DIR__.'/easa-part-is-shared.php';

// Points (objectives) per requirement, counted by hand from the Annex to Delegated Regulation (EU) 2022/1645.
$points = array(
    '100' => 1,
    '200' => 18,
    '205' => 12,
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

$tests = $shared('easa-part-is-d', 'D', 'easa/reg-2022-1645-consolidated-2025-10-16.html', $points, 'ANNEX — ORGANISATION REQUIREMENTS (PART-IS.D.OR)');

$tests['124 points in total; IS.D.OR.205 has no ATM/ANS derogation point (e)'] = function () {
    $d = json_decode(file_get_contents(ROOT.'/frameworks/easa-part-is-d.json'), true);
    Assert::same(124, $d['counts']['objectives'], 'point count');
    Assert::true(strpos(json_encode($d['families']), '"IS.D.OR.205(e)"') === false, 'no IS.D.OR.205(e)');
};

$tests['consolidated text includes the 2025/22 ISMM and change amendments and corrigendum'] = function () {
    $html = Source::normalise(strip_tags(file_get_contents(Source::path('easa/reg-2022-1645-consolidated-2025-10-16.html'))));
    Assert::true(strpos($html, 'COMMISSION DELEGATED REGULATION (EU) 2025/22') !== false, 'consolidated text includes 2025/22');
    Assert::true(strpos($html, 'An approval shall not be required for declaring organisations.') !== false, 'IS.D.OR.250(b) as amended');
    Assert::true(strpos($html, 'It shall apply from 16 October 2025.') !== false, 'Article 8 application date');
};

return $tests;
