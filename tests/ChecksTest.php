<?php
/**
 * Assessment checks: what blocks completion and what only warns.
 */
require_once ROOT.'/libs/Classes/FrameworkLibrary.php';
require_once ROOT.'/libs/Classes/SpellCheck.php';
require_once ROOT.'/libs/Classes/AssessmentScore.php';
require_once ROOT.'/libs/Classes/AssessmentChecks.php';

$assessment = function (array $fields = array()) {
    return array_merge(array(
        'assessment_name' => 'Baseline',
        'assessment_type' => 'Baseline',
        'baseline_assessment_id' => 0,
        'no_baseline' => 0,
        'client_name' => 'Geneva Ground Services',
        'scope_categories' => '',
        'standard_id' => 132,
        'standard_name' => 'CMMC Level 2',
        'version' => '32 CFR Part 170',
        'framework_key' => 'cmmc-l2'
    ), $fields);
};

$control = function (string $id, string $result, array $fields = array()) {
    return array_merge(array(
        'control_identifier' => $id,
        'family' => 'Access Control',
        'item_result' => $result,
        'control_weight' => 1,
        'partial_weight' => null,
        'partial_credit' => 0,
        'applicability' => 'Applicable'
    ), $fields);
};

$codes = function (array $issues, string $severity = '') {
    $out = array();

    foreach ($issues as $issue) {
        if ($severity === '' || $issue['severity'] === $severity) {
            $out[] = $issue['code'];
        }
    }

    return $out;
};

return array(
    'only unassessed controls block completion; failed controls need nothing more' => function () use ($assessment, $control, $codes) {
        $controls = array(
            $control('AC.L2-3.1.1', 'Not Assessed'),
            $control('AC.L2-3.1.2', 'Not Implemented'),
            $control('AC.L2-3.1.3', 'Partially Implemented')
        );
        $issues = AssessmentChecks::run(null, $assessment(), array('project_name' => 'CMMC readiness'), $controls, null);
        Assert::same(array('unassessed'), $codes($issues, 'block'), 'blocking checks');
        Assert::same(array(), $codes($issues, 'warn'), 'no warnings');
    },

    'a Final needs a baseline or the no-baseline flag' => function () use ($assessment, $codes) {
        $without = AssessmentChecks::run(null, $assessment(array('assessment_type' => 'Final')), array('project_name' => 'x'), array(), null);
        $flagged = AssessmentChecks::run(null, $assessment(array('assessment_type' => 'Final', 'no_baseline' => 1)), array('project_name' => 'x'), array(), null);
        $linked = AssessmentChecks::run(null, $assessment(array('assessment_type' => 'Final', 'baseline_assessment_id' => 901)), array('project_name' => 'x'), array(), null);
        Assert::same(array('final_needs_baseline'), $codes($without, 'block'), 'without');
        Assert::same(array(), $codes($flagged, 'block'), 'no-baseline flag');
        Assert::same(array(), $codes($linked, 'block'), 'baseline linked');
    },

    'spelling and repeated words warn but never block' => function () use ($assessment, $control, $codes) {
        $issues = AssessmentChecks::run(null, $assessment(), array('project_name' => 'Part-IS Implementation'), array(
            $control('AC.L2-3.1.1', 'Not Applicable', array(
                'applicability' => 'Excluded',
                'applicability_justification' => 'Geneva Ground Services has has a recieved policy set, and MFA is enforced for every organisation user.'
            ))
        ), null);
        Assert::same(array('repeated_word', 'spelling'), $codes($issues), 'warnings');
        Assert::same(array(), $codes($issues, 'block'), 'nothing blocks');
        Assert::true(strpos($issues[1]['message'], 'recieved') !== false, 'flags the misspelling');
    },

    'an excluded control without a justification blocks' => function () use ($assessment, $control, $codes) {
        $issues = AssessmentChecks::run(null, $assessment(), array('project_name' => 'x'), array(
            $control('A.5.1', 'Not Applicable', array(
                'applicability' => 'Excluded'
            ))
        ), null);
        Assert::same(array('exclusion_unjustified'), $codes($issues, 'block'), 'blocking');
    }
);
