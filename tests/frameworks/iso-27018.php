<?php
/**
 * ISO/IEC 27018 HCG control set checks: every control in the preview's contents list is
 * assessed under its official number and title, and nothing of the standard's text is reproduced.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/iso-27018.json'), true);
};

return array(
    'every control in the contents list is assessed' => function () use ($definition) {
        $raw = file_get_contents(Source::path('iso27018/iso-iec-27018-2025-sample.raw.txt'));
        preg_match_all('/^([5-8]\.\d+) [A-Z]/m', substr($raw, 0, strpos($raw, 'Annex A')), $m);
        $built = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                if ($control['type'] === 'clause') {
                    $built[] = $control['id'];
                }
            }
        }

        Assert::same(array_values(array_unique($m[1])), $built, 'controls');
    },

    'the standard is not reproduced: controls carry HCG wording only' => function () use ($definition) {
        $d = $definition();

        Assert::same('hcg_authored', $d['content_licence'], 'licence');

        foreach ($d['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same('', $control['text'], $control['id'].' carries no standard text');
                Assert::true(strlen($control['description']) > 30 && strlen($control['guidance']) > 40, $control['id'].' has HCG description and guidance');
                Assert::true(count($control['objectives']) >= 1, $control['id'].' has test points');
            }
        }
    },

    'HCG areas are labelled as HCG' => function () use ($definition) {
        $areas = 0;

        foreach ($definition()['families'] as $family) {
            if (strpos($family['code'], 'HCG-') === 0) {
                $areas++;
                Assert::true(substr($family['name'], -6) === ' (HCG)', $family['code'].' name says HCG');
            }
        }

        Assert::same(7, $areas, 'HCG areas');
    }
);
