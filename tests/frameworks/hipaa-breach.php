<?php
/**
 * HIPAA Breach Notification Rule checks. The parser walks the XML paragraph tree;
 * these re-derive the headings and the paragraph text from the plain text instead.
 */
$file = 'hipaa/45-cfr-164-subpart-d.xml';

$controls = function () {
    $d = json_decode(file_get_contents(ROOT.'/frameworks/hipaa-breach.json'), true);
    $all = array();

    foreach ($d['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $control['family'] = $family['number'];
            $all[$control['id']] = $control;
        }
    }

    return $all;
};

// Plain text of 164.404 to 164.414, one paragraph per line.
$body = function () use ($file) {
    $text = Source::xml_text($file);

    return substr($text, strpos($text, '§ 164.404 Notification to individuals.'));
};

$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

return array(
    'six sections, sixteen requirements' => function () use ($controls) {
        $per_section = array();

        foreach ($controls() as $c) {
            $per_section[$c['family']] = ($per_section[$c['family']] ?? 0) + 1;
        }

        Assert::same(array(
            '164.404' => 4,
            '164.406' => 3,
            '164.408' => 3,
            '164.410' => 3,
            '164.412' => 1,
            '164.414' => 2
        ), $per_section, 'requirements per section');
    },

    'every "Standard" heading in the text is a standard' => function () use ($controls, $body) {
        $expected = preg_match_all('/^\(a\) Standard\b/m', $body());
        $standards = array();

        foreach ($controls() as $c) {
            if ($c['requirement_type'] === 'standard') {
                $standards[] = $c['id'];
            }
        }

        Assert::same(4, $expected, 'Standard headings in the text');
        Assert::same(array(
            '164.404(a)',
            '164.406(a)',
            '164.408(a)',
            '164.410(a)'
        ), $standards, 'standards');
    },

    'every "Implementation specification" heading is a specification with its title' => function () use ($controls, $body) {
        preg_match_all('/^\(([a-d])\) Implementation specifications?: ([^.—]+)/mu', $body(), $m, PREG_SET_ORDER);
        $titles = array();

        foreach ($m as $heading) {
            $titles[] = trim($heading[2]);
        }

        $specs = array();

        foreach ($controls() as $c) {
            if ($c['requirement_type'] === 'implementation_specification') {
                $specs[] = $c['title'];
                Assert::same($c['family'].'(a)', $c['parent'], $c['id'].' parent');
            }
        }

        Assert::same(9, count($titles), 'specification headings in the text');
        Assert::same($titles, $specs, 'specification titles');
    },

    'every paragraph of the six sections is carried by a requirement' => function () use ($controls, $body, $flat) {
        $carried = '';

        foreach ($controls() as $c) {
            $carried .= $flat($c['title'].' '.$c['text']);

            foreach ($c['objectives'] as $o) {
                $carried .= $flat($o['text']);
            }
        }

        foreach (explode("\n", $body()) as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '§') === 0 || $line[0] === '[') {
                continue;
            }

            // Drop paragraph labels and headings; what is left must appear in a requirement.
            $sentences = preg_split('/(?<=[.:;])\s+/', $line);
            $last = $flat(preg_replace('/^(\([a-zA-Z0-9]{1,4}\)\s*)+/', '', (string) end($sentences)));

            if (strlen($last) < 25) {
                continue;
            }

            Assert::true(strpos($carried, $last) !== false, 'not carried: '.substr($line, 0, 80));
        }
    },

    'law enforcement delay offers two alternatives that read alone' => function () use ($controls) {
        $c = $controls()['164.412'];

        Assert::same(2, count($c['objectives']), '164.412 alternatives');

        foreach ($c['objectives'] as $o) {
            Assert::true(strpos($o['description'], 'If a law enforcement official states') === 0, $o['id'].' description opens with the lead-in');
        }
    }
);
