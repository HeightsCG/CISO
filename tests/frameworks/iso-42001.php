<?php
/**
 * ISO/IEC 42001 HCG control set checks: the official clauses are complete against the
 * preview's contents list, nothing of the standard's text is reproduced, and every control
 * carries HCG descriptions, guidance and test points.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/iso-42001.json'), true);
};

return array(
    'every requirement clause in the contents list, 4.1 to 10.2, is a control' => function () use ($definition) {
        $raw = file_get_contents(Source::path('iso42001/iso-iec-42001-2023-sample.raw.txt'));
        preg_match_all('/^((?:[4-9]|10)\.\d+(?:\.\d+)?) [A-Z]/m', substr($raw, 0, strpos($raw, 'Annex A')), $m);
        $listed = array_values(array_unique($m[1]));

        // A clause with sub-clauses is assessed through them (6.1 through 6.1.1 to 6.1.4; 7.5, 9.2 and 9.3 whole).
        $parents = array('6.1', '7.5.1', '7.5.2', '7.5.3', '9.2.1', '9.2.2', '9.3.1', '9.3.2', '9.3.3');
        $expected = array_values(array_diff($listed, $parents));
        $built = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                if ($control['type'] === 'clause') {
                    $built[] = $control['id'];
                }
            }
        }

        Assert::same($expected, $built, 'clauses');
    },

    'the standard is not reproduced: clause controls carry HCG wording only' => function () use ($definition) {
        $d = $definition();

        Assert::same('hcg_authored', $d['content_licence'], 'licence');

        foreach ($d['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same('', $control['text'], $control['id'].' carries no standard text');
                Assert::true(strlen($control['description']) > 40 && strlen($control['guidance']) > 40, $control['id'].' has HCG description and guidance');
                Assert::true(count($control['objectives']) >= 1, $control['id'].' has test points');
            }
        }
    },

    'HCG areas are labelled and numbered as HCG' => function () use ($definition) {
        $areas = 0;

        foreach ($definition()['families'] as $family) {
            if (strpos($family['code'], 'HCG-') !== 0) {
                continue;
            }

            $areas++;
            Assert::true(substr($family['name'], -6) === ' (HCG)', $family['code'].' name says HCG');

            foreach ($family['controls'] as $control) {
                Assert::same('hcg', $control['type'], $control['id'].' type');
                Assert::same('hcg', $control['source'], $control['id'].' source');
            }
        }

        Assert::same(9, $areas, 'AI control areas');
    }
);
