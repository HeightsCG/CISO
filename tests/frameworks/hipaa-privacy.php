<?php
/**
 * HIPAA Privacy Rule checks. The parser walks the XML paragraph tree and reads
 * headings from italic runs; these count the same headings in the plain text.
 */
$file = 'hipaa/45-cfr-164-subpart-e.xml';

$controls = function () {
    $d = json_decode(file_get_contents(ROOT.'/frameworks/hipaa-privacy.json'), true);
    $all = array();

    foreach ($d['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $control['family'] = $family['number'];
            $all[$control['id']] = $control;
        }
    }

    return $all;
};

// Plain text of 164.502 to 164.530, one paragraph per line.
$body = function () use ($file) {
    $text = Source::xml_text($file);

    return Source::between($text, '§ 164.502 Uses and disclosures of protected health information: General rules.', '§ 164.532 Transition provisions.');
};

return array(
    'fourteen sections, 164.502 to 164.530' => function () {
        $d = json_decode(file_get_contents(ROOT.'/frameworks/hipaa-privacy.json'), true);

        Assert::same(array(
            '164.502',
            '164.504',
            '164.506',
            '164.508',
            '164.509',
            '164.510',
            '164.512',
            '164.514',
            '164.520',
            '164.522',
            '164.524',
            '164.526',
            '164.528',
            '164.530'
        ), array_column($d['families'], 'number'), 'sections');
    },

    'every "Standard" and "Implementation specification" heading in the text is a control' => function () use ($controls, $body) {
        $text = $body();
        $standards = preg_match_all('/^(?:\([a-z0-9]+\))+(?: [A-Z][a-z ]+—)?(?:\(1\))? ?Standard[:.]/m', $text);
        $specs = preg_match_all('/^(?:\([a-z0-9]+\))+ ?Implementation specifications?:/m', $text);
        $types = array(
            'standard' => 0,
            'implementation_specification' => 0
        );

        foreach ($controls() as $c) {
            $types[$c['requirement_type']]++;
        }

        Assert::same(55, $standards, 'Standard headings in the text');
        Assert::same(55, $specs, 'Implementation specification headings in the text');
        Assert::same(array(
            'standard' => 55,
            'implementation_specification' => 55
        ), $types, 'controls by type');
    },

    'every control title is the heading printed at its citation' => function () use ($controls, $body) {
        $text = Source::normalise($body());

        foreach ($controls() as $c) {
            if ($c['id'] === '164.502(a)') {
                continue;
            }

            $kind = $c['requirement_type'] === 'standard' ? 'Standard' : 'Implementation specifications?';
            $title = preg_quote(Source::normalise($c['title']), '/');
            Assert::true(preg_match('/'.$kind.': '.$title.'/i', $text) === 1, $c['id'].' heading "'.$c['title'].'"');
        }
    },

    'specifications point at a standard in the same section' => function () use ($controls) {
        $all = $controls();

        foreach ($all as $c) {
            if ($c['requirement_type'] === 'standard') {
                Assert::same('', $c['parent'], $c['id'].' has no parent');
                continue;
            }

            Assert::true(isset($all[$c['parent']]), $c['id'].' parent exists');
            Assert::same('standard', $all[$c['parent']]['requirement_type'], $c['id'].' parent is a standard');
            Assert::same($c['family'], $all[$c['parent']]['family'], $c['id'].' parent is in its section');
        }
    },

    'a heading on the first sub-paragraph governs the whole paragraph' => function () use ($controls) {
        $all = $controls();

        Assert::same('164.502(g)(1)', $all['164.502(g)(3)']['parent'], 'unemancipated minors parent');
        Assert::true(count($all['164.502(g)(3)']['objectives']) >= 2, '164.502(g)(3) carries its sub-paragraphs');
        Assert::same('164.530(c)(1)', $all['164.530(c)(2)']['parent'], 'safeguards specification parent');
        Assert::true(!isset($all['164.530(c)']), '164.530(c) is not itself a control');
    },

    'eCFR still prints 164.502(b)(1) without its label' => function () use ($body) {
        // When this fails the source has been corrected: remove the workaround in the parser.
        Assert::true(strpos($body(), '(b) Standard: Minimum necessary— Minimum necessary applies.') !== false, 'the unlabelled paragraph');
    },

    'list items read alone' => function () use ($controls) {
        $names = null;

        foreach ($controls()['164.514(e)(2)']['objectives'] as $o) {
            if ($o['id'] === '164.514(e)(2)(i)') {
                $names = $o;
            }
        }

        Assert::true($names !== null && strlen($names['description'] ?? '') > strlen($names['text']), '164.514(e)(2)(i) has a standalone description');
    }
);
