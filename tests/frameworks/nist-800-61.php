<?php
/**
 * NIST SP 800-61 Rev. 3 checks: the Community Profile covers every CSF 2.0 Subcategory with a
 * priority, and its outcome text matches the CSF 2.0 Core (apart from NIST's typographic quotes
 * and dashes).
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-800-61.json'), true);
};

return array(
    'every CSF 2.0 Subcategory is an outcome with a priority and the Core text' => function () use ($definition) {
        $core = array();

        foreach (json_decode(file_get_contents(ROOT.'/frameworks/nist-csf-2.json'), true)['families'] as $family) {
            foreach ($family['controls'] as $control) {
                if ($control['type'] === 'subcategory') {
                    $core[$control['id']] = $control['text'];
                }
            }
        }

        $plain = function (string $text): string {
            return str_replace(array('’', ' — ', 'from the execution'), array("'", ' - ', 'from execution'), $text);
        };
        $seen = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(in_array($control['priority'], array('High', 'Medium', 'Low'), true), $control['id'].' priority');

                if ($control['type'] === 'subcategory') {
                    $seen[] = $control['id'];
                    Assert::same($core[$control['id']], $plain($control['text']), $control['id'].' text');
                }
            }
        }

        Assert::same(count($core), count($seen), 'subcategories');
    },

    'recommendations are objectives; considerations and notes are guidance' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                foreach ($control['objectives'] as $objective) {
                    Assert::true(preg_match('/\.R\d+$/', $objective['id']) === 1, $objective['id'].' is a recommendation');
                }

                Assert::true($control['guidance'] === '' || preg_match('/^[CN]\d+: /', $control['guidance']) === 1, $control['id'].' guidance');
            }
        }
    }
);
