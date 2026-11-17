<?php
/**
 * HITRUST HCG control set checks: the 19 assessment domains are covered, controls carry HCG
 * numbering only, and every control has HCG description, guidance and test points.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/hitrust.json'), true);
};

return array(
    'the 19 HITRUST assessment domains are each an area' => function () use ($definition) {
        Assert::same(19, count($definition()['families']), 'domains');
    },

    'controls are HCG-written with HCG numbering' => function () use ($definition) {
        $d = $definition();

        Assert::same('hcg_authored', $d['content_licence'], 'licence');

        foreach ($d['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(preg_match('/^HT-[A-Z]{3}-\d{2}$/', $control['id']) === 1, $control['id'].' HCG number');
                Assert::same('hcg', $control['source'], $control['id'].' source');
                Assert::same('', $control['text'], $control['id'].' text');
                Assert::true(count($control['objectives']) >= 1, $control['id'].' has test points');
            }
        }
    }
);
