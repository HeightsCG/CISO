<?php
/**
 * Cyber Resilience Act checks. The parser walks the article and annex markup; these read
 * the same parts as plain text and count items a second way.
 */
$file = 'eu-cra/reg-2024-2847.html';

$families = function () {
    $d = json_decode(file_get_contents(ROOT.'/frameworks/eu-cra.json'), true);
    $all = array();

    foreach ($d['families'] as $family) {
        $all[$family['code']] = $family;
    }

    return $all;
};

$html = function () use ($file) {
    static $html = null;
    $html = $html ?? file_get_contents(Source::path($file));

    return $html;
};

// Markup between two element ids.
$markup = function (string $from, string $to) use ($html) {
    $start = strpos($html(), 'id="'.$from.'"');
    $end = strpos($html(), 'id="'.$to.'"');

    return substr($html(), $start, $end - $start);
};

$plain = function (string $markup) {
    return Source::normalise(str_replace("\u{a0}", ' ', html_entity_decode(strip_tags($markup), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
};

return array(
    'ten sections, each bound to an economic operator' => function () use ($families) {
        $roles = array();

        foreach ($families() as $code => $family) {
            $roles[$code] = $family['role'];
        }

        Assert::same(array(
            'Art. 13' => 'manufacturer',
            'Art. 14' => 'manufacturer',
            'Annex I, Part I' => 'manufacturer',
            'Annex I, Part II' => 'manufacturer',
            'Annex II' => 'manufacturer',
            'Annex VII' => 'manufacturer',
            'Art. 19' => 'importer',
            'Art. 20' => 'distributor',
            'Art. 23' => 'any',
            'Art. 24' => 'open-source software steward'
        ), $roles, 'sections and roles');
    },

    'Annex I: one general requirement, thirteen product requirements, eight vulnerability handling requirements' => function () use ($families, $markup, $plain) {
        $all = $families();
        $ids = array_column($all['Annex I, Part I']['controls'], 'id');

        Assert::same('Annex I, Part I(1)', $ids[0], 'general requirement');
        Assert::same(14, count($ids), 'Part I requirements');
        Assert::same('Annex I, Part I(2)(m)', end($ids), 'last product requirement');
        Assert::same(8, count($all['Annex I, Part II']['controls']), 'Part II requirements');

        // Counted in the plain text of Annex I.
        $text = $plain($markup('anx_I', 'anx_II'));
        $part_one = substr($text, 0, strpos($text, 'Part II Vulnerability handling requirements'));
        $part_two = substr($text, strpos($text, 'Part II Vulnerability handling requirements'));
        Assert::same(13, preg_match_all('/ \([a-m]\) /', $part_one), 'lettered product requirements printed');
        Assert::same(8, preg_match_all('/ \([1-8]\) /', $part_two), 'vulnerability handling requirements printed');
    },

    'every numbered paragraph of the articles is a requirement or a listed exclusion' => function () use ($families, $markup) {
        $excluded = array(
            13 => 3,
            14 => 4
        );

        foreach (array(13, 14, 19, 20, 23, 24) as $n) {
            preg_match_all('/id="'.sprintf('%03d', $n).'\.\d{3}"/', $markup('art_'.$n, 'art_'.($n + 1)), $ids);
            Assert::same(count(array_unique($ids[0])), count($families()['Art. '.$n]['controls']) + ($excluded[$n] ?? 0), 'Article '.$n.' paragraphs');
        }
    },

    'every requirement and element is printed in its section' => function () use ($families, $markup, $plain) {
        $bounds = array(
            'Annex I, Part I' => array('anx_I', 'anx_II'),
            'Annex I, Part II' => array('anx_I', 'anx_II'),
            'Annex II' => array('anx_II', 'anx_III'),
            'Annex VII' => array('anx_VII', 'anx_VIII')
        );

        foreach ($families() as $code => $family) {
            $range = $bounds[$code] ?? array('art_'.$family['number'], 'art_'.((int) $family['number'] + 1));
            $text = $plain($markup($range[0], $range[1]));

            foreach ($family['controls'] as $control) {
                $opening = implode(' ', array_slice(explode(' ', $control['text']), 0, 8));
                Assert::true(strpos($text, $opening) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $objective) {
                    $opening = implode(' ', array_slice(explode(' ', $objective['text']), 0, 5));
                    Assert::true(strpos($text, $opening) !== false, $objective['id'].' text');
                    Assert::true(strpos($objective['id'], $control['id'].'(') === 0, $objective['id'].' belongs to '.$control['id']);
                }
            }
        }
    },

    'annex items that complete a sentence read alone' => function () use ($families) {
        $all = $families();

        foreach ($all['Annex I, Part II']['controls'] as $control) {
            Assert::true(strpos($control['description'], 'Manufacturers of products with digital elements shall: ') === 0, $control['id'].' description');
        }

        foreach ($all['Annex I, Part I']['controls'] as $control) {
            if ($control['id'] !== 'Annex I, Part I(1)') {
                Assert::true(strpos($control['description'], 'products with digital elements shall: ') !== false, $control['id'].' description');
            }
        }
    },

    'reporting stages carry their deadlines' => function () use ($families) {
        $stages = array();

        foreach ($families()['Art. 14']['controls'] as $control) {
            $stages[$control['id']] = $control;
        }

        Assert::same(3, count($stages['Art. 14(2)']['objectives']), 'vulnerability reporting stages');
        Assert::true(strpos($stages['Art. 14(2)']['text'], 'within 24 hours') !== false && strpos($stages['Art. 14(2)']['text'], 'within 72 hours') !== false && strpos($stages['Art. 14(2)']['text'], 'no later than 14 days') !== false, 'vulnerability deadlines');
        Assert::true(strpos($stages['Art. 14(4)']['text'], 'within one month') !== false, 'incident final report');
    }
);
