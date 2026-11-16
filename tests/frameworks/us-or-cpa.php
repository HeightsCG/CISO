<?php
/**
 * Oregon Consumer Privacy Act checks. The parser reads the Word-export paragraphs; these
 * read each section as plain text and find the duties a second way.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/us-or-cpa.json'), true);
};

// Plain text of the Act's body (646A.570 to 646A.589), paragraphs joined by spaces.
$section = function () {
    static $text = null;

    if ($text === null) {
        $html = mb_convert_encoding(file_get_contents(Source::path('us-or-cpa/ors646a.html')), 'UTF-8', 'Windows-1252');
        $all = Source::normalise(str_replace("\u{a0}", ' ', html_entity_decode(strip_tags(str_replace('<p', ' <p', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $start = strrpos($all, '646A.570 Definitions. ');
        $text = substr($all, $start, strpos($all, 'DATA BROKERS', $start) - $start);
    }

    return $text;
};

return array(
    'six sections that bind controllers and processors' => function () use ($definition) {
        Assert::same(array(
            '646A.574',
            '646A.576',
            '646A.578',
            '646A.581',
            '646A.583',
            '646A.586'
        ), array_column($definition()['families'], 'number'), 'sections');
    },

    'every requirement states a duty and is printed in its section' => function () use ($definition, $section) {
        $total = 0;

        foreach ($definition()['families'] as $family) {
            $text = $section();

            foreach ($family['controls'] as $control) {
                Assert::true(preg_match('/\b(shall|must|may not)\b/', $control['text']) === 1, $control['id'].' states a duty');
                Assert::true(strpos($text, implode(' ', array_slice(explode(' ', $control['text']), 0, 10))) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $objective) {
                    Assert::true(strpos($text, implode(' ', array_slice(explode(' ', $objective['text']), 0, 8))) !== false, $objective['id'].' text');
                }

                $total++;
            }
        }

        Assert::same(17, $total, 'requirements');
    },

    'what is left out states no duty' => function () use ($section) {
        Assert::true(strpos($section(), '(3) Subsections (1) and (2) of this section do not:') !== false, '646A.578(3) limits');
        Assert::true(strpos($section(), '(3) The Attorney General may require') !== false, '646A.586(3) binds the Attorney General');
    },

    'well-known lists have their published length' => function () use ($definition) {
        $points = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = count($control['objectives']);
            }
        }

        Assert::same(5, $points['646A.576(5)'], 'how a controller responds to requests');
        Assert::same(5, $points['646A.578(2)'], 'controller prohibitions');
        Assert::same(9, $points['646A.578(4)'], 'privacy notice contents');
    }
);
