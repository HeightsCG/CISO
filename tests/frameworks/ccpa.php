<?php
/**
 * CCPA checks. The parser reads subdivisions by their position in the layout-preserving
 * extract; these hold the result against a second, reading-order extract of the same PDF.
 */
$families = function () {
    $d = json_decode(file_get_contents(ROOT.'/frameworks/ccpa.json'), true);
    $all = array();

    foreach ($d['families'] as $family) {
        $all[$family['number']] = $family;
    }

    return $all;
};

$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

// Reading-order text of one section, page furniture removed, letters and digits only.
$section = function (string $number, string $next) use ($flat) {
    static $raw = null;

    if ($raw === null) {
        $raw = file_get_contents(Source::path('ccpa/ccpa_statute.raw.txt'));
        $raw = preg_replace('/Page \d+ of \d+/', ' ', $raw);
    }

    $start = strrpos($raw, "\n".$number.'. ');
    $end = strrpos($raw, "\n".$next.'. ');
    Assert::true($start !== false && $end !== false && $end > $start, 'section '.$number.' is in the reading-order extract');

    return $flat(substr($raw, $start, $end - $start));
};

$bounds = array(
    '1798.100' => '1798.105',
    '1798.105' => '1798.106',
    '1798.106' => '1798.110',
    '1798.110' => '1798.115',
    '1798.115' => '1798.120',
    '1798.120' => '1798.121',
    '1798.121' => '1798.125',
    '1798.125' => '1798.130',
    '1798.130' => '1798.135',
    '1798.135' => '1798.140'
);

return array(
    'ten sections with the subdivisions that state duties' => function () use ($families) {
        $subdivisions = array();

        foreach ($families() as $number => $family) {
            $subdivisions[$number] = implode('', array_map(function ($c) use ($number) {
                return substr($c['id'], strlen($number) + 1, 1);
            }, $family['controls']));
        }

        Assert::same(array(
            '1798.100' => 'abcde',
            '1798.105' => 'abc',
            '1798.106' => 'abc',
            '1798.110' => 'abc',
            '1798.115' => 'abcd',
            '1798.120' => 'abcd',
            '1798.121' => 'abc',
            '1798.125' => 'a',
            '1798.130' => 'a',
            '1798.135' => 'abcef'
        ), $subdivisions, 'subdivisions per section');
    },

    'section headings are the published ones' => function () use ($families) {
        $all = $families();

        Assert::same('General Duties of Businesses that Collect Personal Information', $all['1798.100']['name'], '1798.100');
        Assert::same("Consumers' Right to Know What Personal Information is Being Collected. Right to Access Personal Information", $all['1798.110']['name'], '1798.110 heading wraps onto a second line');
        Assert::same('Notice, Disclosure, Correction, and Deletion Requirements', $all['1798.130']['name'], '1798.130');
    },

    'every requirement and element matches the reading-order extract' => function () use ($families, $section, $bounds, $flat) {
        foreach ($families() as $number => $family) {
            $text = $section($number, $bounds[$number]);

            foreach ($family['controls'] as $control) {
                // A subdivision can run over a page turn; its first ten words do not.
                $opening = implode(' ', array_slice(explode(' ', preg_replace('/^\(\d+\) /', '', $control['text'])), 0, 10));
                Assert::true(strpos($text, $flat($opening)) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $i => $objective) {
                    Assert::same($control['id'].'('.($i + 1).')', $objective['id'], $control['id'].' paragraphs run in order');
                    $opening = implode(' ', array_slice(explode(' ', $objective['text']), 0, 8));
                    Assert::true(strpos($text, $flat($opening)) !== false, $objective['id'].' text');
                }
            }
        }
    },

    'what is left out states no duty' => function () use ($section, $bounds, $flat) {
        $openings = array(
            '1798.105' => '(d) A business, or a service provider or contractor acting pursuant to its contract',
            '1798.125' => '(b) (1) A business may offer financial incentives',
            '1798.130' => '(b) A business is not obligated to provide the information required',
            '1798.135' => '(d) Nothing in this title shall be construed to require a business'
        );

        foreach ($openings as $number => $opening) {
            Assert::true(strpos($section($number, $bounds[$number]), $flat($opening)) !== false, $number.': '.$opening);
        }
    },

    'the 45-day response period sits in 1798.130(a)' => function () use ($families) {
        foreach ($families()['1798.130']['controls'] as $control) {
            Assert::true(strpos($control['text'], 'within 45 days of receiving a verifiable consumer request') !== false, '1798.130(a) carries the deadline');
            Assert::same(7, count($control['objectives']), '1798.130(a) paragraphs');
        }
    }
);
