<?php
/**
 * NIST SP 800-53 Rev 5.2.0. The parser takes structure from the OSCAL catalog; these
 * checks hold it against NIST's CPRT export of the same release, a separately
 * published artefact, and against the catalog's own metadata.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-800-53-r5.json'), true);
};

$controls = function () use ($definition) {
    $all = array();

    foreach ($definition()['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $control['family_code'] = $family['code'];
            $all[$control['id']] = $control;
        }
    }

    return $all;
};

// CPRT rows: unpadded ID => name, statement. Withdrawn rows read "[Incorporated into ...]",
// "[ Moved to ...]" or carry a JSON withdrawal note ("[ { "description": ...}]").
$cprt = function () {
    $rows = array();

    foreach (array_slice(Source::xlsx_rows('sp800-53/cprt-sp800-53-5.2.0.xlsx'), 1) as $row) {
        $id = trim($row['B'] ?? '');

        if ($id === '') {
            continue;
        }

        $statement = trim($row['D'] ?? '');
        $rows[preg_replace('/(?<=-)0(?=\d)|(?<=\()0(?=\d\))/', '', $id)] = array(
            'family' => trim($row['A'] ?? ''),
            'name' => Source::normalise($row['C'] ?? ''),
            'withdrawn' => preg_match('/^\[\s*(Incorporated into|Moved to|\{)/', $statement) === 1
        );
    }

    return $rows;
};

return array(
    'release recorded in meta is the catalog release' => function () {
        $catalog = json_decode(file_get_contents(Source::path('sp800-53/NIST_SP-800-53_rev5_catalog.json')), true)['catalog'];
        Assert::same('5.2.0', $catalog['metadata']['version'], 'OSCAL catalog version');
        Assert::same('5.2.0', FrameworkBuilder::meta('nist-800-53-r5')['release'], 'release in meta');
    },

    '1014 active controls and enhancements, matching the CPRT export' => function () use ($definition, $controls, $cprt) {
        $active = array();
        $withdrawn = 0;

        foreach ($cprt() as $id => $row) {
            if ($row['withdrawn']) {
                $withdrawn++;
            } else {
                $active[] = $id;
            }
        }

        Assert::same(1196, count($cprt()), 'entries in the CPRT export');
        Assert::same(182, $withdrawn, 'withdrawn entries in the CPRT export');
        Assert::same(1014, $definition()['counts']['controls'], 'controls loaded');
        $loaded = array_keys($controls());
        Assert::same(array(), array_values(array_diff($active, $loaded)), 'active in CPRT but not loaded');
        Assert::same(array(), array_values(array_diff($loaded, $active)), 'loaded but withdrawn or missing in CPRT');
    },

    '300 base controls and 714 enhancements with valid parents' => function () use ($controls) {
        $base = 0;
        $enhancements = 0;
        $all = $controls();

        foreach ($all as $id => $control) {
            if ($control['type'] === 'control') {
                $base++;
                Assert::same(null, $control['parent'], 'parent of '.$id);
                Assert::true(preg_match('/^[A-Z]{2}-\d+$/', $id) === 1, 'base control ID '.$id);
            } else {
                $enhancements++;
                Assert::true(preg_match('/^([A-Z]{2}-\d+)\(\d+\)$/', $id, $m) === 1, 'enhancement ID '.$id);
                Assert::same($m[1], $control['parent'], 'parent of '.$id);
                Assert::true(isset($all[$control['parent']]), 'parent of '.$id.' is loaded');
            }
        }

        Assert::same(300, $base, 'base controls');
        Assert::same(714, $enhancements, 'enhancements');
    },

    '20 families in CPRT order, each control in its CPRT family' => function () use ($definition, $controls, $cprt) {
        $expected = array();

        foreach ($cprt() as $row) {
            if ($row['family'] !== '' && !in_array($row['family'], $expected, true)) {
                $expected[] = $row['family'];
            }
        }

        Assert::same($expected, array_column($definition()['families'], 'code'), 'family codes');
        Assert::same(20, count($expected), 'family count');

        foreach ($controls() as $id => $control) {
            Assert::same(substr($id, 0, 2), $control['family_code'], 'family of '.$id);
        }
    },

    'titles match the CPRT names' => function () use ($controls, $cprt) {
        $rows = $cprt();

        foreach ($controls() as $id => $control) {
            Assert::same(strtolower($rows[$id]['name']), strtolower($control['title']), 'title of '.$id);
        }
    },

    '2790 SP 800-53A objectives with official labels' => function () use ($definition, $controls) {
        Assert::same(2790, $definition()['counts']['objectives'], 'objective count');
        $seen = array();

        foreach ($controls() as $id => $control) {
            Assert::true(count($control['objectives']) > 0, $id.' has objectives');

            // 800-53A labels pad numbers: AC-2(1) is assessed as AC-02(01).
            $padded = preg_replace_callback('/-(\d+)(\((\d+)\))?$/', function ($m) {
                return sprintf('-%02d', $m[1]).(isset($m[3]) ? sprintf('(%02d)', $m[3]) : '');
            }, $id);

            foreach ($control['objectives'] as $objective) {
                Assert::true(strpos($objective['id'], $padded) === 0, $objective['id'].' belongs to '.$id);
                Assert::true(!isset($seen[$objective['id']]), 'objective '.$objective['id'].' repeated');
                Assert::true(strpos($objective['text'], '{{') === false, 'unresolved parameter in '.$objective['id']);
                $seen[$objective['id']] = true;
            }
        }
    },

    'rule controls exist' => function () use ($controls) {
        $all = $controls();

        foreach (FrameworkBuilder::meta('nist-800-53-r5')['rules'] as $rule) {
            if (isset($rule['control'])) {
                Assert::true(isset($all[$rule['control']]), 'rule '.$rule['id'].' control '.$rule['control']);
            }
        }

        Assert::true(isset($all[FrameworkBuilder::meta('nist-800-53-r5')['governing_document']['required_by']]), 'governing document control');
    }
);
