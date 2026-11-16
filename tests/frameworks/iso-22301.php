<?php
/**
 * ISO 22301 HCG control set checks: the official clauses are complete against the preview's
 * contents list and nothing of the standard's text is reproduced.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/iso-22301.json'), true);
};

return array(
    'every requirement clause in the contents list, 4.1 to 10.2, is assessed' => function () use ($definition) {
        $raw = file_get_contents(Source::path('iso22301/iso-22301-2019-sample.raw.txt'));
        preg_match_all('/^((?:[4-9]|10)\.\d+(?:\.\d+)?) [A-Z]/m', substr($raw, 0, strpos($raw, 'Bibliography')), $m);
        $listed = array_values(array_unique($m[1]));

        // Assessed at the level where the requirement sits: sub-clauses of 4.2, 4.3, 5.2, 6.1, 6.2, 7.5, 9.2 and 9.3 through their parent; 8.2, 8.3 and 8.4 through their sub-clauses ("General" of 8.2 and 8.3 through those sub-clauses).
        $assessed_elsewhere = array('4.2.1', '4.2.2', '4.3.1', '4.3.2', '5.2.1', '5.2.2', '6.1.1', '6.1.2', '6.2.1', '6.2.2', '7.5.1', '7.5.2', '7.5.3', '8.2', '8.2.1', '8.3', '8.3.1', '8.4', '9.2.1', '9.2.2', '9.3.1', '9.3.2', '9.3.3');
        $expected = array_values(array_diff($listed, $assessed_elsewhere));
        $built = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same('clause', $control['type'], $control['id'].' type');
                $built[] = $control['id'];
            }
        }

        Assert::same($expected, $built, 'clauses');
    },

    'the standard is not reproduced: controls carry HCG wording only' => function () use ($definition) {
        $d = $definition();

        Assert::same('hcg_authored', $d['content_licence'], 'licence');

        foreach ($d['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same('', $control['text'], $control['id'].' carries no standard text');
                Assert::true(strlen($control['description']) > 40 && strlen($control['guidance']) > 40, $control['id'].' has HCG description and guidance');
                Assert::true(count($control['objectives']) >= 2, $control['id'].' has test points');
            }
        }
    }
);
