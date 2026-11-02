<?php
/**
 * SOC 2 checks. The AICPA PDF is only on HCG machines: when present, the
 * committed criteria list is re-derived from it, rule quotes are checked, and
 * HCG wording must not copy eight or more consecutive words of the criteria.
 */
require_once ROOT.'/frameworks/parsers/soc2-extract.php';

$pdf = ROOT.'/frameworks/sources/soc2/Trust-services-criteria.pdf';
$txt = ROOT.'/frameworks/sources/soc2/Trust-services-criteria.txt';

$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/soc2.json'), true);
};

return array(
    '61 criteria in 13 categories with the published counts' => function () use ($definition) {
        $expected = array(
            'CC1' => 5,
            'CC2' => 3,
            'CC3' => 4,
            'CC4' => 2,
            'CC5' => 3,
            'CC6' => 8,
            'CC7' => 5,
            'CC8' => 1,
            'CC9' => 2,
            'A1' => 3,
            'C1' => 2,
            'PI1' => 5,
            'P' => 18
        );
        $actual = array();

        foreach ($definition()['families'] as $family) {
            $actual[$family['code']] = count($family['controls']);
        }

        Assert::same($expected, $actual, 'criteria per category');
        Assert::same(330, $definition()['counts']['objectives'], 'points of focus');
    },

    'no AICPA criteria text is stored' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same('', $control['text'], $control['id'].' stores criteria text');
                Assert::true(trim($control['description']) !== '' && trim($control['guidance']) !== '', $control['id'].' needs HCG description and guidance');
            }
        }
    },

    'group names appear in the source' => function () use ($txt) {
        if (!is_file($txt)) {
            return;
        }

        $text = strtolower(Source::normalise(file_get_contents($txt)));

        foreach (soc2_groups() as $name) {
            Assert::true(strpos($text, strtolower($name)) !== false, 'group '.$name.' not in the source');
        }
    },

    'licensed copy: committed list matches a fresh extract' => function () use ($pdf, $txt) {
        if (!is_file($pdf) || !is_file($txt)) {
            return;
        }

        $list = json_decode(file_get_contents(ROOT.'/frameworks/sources/soc2/tsc-2017-ids.json'), true);
        Assert::same($list['pdf_sha256'], hash_file('sha256', $pdf), 'AICPA PDF checksum');
        Assert::same($list['criteria'], soc2_extract(file_get_contents($txt)), 'criteria list');
        $raw = file_get_contents($txt);
        $from = strpos($raw, 'CC1.1 COSO Principle 1:');
        $to = strpos($raw, 'Appendix A — Glossary', $from);
        $bullets = substr_count(substr($raw, $from, $to - $from), '•');

        Assert::same($bullets, array_sum(array_map(function ($c) {
            return count($c['points_of_focus']);
        }, $list['criteria'])), 'every bulleted point of focus is extracted');
    },

    'licensed copy: rule quotes appear in the criteria' => function () use ($txt) {
        if (!is_file($txt)) {
            return;
        }

        $text = Source::normalise(file_get_contents($txt));

        foreach (FrameworkBuilder::meta('soc2')['rules'] as $rule) {
            Assert::true(strpos($text, Source::normalise($rule['quote'])) !== false, $rule['id'].' quote not found');
        }
    },

    'licensed copy: HCG wording does not copy the criteria' => function () use ($txt, $definition) {
        if (!is_file($txt)) {
            return;
        }

        $text = ' '.strtolower(preg_replace('/[^a-z0-9]+/i', ' ', file_get_contents($txt))).' ';

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                foreach (array('title', 'description', 'guidance') as $field) {
                    $words = preg_split('/\s+/', trim(strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $control[$field]))));

                    for ($i = 0; $i + 8 <= count($words); $i++) {
                        $run = ' '.implode(' ', array_slice($words, $i, 8)).' ';
                        Assert::true(strpos($text, $run) === false, $control['id'].' '.$field.' copies the criteria: "'.trim($run).'"');
                    }
                }
            }
        }
    }
);
