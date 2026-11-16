<?php
/**
 * Montana Consumer Data Privacy Act checks. The parser builds a label tree from each
 * section page; these read each page as plain text and find the duties a second way.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/us-mt-cdpa.json'), true);
};

// Plain text of one section page's law text, paragraphs joined by spaces.
$section = function (string $number) {
    $part = substr($number, -4);
    $html = file_get_contents(Source::path('us-mt-cdpa/0300-0140-0280-0'.substr($part, 2).'0.html'));
    $html = substr($html, strpos($html, 'section-content'));
    $text = html_entity_decode(strip_tags(str_replace('<p', ' <p', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return Source::normalise(str_replace(array("\u{2003}", "\t"), ' ', $text));
};

return array(
    'nine sections, each read from its own page' => function () use ($definition) {
        $d = $definition();

        Assert::same(array(
            '30-14-2808',
            '30-14-2809',
            '30-14-2811',
            '30-14-2812',
            '30-14-2813',
            '30-14-2814',
            '30-14-2815',
            '30-14-2818',
            '30-14-2819'
        ), array_column($d['families'], 'number'), 'sections');

        foreach ($d['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same('mca'.substr($family['number'], -4), $control['source'], $control['id'].' cites its own page');
            }
        }
    },

    'every requirement states a duty and is printed in its section' => function () use ($definition, $section) {
        $total = 0;

        foreach ($definition()['families'] as $family) {
            $text = $section($family['number']);

            foreach ($family['controls'] as $control) {
                Assert::true(preg_match('/\b(shall|must|may not)\b/', $control['text']) === 1, $control['id'].' states a duty');
                Assert::true(strpos($text, implode(' ', array_slice(explode(' ', $control['text']), 0, 10))) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $objective) {
                    Assert::true(strpos($text, implode(' ', array_slice(explode(' ', $objective['text']), 0, 8))) !== false, $objective['id'].' text');
                }

                $total++;
            }
        }

        Assert::same(34, $total, 'requirements');
    },

    'well-known lists have their published length' => function () use ($definition) {
        $points = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = count($control['objectives']);
            }
        }

        Assert::same(3, $points['30-14-2812(1)'], 'controller duties');
        Assert::same(5, $points['30-14-2812(2)'], 'controller prohibitions');
        Assert::same(7, $points['30-14-2812(5)'], 'privacy notice contents');
    }
);
