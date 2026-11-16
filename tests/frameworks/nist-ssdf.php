<?php
/**
 * NIST SSDF 1.1. The parser reads the practices spreadsheet; these checks hold it
 * against Table 1 of SP 800-218 itself, in a reading-order extract of the PDF.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-ssdf.json'), true);
};

// Table cells wrap and hyphenate, so compare letters and digits only.
$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

return array(
    '4 practice groups, 19 practices and 42 tasks' => function () use ($definition) {
        $d = $definition();
        $practices = array();
        $tasks = array();

        foreach ($d['families'] as $family) {
            $practices[$family['function_code']] = ($practices[$family['function_code']] ?? 0) + 1;
            $tasks[$family['function_code']] = ($tasks[$family['function_code']] ?? 0) + count($family['controls']);
        }

        Assert::same(array(
            'PO' => 5,
            'PS' => 3,
            'PW' => 8,
            'RV' => 3
        ), $practices, 'practices per group');
        Assert::same(19, $d['counts']['families'], 'practices');
        Assert::same(42, $d['counts']['controls'], 'tasks');
        Assert::same(42, array_sum($tasks), 'tasks across groups');
    },

    'task identifiers are exactly those printed in Table 1 of SP 800-218' => function () use ($definition) {
        $text = file_get_contents(Source::path('ssdf/NIST.SP.800-218.raw.txt'));
        // Version 1.1 still prints the five tasks it relocated, as "PW.3.1: Moved to PO.1.3"; they are no longer tasks.
        preg_match_all('/^((?:PO|PS|PW|RV)\.\d+\.\d+): (?!Moved to )/m', $text, $m);
        $printed = array_values(array_unique($m[1]));
        Assert::same(5, preg_match_all('/^(?:PO|PS|PW|RV)\.\d+\.\d+: Moved to /m', $text), 'relocated tasks printed');
        $built = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $built[] = $control['id'];
            }
        }

        sort($printed);
        sort($built);
        Assert::same($printed, $built, 'task identifiers');
    },

    'practice names and task statements open as printed in SP 800-218' => function () use ($definition, $flat) {
        $text = $flat(file_get_contents(Source::path('ssdf/NIST.SP.800-218.raw.txt')));

        foreach ($definition()['families'] as $family) {
            Assert::true(strpos($text, $flat($family['name'].' ('.$family['code'].')')) !== false, $family['code'].' '.$family['name']);

            foreach ($family['controls'] as $control) {
                // A statement can run over a page turn or carry a footnote mark; its first four words do neither.
                $opening = implode(' ', array_slice(explode(' ', $control['text']), 0, 4));
                Assert::true(strpos($text, $flat($control['id'].': '.$opening)) !== false, $control['id'].' statement');
                Assert::true(strpos($control['text'], '[Formerly') === false, $control['id'].' text carries no relocation note');
            }
        }
    },

    'every task carries notional implementation examples' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(strpos($control['guidance'], 'Example 1:') === 0, $control['id'].' examples');
            }
        }
    }
);
