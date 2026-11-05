<?php
/**
 * ReportQuality's deterministic narrative checks on a small fixed analysis:
 * unsupported assertions, contradictions with recorded results, cross-report
 * consistency, repetition and overstatement, plus the revision notes the
 * Narratives job builds from them. No network, no database.
 */
require_once ROOT.'/libs/Classes/AiAssistant.php';
require_once ROOT.'/libs/Classes/ReportAnalysis.php';
require_once ROOT.'/libs/Classes/ReportAI.php';
require_once ROOT.'/libs/Classes/ReportQuality.php';
require_once ROOT.'/libs/Classes/ReportJobs.php';

$objective = function (string $ref, string $short, string $result, int $evidence = 0) {
    return array(
        'ref' => $ref,
        'short' => $short,
        'title' => $short.' title',
        'description' => '',
        'result' => $result,
        'evidence' => array_fill(0, $evidence, 'Evidence file'),
        'assessor' => array()
    );
};

$analysis = function () use ($objective) {
    $controls = array(
        array(
            'ref' => 'CC6.1',
            'family' => 'Access',
            'result' => 'Partially Implemented',
            'evidence' => array(),
            'evidence_count' => 0,
            'assessor' => array(),
            'is_gap' => true,
            'priority' => 'High',
            'objectives' => array(
                $objective('CC6.1-PF01', 'PF01', 'Not Implemented'),
                $objective('CC6.1-PF02', 'PF02', 'Partially Implemented'),
                $objective('CC6.1-PF03', 'PF03', 'Implemented')
            )
        ),
        array(
            'ref' => 'CC6.2',
            'family' => 'Access',
            'result' => 'Partially Implemented',
            'evidence' => array(),
            'evidence_count' => 0,
            'assessor' => array(),
            'is_gap' => true,
            'priority' => 'Medium',
            'objectives' => array(
                $objective('CC6.2-PF01', 'PF01', 'Partially Implemented'),
                $objective('CC6.2-PF02', 'PF02', 'Partially Implemented', 2)
            )
        ),
        array(
            'ref' => 'CC6.3',
            'family' => 'Access',
            'result' => 'Implemented',
            'evidence' => array(),
            'evidence_count' => 0,
            'assessor' => array(),
            'is_gap' => false,
            'priority' => null,
            'objectives' => array()
        ),
        array(
            'ref' => 'CC7.1',
            'family' => 'Operations',
            'result' => 'Not Implemented',
            'evidence' => array(),
            'evidence_count' => 0,
            'assessor' => array(
                'notes' => 'Patching is run from the ServiceNow change queue.'
            ),
            'is_gap' => true,
            'priority' => 'High',
            'objectives' => array()
        )
    );

    return array(
        'controls' => $controls,
        'assessor' => array(),
        'counts' => array(
            'Implemented' => 1,
            'Partially Implemented' => 2,
            'Not Implemented' => 1,
            'Not Applicable' => 0,
            'Not Assessed' => 0
        ),
        'score' => 50,
        'with_evidence' => 0,
        'families' => array(
            'Access' => array(
                'counts' => array(
                    'Implemented' => 1,
                    'Partially Implemented' => 2
                ),
                'gaps' => array(
                    $controls[0],
                    $controls[1]
                ),
                'score' => 67
            ),
            'Operations' => array(
                'counts' => array(
                    'Not Implemented' => 1
                ),
                'gaps' => array(
                    $controls[3]
                ),
                'score' => 0
            )
        )
    );
};

$domain = function (array $narrative, array $entries = array()) {
    return array(
        'domain:Access' => array(
            'narrative' => $narrative,
            'controls' => $entries
        )
    );
};

$categories = function (array $issues, string $category) {
    return array_values(array_filter($issues, function ($issue) use ($category) {
        return $issue['category'] === $category;
    }));
};

return array(

    'a partially met objective described as not met is a contradiction' => function () use ($analysis, $domain, $categories) {
        $issues = $categories(ReportQuality::check($analysis(), $domain(array(
            'CC6.1 PF02 is not met, and PF01 is recorded as not implemented.'
        ))), 'contradiction');

        Assert::same(1, count($issues), 'only the misstated objective is flagged');
        Assert::same('High', $issues[0]['severity'], 'misstating a recorded result is High');
        Assert::same(array(
            'CC6.1-PF02'
        ), $issues[0]['refs'], 'the short id resolves under the control named before it');
        Assert::same('rules', $issues[0]['source'], 'rules issues carry their source');
    },

    'claims are read from runs of refs, parentheses and which-clauses' => function () use ($analysis, $domain, $categories) {
        $issues = $categories(ReportQuality::check($analysis(), $domain(array(
            'CC6.2-PF01 and CC6.2-PF02 are recorded as not met.',
            'Access requests are missing (CC6.2-PF01).',
            'CC6.1-PF03, which covers role design, is not in place.',
            'CC6.1-PF01 (asset inventory) is recorded as not implemented.'
        ))), 'contradiction');

        Assert::same(3, count($issues), 'three misstatements, the correct sentence passes');
        Assert::same(array(
            'CC6.2-PF01',
            'CC6.2-PF02'
        ), $issues[0]['refs'], 'a run of refs shares the status after it');
    },

    'a ref governed by a preposition is not the subject of the claim' => function () use ($analysis, $domain, $categories) {
        $issues = ReportQuality::check($analysis(), $domain(array(
            'Backup elements under CC6.2 are recorded as not implemented.'
        )));

        Assert::same(0, count($categories($issues, 'contradiction')), 'no contradiction is raised for "under CC6.2"');
    },

    'partial described as met, and not implemented described as partial' => function () use ($analysis, $domain, $categories) {
        $issues = $categories(ReportQuality::check($analysis(), $domain(array(
            'CC6.2 is implemented.',
            'CC6.1-PF01 is partially implemented.'
        ))), 'contradiction');

        Assert::same(2, count($issues), 'both are flagged');
        Assert::same('High', $issues[0]['severity'], 'overstating a result is High');
        Assert::same('Medium', $issues[1]['severity'], 'understating an absent element is Medium');
    },

    'unsupported client-specific assertions are flagged and hedged text is not' => function () use ($analysis, $domain, $categories) {
        $issues = $categories(ReportQuality::check($analysis(), $domain(array(
            'The organization uses Okta for single sign-on.',
            'Administrators currently share accounts on the firewall.',
            'Personal data flows from the booking platform to the maintenance tracking system.',
            'The assessor verified the configuration of the organization\'s identity provider.',
            'The weakness stems from a lack of ownership.',
            'If access reviews are not performed, staff could keep access they no longer need.'
        ))), 'unsupported_assertion');
        $text = implode("\n", array_column($issues, 'description'));

        Assert::true(strpos($text, '"okta"') !== false, 'a product name is flagged');
        Assert::same('High', $issues[0]['severity'], 'a named product in recorded text is High');
        Assert::true(strpos($text, 'what staff currently do') !== false, 'staff behaviour is flagged');
        Assert::true(strpos($text, 'a data flow') !== false, 'a data flow is flagged');
        Assert::true(strpos($text, 'identity provider') !== false, 'a presumed system is flagged');
        Assert::true(strpos($text, 'verification that is not recorded') !== false, 'unrecorded verification is flagged');
        Assert::true(strpos($text, 'a cause') !== false, 'a causal claim is flagged');
        Assert::true(strpos($text, 'no longer need') === false, 'the hedged scenario sentence is not flagged');
    },

    'a product named in an assessor note is supported' => function () use ($analysis, $domain, $categories) {
        $issues = ReportQuality::check($analysis(), $domain(array(
            'The assessor noted that patching runs from the ServiceNow change queue.'
        )));

        Assert::same(0, count($categories($issues, 'unsupported_assertion')), 'the recorded product name passes');
    },

    'scenarios must be written as possibilities; items to confirm are exempt' => function () use ($analysis, $domain, $categories) {
        $issues = $categories(ReportQuality::check($analysis(), $domain(array(), array(
            array(
                'ref' => 'CC6.1',
                'recorded_condition' => 'PF01 is recorded as not implemented.',
                'risk_scenarios' => array(
                    array(
                        'scenario' => 'Unmanaged assets sit outside access reviews.',
                        'consequence' => 'Data could be exposed.'
                    )
                ),
                'suggested_approach' => array(),
                'confirm' => array(
                    'Which databases hold personal data and whether the HR system feeds the identity provider.'
                )
            ),
            array(
                'ref' => 'CC6.2',
                'recorded_condition' => 'PF01 and PF02 are recorded as partially implemented.'
            )
        ))), 'unsupported_assertion');

        Assert::same(1, count($issues), 'only the unhedged scenario is flagged');
        Assert::same('controls.CC6.1.risk_scenarios[0].scenario', $issues[0]['field'], 'the field is identified');
    },

    'absolute language over partial, unevidenced results is an overstatement' => function () use ($analysis, $domain, $categories) {
        $issues = $categories(ReportQuality::check($analysis(), $domain(array(
            'Account requests are never approved for CC6.1-PF02.',
            'Account reviews are never performed for CC6.2-PF02.',
            'Asset ownership is never assigned for CC7.1.',
            'There is no linked evidence for CC6.1.',
            'If approvals are never recorded for CC6.1-PF02, access could not be traced.'
        ))), 'overstatement');

        Assert::same(1, count($issues), 'only the claim over a partial, unevidenced objective is flagged');
        Assert::same(array(
            'CC6.1-PF02'
        ), $issues[0]['refs'], 'the related ref is reported; evidenced, absent, evidence and hedged sentences pass');
    },

    'counts, scores and initiative ids must match the records' => function () use ($analysis, $categories) {
        $issues = $categories(ReportQuality::check($analysis(), array(
            'remediation' => array(
                'initiatives' => array(
                    array(
                        'title' => 'Govern access',
                        'phase' => 'Program maturity',
                        'controls' => array(
                            'CC6.1',
                            'CC6.2'
                        )
                    ),
                    array(
                        'title' => 'Duplicate',
                        'phase' => 'Immediate priorities',
                        'controls' => array(
                            'CC6.2'
                        )
                    )
                )
            ),
            'executive' => array(
                'overview' => array(
                    'Of the 4 criteria, 3 are recorded as partially implemented and the score of 55 reflects that.',
                    'One criterion is recorded as Not Implemented; see R-07.'
                ),
                'key_findings' => array(
                    array(
                        'title' => 'Roles',
                        'explanation' => 'Roles are recorded as in place.',
                        'refs' => array(
                            'CC6.3'
                        )
                    )
                ),
                'strategic_recommendations' => array()
            ),
            'patterns' => array(
                'patterns' => array(
                    array(
                        'label' => 'Reviews',
                        'description' => 'Reviews are partial in CC6.1, CC6.2 and CC7.1.',
                        'refs' => array(
                            'CC6.1',
                            'CC6.2'
                        )
                    )
                )
            )
        )), 'cross_report_inconsistency');
        $text = implode("\n", array_column($issues, 'description'));

        Assert::true(strpos($text, 'CC6.2 is covered by more than one') !== false, 'double coverage');
        Assert::true(strpos($text, 'No remediation initiative covers CC7.1') !== false, 'an uncovered gap');
        Assert::true(strpos($text, 'placed in Program maturity but covers gaps with High derived priority (CC6.1)') !== false, 'phase against priority');
        Assert::true(strpos($text, 'cites CC6.3, which is recorded as Implemented') !== false, 'a key finding on a non-gap');
        Assert::true(strpos($text, 'States 3 partially implemented') !== false, 'a wrong count');
        Assert::true(strpos($text, 'States 1 not implemented') === false, 'a correct count in words passes');
        Assert::true(strpos($text, 'score of 55') !== false, 'a wrong score');
        Assert::true(strpos($text, 'R-07') !== false, 'an unknown initiative id');
        Assert::true(strpos($text, 'names CC7.1 in its description but not in its supporting refs') !== false, 'a pattern ref outside its refs');
    },

    'repeated sentences across requirements are reported, short ones ignored' => function () use ($analysis, $domain, $categories) {
        $approach = 'Consider defining a documented access request and approval workflow that records the approver, the role granted and the date of approval for every account.';
        $issues = $categories(ReportQuality::check($analysis(), $domain(array(), array(
            array(
                'ref' => 'CC6.1',
                'recorded_condition' => 'No evidence is linked.',
                'suggested_approach' => array(
                    $approach
                )
            ),
            array(
                'ref' => 'CC6.2',
                'recorded_condition' => 'No evidence is linked.',
                'suggested_approach' => array(
                    $approach
                )
            )
        ))), 'repetition');

        Assert::same(1, count($issues), 'one repeated pair; the short repeated sentence is ignored');
        Assert::same('Medium', $issues[0]['severity'], 'a near-identical suggested approach is Medium');
        Assert::same(array(
            'CC6.1',
            'CC6.2'
        ), $issues[0]['refs'], 'both requirements are referenced');
    },

    'ratings, schedules, costs and staffing are unsupported unless recorded' => function () use ($analysis, $domain, $categories) {
        $issues = $categories(ReportQuality::check($analysis(), $domain(array(), array(
            array(
                'ref' => 'CC6.1',
                'recorded_condition' => 'PF01 is recorded as not implemented.',
                'risk_scenarios' => array(
                    array(
                        'scenario' => 'If accounts are not reviewed, this is a high-risk weakness with a high likelihood of misuse.',
                        'consequence' => 'Access could persist.'
                    )
                ),
                'suggested_approach' => array(
                    'Consider completing access reviews within thirty days, a quick win.',
                    'One option is to hire two additional analysts to run the reviews.',
                    'Consider a quarterly access review signed off by each system owner.'
                )
            )
        ))), 'unsupported_assertion');
        $text = implode("\n", array_column($issues, 'description'));

        Assert::true(strpos($text, 'a likelihood, impact or risk rating') !== false, 'a rating is flagged');
        Assert::true(strpos($text, 'a schedule or timeframe ("within thirty days")') !== false, 'a schedule in words is flagged');
        Assert::true(strpos($text, 'a cost or effort estimate ("quick win")') !== false, 'an effort estimate is flagged');
        Assert::true(strpos($text, 'a staffing level') !== false, 'staffing is flagged');
        Assert::true(strpos($text, 'quarterly access review') === false, 'a review frequency without a schedule passes');
    },

    'an assessor risk rating supports rating language' => function () use ($analysis, $domain, $categories) {
        $fixture = $analysis();
        $fixture['controls'][0]['risk_rating'] = 'High';
        $issues = $categories(ReportQuality::check($fixture, $domain(array(
            'CC6.1 is rated by the assessor as a high-risk gap.'
        ))), 'unsupported_assertion');

        Assert::same(0, count($issues), 'the recorded rating passes');
    },

    'generic To Be Confirmed questions repeated across findings are reported once' => function () use ($analysis, $categories) {
        $fixture = $analysis();
        $fixture['controls'][1]['objectives'][] = array(
            'ref' => 'CC6.2-PF03',
            'short' => 'PF03',
            'title' => 'PF03 title',
            'description' => '',
            'result' => 'Partially Implemented',
            'evidence' => array(),
            'assessor' => array()
        );
        $entry = function (string $ref, array $confirm) {
            return array(
                'ref' => $ref,
                'recorded_condition' => 'Recorded as partially implemented.',
                'confirm' => $confirm
            );
        };
        $sections = array(
            'domain:Access' => array(
                'narrative' => array(),
                'controls' => array(
                    $entry('CC6.1', array(
                        'What the partially implemented result for PF02 covers today.'
                    )),
                    $entry('CC6.2', array(
                        'Which elements of PF01 are recorded as in place and which are incomplete.'
                    )),
                    $entry('CC6.3', array(
                        'Which part of the partial PF03 result is missing.'
                    ))
                )
            )
        );
        $issues = $categories(ReportQuality::check($fixture, $sections), 'repetition');

        Assert::same(1, count($issues), 'one issue for the section');
        Assert::same('domain:Access', $issues[0]['section'], 'raised on the section');
        Assert::same(array(
            'CC6.1',
            'CC6.2',
            'CC6.3'
        ), $issues[0]['refs'], 'every finding is referenced');

        $sections['domain:Access']['controls'][2]['confirm'] = array(
            'Whether the role model also governs service accounts used by integrations.'
        );
        Assert::same(0, count($categories(ReportQuality::check($fixture, $sections), 'repetition')), 'two generic questions and a specific one pass');
    },

    'a suggested approach that restates the condition is reported' => function () use ($analysis, $domain, $categories) {
        $issues = $categories(ReportQuality::check($analysis(), $domain(array(), array(
            array(
                'ref' => 'CC6.1',
                'recorded_condition' => 'Periodic user access reviews (PF01) are recorded as not implemented.',
                'suggested_approach' => array(
                    'Consider implementing periodic user access reviews (PF01).',
                    'Consider addressing the remaining gaps.',
                    'One option is a quarterly certification in which each system owner confirms every account and role, with removals tracked to closure.'
                )
            )
        ))), 'repetition');
        $fields = array_column($issues, 'field');

        Assert::same(2, count($issues), 'two restatements, the specific approach passes');
        Assert::true(in_array('controls.CC6.1.suggested_approach[0]', $fields, true), 'the rephrased condition');
        Assert::true(in_array('controls.CC6.1.suggested_approach[1]', $fields, true), 'the generic instruction');
    },

    'narratives that enumerate objective refs are reported' => function () use ($analysis, $categories) {
        $fixture = $analysis();
        for ($n = 4; $n <= 9; $n++) {
            $fixture['controls'][0]['objectives'][] = array(
                'ref' => 'CC6.1-PF0'.$n,
                'short' => 'PF0'.$n,
                'title' => 'PF0'.$n.' title',
                'description' => '',
                'result' => 'Partially Implemented',
                'evidence' => array(),
                'assessor' => array()
            );
        }
        $issues = $categories(ReportQuality::check($fixture, array(
            'domain:Access' => array(
                'narrative' => array(
                    'CC6.1-PF01, CC6.1-PF02, CC6.1-PF04, CC6.1-PF05, CC6.1-PF06 and CC6.1-PF07 are recorded as open.',
                    'The open elements concentrate on review and approval steps.'
                ),
                'controls' => array()
            )
        )), 'repetition');

        Assert::same(1, count($issues), 'the enumerating paragraph only');
        Assert::same('narrative[0]', $issues[0]['field'], 'the field is identified');
    },

    'identifiers that are not in the record are reported' => function () use ($analysis, $domain, $categories) {
        $issues = $categories(ReportQuality::check($analysis(), $domain(array(
            'CC6.9 is recorded as partially implemented.',
            'Under CC6.2, PF07 is recorded as not met.',
            'CC6.1-PF02 and CC6.2-PF01 are recorded as partially implemented; PF02 is open.'
        ))), 'cross_report_inconsistency');
        $issues = array_values(array_filter($issues, function ($issue) {
            return strpos($issue['description'], 'No recorded condition') === false;
        }));
        $text = implode("\n", array_column($issues, 'description'));

        Assert::same(2, count($issues), 'the unknown ref and the misplaced objective; the valid sentence passes');
        Assert::true(strpos($text, 'Cites CC6.9') !== false, 'an unknown requirement ref');
        Assert::true(strpos($text, 'PF07 is cited under CC6.2') !== false, 'an objective id under the wrong requirement');
    },

    'objective counts and framework phrases are not misread' => function () use ($analysis, $domain, $categories) {
        $fixture = $analysis();
        $fixture['controls'][0]['objectives'][0]['title'] = 'Root cause analysis and recurrence prevention';
        $issues = ReportQuality::check($fixture, $domain(array(
            'CC6.1 has three points of focus, with one recorded as not implemented and two as partially implemented.',
            'CC6.1-PF01 (root cause analysis and recurrence prevention) is recorded as not implemented.'
        )));

        $counts = array_filter($categories($issues, 'cross_report_inconsistency'), function ($issue) {
            return strpos($issue['description'], 'does not match the recorded counts') !== false;
        });

        Assert::same(0, count($counts), 'objective counts are not compared with requirement counts');
        Assert::same(0, count($categories($issues, 'unsupported_assertion')), 'an objective title in parentheses is not a causal claim');
    },

    'summary counts by severity and category in a stable shape' => function () use ($analysis, $domain) {
        $issues = ReportQuality::check($analysis(), $domain(array(
            'CC6.2 is implemented.',
            'The organization uses Okta.'
        )));
        $summary = ReportQuality::summary($issues);

        Assert::same(array(
            'High',
            'Medium',
            'Low'
        ), array_keys($summary['counts']), 'every severity is present');
        Assert::same(count($issues), array_sum($summary['counts']), 'counts add up');
        foreach ($summary['issues'] as $issue) {
            Assert::same(array(
                'severity',
                'category',
                'section',
                'field',
                'description',
                'refs',
                'source'
            ), array_keys($issue), 'each issue has the stable shape');
        }
        Assert::same(count($issues), count(ReportQuality::for_section($issues, 'domain:Access')), 'issues filter by section');
    },

    'revision notes merge review and rules issues, most severe first' => function () {
        $issues = ReportJobs::revision_issues(array(
            array(
                'severity' => 'Medium',
                'category' => 'repetition',
                'section' => 'executive',
                'description' => 'Review note.'
            )
        ), array(
            array(
                'severity' => 'High',
                'category' => 'contradiction',
                'section' => 'executive',
                'description' => 'Rules note.',
                'source' => 'rules'
            ),
            array(
                'severity' => 'Low',
                'category' => 'repetition',
                'section' => 'executive',
                'description' => 'Ignored.',
                'source' => 'rules'
            )
        ));
        $targets = ReportJobs::revision_targets($issues, array(
            'executive',
            'review'
        ));

        Assert::same(array(
            array(
                'key' => 'executive',
                'notes' => array(
                    '[High · automated check · contradiction] Rules note.',
                    '[Medium · repetition] Review note.'
                )
            )
        ), $targets, 'High rules issue first, Low dropped, tags name the source and category');
    },

    'review issues are mapped to the rules shape' => function () {
        $issues = ReportQuality::review_issues(array(
            'issues' => array(
                array(
                    'severity' => 'High',
                    'category' => 'overstatement',
                    'section' => 'executive',
                    'description' => 'Too strong.',
                    'refs' => array(
                        'CC6.1'
                    )
                ),
                array(
                    'severity' => 'Medium',
                    'section' => 'patterns',
                    'description' => 'Stored before categories existed.'
                )
            )
        ));

        Assert::same('review', $issues[0]['source'], 'source review');
        Assert::same('other', $issues[1]['category'], 'an issue without category becomes other');
        Assert::same(array(), $issues[1]['refs'], 'missing refs become an empty list');
    }
);
