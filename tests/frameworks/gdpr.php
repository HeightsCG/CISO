<?php
/**
 * GDPR checks. The parser walks the article markup of the consolidated text; these read
 * the same articles as plain text and count paragraphs a second way.
 */
$file = 'gdpr/reg-2016-679-consolidated-2016-05-04.html';

$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/gdpr.json'), true);
};

$html = function () use ($file) {
    static $html = null;
    $html = $html ?? file_get_contents(Source::path($file));

    return $html;
};

// Markup of one article, from its heading to the next article's.
$markup = function (int $number) use ($html) {
    $start = strpos($html(), 'id="art_'.$number.'"');
    $end = strpos($html(), 'id="art_'.($number + 1).'"');

    return substr($html(), $start, $end - $start);
};

// Plain text of one article, consolidation marks removed.
$article = function (int $number) use ($markup) {
    $text = html_entity_decode(strip_tags($markup($number)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return Source::normalise(str_replace("\u{a0}", ' ', preg_replace('/►[A-Z]\d+|◄/u', ' ', $text)));
};

$excluded = array(
    '6(2)',
    '6(3)',
    '8(3)',
    '9(4)',
    '12(8)',
    '24(3)',
    '25(3)',
    '27(5)',
    '28(5)',
    '28(6)',
    '28(7)',
    '28(8)',
    '30(5)',
    '32(3)',
    '34(4)',
    '35(4)',
    '35(5)',
    '35(6)',
    '35(8)',
    '35(10)',
    '36(2)',
    '36(4)',
    '36(5)',
    '37(2)',
    '37(3)',
    '37(4)',
    '38(4)',
    '46(4)',
    '46(5)',
    '47(1)',
    '47(3)',
    '49(3)',
    '49(4)',
    '49(5)'
);

return array(
    'thirty-nine articles in four chapters' => function () use ($definition) {
        $chapters = array();

        foreach ($definition()['families'] as $family) {
            $chapters[$family['chapter']][] = (int) $family['number'];
        }

        Assert::same(array(
            'Chapter II' => range(5, 11),
            'Chapter III' => range(12, 22),
            'Chapter IV' => range(24, 39),
            'Chapter V' => array(44, 46, 47, 48, 49)
        ), $chapters, 'articles per chapter');
    },

    'every numbered paragraph is an obligation or a listed exclusion' => function () use ($definition, $markup, $excluded) {
        $total = 0;

        foreach ($definition()['families'] as $family) {
            $n = (int) $family['number'];
            $printed = preg_match_all('/<span class="no-parag">\s*\d+\.\s*<\/span>/u', $markup($n));
            $built = count($family['controls']);
            $left_out = count(array_filter($excluded, function ($ref) use ($n) {
                return strpos($ref, $n.'(') === 0;
            }));

            Assert::same(max($printed, 1), $built + $left_out, 'Article '.$n.' paragraphs');
            $total += $built;
        }

        Assert::same(120, $total, 'obligations');
    },

    'excluded paragraphs are addressed to someone else or grant an option' => function () use ($article, $excluded) {
        $openings = array(
            '6(2)' => 'Member States may maintain or introduce more specific provisions',
            '12(8)' => 'The Commission shall be empowered to adopt delegated acts',
            '28(7)' => 'The Commission may lay down standard contractual clauses',
            '30(5)' => 'The obligations referred to in paragraphs 1 and 2 shall not apply',
            '35(4)' => 'The supervisory authority shall establish and make public a list',
            '36(4)' => 'Member States shall consult the supervisory authority',
            '47(1)' => 'The competent supervisory authority shall approve binding corporate rules'
        );

        foreach ($openings as $ref => $opening) {
            Assert::true(in_array($ref, $excluded, true), $ref.' is listed');
            Assert::true(strpos($article((int) $ref), $opening) !== false, 'Article '.$ref.': '.$opening);
        }
    },

    'every obligation and element is printed in its article, without consolidation marks' => function () use ($definition, $article) {
        foreach ($definition()['families'] as $family) {
            $text = $article((int) $family['number']);
            Assert::true(strpos($text, $family['name']) !== false, $family['code'].' title');

            foreach ($family['controls'] as $control) {
                Assert::true(preg_match('/[►◄]/u', $control['text']) === 0, $control['id'].' carries no consolidation mark');
                $opening = implode(' ', array_slice(explode(' ', $control['text']), 0, 10));
                Assert::true(strpos($text, $opening) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $i => $objective) {
                    Assert::same($control['id'].'('.chr(ord('a') + $i).')', $objective['id'], $control['id'].' points run in order');
                    $opening = implode(' ', array_slice(explode(' ', $objective['text']), 0, 6));
                    Assert::true(strpos($text, $opening) !== false, $objective['id'].' text');
                }
            }
        }
    },

    'well-known lists have their published length' => function () use ($definition) {
        $points = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = count($control['objectives']);
            }
        }

        Assert::same(6, $points['Art. 5(1)'], 'processing principles');
        Assert::same(6, $points['Art. 6(1)'], 'lawful bases');
        Assert::same(10, $points['Art. 9(2)'], 'special category exceptions');
        Assert::same(8, $points['Art. 28(3)'], 'processor contract terms');
        Assert::same(7, $points['Art. 30(1)'], 'controller record contents');
        Assert::same(4, $points['Art. 32(1)'], 'security measures');
        Assert::same(4, $points['Art. 33(3)'], 'breach notification contents');
    }
);
