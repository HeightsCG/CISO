<?php
/**
 * NIST CSF 2.0. The parser reads the CSF 2.0 Reference Tool export; these checks hold
 * it against the CSF 2.0 publication itself (NIST CSWP 29): Table 1 for Functions and
 * Categories, Appendix A for Subcategory text, Appendix B and C for the Tiers.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-csf-2.json'), true);
};

$publication = function () {
    return Source::pdf_text('csf/NIST.CSWP.29.pdf');
};

// Wrapped and hyphenated PDF lines make spacing unreliable; compare letters and digits only.
$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

return array(
    '6 functions, 22 categories and 106 subcategories' => function () use ($definition) {
        $d = $definition();
        $functions = array();
        $per_function = array();

        foreach ($d['families'] as $family) {
            $functions[$family['function_code']] = $family['function'];
            $per_function[$family['function_code']] = ($per_function[$family['function_code']] ?? 0) + count($family['controls']);
        }

        Assert::same(array(
            'GV' => 'GOVERN',
            'ID' => 'IDENTIFY',
            'PR' => 'PROTECT',
            'DE' => 'DETECT',
            'RS' => 'RESPOND',
            'RC' => 'RECOVER'
        ), $functions, 'functions');
        Assert::same(22, $d['counts']['families'], 'categories');
        Assert::same(106, $d['counts']['controls'], 'subcategories');
        Assert::same(array(
            'GV' => 31,
            'ID' => 21,
            'PR' => 22,
            'DE' => 11,
            'RS' => 13,
            'RC' => 8
        ), $per_function, 'subcategories per function');
    },

    'functions and categories match Table 1 of CSWP 29' => function () use ($definition, $publication) {
        $table = Source::between($publication(), 'Function Category Category Identifier', 'Recover (RC)');
        $table .= Source::between($publication(), 'Recover (RC)', 'RC.CO')."RC.CO\n";
        $expected = array();
        $function = '';

        foreach (preg_split('/\R/', $table) as $line) {
            if (!preg_match('/^(?:([A-Z][a-z]+) \(([A-Z]{2})\) )?(.+) ([A-Z]{2}\.[A-Z]{2})$/', trim($line), $m)) {
                continue;
            }

            if ($m[1] !== '') {
                $function = strtoupper($m[1]);
            }

            $expected[$m[4]] = array(
                $function,
                $m[3]
            );
        }

        $actual = array();

        foreach ($definition()['families'] as $family) {
            $actual[$family['code']] = array(
                $family['function'],
                $family['name']
            );
        }

        Assert::same(22, count($expected), 'categories in Table 1');
        Assert::same($expected, $actual, 'function and name of each category');
    },

    'subcategory IDs and text match Appendix A of CSWP 29' => function () use ($definition, $publication, $flat) {
        $appendix = Source::between($publication(), "Appendix A. CSF Core\nThis appendix", 'Appendix B. CSF Tiers');
        preg_match_all('/^o ([A-Z]{2}\.[A-Z]{2}-\d{2}): (.+?)(?=^o [A-Z]{2}\.[A-Z]{2}-\d{2}:|^• |^[A-Z]+ \([A-Z]{2}\): |\z)/ms', $appendix, $m, PREG_SET_ORDER);
        $expected = array();

        foreach ($m as $entry) {
            // Drop running page headers that land inside an entry.
            $text = preg_replace('/NIST CSWP 29 The NIST Cybersecurity Framework \(CSF\) 2\.0\s+February 26, 2024\s+\d+\s*/', ' ', $entry[2]);
            $expected[$entry[1]] = $text;
        }

        Assert::same(106, count($expected), 'subcategories in Appendix A');
        $actual = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $actual[$control['id']] = $control['text'];
            }
        }

        Assert::same(array_keys($expected), array_keys($actual), 'subcategory IDs in order');

        foreach ($expected as $id => $text) {
            Assert::same($flat($text), $flat($actual[$id]), 'text of '.$id);
        }
    },

    'every subcategory carries implementation examples' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(strpos($control['guidance'], 'Ex1: ') === 0, $control['id'].' implementation examples');
            }
        }
    },

    'tier levels match CSWP 29 and their quotes appear in Appendix B' => function () use ($publication) {
        $text = Source::normalise($publication());
        preg_match('/There are four Tiers: (\w+) \(Tier 1\), ([\w ]+) \(Tier 2\), (\w+) \(Tier 3\), and (\w+) \(Tier 4\)\./', $text, $m);
        Assert::same(5, count($m), 'tier sentence found');
        $levels = FrameworkBuilder::meta('nist-csf-2')['scoring']['levels'];
        $tiers = Source::between($text, 'Table 2 contains a notional illustration of the CSF Tiers', 'Appendix C. Glossary');

        foreach ($levels as $i => $level) {
            Assert::same($i + 1, $level['level'], 'tier number');
            Assert::same($m[$i + 1], $level['label'], 'tier '.($i + 1).' label');
            Assert::true(strpos($tiers, Source::normalise($level['quote'])) !== false, 'tier '.$level['label'].' quote not in Appendix B');
        }
    }
);
