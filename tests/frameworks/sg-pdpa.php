<?php
/**
 * Singapore PDPA checks. The parser reads sections by their layout in the PDF extract;
 * these hold the result against a reading-order extract of the same PDF.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/sg-pdpa.json'), true);
};

$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

$raw = function () use ($flat) {
    static $raw = null;

    if ($raw === null) {
        $text = file_get_contents(Source::path('sg-pdpa/PDPA2012-2026-10-16.raw.txt'));
        $text = preg_replace('/Informal Consolidation – version in force from 16\/10\/2026|Personal Data Protection\s+\d*\s*Act 2012\s*(2020 Ed\.)?\s*\d*|\[\d+\/\d+\]/u', ' ', $text);
        $raw = $flat($text);
    }

    return $raw;
};

return array(
    'nineteen sections across Parts 3 to 6A' => function () use ($definition) {
        $parts = array();

        foreach ($definition()['families'] as $family) {
            $parts[substr($family['part'], 0, strpos($family['part'], ':'))][] = $family['number'];
        }

        Assert::same(array(
            'Part 3' => array('11', '12'),
            'Part 4' => array('13', '14', '15A', '16', '18', '20'),
            'Part 5' => array('21', '22', '22A'),
            'Part 6' => array('23', '24', '25', '26'),
            'Part 6A' => array('26C', '26D', '26E')
        ), $parts, 'sections per Part');
    },

    'every obligation says must, or permits only on conditions' => function () use ($definition) {
        $total = 0;

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(preg_match('/\bmust\b/', $control['text']) === 1 || preg_match('/\bmay\b.{0,80}\bonly\b/', $control['text']) === 1, $control['id'].' states a duty');
                Assert::true(strpos($control['text'], 'The Commission') !== 0, $control['id'].' is not a duty of the Commission');
                $total++;
            }
        }

        Assert::same(40, $total, 'obligations');
    },

    'every obligation and element matches the reading-order extract' => function () use ($definition, $raw, $flat) {
        $text = $raw();

        foreach ($definition()['families'] as $family) {
            Assert::true(strpos($text, $flat($family['name'])) !== false, $family['code'].' heading');

            foreach ($family['controls'] as $control) {
                $opening = implode(' ', array_slice(explode(' ', $control['text']), 0, 10));
                Assert::true(strpos($text, $flat($opening)) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $i => $objective) {
                    Assert::same($control['id'].'('.chr(ord('a') + $i).')', $objective['id'], $control['id'].' paragraphs run in order');
                    // A paragraph can run over a page turn; its first eight words do not.
                    Assert::true(strpos($text, $flat(implode(' ', array_slice(explode(' ', $objective['text']), 0, 8)))) !== false, $objective['id'].' text');
                }
            }
        }
    },

    'well-known contents have their published length' => function () use ($definition) {
        $points = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = $control;
            }
        }

        Assert::same(4, count($points['s 12']['objectives']), 'policies and practices');
        Assert::same(2, count($points['s 24']['objectives']), 'protection of personal data');
        Assert::true(strpos($points['s 26D(1)']['text'], 'no later than 3 calendar days') !== false, 'breach notification deadline');
    }
);
