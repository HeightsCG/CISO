<?php
/**
 * CIS Controls HCG control set checks: all 18 Controls are areas, and every control is
 * HCG-written and states its Implementation Group.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/cis-controls.json'), true);
};

return array(
    'all 18 CIS Controls are areas, in order' => function () use ($definition) {
        $codes = array_column($definition()['families'], 'code');

        Assert::same(18, count($codes), 'areas');
        Assert::same('CIS-01', $codes[0], 'first');
        Assert::same('CIS-18', $codes[17], 'last');
    },

    'controls are HCG-written and state their Implementation Group' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same('hcg', $control['source'], $control['id'].' source');
                Assert::true(preg_match('/Implementation group: IG[123]/', $control['guidance']) === 1, $control['id'].' states its IG');
            }
        }
    }
);
