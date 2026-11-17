<?php
/**
 * CISA ZTMM v2.0 checks: the five pillars and the cross-cutting capabilities carry their
 * functions, each with its Traditional text and Initial, Advanced and Optimal stages.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/cisa-ztmm.json'), true);
};

return array(
    'pillars have their functions' => function () use ($definition) {
        $counts = array();

        foreach ($definition()['families'] as $family) {
            $counts[$family['name']] = count($family['controls']);
        }

        Assert::same(array('Identity' => 7, 'Devices' => 7, 'Networks' => 7, 'Applications and Workloads' => 8, 'Data' => 8, 'Cross-Cutting Capabilities' => 3), $counts, 'functions per pillar');
    },

    'every function has its four stages, free of footnotes' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(strpos($control['text'], 'Agency') === 0, $control['id'].' Traditional text');
                Assert::same(array('Initial', 'Advanced', 'Optimal'), array_column($control['objectives'], 'stage'), $control['id'].' stages');

                foreach ($control['objectives'] as $objective) {
                    Assert::true(strpos($objective['text'], 'Agency') === 0, $objective['id'].' starts with Agency');
                    Assert::true(preg_match('/https?:|www\.|\s\d{1,2}\s/', $objective['text']) === 0, $objective['id'].' has no footnote text');
                }
            }
        }
    }
);
