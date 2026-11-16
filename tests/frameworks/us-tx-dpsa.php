<?php
/**
 * Texas Data Privacy and Security Act checks. The parser reads paragraph indents; these
 * read each section as plain text and find the duties a second way.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/us-tx-dpsa.json'), true);
};

// Plain text of one section, from its heading to the next (history notes removed).
$section = function (string $number) {
    static $text = null;

    if ($text === null) {
        $html = file_get_contents(Source::path('us-tx-dpsa/BC.541.htm'));
        $text = Source::normalise(str_replace("\u{a0}", ' ', html_entity_decode(strip_tags(str_replace('<p', ' <p', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    $start = strrpos($text, 'Sec. '.$number.'. ');
    $end = strpos($text, 'Sec. ', $start + 10);
    $chunk = substr($text, $start, ($end === false ? strlen($text) : $end) - $start);

    return preg_replace('/(Added|Amended) by Acts .*$/', '', $chunk);
};

return array(
    'eleven sections in subchapters B and C' => function () use ($definition) {
        Assert::same(array(
            '541.051',
            '541.052',
            '541.053',
            '541.055',
            '541.101',
            '541.102',
            '541.103',
            '541.104',
            '541.105',
            '541.106',
            '541.107'
        ), array_column($definition()['families'], 'number'), 'sections');
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

        Assert::same(29, $total, 'requirements');
    },

    'a subsection without a duty is left out' => function () use ($definition, $section) {
        $built = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $built[$control['id']] = true;
            }
        }

        Assert::true(!isset($built['541.051(a)']), '541.051(a) entitles the consumer');
        Assert::true(strpos($section('541.051'), '(a) A consumer is entitled to exercise') !== false, '541.051(a) text');
    },

    'enumerations joined by "or" are each required' => function () use ($definition) {
        $points = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = count($control['objectives']);
            }
        }

        Assert::same(5, $points['541.051(b)'], 'consumer rights the controller must honour');
        Assert::same(4, $points['541.101(b)'], 'controller prohibitions');
        Assert::same(6, $points['541.102(a)'], 'privacy notice contents');
    }
);
