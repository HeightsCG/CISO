<?php
/**
 * UK GDPR checks. The parser walks the provision tree of the legislation.gov.uk XML;
 * these read the same articles as plain text and count paragraphs a second way.
 */
$file = 'uk-gdpr/eur-2016-679.xml';

$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/uk-gdpr.json'), true);
};

$xml = function () use ($file) {
    static $xml = null;
    $xml = $xml ?? file_get_contents(Source::path($file));

    return $xml;
};

// Markup of one article, from its <P1> to the end of that element's group.
$markup = function (string $article) use ($xml) {
    $start = strpos($xml(), 'id="article-'.$article.'"');
    Assert::true($start !== false, 'Article '.$article.' is in the text');
    $end = strpos($xml(), '</P1group>', $start);

    return substr($xml(), $start, $end - $start);
};

// Plain text of one article with footnote anchors and repeal dots removed.
$article = function (string $article) use ($markup) {
    $text = html_entity_decode(strip_tags(preg_replace('/<CommentaryRef[^>]*\/>/', '', $markup($article))), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = Source::normalise(str_replace("\u{a0}", ' ', $text));

    return preg_replace('/[^a-z0-9]+/i', '', $text);
};

$flat = function (string $text): string {
    return preg_replace('/[^a-z0-9]+/i', '', $text);
};

return array(
    'forty articles in four chapters, with the United Kingdom additions' => function () use ($definition) {
        $chapters = array();

        foreach ($definition()['families'] as $family) {
            $chapters[$family['chapter']][] = $family['number'];
        }

        Assert::same(array('5', '6', '7', '8', '8A', '9', '10', '11'), $chapters['Chapter II'], 'Chapter II');
        Assert::true(in_array('22B', $chapters['Chapter III'], true) && in_array('22C', $chapters['Chapter III'], true) && !in_array('22', $chapters['Chapter III'], true), 'automated decision-making articles');
        Assert::same(array('44A', '46', '47', '49'), $chapters['Chapter V'], 'Chapter V');
        Assert::same(40, count($definition()['families']), 'articles');
    },

    'repealed articles are gone from the text' => function () use ($markup) {
        foreach (array('22', '44', '45', '48') as $n) {
            Assert::true(preg_match('/<P2\b/', $markup($n)) === 0, 'Article '.$n.' has no paragraphs');
        }
    },

    'paragraph counts match the text and every kept paragraph exists' => function () use ($definition, $markup) {
        $total = 0;

        foreach ($definition()['families'] as $family) {
            $printed = preg_match_all('/<P2 [^>]*id="article-'.preg_quote($family['number'], '/').'-[0-9A-Z]+"/', $markup($family['number']));
            Assert::true($family['paragraphs_in_text'] <= max($printed, 1), $family['code'].' paragraphs in the text');
            Assert::true(count($family['controls']) <= $family['paragraphs_in_text'], $family['code'].' keeps no more than the text holds');
            $total += count($family['controls']);
        }

        Assert::same(130, $total, 'obligations');
    },

    'no kept paragraph is a power to make regulations' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(strpos($control['text'], 'The Secretary of State may by regulations') !== 0, $control['id'].' is not a regulation-making power');
                Assert::true(strpos($control['text'], 'Regulations under') !== 0, $control['id'].' is not a procedure provision');
            }
        }
    },

    'every obligation and element is printed in its article, without repeal dots' => function () use ($definition, $article, $flat) {
        foreach ($definition()['families'] as $family) {
            $text = $article($family['number']);

            foreach ($family['controls'] as $control) {
                Assert::true(preg_match('/\s(?:\.\s?){3,}/', $control['text']) === 0, $control['id'].' carries no repeal dots');
                $opening = implode(' ', array_slice(explode(' ', $control['text']), 0, 8));
                Assert::true(strpos($text, $flat($opening)) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $objective) {
                    Assert::true(strpos($objective['id'], $control['id'].'(') === 0, $objective['id'].' belongs to '.$control['id']);
                    $opening = implode(' ', array_slice(explode(' ', $objective['text']), 0, 5));
                    Assert::true(strpos($text, $flat($opening)) !== false, $objective['id'].' text');
                }
            }
        }
    },

    'United Kingdom changes are present' => function () use ($definition) {
        $points = array();
        $text = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = array_column($control['objectives'], 'id');
                $text[$control['id']] = $control['text'];
            }
        }

        Assert::true(in_array('Art. 6(1)(ea)', $points['Art. 6(1)'], true), 'recognised legitimate interests');
        Assert::same(7, count($points['Art. 6(1)']), 'lawful bases');
        Assert::true(strpos($text['Art. 33(1)'], 'the Commission') !== false, 'breaches are notified to the Information Commission');
        Assert::true(isset($text['Art. 22C(2)']) && isset($text['Art. 44A(1)']) && isset($text['Art. 8A(2)']), 'Data (Use and Access) Act 2025 articles');
    }
);
