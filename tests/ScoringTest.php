<?php
/**
 * Scoring against hand calculations. Each case builds an assessment from the
 * real framework definition, sets results, and checks the numbers worked out
 * by hand in the comment beside it.
 */
require_once ROOT.'/libs/Classes/AssessmentScore.php';

$definition = function (string $key) {
    return json_decode(file_get_contents(ROOT.'/frameworks/'.$key.'.json'), true);
};

/* Every control of a framework as an assessment row, all set to $default, then $results applied. */
$controls = function (string $key, string $default, array $results = array(), array $partial_credit = array(), array $excluded = array()) use ($definition) {
    $rows = array();

    foreach ($definition($key)['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $rows[] = array(
                'control_identifier' => $control['id'],
                'family' => $family['name'],
                'item_result' => $results[$control['id']] ?? $default,
                'control_weight' => $control['weight'] ?? null,
                'partial_weight' => $control['partial_weight'] ?? null,
                'partial_credit' => in_array($control['id'], $partial_credit, true) ? 1 : 0,
                'applicability' => in_array($control['id'], $excluded, true) ? 'Excluded' : 'Applicable'
            );
        }
    }

    return $rows;
};

$score = function (string $key, array $rows, array $assessment = array()) use ($definition) {
    return AssessmentScore::score($definition($key), $assessment, $rows);
};

$failing = function (array $ids) {
    return array_fill_keys($ids, 'Not Implemented');
};

return array(
    'CMMC L2: everything met scores 110 and supports Final Level 2' => function () use ($controls, $score) {
        $s = $score('cmmc-l2', $controls('cmmc-l2', 'Implemented'));
        Assert::same(110, $s['points']['score'], 'score');
        Assert::same('pass', $s['outcome']['status'], 'status');
        Assert::same('Final Level 2', $s['outcome']['label'], 'label');
    },

    'CMMC L2: everything not met scores -203 (110 - 42x5 - 14x3 - 51x1 - 2x5)' => function () use ($controls, $score) {
        $s = $score('cmmc-l2', $controls('cmmc-l2', 'Not Implemented'));
        Assert::same(-203, $s['points']['score'], 'score');
        Assert::same(313, $s['points']['deducted'], 'points deducted');
        Assert::same('not_eligible', $s['outcome']['status'], 'status');
    },

    'CMMC L2: three 1-point gaps score 107 and support Conditional Level 2' => function () use ($controls, $score, $failing) {
        $s = $score('cmmc-l2', $controls('cmmc-l2', 'Implemented', $failing(array('AC.L2-3.1.3', 'AC.L2-3.1.4', 'AC.L2-3.1.6'))));
        Assert::same(107, $s['points']['score'], 'score');
        Assert::same('conditional', $s['outcome']['status'], 'status');
        Assert::same('Conditional Level 2', $s['outcome']['label'], 'label');
        Assert::same(array('AC.L2-3.1.3', 'AC.L2-3.1.4', 'AC.L2-3.1.6'), $s['plan']['deferrable'], 'POA&M items');
    },

    'CMMC L2: 22 one-point gaps score 88, exactly 80%, Conditional; 23 score 87, not eligible' => function () use ($controls, $score, $failing, $definition) {
        $ones = array();

        foreach ($definition('cmmc-l2')['families'] as $family) {
            foreach ($family['controls'] as $control) {
                if ($control['weight'] === 1 && !in_array($control['id'], array('AC.L2-3.1.20', 'AC.L2-3.1.22', 'PE.L2-3.10.3', 'PE.L2-3.10.4', 'PE.L2-3.10.5'), true)) {
                    $ones[] = $control['id'];
                }
            }
        }

        $at = $score('cmmc-l2', $controls('cmmc-l2', 'Implemented', $failing(array_slice($ones, 0, 22))));
        $below = $score('cmmc-l2', $controls('cmmc-l2', 'Implemented', $failing(array_slice($ones, 0, 23))));
        Assert::same(88, $at['points']['score'], 'score at threshold');
        Assert::same('conditional', $at['outcome']['status'], 'status at threshold');
        Assert::same(87, $below['points']['score'], 'score below threshold');
        Assert::same('not_eligible', $below['outcome']['status'], 'status below threshold');
    },

    'CMMC L2: SC.L2-3.13.11 partial credit subtracts 3 and may be deferred; without it subtracts 5 and must close' => function () use ($controls, $score, $failing) {
        $partial = $score('cmmc-l2', $controls('cmmc-l2', 'Implemented', $failing(array('SC.L2-3.13.11')), array('SC.L2-3.13.11')));
        $full = $score('cmmc-l2', $controls('cmmc-l2', 'Implemented', $failing(array('SC.L2-3.13.11'))));
        Assert::same(107, $partial['points']['score'], 'partial score');
        Assert::same('conditional', $partial['outcome']['status'], 'partial status');
        Assert::same(105, $full['points']['score'], 'full score');
        Assert::same(array('SC.L2-3.13.11'), $full['plan']['must_close'], 'must close');
        Assert::same('not_eligible', $full['outcome']['status'], 'full status');
    },

    'CMMC L2: IA.L2-3.5.3 partial credit subtracts 3 but a 3-point item cannot be deferred' => function () use ($controls, $score, $failing) {
        $s = $score('cmmc-l2', $controls('cmmc-l2', 'Implemented', $failing(array('IA.L2-3.5.3')), array('IA.L2-3.5.3')));
        Assert::same(107, $s['points']['score'], 'score');
        Assert::same(array('IA.L2-3.5.3'), $s['plan']['must_close'], 'must close');
    },

    'CMMC L2: a never-deferrable 1-point item (AC.L2-3.1.20) blocks Conditional' => function () use ($controls, $score, $failing) {
        $s = $score('cmmc-l2', $controls('cmmc-l2', 'Implemented', $failing(array('AC.L2-3.1.20'))));
        Assert::same(109, $s['points']['score'], 'score');
        Assert::same('not_eligible', $s['outcome']['status'], 'status');
    },

    'CMMC L2: a missing SSP (CA.L2-3.12.4) costs no points but blocks every status' => function () use ($controls, $score, $failing) {
        $s = $score('cmmc-l2', $controls('cmmc-l2', 'Implemented', $failing(array('CA.L2-3.12.4'))));
        Assert::same(110, $s['points']['score'], 'score');
        Assert::same(false, $s['gates'][0]['met'], 'SSP gate');
        Assert::same('not_eligible', $s['outcome']['status'], 'status');
    },

    'CMMC L2: unassessed requirements subtract nothing and make the result provisional' => function () use ($controls, $score) {
        $s = $score('cmmc-l2', $controls('cmmc-l2', 'Not Assessed', array('AC.L2-3.1.1' => 'Not Implemented')));
        Assert::same(105, $s['points']['score'], 'score');
        Assert::same(false, $s['complete'], 'complete');
        Assert::same(109, $s['counts']['Not Assessed'], 'unassessed');
    },

    'CMMC L2: nothing assessed yet gives no outcome' => function () use ($controls, $score) {
        $s = $score('cmmc-l2', $controls('cmmc-l2', 'Not Assessed'));
        Assert::same('not_started', $s['outcome']['status'], 'status');
        Assert::same(110, $s['points']['score'], 'score');
    },

    'NIST 800-171 r2: N/A subtracts nothing and the SPRS maths matches CMMC' => function () use ($controls, $score) {
        $s = $score('nist-800-171-r2', $controls('nist-800-171-r2', 'Implemented', array('3.1.1' => 'Not Implemented', '3.1.2' => 'Not Applicable', '3.1.5' => 'Partially Implemented')));
        Assert::same(102, $s['points']['score'], '110 - 5 (3.1.1) - 3 (3.1.5)');
    },

    'CMMC L1: percentage with no plan of action allowed' => function () use ($controls, $score) {
        $s = $score('cmmc-l1', $controls('cmmc-l1', 'Implemented', array('AC.L1-b.1.i' => 'Not Implemented')));
        Assert::same(93.3, $s['percentage'], '14 of 15');
        Assert::same(array('AC.L1-b.1.i'), $s['plan']['must_close'], 'must close');
        Assert::same('not_eligible', $s['outcome']['status'], 'status');
    },

    'ISO 27001: partial counts half and excluded controls leave the denominator' => function () use ($controls, $score, $definition) {
        $annex = array();

        foreach ($definition('iso-27001-2022')['families'] as $family) {
            foreach ($family['controls'] as $control) {
                if (strpos($control['id'], 'A.') === 0) {
                    $annex[] = $control['id'];
                }
            }
        }

        $results = array_fill_keys(array_slice($annex, 0, 10), 'Partially Implemented') + array_fill_keys(array_slice($annex, 10, 13), 'Not Implemented');
        $s = $score('iso-27001-2022', $controls('iso-27001-2022', 'Implemented', $results, array(), array_slice($annex, 20, 3)));
        Assert::same(120, $s['applicable'], '123 minus 3 excluded');
        Assert::same(3, $s['excluded'], 'excluded');
        Assert::same(87.5, $s['percentage'], '100 met (3 of the 13 gaps are excluded) + 0.5 x 10 partial, over 120');
    },

    'SOC 2: only the common criteria are in scope until a category is chosen' => function () use ($controls, $score) {
        $common = $score('soc2', $controls('soc2', 'Implemented'));
        $availability = $score('soc2', $controls('soc2', 'Implemented'), array(
            'scope_categories' => 'Availability'
        ));
        Assert::same(33, $common['in_scope'], 'common criteria');
        Assert::same(36, $availability['in_scope'], 'common criteria plus A1.1 to A1.3');
    },

    'PCI DSS: compliant only with every applicable requirement in place' => function () use ($controls, $score) {
        $all = $score('pci-dss-4', $controls('pci-dss-4', 'Implemented', array('12.5.2.1' => 'Not Applicable')));
        $one = $score('pci-dss-4', $controls('pci-dss-4', 'Implemented', array('8.4.2' => 'Not Implemented')));
        Assert::same('pass', $all['outcome']['status'], 'all in place');
        Assert::same(100.0, $all['percentage'], 'percentage');
        Assert::same('not_eligible', $one['outcome']['status'], 'one gap');
        Assert::same(99.6, $one['percentage'], '249 of 250');
    },

    'CSF 2.0: tiers come from the consultant, never from the results' => function () use ($controls, $score) {
        $s = $score('nist-csf-2', $controls('nist-csf-2', 'Not Implemented'), array(
            'tier_governance' => 2,
            'tier_management' => 3,
            'target_tier_governance' => 3,
            'target_tier_management' => 4
        ));
        Assert::same('Risk Informed', $s['maturity']['governance']['label'], 'governance tier');
        Assert::same('Repeatable', $s['maturity']['management']['label'], 'management tier');
        Assert::same('Adaptive', $s['maturity']['target_management']['label'], 'target');
        Assert::same(0.0, $s['percentage'], 'nothing achieved');
    },

    'CMMC L3: one point each from 24' => function () use ($controls, $score) {
        $s = $score('cmmc-l3', $controls('cmmc-l3', 'Implemented', array('AC.L3-3.1.2e' => 'Not Implemented')));
        Assert::same(23, $s['points']['score'], 'score');
        Assert::same('conditional', $s['outcome']['status'], '23/24 is above 80%, and 3.1.2e may be deferred');
    }
);
