<?php
/**
 * Checks shared by the FedRAMP Rev 5 Low, Moderate and High baselines. Membership is
 * read by the parser from the FedRAMP baseline workbook; it is checked here against
 * FedRAMP's SAR Appendix B traceability matrix for the same baseline and against the
 * NIST SP 800-53B baseline columns in NIST's CPRT export.
 */
return function (string $key, string $level, int $expected): array {
    $definition = function () use ($key) {
        return json_decode(file_get_contents(ROOT.'/frameworks/'.$key.'.json'), true);
    };

    $controls = function () use ($definition) {
        $all = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $control['family_code'] = $family['code'];
                $control['family_name'] = $family['name'];
                $all[$control['id']] = $control;
            }
        }

        return $all;
    };

    // Baseline sheet rows keyed by unspaced ID ("AC-2 (1)" -> "AC-2(1)").
    $workbook = function () use ($level) {
        $sheets = array(
            'High' => 2,
            'Moderate' => 3,
            'Low' => 4
        );
        $rows = array();

        foreach (array_slice(Source::xlsx_rows('fedramp/FedRAMP_Security_Controls_Baseline.xlsx', $sheets[$level]), 2) as $row) {
            $id = str_replace(' ', '', trim($row['C'] ?? ''));

            if ($id !== '') {
                $rows[$id] = $row;
            }
        }

        return $rows;
    };

    $rules = function () use ($key) {
        $out = array();

        foreach (FrameworkBuilder::meta($key)['rules'] as $rule) {
            $out[$rule['id']] = $rule;
        }

        return $out;
    };

    return array(
        $expected.' controls, the same set as the SAR Appendix B traceability matrix' => function () use ($definition, $controls, $level, $expected) {
            Assert::same($expected, $definition()['counts']['controls'], 'controls loaded');
            $matrix = array();

            // ControlSummary sheet: one row per control in the baseline.
            foreach (array_slice(Source::xlsx_rows('fedramp/FedRAMP-SAR-Appendix-B-'.$level.'-Security-Requirements-Traceability-Matrix-Template.xlsx', 5), 1) as $row) {
                $id = trim($row['B'] ?? '');

                if ($id !== '') {
                    $matrix[] = str_replace(' ', '', $id);
                }
            }

            Assert::same($expected, count(array_unique($matrix)), 'controls in the traceability matrix');
            $loaded = array_keys($controls());
            Assert::same(array(), array_values(array_diff($matrix, $loaded)), 'in the traceability matrix but not loaded');
            Assert::same(array(), array_values(array_diff($loaded, $matrix)), 'loaded but not in the traceability matrix');
        },

        'includes every control of the NIST SP 800-53B '.$level.' baseline' => function () use ($controls, $level) {
            $columns = array(
                'Low' => 'H',
                'Moderate' => 'I',
                'High' => 'J'
            );
            $nist = array();

            foreach (array_slice(Source::xlsx_rows('sp800-53/cprt-sp800-53-5.2.0.xlsx'), 1) as $row) {
                if (strtolower(trim($row[$columns[$level]] ?? '')) === 'x') {
                    $nist[] = preg_replace('/(?<=-)0(?=\d)|(?<=\()0(?=\d\))/', '', trim($row['B']));
                }
            }

            Assert::true(count($nist) > 100, 'NIST baseline read from CPRT');
            Assert::same(array(), array_values(array_diff($nist, array_keys($controls()))), 'NIST '.$level.' controls missing from FedRAMP '.$level);
        },

        'workbook names, families and statements agree with SP 800-53 Rev 5.2.0' => function () use ($controls, $workbook, $level) {
            $all = $controls();

            // Rendering differences only: CPRT prints "Selection (one)" and pads cited IDs.
            $flat = function (string $text): string {
                $text = str_replace('Selection (one)', 'Selection', Source::normalise($text));
                $text = preg_replace('/(?<=\b[A-Z]{2}-)0(?=\d)|(?<=\()0(?=\d\))/', '', $text);

                return strtolower(preg_replace('/[^a-z0-9]+/i', '', $text));
            };

            // 5.2.0 changed "external organization" to "external organization(s)" in CA-2(3).
            $reworded = array(
                'CA-2(3)'
            );

            // The Moderate sheet prints the SP 800-53 Rev 4 wording of MA-5(1); the Rev 5 text is loaded.
            if ($level === 'Moderate') {
                $reworded[] = 'MA-5(1)';
            }

            // The workbook keeps two older family labels; the Rev 5 catalog names win.
            $family_labels = array(
                'SECURITY ASSESSMENT AND AUTHORIZATION' => 'ASSESSMENT, AUTHORIZATION, AND MONITORING',
                'SUPPLY CHAIN RISK MANAGEMENT FAMILY' => 'SUPPLY CHAIN RISK MANAGEMENT'
            );

            foreach ($workbook() as $id => $row) {
                $control = $all[$id];
                $name = $control['parent'] === null ? $control['title'] : $all[$control['parent']]['title'].' | '.$control['title'];
                Assert::same(strtolower($name), strtolower(Source::normalise($row['D'])), 'name of '.$id);
                Assert::same(substr($id, 0, 2), $control['family_code'], 'family code of '.$id);
                $label = Source::normalise($row['B']);
                Assert::same(strtoupper($control['family_name']), $family_labels[$label] ?? $label, 'family of '.$id);

                if (!in_array($id, $reworded, true)) {
                    Assert::same($flat($row['E']), $flat($control['text']), 'statement of '.$id);
                }
            }
        },

        'FedRAMP parameters and requirements come from the workbook' => function () use ($controls, $workbook) {
            $all = $controls();
            $with = 0;

            foreach ($workbook() as $id => $row) {
                $cell = Source::normalise($row['G'] ?? '');
                Assert::same($cell, Source::normalise($all[$id]['fedramp_parameters'] ?? ''), 'FedRAMP parameters of '.$id);
                Assert::same(Source::normalise($row['H'] ?? ''), Source::normalise($all[$id]['fedramp_requirements'] ?? ''), 'FedRAMP requirements of '.$id);

                if ($cell !== '') {
                    $with++;
                }
            }

            Assert::true($with > 0, 'some controls carry FedRAMP parameters');
        },

        'every control has SP 800-53A objectives' => function () use ($controls) {
            foreach ($controls() as $id => $control) {
                Assert::true(count($control['objectives']) > 0, $id.' has objectives');
            }
        },

        'POA&M rules match the FedRAMP Playbook' => function () use ($rules) {
            $html = Source::normalise(file_get_contents(Source::path('fedramp/fedramp-playbook-csp-poam.html')));
            $matched = preg_match('/Critical and High risks to be remediated within (\d+) days of discovery, Moderate risks within (\d+) days of discovery, and Low risks within (\d+) days of discovery/', $html, $m);
            Assert::same(1, $matched, 'remediation sentence found');
            $expected = array(
                'critical' => (int) $m[1],
                'high' => (int) $m[1],
                'moderate' => (int) $m[2],
                'low' => (int) $m[3]
            );
            Assert::same($expected, $rules()['remediation_timeframes']['days_by_risk'], 'remediation days by risk');
            Assert::same(1, preg_match('/High-risk VDs must be mitigated to a Moderate level through compensating controls within [a-z]+ \((\d+)\) days/', $html, $v), 'vendor dependency sentence found');
            Assert::same((int) $v[1], $rules()['vendor_dependency_mitigation']['days'], 'vendor dependency days');
        },

        'legacy notice date matches the workbook cover sheet' => function () use ($rules) {
            $cover = '';

            foreach (Source::xlsx_rows('fedramp/FedRAMP_Security_Controls_Baseline.xlsx', 1) as $row) {
                $cover .= implode(' ', $row).' ';
            }

            $cover = Source::normalise($cover);
            Assert::true(strpos($cover, 'LEGACY NOTICE June 23, 2026') !== false, 'legacy notice date on the cover sheet');
            Assert::same('2026-06-23', $rules()['legacy_baseline']['date'], 'legacy date in the rule');
        }
    );
};
