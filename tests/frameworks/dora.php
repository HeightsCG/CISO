<?php
/**
 * DORA checks. The parser walks the article markup; these read the same articles as
 * plain text and count paragraphs and points a second way.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/dora.json'), true);
};

// Plain text of one article, from its heading to the next article's.
$article = function (int $number) {
    static $html = null;
    $html = $html ?? file_get_contents(Source::path('dora/reg-2022-2554.html'));
    $start = strpos($html, 'id="art_'.$number.'"');
    $end = strpos($html, 'id="art_'.($number + 1).'"');

    return Source::normalise(str_replace("\u{a0}", ' ', html_entity_decode(strip_tags(substr($html, $start, $end - $start)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
};

return array(
    'twenty articles in four chapters' => function () use ($definition) {
        $chapters = array();

        foreach ($definition()['families'] as $family) {
            $chapters[$family['chapter']][] = (int) $family['number'];
        }

        Assert::same(array(
            'Chapter II' => range(5, 14),
            'Chapter III' => array(17, 18, 19),
            'Chapter IV' => array(24, 25, 26, 27),
            'Chapter V' => array(28, 29, 30)
        ), $chapters, 'articles per chapter');
    },

    'every numbered paragraph is an obligation or a listed exclusion' => function () use ($definition, $article) {
        $excluded = array(
            '6(9)',
            '11(11)',
            '18(3)',
            '18(4)',
            '19(2)',
            '19(6)',
            '19(7)',
            '19(8)',
            '26(7)',
            '26(9)',
            '26(10)',
            '26(11)',
            '28(9)',
            '28(10)',
            '30(5)'
        );
        $total = 0;

        foreach ($definition()['families'] as $family) {
            $n = (int) $family['number'];
            $html = file_get_contents(Source::path('dora/reg-2022-2554.html'));
            preg_match_all('/id="'.sprintf('%03d', $n).'\.\d{3}"/', $html, $ids);
            $printed = count(array_unique($ids[0]));
            $built = count($family['controls']);
            $left_out = count(array_filter($excluded, function ($ref) use ($n) {
                return strpos($ref, $n.'(') === 0;
            }));

            Assert::same(max($printed, 1), $built + $left_out, 'Article '.$n.' paragraphs');
            $total += $built;
        }

        Assert::same(98, $total, 'obligations');
    },

    'excluded paragraphs are addressed to someone else or grant an option' => function () use ($article) {
        foreach (array(
            11 => '11. In accordance with Article 16 of Regulations',
            18 => '3. The ESAs shall',
            19 => '6. Upon receipt of the initial notification',
            26 => '11. The ESAs shall',
            28 => '9. The ESAs shall',
            30 => '5. The ESAs shall',
            6 => '9. Financial entities may'
        ) as $n => $opening) {
            Assert::true(strpos($article($n), $opening) !== false, 'Article '.$n.': '.$opening);
        }
    },

    'every obligation and element is printed in its article' => function () use ($definition, $article) {
        foreach ($definition()['families'] as $family) {
            $text = $article((int) $family['number']);
            Assert::true(strpos($text, 'Article '.$family['number'].' '.$family['name']) === 0 || strpos($text, $family['name']) !== false, $family['code'].' title');

            foreach ($family['controls'] as $control) {
                $opening = implode(' ', array_slice(explode(' ', $control['text']), 0, 12));
                Assert::true(strpos($text, $opening) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $objective) {
                    $opening = implode(' ', array_slice(explode(' ', $objective['text']), 0, 8));
                    Assert::true(strpos($text, $opening) !== false, $objective['id'].' text');
                    Assert::true(strpos($objective['id'], $control['id'].'(') === 0, $objective['id'].' belongs to '.$control['id']);
                }
            }
        }
    },

    'lettered points are counted the same way in the plain text' => function () use ($definition, $article) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $labels = array();

                foreach ($control['objectives'] as $i => $objective) {
                    $labels[] = substr($objective['id'], strlen($control['id']));
                    Assert::same('('.chr(ord('a') + $i).')', $labels[$i], $control['id'].' points run in order');
                }
            }
        }

        // Article 30(2) and 30(3): the key contractual provisions, counted in the text.
        $text = $article(30);
        $two = substr($text, strpos($text, '2. The contractual arrangements'), strpos($text, '3. The contractual arrangements') - strpos($text, '2. The contractual arrangements'));
        $built = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $built[$control['id']] = count($control['objectives']);
            }
        }

        Assert::same(preg_match_all('/ \([a-z]\) /', $two), $built['Art. 30(2)'], 'Article 30(2) points');
    },

    'microenterprise carve-outs are flagged from the text' => function () use ($definition) {
        $flagged = 0;

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same(strpos($control['text'], 'other than microenterprises') !== false, $control['excludes_microenterprises'], $control['id'].' flag');
                $flagged += $control['excludes_microenterprises'] ? 1 : 0;
            }
        }

        Assert::same(19, $flagged, 'paragraphs that exclude microenterprises');
    }
);
