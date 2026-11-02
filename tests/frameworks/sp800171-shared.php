<?php
/**
 * Checks shared by NIST SP 800-171 Rev 2 and CMMC Level 2, which use the same
 * 110 requirements. Point values are checked against 32 CFR 170.24, a source
 * independent of the DoD Methodology annex the parser reads them from.
 */
return function (string $key, callable $id_for): array {
    $definition = function () use ($key) {
        return json_decode(file_get_contents(ROOT.'/frameworks/'.$key.'.json'), true);
    };

    $controls = function () use ($definition) {
        $all = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $control['family_code'] = $family['code'];
                $all[$control['number']] = $control;
            }
        }

        return $all;
    };

    return array(
        '110 requirements in 14 families with the published counts' => function () use ($definition) {
            $expected = array(
                '3.1' => 22,
                '3.2' => 3,
                '3.3' => 9,
                '3.4' => 9,
                '3.5' => 11,
                '3.6' => 3,
                '3.7' => 6,
                '3.8' => 9,
                '3.9' => 2,
                '3.10' => 6,
                '3.11' => 3,
                '3.12' => 4,
                '3.13' => 16,
                '3.14' => 7
            );
            $actual = array();

            foreach ($definition()['families'] as $family) {
                $actual[$family['number']] = count($family['controls']);
            }

            Assert::same($expected, $actual, 'requirements per family');
        },

        '320 assessment objectives from SP 800-171A' => function () use ($definition, $controls) {
            Assert::same(320, $definition()['counts']['objectives'], 'objective count');
            $raw = Source::pdf_text('cmmc/sp800-171a-2018.pdf');
            preg_match_all('/^(3\.\d+\.\d+\[[a-z]+\])/m', $raw, $m);
            $lettered = array_unique($m[1]);
            $loaded = array();

            foreach ($controls() as $control) {
                foreach ($control['objectives'] as $objective) {
                    $loaded[] = $objective['id'];
                }
            }

            Assert::same(array(), array_values(array_diff($lettered, $loaded)), 'lettered objectives in the source but not loaded');
            Assert::same(297, count($lettered), 'lettered objectives in the source');
        },

        'domain codes match 32 CFR 170.24' => function () use ($definition) {
            $expected = array(
                '3.1' => 'AC',
                '3.2' => 'AT',
                '3.3' => 'AU',
                '3.4' => 'CM',
                '3.5' => 'IA',
                '3.6' => 'IR',
                '3.7' => 'MA',
                '3.8' => 'MP',
                '3.9' => 'PS',
                '3.10' => 'PE',
                '3.11' => 'RA',
                '3.12' => 'CA',
                '3.13' => 'SC',
                '3.14' => 'SI'
            );

            foreach ($definition()['families'] as $family) {
                Assert::same($expected[$family['number']], $family['code'], 'domain code for '.$family['number']);
            }
        },

        'point values match 32 CFR 170.24 category lists' => function () use ($controls) {
            $cfr = Source::normalise(Source::xml_text('cmmc/32-cfr-170.xml'));
            $five = Source::between($cfr, 'five (5) points are subtracted from the maximum score. The basic and derived', '(2) For basic and derived security requirements');
            $three = Source::between($cfr, 'three (3) points are subtracted from the maximum score. The basic and derived', '(3) All remaining derived');
            $partial = Source::between($cfr, '(4) Two derived security requirements', '(i) Multi-factor authentication');
            $gate = Source::between($cfr, '(5) OSAs must have a System Security Plan', 'in place at the time of assessment');
            $numbers = function (string $text): array {
                preg_match_all('/[A-Z]{2}[.-]L2-\s?(3\.\d+\.\d+)/', $text, $m);

                return array_unique($m[1]);
            };
            $expected = array();

            foreach (array_keys($controls()) as $n) {
                $expected[$n] = array(1, null);
            }

            foreach ($numbers($five) as $n) {
                $expected[$n] = array(5, null);
            }

            foreach ($numbers($three) as $n) {
                $expected[$n] = array(3, null);
            }

            foreach ($numbers($partial) as $n) {
                $expected[$n] = array(5, 3);
            }

            foreach ($numbers($gate) as $n) {
                $expected[$n] = array(0, null);
            }

            Assert::same(42, count($numbers($five)), '5-point requirements listed in 170.24');
            Assert::same(14, count($numbers($three)), '3-point requirements listed in 170.24');

            foreach ($controls() as $n => $control) {
                Assert::same($expected[$n], array($control['weight'], $control['partial_weight']), 'point value for '.$n);
            }
        },

        'maximum and floor follow from the point values' => function () use ($definition, $controls) {
            $sum = 0;

            foreach ($controls() as $control) {
                $sum += $control['weight'];
            }

            $scoring = $definition()['scoring'];
            Assert::same(110, $scoring['maximum'], 'maximum');
            Assert::same(count($controls()), $scoring['maximum'], 'maximum equals the number of requirements');
            Assert::same($scoring['maximum'] - $sum, $scoring['floor'], 'floor');
            Assert::same(-203, $scoring['floor'], 'floor');
        },

        'control IDs use the framework numbering' => function () use ($controls, $id_for) {
            foreach ($controls() as $n => $control) {
                Assert::same($id_for($control), $control['id'], 'ID for '.$n);
            }
        }
    );
};
