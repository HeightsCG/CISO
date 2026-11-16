<?php
/**
 * NIST Privacy Framework 1.0. The parser reads the Reference Tool export; these checks
 * hold it against the publication itself (Table 2 in Appendix A, Table 1 for the names).
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-privacy-framework.json'), true);
};

// Table cells wrap inside three columns, so compare letters and digits only.
$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

return array(
    '5 functions, 18 categories and 100 subcategories' => function () use ($definition) {
        $d = $definition();
        $functions = array();
        $per_function = array();

        foreach ($d['families'] as $family) {
            $functions[$family['function_code']] = $family['function'];
            $per_function[$family['function_code']] = ($per_function[$family['function_code']] ?? 0) + count($family['controls']);
        }

        Assert::same(array(
            'ID-P' => 'IDENTIFY-P',
            'GV-P' => 'GOVERN-P',
            'CT-P' => 'CONTROL-P',
            'CM-P' => 'COMMUNICATE-P',
            'PR-P' => 'PROTECT-P'
        ), $functions, 'functions');
        Assert::same(18, $d['counts']['families'], 'categories');
        Assert::same(100, $d['counts']['controls'], 'subcategories');
        Assert::same(100, array_sum($per_function), 'subcategories across functions');
    },

    'subcategories are numbered from 1 without gaps inside each category' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $i => $control) {
                Assert::same($family['code'].($i + 1), $control['id'], $family['code'].' numbering');
            }
        }
    },

    'every category and subcategory statement matches Table 2 of the publication' => function () use ($definition, $flat) {
        // The reading-order extract keeps each table cell together, which the layout extract does not.
        $text = $flat(file_get_contents(Source::path('privacy-framework/NIST.CSWP.01162020.raw.txt')));

        foreach ($definition()['families'] as $family) {
            // A statement that runs over a page turn is not contiguous; its opening and closing words are.
            $words = explode(' ', $family['description']);
            Assert::true(strpos($text, $flat($family['name'].' ('.$family['code'].'): '.implode(' ', array_slice($words, 0, 4)))) !== false, $family['code'].' '.$family['name']);
            Assert::true(strpos($text, $flat(implode(' ', array_slice($words, -4)))) !== false, $family['code'].' closing words');

            foreach ($family['controls'] as $control) {
                Assert::true(strpos($text, $flat($control['id'].': '.$control['text'])) !== false, $control['id'].' statement');
            }
        }
    },

    'every subcategory identifier is printed in Table 2 of the publication' => function () use ($definition) {
        $text = Source::pdf_text('privacy-framework/NIST.CSWP.01162020.pdf');
        preg_match_all('/\b[A-Z]{2}\.[A-Z]{2}-P\d+(?=:)/', substr($text, strpos($text, 'Table 2: Privacy Framework Core', 8000)), $m);
        $printed = array_values(array_unique($m[0]));
        $built = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $built[] = $control['id'];
            }
        }

        sort($printed);
        sort($built);
        Assert::same($printed, $built, 'subcategory identifiers');
    }
);
