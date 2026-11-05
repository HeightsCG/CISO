<?php
/**
 * One score everywhere. AssessmentScore is the scoring service the assessment
 * screens and API use; ReportAnalysis (and so every report builder, in PDF,
 * Word and Excel) must print the same overall and family numbers for the
 * same control rows, describe the framework's own rule, and keep the points
 * score and formal outcome apart from the percentage. Boundary cases cover
 * all met, all partial, all not met, not applicable and unassessed for a
 * percentage framework without partial credit (SOC 2), one with partial
 * credit (ISO 27001) and a points framework (CMMC Level 2). No database.
 */
require_once ROOT.'/libs/Classes/AssessmentScore.php';
require_once ROOT.'/libs/Classes/ReportAnalysis.php';
require_once ROOT.'/libs/Classes/ReportQuality.php';
require_once ROOT.'/libs/Classes/AiAssistant.php';
require_once ROOT.'/libs/Classes/ReportAI.php';

$definition = function (string $key) {
    return json_decode(file_get_contents(ROOT.'/frameworks/'.$key.'.json'), true);
};

/* Every control of a framework as an assessment row: $result(index, id) gives each result. */
$rows = function (string $key, callable $result) use ($definition) {
    $out = array();
    $index = 0;

    foreach ($definition($key)['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $out[] = array(
                'control_identifier' => $control['id'],
                'family' => $family['name'],
                'item_result' => $result($index, $control['id']),
                'control_weight' => $control['weight'] ?? null,
                'partial_weight' => $control['partial_weight'] ?? null,
                'partial_credit' => 0,
                'applicability' => 'Applicable'
            );
            $index++;
        }
    }

    return $out;
};

$all = function (string $value) {
    return function () use ($value) {
        return $value;
    };
};

/* Both sides for the same rows: the service result and what the reports print. */
$both = function (string $key, array $control_rows) use ($definition) {
    $d = $definition($key);

    return array(
        'service' => AssessmentScore::score($d, array(), $control_rows),
        'report' => ReportAnalysis::scores($d, array(), $control_rows, $d['name'])
    );
};

$agree = function (array $pair, string $label) {
    $service = $pair['service'];
    $report = $pair['report'];
    $assessed = $service['counts']['Implemented'] + $service['counts']['Partially Implemented'] + $service['counts']['Not Implemented'];

    Assert::same($assessed === 0 ? null : (int) round($service['percentage']), $report['score'], $label.': overall score');

    foreach ($service['families'] as $family) {
        $family_assessed = $family['counts']['Implemented'] + $family['counts']['Partially Implemented'] + $family['counts']['Not Implemented'];
        Assert::true(array_key_exists($family['name'], $report['families']), $label.': family '.$family['name'].' is reported');
        Assert::same($family_assessed === 0 ? null : (int) round($family['percentage']), $report['families'][$family['name']], $label.': '.$family['name']);
    }
};

return array(
    'SOC 2 (no partial credit): all met, all partial, all not met' => function () use ($rows, $all, $both, $agree) {
        $met = $both('soc2', $rows('soc2', $all('Implemented')));
        $partial = $both('soc2', $rows('soc2', $all('Partially Implemented')));
        $not_met = $both('soc2', $rows('soc2', $all('Not Implemented')));

        Assert::same(100.0, $met['service']['percentage'], 'all met');
        Assert::same(100, $met['report']['score'], 'all met, as printed');
        Assert::same(0.0, $partial['service']['percentage'], 'partially met criteria receive no credit');
        Assert::same(0, $partial['report']['score'], 'all partial, as printed');
        Assert::same(0, $not_met['report']['score'], 'all not met');
        $agree($met, 'all met');
        $agree($partial, 'all partial');
        $agree($not_met, 'all not met');
    },

    'SOC 2: not applicable and unassessed criteria leave the denominator; nothing assessed is not yet scored' => function () use ($rows, $both, $agree) {
        $na = $both('soc2', $rows('soc2', function ($i) {
            return $i % 2 === 0 ? 'Not Applicable' : 'Implemented';
        }));
        Assert::same(100.0, $na['service']['percentage'], 'every applicable criterion met');
        Assert::same(100, $na['report']['score'], 'as printed');
        $agree($na, 'not applicable');

        $none = $both('soc2', $rows('soc2', function () {
            return 'Not Assessed';
        }));
        Assert::same(null, $none['service']['percentage'], 'nothing assessed is not yet scored');
        Assert::same(null, $none['report']['score'], 'reports show no score until something is assessed');
        $agree($none, 'nothing assessed');

        $half = $both('soc2', $rows('soc2', function ($i) {
            return $i < 10 ? 'Implemented' : 'Not Assessed';
        }));
        $applicable = $half['service']['applicable'];
        Assert::same(100.0, $half['service']['percentage'], 'unassessed criteria are left out until assessed');
        Assert::same($applicable - 10, $half['service']['counts']['Not Assessed'], 'completion is still tracked separately');
        $agree($half, 'partly assessed');
    },

    'SOC 2: the recorded mix of assessment 916 scores 7, not 52' => function () use ($rows, $both, $agree) {
        $pair = $both('soc2', $rows('soc2', function ($i) {
            return $i < 4 ? 'Implemented' : ($i < 60 ? 'Partially Implemented' : ($i === 60 ? 'Not Implemented' : 'Not Applicable'));
        }));
        Assert::same(61, $pair['service']['applicable'], '61 applicable criteria');
        Assert::same(6.6, $pair['service']['percentage'], '4 of 61');
        Assert::same(7, $pair['report']['score'], 'printed as 7%');
        $agree($pair, '916 mix');
    },

    'ISO 27001 (partial counts half): all met, all partial, all not met, N/A, unassessed' => function () use ($rows, $all, $both, $agree) {
        $met = $both('iso-27001-2022', $rows('iso-27001-2022', $all('Implemented')));
        $partial = $both('iso-27001-2022', $rows('iso-27001-2022', $all('Partially Implemented')));
        $not_met = $both('iso-27001-2022', $rows('iso-27001-2022', $all('Not Implemented')));
        $na = $both('iso-27001-2022', $rows('iso-27001-2022', function ($i) {
            return $i % 3 === 0 ? 'Not Applicable' : 'Partially Implemented';
        }));
        $unassessed = $both('iso-27001-2022', $rows('iso-27001-2022', function ($i) {
            return $i < 20 ? 'Partially Implemented' : 'Not Assessed';
        }));

        Assert::same(100, $met['report']['score'], 'all met');
        Assert::same(50.0, $partial['service']['percentage'], 'all partial counts half');
        Assert::same(50, $partial['report']['score'], 'as printed');
        Assert::same(0, $not_met['report']['score'], 'all not met');
        Assert::same(50.0, $na['service']['percentage'], 'not applicable leaves the denominator');
        Assert::same(round(10 / $unassessed['service']['scored'] * 100, 1), $unassessed['service']['percentage'], 'unassessed controls are left out of the denominator');
        foreach (array($met, $partial, $not_met, $na, $unassessed) as $n => $pair) {
            $agree($pair, 'ISO case '.$n);
        }
    },

    'CMMC L2 (points): percentage and points score kept apart' => function () use ($rows, $all, $both, $agree) {
        $met = $both('cmmc-l2', $rows('cmmc-l2', $all('Implemented')));
        $partial = $both('cmmc-l2', $rows('cmmc-l2', $all('Partially Implemented')));
        $not_met = $both('cmmc-l2', $rows('cmmc-l2', $all('Not Implemented')));
        $na = $both('cmmc-l2', $rows('cmmc-l2', function ($i) {
            return $i === 0 ? 'Not Applicable' : 'Implemented';
        }));
        $unassessed = $both('cmmc-l2', $rows('cmmc-l2', function ($i) {
            return $i === 0 ? 'Not Implemented' : 'Not Assessed';
        }));

        Assert::same(100, $met['report']['score'], 'all met');
        Assert::same(110, $met['report']['score_method']['points']['score'], 'all met scores 110');
        Assert::same('SPRS score', $met['report']['score_method']['points']['label'], 'the framework names its points score');
        Assert::same('Final Level 2', $met['report']['score_method']['outcome']['label'], 'formal outcome reported separately');
        Assert::same(0, $partial['report']['score'], 'partial earns no percentage credit');
        Assert::same(-203, $partial['report']['score_method']['points']['score'], 'without the partial-effectiveness flag a partial requirement loses its full weight');
        Assert::same(-203, $not_met['report']['score_method']['points']['score'], 'all not met');
        Assert::same(110, $na['report']['score_method']['points']['score'], 'not applicable subtracts nothing');
        Assert::same(100, $na['report']['score'], 'and leaves the denominator');
        Assert::same($unassessed['service']['points']['score'], $unassessed['report']['score_method']['points']['score'], 'points as the service works them out');
        Assert::true(strpos($unassessed['report']['score_method']['points']['sentence'], 'provisional') !== false, 'unassessed requirements make the points score provisional');
        foreach (array($met, $partial, $not_met, $na, $unassessed) as $n => $pair) {
            $agree($pair, 'CMMC case '.$n);
        }
    },

    'every framework: reports print the service score for a mixed assessment' => function () use ($rows, $both, $agree) {
        $cycle = array(
            'Implemented',
            'Partially Implemented',
            'Not Implemented',
            'Implemented',
            'Not Applicable',
            'Not Assessed',
            'Partially Implemented'
        );

        foreach (glob(ROOT.'/frameworks/*.json') as $file) {
            $key = basename($file, '.json');
            $pair = $both($key, $rows($key, function ($i) use ($cycle) {
                return $cycle[($i * 5 + intdiv($i, 7)) % count($cycle)];
            }));
            $agree($pair, $key);
        }
    },

    'method wording comes from the definition, never a universal rule' => function () use ($rows, $all, $both) {
        $soc2 = $both('soc2', $rows('soc2', $all('Implemented')))['report']['score_method'];
        $iso = $both('iso-27001-2022', $rows('iso-27001-2022', $all('Implemented')))['report']['score_method'];
        $fedramp = $both('fedramp-high', $rows('fedramp-high', $all('Implemented')))['report']['score_method'];

        Assert::true(strpos($soc2['method'], 'recorded as met') !== false, 'SOC 2: criteria recorded as met');
        Assert::true(strpos($soc2['method'], 'Partially met criteria receive no credit') !== false, 'SOC 2: no partial credit');
        Assert::true(strpos($soc2['method'], 'not an audit opinion') !== false, 'SOC 2: not an opinion');
        Assert::true(stripos($soc2['method'], 'half') === false, 'SOC 2: never half credit');
        Assert::same(null, $soc2['points'], 'SOC 2 has no points score');
        Assert::same(null, $soc2['outcome'], 'SOC 2 has no formal outcome');
        Assert::true(strpos($iso['method'], 'counts as half') !== false, 'ISO: the partial credit rule value');
        Assert::same(0.5, $iso['partial_value'], 'ISO partial value from the definition');
        Assert::true(strpos($fedramp['method'], 'recorded as satisfied') !== false, 'FedRAMP: its own result label');

        $plain = ReportAnalysis::scores(null, array(), $rows('soc2', $all('Partially Implemented')), 'Custom standard');
        Assert::same(0, $plain['score'], 'a standard without a definition gives no partial credit');
        Assert::true(strpos($plain['score_method']['method'], 'Custom standard') !== false, 'named by its standard');
    },

    'no report code computes its own score' => function () {
        $files = array(
            'ReportAnalysis',
            'ReportAI',
            'ReportBuilder',
            'ReportExecutiveSummary',
            'ReportAssessmentResults',
            'ReportRiskAssessment',
            'ReportGapAnalysis',
            'ReportRemediationPlan',
            'ReportStatementOfApplicability',
            'ReportEvidenceRegister',
            'ReportQuality',
            'ReportNarratives'
        );

        foreach ($files as $file) {
            $source = (string) file_get_contents(ROOT.'/libs/Classes/'.$file.'.php');
            Assert::true(preg_match('/0\.5\s*(?:\*|×|x)\s*(?:\$|partial|Partially)|counts? (?:at )?half|at half\b/i', $source) !== 1, $file.' hardcodes half credit');
        }
    },

    'a narrative quoting a score other than the authoritative one is flagged' => function () use ($rows, $both) {
        $pair = $both('soc2', $rows('soc2', function ($i) {
            return $i < 4 ? 'Implemented' : ($i < 60 ? 'Partially Implemented' : ($i === 60 ? 'Not Implemented' : 'Not Applicable'));
        }));
        $analysis = array(
            'controls' => array(),
            'counts' => $pair['service']['counts'],
            'score' => $pair['report']['score'],
            'score_method' => $pair['report']['score_method'],
            'families' => array(),
            'findings' => array(),
            'initiatives' => array(),
            'with_evidence' => 0
        );
        $sections = array(
            'executive' => array(
                'overview' => array(
                    'The assessment gives an implementation score of 52 across the criteria.'
                )
            )
        );
        $flagged = array_filter(ReportQuality::check($analysis, $sections), function ($issue) {
            return $issue['category'] === 'cross_report_inconsistency' && strpos($issue['description'], 'score of 52') !== false;
        });
        Assert::true(count($flagged) === 1, 'the old figure is flagged against the authoritative 7');

        $sections['executive']['overview'] = array(
            'The assessment gives an implementation score of 7 across the criteria.'
        );
        $flagged = array_filter(ReportQuality::check($analysis, $sections), function ($issue) {
            return $issue['category'] === 'cross_report_inconsistency' && strpos($issue['description'], 'Quotes a score') !== false;
        });
        Assert::true(count($flagged) === 0, 'the authoritative figure passes');
    },
    'Every percentage partial credit rule states its value; a rule without one is an error, not a silent zero' => function () use ($definition) {
        foreach (glob(ROOT.'/frameworks/*.json') as $file) {
            $d = json_decode(file_get_contents($file), true);
            foreach ($d['rules'] ?? array() as $rule) {
                if (($rule['type'] ?? '') === 'partial_credit' && !isset($rule['controls'])) {
                    Assert::true(isset($rule['value']) && is_numeric($rule['value']), basename($file).' '.$rule['id'].' has a value');
                }
            }
        }

        $broken = $definition('soc2');
        $broken['rules'][] = array(
            'id' => 'incomplete',
            'type' => 'partial_credit'
        );
        $threw = false;
        try {
            AssessmentScore::score($broken, array(), array());
        } catch (RuntimeException $e) {
            $threw = true;
        }
        Assert::true($threw, 'a partial credit rule without a value is refused');
    },

    'NIST CSF 2.0: a partially achieved outcome counts half under the explicit rule' => function () use ($rows, $all, $both, $agree) {
        $partial = $both('nist-csf-2', $rows('nist-csf-2', $all('Partially Implemented')));
        Assert::same(0.5, $partial['service']['partial_value'], 'explicit value from the definition');
        Assert::same(50.0, $partial['service']['percentage'], 'all partial counts half');
        $agree($partial, 'CSF all partial');
    },

    'CMMC Level 2: partial answers stay as data; the formal points score follows the framework rule' => function () use ($rows, $both) {
        $pair = $both('cmmc-l2', $rows('cmmc-l2', function ($i) {
            return $i === 0 ? 'Partially Implemented' : 'Implemented';
        }));
        $control_rows = $pair['service'];
        Assert::same(1, $control_rows['counts']['Partially Implemented'], 'the partial answer is kept');
        Assert::same(0.0, $control_rows['partial_value'], 'no percentage credit for a partial answer');
        Assert::true($control_rows['points']['deducted'] > 0, 'the points score deducts the requirement as not met');
        Assert::same(null, $both('cmmc-l2', $rows('cmmc-l2', function () {
            return 'Not Assessed';
        }))['service']['percentage'], 'nothing assessed is not yet scored');
    },

    'A scoring method change marks an issued Final as changed' => function () {
        require_once ROOT.'/libs/Classes/ReportStates.php';
        $analysis = array(
            'name' => 'A',
            'controls' => array(),
            'scoring' => array(
                'method' => AssessmentScore::METHOD,
                'model' => 'percentage',
                'partial_value' => 0.0
            ),
            'score_method' => array(
                'method' => 'basis'
            )
        );
        $changed = $analysis;
        $changed['scoring']['method'] = 'next';
        Assert::true(ReportStates::facts($analysis) !== ReportStates::facts($changed), 'method version is part of the Final facts');
        $changed = $analysis;
        $changed['scoring']['partial_value'] = 0.5;
        Assert::true(ReportStates::facts($analysis) !== ReportStates::facts($changed), 'partial credit value is part of the Final facts');
    }
);
