<?php
/**
 * ISO/IEC 27001:2022 checks. Titles are re-checked against the sample
 * contents lists, and IDs against two independent NIST OLIR mappings.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/iso-27001-2022.json'), true);
};

$controls = function () use ($definition) {
    $all = array();

    foreach ($definition()['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $control['family'] = $family['code'];
            $all[$control['id']] = $control;
        }
    }

    return $all;
};

// Normalised text with the zero-width spaces the ISO PDFs put after clause numbers removed
$text = function (string $file) {
    return Source::normalise(str_replace("\u{200B}", '', Source::pdf_text($file)));
};

return array(
    '93 Annex A controls in the four published themes' => function () use ($definition) {
        $expected = array(
            'A.5' => array(
                'Organizational controls',
                37
            ),
            'A.6' => array(
                'People controls',
                8
            ),
            'A.7' => array(
                'Physical controls',
                14
            ),
            'A.8' => array(
                'Technological controls',
                34
            )
        );
        $actual = array();
        $total = 0;

        foreach ($definition()['families'] as $family) {
            if ($family['code'] === 'ISMS') {
                continue;
            }

            $actual[$family['code']] = array(
                $family['name'],
                count($family['controls'])
            );
            $total += count($family['controls']);
        }

        Assert::same($expected, $actual, 'Annex A themes and counts');
        Assert::same(93, $total, 'Annex A control count');
    },

    'Annex A numbering is contiguous within each theme' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            if ($family['code'] === 'ISMS') {
                continue;
            }

            $theme = substr($family['code'], 2);

            foreach ($family['controls'] as $i => $control) {
                Assert::same('A.'.$theme.'.'.($i + 1), $control['id'], 'Annex A order in '.$family['code']);
            }
        }
    },

    'clauses 4 to 10 at requirement level, including 6.3' => function () use ($definition) {
        $expected = array(
            '4.1',
            '4.2',
            '4.3',
            '4.4',
            '5.1',
            '5.2',
            '5.3',
            '6.1.1',
            '6.1.2',
            '6.1.3',
            '6.2',
            '6.3',
            '7.1',
            '7.2',
            '7.3',
            '7.4',
            '7.5.1',
            '7.5.2',
            '7.5.3',
            '8.1',
            '8.2',
            '8.3',
            '9.1',
            '9.2.1',
            '9.2.2',
            '9.3.1',
            '9.3.2',
            '9.3.3',
            '10.1',
            '10.2'
        );
        $isms = $definition()['families'][0];
        Assert::same('Management system requirements', $isms['name'], 'clause group name');
        Assert::same($expected, array_column($isms['controls'], 'id'), 'clause IDs');
    },

    'every title appears as a contents entry in its source snapshot' => function () use ($controls, $text) {
        $files = array(
            'iso27001' => $text('iso27001/sist-en-iso-iec-27001-2023-sample.pdf'),
            'iso27002' => $text('iso27001/sist-en-iso-iec-27002-2022-sample.pdf')
        );
        $redline = $text('iso27001/iso-iec-27001-2022-redline-sample.pdf');

        foreach ($controls() as $id => $c) {
            if ($c['source'] === 'iso27001redline') {
                Assert::true(strpos($redline, ' '.$c['number'].' '.$c['title'].' ') !== false, $id.' heading not in the redline sample');
                continue;
            }

            // Contents lines end in a dot leader: "5.23 Information security for use of cloud services....41"
            $needle = $c['number'].' '.$c['title'].'...';
            Assert::true(strpos($files[$c['source']], $needle) !== false, $id.' "'.$c['title'].'" not found in '.$c['source']);
        }
    },

    'known titles, including the 2022 renumbering of clause 10' => function () use ($controls) {
        $all = $controls();
        $expected = array(
            '6.3' => 'Planning of changes',
            '10.1' => 'Continual improvement',
            '10.2' => 'Nonconformity and corrective action',
            'A.5.7' => 'Threat intelligence',
            'A.5.23' => 'Information security for use of cloud services',
            'A.7.4' => 'Physical security monitoring',
            'A.8.12' => 'Data leakage prevention',
            'A.8.28' => 'Secure coding',
            'A.8.34' => 'Protection of information systems during audit testing'
        );

        foreach ($expected as $id => $title) {
            Assert::same($title, $all[$id]['title'], $id.' title');
        }

        Assert::same('Actions to address risks and opportunities', $all['6.1.2']['parent_title'], '6.1.2 parent');
    },

    'IDs agree with the NIST 800-53 to ISO 27001:2022 crosswalk (OLIR 155)' => function () use ($controls) {
        $ids = array();

        for ($sheet = 1; $sheet <= 21; $sheet++) {
            foreach (Source::xlsx_rows('iso27001/olir-155-sp800-53r5-to-iso-27001-2022.xlsx', $sheet) as $row) {
                $d = trim($row['D'] ?? '');

                // Clause numbers were stored as numbers: 4.0999999999999996 is 4.1
                if (preg_match('/^\d+\.\d{6,}$/', $d)) {
                    $d = (string) round((float) $d, 1);
                }

                if (preg_match('/^(A\.)?\d+(\.\d+)+$/', $d)) {
                    $ids[$d] = true;
                }
            }
        }

        $ours = array_keys($controls());
        $missing = array();

        // NIST sometimes maps to a parent heading (9.2); that counts if its sub-clauses are present
        foreach (array_keys($ids) as $id) {
            if (in_array($id, $ours, true)) {
                continue;
            }

            $children = array_filter($ours, function ($o) use ($id) {
                return strpos($o, $id.'.') === 0;
            });

            if (count($children) === 0) {
                $missing[] = $id;
            }
        }

        Assert::same(array(), $missing, 'OLIR 155 IDs missing from the definition');
        // NIST maps no 800-53 control to these three; every other ID is in the crosswalk
        $unmapped = array_values(array_diff($ours, array_keys($ids)));
        Assert::same(array('6.3', 'A.7.6', 'A.8.34'), $unmapped, 'definition IDs not in OLIR 155');
    },

    'Annex A IDs agree with the ISO 27001:2022 to CSF 2.0 mapping (OLIR 218)' => function () use ($controls) {
        $json = json_decode(file_get_contents(Source::path('iso27001/olir-218-iso-27001-2022-to-csf-2.0.json')), true);
        $ids = array();

        foreach ($json['response']['searchResults'] as $row) {
            $ids['A.'.preg_replace('/^Control\s+/', '', trim($row['referenceDocumentElementIdentifier']))] = true;
        }

        $annex = array_values(array_filter(array_keys($controls()), function ($id) {
            return strpos($id, 'A.') === 0;
        }));
        $mapped = array_keys($ids);
        sort($mapped);
        sort($annex);
        Assert::same($annex, $mapped, 'OLIR 218 Annex A IDs');
    },

    'licensed text is never stored; HCG fields are filled in' => function () use ($controls) {
        foreach ($controls() as $id => $c) {
            Assert::same('', $c['text'], $id.' must not carry ISO text');
            Assert::true(strlen($c['description']) > 40, $id.' description too short');
            Assert::true(strlen($c['guidance']) > 40, $id.' guidance too short');
            Assert::true($c['description'] !== $c['guidance'], $id.' description repeats guidance');
            Assert::true(preg_match_all('/[.!?](\s|$)/', $c['description']) <= 2, $id.' description longer than two sentences');
            Assert::true(preg_match_all('/[.!?](\s|$)/', $c['guidance']) <= 3, $id.' guidance longer than three sentences');
        }
    },

    'Annex A controls carry applicability; clauses do not' => function () use ($controls) {
        foreach ($controls() as $id => $c) {
            if ($c['family'] === 'ISMS') {
                Assert::true(!isset($c['applicability']), $id.' clause marked applicable/excludable');
                Assert::same('requirement', $c['type'], $id.' type');
            } else {
                Assert::same(true, $c['applicability'] ?? null, $id.' applicability flag');
                Assert::same('control', $c['type'], $id.' type');
            }
        }
    },

    'SoA, scoring and applicability rules' => function () use ($definition) {
        $d = $definition();
        $rules = array();

        foreach ($d['rules'] as $rule) {
            $rules[$rule['id']] = $rule;
        }

        Assert::same('percentage', $d['scoring']['model'], 'scoring model');
        Assert::same('soa', $d['governing_document']['key'], 'governing document key');
        Assert::true(in_array('governing_document', $d['deliverables'], true), 'SoA deliverable');
        Assert::same('applicability', $rules['annex_a_exclusion_justified']['type'], 'Annex A exclusion rule type');
        Assert::same(true, $rules['annex_a_exclusion_justified']['requires_justification'], 'exclusions need justification');
        Assert::same('ISO/IEC 27001:2022, 6.1.3 d)', $rules['annex_a_exclusion_justified']['cite'], 'exclusion rule cite');
        Assert::same(false, $rules['clauses_mandatory']['excludable'], 'clauses cannot be excluded');
    }
);
