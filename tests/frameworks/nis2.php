<?php
/**
 * NIS 2 Directive checks. The parser walks the article markup; these read the same
 * articles as plain text and count the points a second way.
 */
$controls = function () {
    $d = json_decode(file_get_contents(ROOT.'/frameworks/nis2.json'), true);
    $all = array();

    foreach ($d['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $control['family'] = $family['code'];
            $all[$control['id']] = $control;
        }
    }

    return $all;
};

// Plain text of one article, from its heading to the next article's.
$article = function (int $number) {
    $html = file_get_contents(Source::path('nis2/dir-2022-2555.html'));
    $start = strpos($html, 'id="art_'.$number.'"');
    $end = strpos($html, 'id="art_'.($number + 1).'"');

    return Source::normalise(str_replace("\u{a0}", ' ', html_entity_decode(strip_tags(substr($html, $start, $end - $start)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
};

return array(
    'Articles 20, 21 and 23 with their official titles' => function () {
        $d = json_decode(file_get_contents(ROOT.'/frameworks/nis2.json'), true);
        $titles = array();

        foreach ($d['families'] as $family) {
            $titles[$family['code']] = $family['name'];
        }

        Assert::same(array(
            'Art. 20' => 'Governance',
            'Art. 21' => 'Cybersecurity risk-management measures',
            'Art. 23' => 'Reporting obligations'
        ), $titles, 'articles');
    },

    'the ten measures of Article 21(2) and the five reporting stages of Article 23(4)' => function () use ($controls, $article) {
        $ids = array_keys($controls());
        $measures = array_values(array_filter($ids, function ($id) {
            return strpos($id, 'Art. 21(2)') === 0;
        }));
        $stages = array_values(array_filter($ids, function ($id) {
            return strpos($id, 'Art. 23(4)') === 0;
        }));

        Assert::same(10, count($measures), 'Article 21(2) measures');
        Assert::same('Art. 21(2)(j)', end($measures), 'last measure');
        Assert::same(5, count($stages), 'Article 23(4) stages');

        // Counted in the plain text: lettered points between "2." and "3." of Article 21.
        $text = $article(21);
        $between = substr($text, strpos($text, '2. The measures referred to in paragraph 1'), strpos($text, '3. Member States shall ensure that, when considering') - strpos($text, '2. The measures referred to in paragraph 1'));
        Assert::same(10, preg_match_all('/\(([a-j])\) /', $between), 'lettered points printed in Article 21(2)');
    },

    'every obligation is printed word for word in its article' => function () use ($controls, $article) {
        $text = array(
            'Art. 20' => $article(20),
            'Art. 21' => $article(21),
            'Art. 23' => $article(23)
        );

        foreach ($controls() as $c) {
            // Nested points are joined behind their labels; the first twelve words are contiguous either way.
            $opening = implode(' ', array_slice(explode(' ', $c['text']), 0, 12));
            Assert::true(strpos($text[$c['family']], $opening) !== false, $c['id'].' text');
        }
    },

    'points read alone' => function () use ($controls) {
        foreach ($controls() as $c) {
            if ($c['type'] !== 'point') {
                Assert::true(!isset($c['description']), $c['id'].' is a whole paragraph');
                continue;
            }

            Assert::true(strpos($c['description'], rtrim($c['lead_in'], ':')) === 0, $c['id'].' description opens with the lead-in');
            Assert::true(substr($c['description'], -1) === '.' && strpos($c['description'], ';.') === false, $c['id'].' description is one sentence');
        }
    },

    'reporting deadlines sit on the stages they govern' => function () use ($controls) {
        $all = $controls();

        Assert::true(strpos($all['Art. 23(4)(a)']['text'], 'within 24 hours') !== false, 'early warning');
        Assert::true(strpos($all['Art. 23(4)(b)']['text'], 'within 72 hours') !== false, 'incident notification');
        Assert::true(strpos($all['Art. 23(4)(d)']['text'], 'not later than one month') !== false, 'final report');
    }
);
