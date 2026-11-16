<?php
/**
 * Utah Consumer Privacy Act checks. The parser walks the subsection tree of the chapter
 * XML; these read the same sections as plain text and find the duties a second way.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/us-ut-cpa.json'), true);
};

// Plain text of every version of one section, letters and digits only.
$section = function (string $number) {
    $xml = simplexml_load_file(Source::path('us-ut-cpa/C13-61.xml'));
    $text = '';

    foreach ($xml->xpath('//section[@number="'.$number.'"]') as $node) {
        $text .= ' '.dom_import_simplexml($node)->textContent;
    }

    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', $text));
};

return array(
    'four sections that bind controllers and processors' => function () use ($definition) {
        Assert::same(array('13-61-203', '13-61-301', '13-61-302', '13-61-303'), array_column($definition()['families'], 'number'), 'sections');
    },

    'every requirement states a duty of the business and is printed in its section' => function () use ($definition, $section, $flat) {
        $total = 0;

        foreach ($definition()['families'] as $family) {
            $text = $section($family['number']);

            foreach ($family['controls'] as $control) {
                Assert::true(preg_match('/\b(shall|must|may not)\b/', $control['text']) === 1, $control['id'].' states a duty');
                Assert::true(strpos($control['id'], $family['number'].'(') === 0, $control['id'].' belongs to '.$family['number']);
                Assert::true(strpos($text, $flat(implode(' ', array_slice(explode(' ', preg_replace('/\(\w+\) /', '', $control['text'])), 0, 10)))) !== false, $control['id'].' text');
                $total++;
            }
        }

        Assert::same(14, $total, 'requirements');
    },

    'the rights sections bind consumers, not the business' => function () use ($section) {
        Assert::true(strpos($section('13-61-201'), 'consumerhastherightto') !== false, '13-61-201 grants consumer rights');
        Assert::true(strpos($section('13-61-202'), 'parentorlegalguardian') !== false, '13-61-202 is about who exercises a right');
    },

    'well-known lists have their published length' => function () use ($definition) {
        $points = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = count($control['objectives']);
            }
        }

        Assert::same(5, $points['13-61-302(1)(a)'], 'privacy notice contents');
        Assert::same(2, $points['13-61-301(1)'], 'processor duties');
        Assert::same(2, $points['13-61-203(2)(a)'], 'response within 45 days');
    }
);
