<?php
/**
 * CMMC Level 3. Identifiers and text are checked against Table 1 to
 * 32 CFR 170.14(c)(4) read as plain text, the base wording against
 * NIST SP 800-172, and the objectives against NIST SP 800-172A.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/cmmc-l3.json'), true);
};

$controls = function () use ($definition) {
    $all = array();

    foreach ($definition()['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $control['family_code'] = $family['code'];
            $all[$control['id']] = $control;
        }
    }

    return $all;
};

$cfr = function () {
    return Source::normalise(Source::xml_text('cmmc/32-cfr-170.xml'));
};

$squash = function (string $text): string {
    return preg_replace('/\s+/u', '', Source::normalise($text));
};

return array(
    '24 requirements with the identifiers and text of Table 1 to 32 CFR 170.14(c)(4)' => function () use ($controls, $cfr) {
        $table = Source::between($cfr(), 'selected NIST SP 800-172 Feb2021 security requirement with DoD ODPs italicized)', '* Roman numerals in parentheses');
        preg_match_all('/\(([ivx]+)\) ([A-Z]{2}\.L3-3\.\d+\.\d+e) (.+?)(?= \([ivx]+\) [A-Z]{2}\.L3-|$)/', trim($table), $m, PREG_SET_ORDER);
        Assert::same(24, count($m), 'rows in Table 1');
        Assert::same(array_column($m, 2), array_keys($controls()), 'identifiers');

        foreach ($m as $row) {
            Assert::same(trim($row[3]), $controls()[$row[2]]['text'], 'text of '.$row[2]);
        }
    },

    'requirements per domain' => function () use ($definition) {
        $expected = array(
            'AC' => 2,
            'AT' => 2,
            'CM' => 3,
            'IA' => 2,
            'IR' => 2,
            'PS' => 1,
            'RA' => 7,
            'CA' => 1,
            'SC' => 1,
            'SI' => 3
        );
        $actual = array();

        foreach ($definition()['families'] as $family) {
            $actual[$family['code']] = count($family['controls']);
        }

        Assert::same($expected, $actual, 'requirements per domain');
    },

    'wording outside the DoD parameters is NIST SP 800-172 word for word' => function () use ($controls, $squash) {
        $sp = Source::pdf_text('nist-800-172/sp800-172.pdf');
        $haystack = $squash($sp);

        // The rule prints "cyber-incident response team" where SP 800-172 has "cyber incident".
        $known = array(
            'IR.L3-3.6.2e' => array(
                'cyber-incident',
                'cyber incident'
            )
        );

        foreach ($controls() as $id => $control) {
            $number = substr($id, strpos($id, '-') + 1);
            Assert::true(preg_match('/^'.preg_quote($number, '/').' /m', $sp) === 1, $number.' not found in SP 800-172');
            $pattern = '/'.implode('|', array_map(function ($p) {
                return preg_quote($p, '/');
            }, $control['dod_parameters'] ?: array("\x00"))).'/';

            foreach (preg_split($pattern, $control['text']) as $fragment) {
                $fragment = trim($fragment, " ,.;");

                if (strlen($fragment) < 4) {
                    continue;
                }

                if (isset($known[$id])) {
                    $fragment = str_replace($known[$id][0], $known[$id][1], $fragment);
                }

                Assert::true(strpos($haystack, $squash($fragment)) !== false, $id.' wording not found in SP 800-172: "'.$fragment.'"');
            }
        }
    },

    '80 lettered SP 800-172A objectives; ODP statements left out' => function () use ($definition, $controls, $squash) {
        $raw = Source::pdf_text('nist-800-172/sp800-172a.pdf');
        $raw = substr($raw, strpos($raw, "\nCHAPTER THREE\n"));
        $haystack = $squash(preg_replace('/(?<=[a-z])-\n/', '-', $raw));
        $odps = 0;

        foreach ($controls() as $id => $control) {
            preg_match_all('/^('.preg_quote($control['number'], '/').'\[[a-z]+\]) /m', $raw, $m);
            Assert::same(array_values(array_unique($m[1])), array_column($control['objectives'], 'id'), 'objectives of '.$id);
            preg_match_all('/^'.preg_quote($control['number'], '/').'_ODP\[\d+\] /m', $raw, $o);
            $odps += count(array_unique($o[0]));

            if (count($control['dod_parameters']) > 0) {
                Assert::true(count($o[0]) > 0, $id.' has DoD parameters but no ODP in SP 800-172A');
            }

            foreach ($control['objectives'] as $objective) {
                Assert::true(strpos($haystack, $squash($objective['id'].' '.$objective['text'])) !== false, 'objective text not found in 172A: '.$objective['id']);
            }
        }

        Assert::same(80, $definition()['counts']['objectives'], 'objective count');
        Assert::same(22, $odps, 'ODP statements in 172A for the 24 requirements (not loaded)');
    },

    'scoring follows 32 CFR 170.24(c)(3)' => function () use ($definition, $controls) {
        $sum = 0;

        foreach ($controls() as $id => $control) {
            Assert::same(1, $control['weight'], 'point value of '.$id);
            $sum += $control['weight'];
        }

        Assert::same(24, $definition()['scoring']['maximum'], 'maximum');
        Assert::same($sum, $definition()['scoring']['maximum'], 'maximum equals the number of requirements');
        Assert::same(0, $definition()['scoring']['floor'], 'floor');
    },

    'POA&M and gate rules match 32 CFR 170' => function () use ($cfr) {
        $rules = array();

        foreach (FrameworkBuilder::meta('cmmc-l3')['rules'] as $rule) {
            $rules[$rule['id']] = $rule;
        }

        $section = Source::between($cfr(), '(ii) The POA&M does not include any of following security requirements:', '(b) POA&M closeout assessment.');
        preg_match_all('/\(([A-G])\) ([A-Z]{2}\.L3-3\.\d+\.\d+e)/', $section, $m);
        Assert::same($m[2], $rules['poam_eligibility']['excluded'], 'requirements that can never go on a POA&M');
        Assert::same(0.8, $rules['conditional_threshold']['value'], 'Conditional threshold');
        Assert::same(180, $rules['poam_closeout']['days'], 'POA&M closeout days');
        Assert::same(3, $rules['reassessment']['years'], 'reassessment interval');
        Assert::true(in_array($rules['level2_prerequisite']['requires']['framework'], FrameworkBuilder::keys(), true), 'Level 2 gate names a defined framework');
        Assert::same(FrameworkBuilder::meta('cmmc-l2')['scoring']['maximum'], $rules['level2_maximum_score']['requires']['score'], 'Level 2 maximum score');
        Assert::true(strpos($cfr(), 'The maximum score is reduced by one (1) point for each security requirement NOT MET.') !== false, 'one point per NOT MET requirement');
    }
);
