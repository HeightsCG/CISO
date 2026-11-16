<?php
/**
 * Virginia CDPA checks. The parser walks the section markup; these read the same sections
 * as plain text and find the duties a second way.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/us-va-cdpa.json'), true);
};

// Plain text of the chapter page, one paragraph per line.
$lines = function () {
    $html = file_get_contents(Source::path('us-va-cdpa/va-code-59.1-chapter-53.html'));
    $text = html_entity_decode(strip_tags(preg_replace('/<(p|b)[ >]/', "\n$0", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return array_values(array_filter(array_map(array('Source', 'normalise'), explode("\n", $text)), 'strlen'));
};

// Lettered subsections of one section, as label => full text.
$subsections = function (string $number) use ($lines) {
    $found = array();
    $inside = false;
    $current = null;

    foreach ($lines() as $line) {
        if (preg_match('/^§ 59\.1-[\d.]+\. /u', $line)) {
            $inside = strpos($line, '§ '.$number.'. ') === 0;
            $current = null;
            continue;
        }

        if (!$inside || preg_match('/^\d{4}, (Sp\. Sess\. [IVX]+, )?cc?\. /', $line)) {
            continue;
        }

        if (preg_match('/^([A-Z])\. (.*)$/', $line, $m)) {
            $current = $m[1];
            $found[$current] = $m[2];
        } elseif ($current !== null) {
            $found[$current] .= ' '.$line;
        }
    }

    return $found;
};

return array(
    'six sections that bind controllers and processors' => function () use ($definition) {
        Assert::same(array(
            '59.1-577',
            '59.1-577.1',
            '59.1-578',
            '59.1-579',
            '59.1-580',
            '59.1-581'
        ), array_column($definition()['families'], 'number'), 'sections');
    },

    'a subsection is a requirement exactly when it imposes a duty' => function () use ($definition, $subsections) {
        $built = array();
        $kept = 0;

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $built[$control['id']] = true;
            }

            foreach ($subsections($family['number']) as $label => $text) {
                $duty = preg_replace('/\bshall (not )?(be construed|apply|be deemed|be considered|be void)\b/', '', $text);
                $expected = preg_match('/\bshall\b/', $duty) === 1 && preg_match('/^(The )?Attorney General shall\b|^Nothing in this (section|chapter)\b/', $text) === 0;
                Assert::same($expected, isset($built[$family['number'].'('.$label.')']), $family['number'].'('.$label.')');
                $kept += $expected ? 1 : 0;
            }
        }

        Assert::same(19, $kept, 'requirements');
    },

    'what is left out states no duty' => function () use ($subsections) {
        Assert::true(strpos($subsections('59.1-578')['B'], 'shall be deemed contrary to public policy and shall be void') !== false, '59.1-578(B) voids waivers');
        Assert::true(strpos($subsections('59.1-579')['C'], 'Nothing in this section shall be construed') === 0, '59.1-579(C) construes');
    },

    'well-known lists have their published length' => function () use ($definition) {
        $points = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = count($control['objectives']);
            }
        }

        Assert::same(5, $points['59.1-577(A)'], 'consumer rights');
        Assert::same(6, $points['59.1-578(A)'], 'controller duties');
        Assert::same(5, $points['59.1-578(C)'], 'privacy notice contents');
        Assert::same(5, $points['59.1-580(A)'], 'processing that needs a data protection assessment');
    }
);
