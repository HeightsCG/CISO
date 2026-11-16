<?php
/**
 * COPPA Rule checks. The parser builds a paragraph tree from the labels; these re-derive
 * the lettered paragraphs and their text from the plain text instead.
 */
$file = 'coppa/16-cfr-312.xml';

$families = function () {
    $d = json_decode(file_get_contents(ROOT.'/frameworks/coppa.json'), true);
    $all = array();

    foreach ($d['families'] as $family) {
        $all[$family['number']] = $family;
    }

    return $all;
};

// Plain text of one section, one paragraph per line.
$section = function (string $number, string $next) use ($file) {
    $text = Source::xml_text($file);

    return Source::between($text, '§ '.$number.' ', '§ '.$next.' ');
};

$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

$bounds = array(
    '312.4' => '312.5',
    '312.5' => '312.6',
    '312.6' => '312.7',
    '312.7' => '312.8',
    '312.8' => '312.9',
    '312.10' => '312.11'
);

return array(
    'six sections and thirteen requirements' => function () use ($families) {
        $counts = array();

        foreach ($families() as $number => $family) {
            $counts[$number] = count($family['controls']);
        }

        Assert::same(array(
            '312.4' => 4,
            '312.5' => 3,
            '312.6' => 1,
            '312.7' => 1,
            '312.8' => 3,
            '312.10' => 1
        ), $counts, 'requirements per section');
    },

    'lettered paragraphs match the plain text' => function () use ($families, $section, $bounds) {
        $left_out = array(
            '312.6' => 2
        );

        foreach ($families() as $number => $family) {
            $printed = preg_match_all('/^\([a-h]\) /m', $section($number, $bounds[$number]));
            $expected = $printed === 0 ? 1 : $printed - ($left_out[$number] ?? 0);
            Assert::same($expected, count($family['controls']), $number.' lettered paragraphs');
        }
    },

    'what 312.6 leaves out states no duty' => function () use ($section) {
        $text = $section('312.6', '312.7');

        Assert::true(strpos($text, "(b) Neither an operator nor the operator's agent shall be held liable") !== false, '312.6(b) is a liability shield');
        Assert::true(strpos($text, '(c) Subject to the limitations set forth in § 312.7, an operator may terminate') !== false, '312.6(c) is an option');
    },

    'every paragraph of the six sections is carried by a requirement' => function () use ($families, $section, $bounds, $flat) {
        foreach ($families() as $number => $family) {
            $carried = '';

            foreach ($family['controls'] as $control) {
                $carried .= $flat($control['title'].' '.$control['text']);

                foreach ($control['objectives'] as $objective) {
                    $carried .= $flat($objective['text']);
                    Assert::true(strpos($objective['id'], $control['id'].'(') === 0, $objective['id'].' belongs to '.$control['id']);
                }
            }

            foreach (explode("\n", $section($number, $bounds[$number])) as $line) {
                $line = trim($line);

                if ($line === '' || strpos($line, '§') === 0 || $line[0] === '[' || ($number === '312.6' && preg_match('/^\((b|c)\) /', $line))) {
                    continue;
                }

                $sentences = preg_split('/(?<=[.:;])\s+/', $line);
                $last = $flat(preg_replace('/^(\([a-zA-Z0-9]{1,4}\)\s*)+/', '', (string) end($sentences)));

                if (strlen($last) < 25) {
                    continue;
                }

                Assert::true(strpos($carried, $last) !== false, $number.' not carried: '.substr($line, 0, 80));
            }
        }
    },

    'consent methods and exceptions are listed as elements' => function () use ($families) {
        $controls = array();

        foreach ($families()['312.5']['controls'] as $control) {
            $controls[$control['id']] = $control;
        }

        Assert::same('Methods for verifiable parental consent', $controls['312.5(b)']['title'], '312.5(b) title');
        Assert::same(3, count($controls['312.5(b)']['objectives']), '312.5(b) elements');
        Assert::same('Exceptions to prior parental consent', $controls['312.5(c)']['title'], '312.5(c) title');
        Assert::true(count($controls['312.5(c)']['objectives']) >= 8, '312.5(c) exceptions');
    }
);
