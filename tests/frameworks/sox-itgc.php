<?php
/**
 * SOX ITGC HCG control set checks: the four ITGC domains and scoping are covered, and every
 * control is HCG-written with test points.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/sox-itgc.json'), true);
};

return array(
    'scoping plus the four ITGC domains are areas' => function () use ($definition) {
        Assert::same(array('ITGC-GOV', 'ITGC-AC', 'ITGC-PC', 'ITGC-PD', 'ITGC-CO'), array_column($definition()['families'], 'code'), 'areas');
    },

    'controls are HCG-written with test points' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same('hcg', $control['source'], $control['id'].' source');
                Assert::true(count($control['objectives']) >= 2, $control['id'].' has test points');
            }
        }
    }
);
