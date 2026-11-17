<?php
/**
 * NIST SP 800-218A checks: every task has a priority; tasks NIST did not mark as modified or
 * new read as in SSDF 1.1 (apart from typographic quotes and dashes); recommendations are the
 * objectives.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-800-218a.json'), true);
};

return array(
    'unmarked tasks match SSDF 1.1' => function () use ($definition) {
        $ssdf = array();

        foreach (json_decode(file_get_contents(ROOT.'/frameworks/nist-ssdf.json'), true)['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $ssdf[$control['id']] = $control['text'];
            }
        }

        // 800-218A rewords PO.5.2 slightly without marking it as modified.
        $reworded = array('PO.5.2');
        $plain = function (string $text): string {
            return str_replace(array('’', ' – '), array("'", ' - '), $text);
        };
        $checked = 0;

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(in_array($control['priority'], array('High', 'Medium', 'Low'), true), $control['id'].' priority');

                if (preg_match('/\[(?:Modified from|Not part of) SSDF 1\.1\]$/', $control['text']) || in_array($control['id'], $reworded, true)) {
                    continue;
                }

                Assert::same($ssdf[$control['id']], $plain($control['text']), $control['id'].' text');
                $checked++;
            }
        }

        Assert::true($checked >= 30, 'most tasks checked against SSDF 1.1');
    },

    'recommendations are objectives; considerations, notes and references are guidance' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                foreach ($control['objectives'] as $objective) {
                    Assert::true(preg_match('/\.R\d+$/', $objective['id']) === 1, $objective['id'].' is a recommendation');
                }
            }
        }
    }
);
