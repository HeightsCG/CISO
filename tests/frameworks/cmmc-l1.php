<?php
/**
 * CMMC Level 1. Requirement text is checked against FAR 52.204-21 and the
 * identifiers and objective mapping against Table 2 to 32 CFR 170.15, both read
 * here from the plain text rather than the XML structure the parser uses.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/cmmc-l1.json'), true);
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

$table = function () {
    $cfr = Source::normalise(Source::xml_text('cmmc/32-cfr-170.xml'));
    $section = Source::between($cfr, 'CMMC Level 1 Security Requirements Mapped to NIST SP 800-171A Jun2018', 'Three of the 48 CFR 52.204-21 requirements were broken apart');
    preg_match_all('/([A-Z]{2}\.L1-b\.1\.[ivx]+)(?: \(FAR b\.1\.[ivx]+ \*\))? (3\.\d+\.\d+)/', $section, $m, PREG_SET_ORDER);
    $map = array();

    foreach ($m as $row) {
        $map[$row[1]][] = $row[2];
    }

    return $map;
};

return array(
    '15 requirements, FAR 52.204-21(b)(1)(i)-(xv), with the Table 2 identifiers' => function () use ($controls, $table) {
        $romans = array(
            'i',
            'ii',
            'iii',
            'iv',
            'v',
            'vi',
            'vii',
            'viii',
            'ix',
            'x',
            'xi',
            'xii',
            'xiii',
            'xiv',
            'xv'
        );
        $ids = array_keys($controls());
        Assert::same(15, count($ids), 'requirement count');
        Assert::same(array_keys($table()), $ids, 'identifiers in Table 2 to 32 CFR 170.15');

        foreach ($ids as $i => $id) {
            Assert::same('b.1.'.$romans[$i], $controls()[$id]['number'], 'paragraph for '.$id);
        }
    },

    'requirement text is FAR 52.204-21(b)(1) word for word' => function () use ($controls) {
        $far = Source::normalise(Source::xml_text('cmmc/far-52.204-21.xml'));
        $list = Source::between($far, 'shall include, at a minimum, the following security controls:', '(2) Other requirements.');
        preg_match_all('/\(([ivx]+)\) (.+?)(?= \([ivx]+\) |$)/', trim($list), $m, PREG_SET_ORDER);
        Assert::same(15, count($m), 'paragraphs in FAR 52.204-21(b)(1)');

        foreach ($m as $i => $row) {
            $control = array_values($controls())[$i];
            Assert::same('b.1.'.$row[1], $control['number'], 'paragraph order');
            Assert::same(trim($row[2]), $control['text'], 'text of '.$control['id']);
        }
    },

    'objectives are the SP 800-171A objectives of the Table 2 requirements' => function () use ($controls, $table) {
        $r2 = array();

        foreach (json_decode(file_get_contents(ROOT.'/frameworks/nist-800-171-r2.json'), true)['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $r2[$control['number']] = $control;
            }
        }

        $raw = Source::pdf_text('cmmc/sp800-171a-2018.pdf');
        $total = 0;

        foreach ($table() as $id => $numbers) {
            $expected = array();

            foreach ($numbers as $n) {
                preg_match_all('/^('.preg_quote($n, '/').'\[[a-z]+\])/m', $raw, $m);
                $lettered = array_values(array_unique($m[1]));

                // A requirement with a single objective (3.10.4) has no letter; its ID is the requirement number.
                if (count($lettered) === 0) {
                    $lettered = array(
                        $n
                    );
                }

                Assert::same($lettered, array_column($r2[$n]['objectives'], 'id'), 'lettered objectives of '.$n.' in 171A');
                $expected = array_merge($expected, $lettered);
            }

            Assert::same($numbers, $controls()[$id]['nist_800_171'], 'mapped requirements for '.$id);
            Assert::same($expected, array_column($controls()[$id]['objectives'], 'id'), 'objectives of '.$id);
            $total += count($expected);
        }

        $split = array(
            '3.10.3',
            '3.10.4',
            '3.10.5'
        );
        Assert::same($split, $table()['PE.L1-b.1.ix'], 'PE.L1-b.1.ix is split by phrase');
        Assert::same(59, $total, 'objective count');
    },

    'domain codes match the identifier and the CMMC Level 2 domain of the mapped requirement' => function () use ($controls) {
        $l2 = array();

        foreach (json_decode(file_get_contents(ROOT.'/frameworks/cmmc-l2.json'), true)['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $l2[$control['number']] = $family['code'];
            }
        }

        foreach ($controls() as $id => $control) {
            Assert::same(substr($id, 0, 2), $control['family_code'], 'domain of '.$id);

            foreach ($control['nist_800_171'] as $n) {
                Assert::same($l2[$n], $control['family_code'], 'Level 2 domain of '.$n.' for '.$id);
            }
        }
    },

    'rules match 32 CFR 170' => function () {
        $rules = array();

        foreach (FrameworkBuilder::meta('cmmc-l1')['rules'] as $rule) {
            $rules[$rule['id']] = $rule;
        }

        $cfr = Source::normalise(Source::xml_text('cmmc/32-cfr-170.xml'));
        Assert::true(strpos($cfr, '(1) Level 1 self-assessment. A POA&M is not permitted at any time for Level 1 self-assessments.') !== false, '170.21(a)(1) wording');
        Assert::same(false, $rules['no_poam']['permitted'], 'POA&M not permitted');
        Assert::same('all_met', $rules['final_status']['metric'], 'pass rule');
        Assert::true(strpos($rules['reassessment']['quote'], 'on an annual basis') !== false, 'annual self-assessment quote');
        Assert::same(1, $rules['reassessment']['years'], 'reassessment interval');
        Assert::same('percentage', FrameworkBuilder::meta('cmmc-l1')['scoring']['model'], 'no point values at Level 1');
    }
);
