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
    'a Part-IS project assessed against CMMC Level 2 raises the mismatch warning' => function () {
        $message = AssessmentChecks::mismatch(FrameworkLibrary::get('cmmc-l2'), 'Part-IS Implementation');
        Assert::true($message !== null, 'warning expected');
        Assert::true(strpos($message, 'EASA Part-IS') !== false && strpos($message, 'CMMC Level 2') !== false, 'message names both: '.$message);
    },

    'no mismatch when the project names the assessed framework, a related one, or none' => function () {
        Assert::same(null, AssessmentChecks::mismatch(FrameworkLibrary::get('cmmc-l2'), 'CMMC Level 2 readiness'), 'same framework');
        Assert::same(null, AssessmentChecks::mismatch(FrameworkLibrary::get('cmmc-l2'), 'NIST 800-171 gap assessment'), 'related framework');
        Assert::same(null, AssessmentChecks::mismatch(FrameworkLibrary::get('easa-part-is-i'), 'Part-IS Implementation'), 'Part-IS project');
        Assert::same(null, AssessmentChecks::mismatch(FrameworkLibrary::get('iso-27001-2022'), 'Annual security review'), 'no framework named');
    },

    'unassessed controls and incomplete findings block completion' => function () use ($assessment, $control, $codes) {
        $controls = array(
            $control('AC.L2-3.1.1', 'Not Assessed'),
            $control('AC.L2-3.1.2', 'Not Implemented', array(
                'gap_description' => 'No access reviews.',
                'risk_rating' => 'High'
            )),
            $control('AC.L2-3.1.3', 'Partially Implemented', array(
                'gap_description' => 'Flow rules incomplete.',
                'business_impact' => 'CUI could leave the enclave.',
                'recommendation' => 'Add egress rules.',
                'risk_rating' => 'Moderate'
            ))
        );
        $issues = AssessmentChecks::run(null, $assessment(), array('project_name' => 'CMMC readiness'), $controls, null);
        Assert::same(array('unassessed', 'finding_incomplete'), $codes($issues, 'block'), 'blocking checks');
        Assert::true(strpos($issues[1]['message'], 'business impact and recommendation') !== false, 'names the missing fields: '.$issues[1]['message']);
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
        $issues = AssessmentChecks::run(null, $assessment(array(
            'posture_text' => 'Geneva Ground Services has has a recieved policy set, and MFA is enforced for every organisation user.'
        )), array('project_name' => 'Part-IS Implementation'), array(), null);
        Assert::same(array('project_frameworks_missing', 'repeated_word', 'spelling'), $codes($issues), 'warnings');
        Assert::same(array(), $codes($issues, 'block'), 'nothing blocks');
        Assert::true(strpos($issues[2]['message'], 'recieved') !== false, 'flags the misspelling');
    },

    'a project covering EASA Part-IS warns on a CMMC assessment whatever it is called' => function () use ($assessment, $codes) {
        $project = array(
            'project_name' => '2026 Readiness',
            'frameworks' => array(
                array(
                    'standard_id' => 139,
                    'framework_key' => 'easa-part-is-i',
                    'standard_name' => 'EASA Part-IS',
                    'version' => 'EU 2023/203'
                )
            )
        );
        $issues = AssessmentChecks::run(FrameworkLibrary::get('cmmc-l2'), $assessment(), $project, array(), null);
        Assert::same(array('framework_mismatch'), $codes($issues, 'warn'), 'warnings');
        Assert::true(strpos($issues[0]['message'], 'This project covers EASA Part-IS (EU 2023/203), but this assessment uses CMMC Level 2 (32 CFR Part 170)') === 0, $issues[0]['message']);
    },

    'no warning when the project covers the assessed framework, even if its name mentions another' => function () use ($assessment, $codes) {
        $project = array(
            'project_name' => 'Part-IS and CMMC programme',
            'frameworks' => array(
                array(
                    'standard_id' => 139,
                    'framework_key' => 'easa-part-is-i',
                    'standard_name' => 'EASA Part-IS',
                    'version' => 'EU 2023/203'
                ),
                array(
                    'standard_id' => 132,
                    'framework_key' => 'cmmc-l2',
                    'standard_name' => 'CMMC Level 2',
                    'version' => '32 CFR Part 170'
                )
            )
        );
        Assert::same(array(), $codes(AssessmentChecks::run(FrameworkLibrary::get('cmmc-l2'), $assessment(), $project, array(), null)), 'no issues');
    },

    'a project with no frameworks chosen falls back to its name and asks for them' => function () use ($assessment, $codes) {
        $issues = AssessmentChecks::run(FrameworkLibrary::get('cmmc-l2'), $assessment(), array('project_name' => 'Part-IS Implementation'), array(), null);
        Assert::same(array('framework_mismatch', 'project_frameworks_missing'), $codes($issues), 'warnings');
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
