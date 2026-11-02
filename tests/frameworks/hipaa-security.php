<?php
/**
 * HIPAA Security Rule checks. Standards and specifications are re-derived two
 * ways the parser does not use: a line regex over the plain text, and the
 * Appendix A matrix (164.308 to 164.312) that HHS publishes with the rule.
 */
$file = 'hipaa/45-cfr-164-subpart-c.xml';

$controls = function () {
    $d = json_decode(file_get_contents(ROOT.'/frameworks/hipaa-security.json'), true);
    $all = array();

    foreach ($d['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $control['family'] = $family['number'];
            $all[$control['id']] = $control;
        }
    }

    return $all;
};

// Plain text of 164.308 to 164.316, one paragraph per line.
$body = function () use ($file) {
    $text = Source::xml_text($file);

    return Source::between($text, '§ 164.308 Administrative safeguards.', '§ 164.318');
};

// Appendix A rows as [standard name, section, specification cell].
$matrix = function () use ($file) {
    $xml = simplexml_load_file(Source::path($file));
    $rows = array();

    foreach ($xml->xpath('//DIV9//TBODY/TR') as $tr) {
        $cells = array();

        foreach ($tr->TD as $td) {
            $cells[] = trim(preg_replace('/\s+/', ' ', strip_tags($td->asXML())));
        }

        if (count($cells) === 3) {
            $rows[] = $cells;
        }
    }

    return $rows;
};

return array(
    'five safeguard sections with the expected counts' => function () use ($controls) {
        $standards = array();
        $specs = array();

        foreach ($controls() as $c) {
            if ($c['requirement_type'] === 'standard') {
                $standards[$c['family']] = ($standards[$c['family']] ?? 0) + 1;
            } else {
                $specs[$c['family']] = ($specs[$c['family']] ?? 0) + 1;
            }
        }

        $expected_standards = array(
            '164.308' => 9,
            '164.310' => 4,
            '164.312' => 5,
            '164.314' => 2,
            '164.316' => 2
        );
        $expected_specs = array(
            '164.308' => 21,
            '164.310' => 8,
            '164.312' => 7,
            '164.314' => 4,
            '164.316' => 3
        );
        Assert::same($expected_standards, $standards, 'standards per section');
        Assert::same($expected_specs, $specs, 'implementation specifications per section');
    },

    'every "Standard:" in the text is a standard control' => function () use ($controls, $body) {
        preg_match_all('/Standard: ([^.]+)\./', $body(), $m);
        $titles = array();

        foreach ($controls() as $c) {
            if ($c['requirement_type'] === 'standard') {
                $titles[] = $c['title'];
            }
        }

        // 164.308(b)(1) is the one standard whose heading omits the word "Standard:".
        $expected = $m[1];
        array_splice($expected, 8, 0, array('Business associate contracts and other arrangements'));
        Assert::same($expected, $titles, 'standard titles in order');
    },

    'every (Required)/(Addressable) title is flagged exactly as written' => function () use ($controls, $body) {
        preg_match_all('/^\((?:[A-Za-z0-9]{1,4})\)(?:\([A-Za-z0-9]{1,4}\))* (?:Implementation specifications?: )?([^.\n(]+?) \((Required|Addressable)\)\./m', $body(), $m, PREG_SET_ORDER);
        $text = array();

        foreach ($m as $hit) {
            $text[$hit[1]] = strtolower($hit[2]);
        }

        $built = array();

        foreach ($controls() as $c) {
            if (isset($text[$c['title']])) {
                $built[$c['title']] = $c['implementation'];
            }
        }

        // 39 named specifications plus 164.314(b)(2), whose heading is "Implementation specifications (Required)."
        Assert::same(40, count($text), 'specifications with their own status in the text');
        Assert::same($text, $built, 'required/addressable per specification');
    },

    '164.314 specifications inherit (Required) from their heading' => function () use ($controls, $body) {
        Assert::true(strpos($body(), '(2) Implementation specifications (Required)—(i) Business associate contracts.') !== false, 'heading for 164.314(a)(2)');
        Assert::true(strpos($body(), '(2) Implementation specifications (Required). The plan documents') !== false, 'heading for 164.314(b)(2)');
        $c = $controls();
        $ids = array(
            '164.314(a)(2)(i)',
            '164.314(a)(2)(ii)',
            '164.314(a)(2)(iii)',
            '164.314(b)(2)'
        );

        foreach ($ids as $id) {
            Assert::same('required', $c[$id]['implementation'], $id);
        }
    },

    'Appendix A matrix agrees for 164.308 to 164.312' => function () use ($controls, $matrix) {
        $standard = null;
        $from_matrix = array();
        $standards = array();

        foreach ($matrix() as $row) {
            if ($row[1] !== '') {
                $standard = $row[1];
                $standards[$standard] = strtolower($row[0]);
            }

            if (preg_match('/^(.*?)\s*\((R|A)\)$/', $row[2], $m) && $m[1] !== '') {
                $from_matrix[] = array(
                    $standard,
                    strtolower($m[1]),
                    $m[2] === 'R' ? 'required' : 'addressable'
                );
            } elseif ($row[2] === 'Workforce Clearance Procedure') {
                // The matrix omits this row's flag; the rule text at 164.308(a)(3)(ii)(B) says Addressable.
                $from_matrix[] = array(
                    $standard,
                    'workforce clearance procedure',
                    'addressable'
                );
            }
        }

        $built = array();
        $built_standards = array();

        foreach ($controls() as $c) {
            if (!in_array($c['family'], array('164.308', '164.310', '164.312'), true)) {
                continue;
            }

            if ($c['requirement_type'] === 'standard') {
                // Matrix cites the standard's paragraph without the trailing (i).
                $built_standards[preg_replace('/\(i\)$/', '', $c['id'])] = strtolower($c['title']);
                continue;
            }

            $built[] = array(
                preg_replace('/\(i\)$/', '', $c['parent']),
                strtolower($c['title']),
                $c['implementation']
            );
        }

        Assert::same(array_keys($standards), array_keys($built_standards), 'standard citations');
        Assert::same(count($from_matrix), count($built), 'specification count');

        // Matrix names are slightly abbreviated ("Function", "Procedure"); compare on the first two words.
        foreach ($from_matrix as $i => $row) {
            Assert::same($row[0], $built[$i][0], 'standard of '.$row[1]);
            Assert::same($row[2], $built[$i][2], 'status of '.$row[1]);
            $short = function (string $s) {
                return implode(' ', array_slice(explode(' ', $s), 0, 2));
            };
            Assert::same($short($row[1]), $short($built[$i][1]), 'name of '.$row[1]);
        }
    },

    'specifications point at a standard in the same section' => function () use ($controls) {
        $c = $controls();

        foreach ($c as $id => $control) {
            if ($control['requirement_type'] === 'standard') {
                Assert::same('', $control['parent'], $id.' parent');
                Assert::same(null, $control['implementation'], $id.' implementation');
                continue;
            }

            Assert::true(isset($c[$control['parent']]), $id.' parent missing');
            Assert::same('standard', $c[$control['parent']]['requirement_type'], $id.' parent type');
            Assert::same($control['family'], $c[$control['parent']]['family'], $id.' parent section');
        }
    },

    'addressable rule quotes 164.306(d)(3)' => function () use ($file) {
        $meta = FrameworkBuilder::meta('hipaa-security');
        $rules = array_column($meta['rules'], null, 'id');
        $text = Source::normalise(Source::xml_text($file));
        $section = Source::between($text, '(3) When a standard adopted in', '(e) Maintenance.');
        Assert::true(strpos($section, Source::normalise($rules['addressable_specifications']['quote'])) !== false, 'quote sits in 164.306(d)(3)');
        Assert::same('addressable', $rules['addressable_specifications']['applies_to'], 'applies to addressable');
        preg_match('/for (\d+) years from the date of its creation/', $text, $m);
        Assert::same((int) $m[1], $rules['documentation_retention']['years'], 'retention years');
    }
);
