<?php
/**
 * NIST SP 800-207 checks: the 7 tenets, 6 network assumptions, 11 logical components and
 * 7 migration steps are all present, with NIST text and HCG test points.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-800-207.json'), true);
};

return array(
    'every tenet, assumption, component and migration step is an element' => function () use ($definition) {
        $counts = array();

        foreach ($definition()['families'] as $family) {
            $counts[$family['code']] = count($family['controls']);
        }

        Assert::same(array('2.1' => 7, '2.2' => 6, '3' => 11, '7.3' => 7), $counts, 'elements per section');
    },

    'element text is NIST text and test points are HCG' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same('sp800207', $control['source'], $control['id'].' source');
                Assert::true(strlen($control['text']) > 80, $control['id'].' has NIST text');
                Assert::true(strpos($control['text'], 'This publication is available') === false, $control['id'].' has no page furniture');

                foreach ($control['objectives'] as $objective) {
                    Assert::same('hcg', $objective['source'], $objective['id'].' source');
                }
            }
        }
    }
);
