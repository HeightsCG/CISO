<?php
/**
 * Connecticut Data Privacy Act checks. The parser walks the section markup; these read
 * each section as plain text and find the duties a second way.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/us-ct-dpa.json'), true);
};

// Plain text of one section, from its catchline to the next section's (source and history notes removed).
$section = function (string $number) {
    $html = file_get_contents(Source::path('us-ct-dpa/chap_743jj.htm'));
    $start = strpos($html, 'id="sec_'.$number.'"');
    $end = strpos($html, '<span class="catchln"', $start + 10);
    $chunk = preg_replace('/<p class="(source|history)[^"]*">.*?<\/p>/s', '', substr($html, $start, $end - $start));

    return Source::normalise(html_entity_decode(strip_tags('<x '.$chunk), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
};

$no_duty = '/\bshall (not )?(be construed|apply|be deemed|be considered|be void|have the right|constitute|be confidential|be exempt|be enforced)\b|\bshall not be required\b/';

return array(
    'eleven sections in two Parts' => function () use ($definition) {
        $parts = array();

        foreach ($definition()['families'] as $family) {
            $parts[$family['part']][] = $family['number'];
        }

        Assert::same(array(
            'Part I' => array('42-518', '42-519', '42-520', '42-521', '42-522', '42-523', '42-526', '42-528'),
            'Part II' => array('42-529a', '42-529b', '42-529c')
        ), $parts, 'sections');
    },

    'a subsection is a requirement exactly when it imposes a duty' => function () use ($definition, $section, $no_duty) {
        $total = 0;

        foreach ($definition()['families'] as $family) {
            $text = $section($family['number']);
            preg_match_all('/(?:^|\. |: )\(([a-z])\) ?/', $text, $labels, PREG_OFFSET_CAPTURE);

            foreach ($family['controls'] as $control) {
                Assert::true(preg_match('/\bshall\b/', preg_replace($no_duty, '', $control['text'])) === 1, $control['id'].' states a duty');
                Assert::true(preg_match('/^(The )?Attorney General shall\b|^Nothing in /', $control['text']) === 0, $control['id'].' is not addressed to the Attorney General');
                Assert::true(strpos($text, implode(' ', array_slice(explode(' ', $control['text']), 0, 12))) !== false, $control['id'].' text');
                $total++;
            }
        }

        Assert::same(25, $total, 'requirements');
    },

    'what is left out states no duty' => function () use ($section) {
        Assert::true(strpos($section('42-520'), '(b) Nothing in subsection (a) of this section shall be construed') !== false, '42-520(b) construes');
        Assert::true(strpos($section('42-528'), 'shall constitute an unfair trade practice') !== false, '42-528(d) classifies a violation');
    },

    'inline lists are split into their numbered items' => function () use ($definition) {
        $points = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = $control['objectives'];
            }
        }

        Assert::same(7, count($points['42-520(a)']), 'controller duties');
        Assert::same(6, count($points['42-520(c)']), 'privacy notice contents');
        Assert::same('The categories of personal data processed by the controller;', $points['42-520(c)'][0]['text'], 'first notice item, as printed');
    }
);
