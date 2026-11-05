<?php
/**
 * The AI narrative layer without the network: input hashing, the figure and
 * ref checks, markdown stripping, per-section validation and the shape check
 * applied to assessor edits.
 */
require_once ROOT.'/libs/Classes/AiAssistant.php';
require_once ROOT.'/libs/Classes/ReportAnalysis.php';
require_once ROOT.'/libs/Classes/ReportAI.php';
require_once ROOT.'/libs/Classes/ReportQuality.php';

$domain_input = function () {
    return array(
        'section' => 'domain:Control Environment',
        'framework' => 'SOC 2 Trust Services Criteria',
        'family' => 'Control Environment',
        'family_results' => array(
            'controls' => 5,
            'gap_count' => 2,
            'score' => 40
        ),
        'implemented_controls' => array(
            array(
                'ref' => 'CC1.2',
                'title' => 'Board Oversight'
            )
        ),
        'gap_controls' => array(
            array(
                'ref' => 'CC1.1',
                'title' => 'Integrity and Ethical Values',
                'result' => 'Partially Implemented',
                'objectives' => array(
                    array(
                        'ref' => 'CC1.1-PF01',
                        'short' => 'PF01',
                        'title' => 'Sets the tone at the top',
                        'result' => 'Implemented'
                    ),
                    array(
                        'ref' => 'CC1.1-PF02',
                        'short' => 'PF02',
                        'title' => 'Establishes standards of conduct',
                        'result' => 'Not Implemented'
                    )
                ),
                'evidence' => array()
            ),
            array(
                'ref' => 'CC1.4',
                'title' => 'Commitment to Competence',
                'result' => 'Not Implemented',
                'objectives' => array(),
                'evidence' => array(
                    'Training matrix 2026'
                )
            )
        )
    );
};

$domain_entry = function (string $ref, array $overrides = array()) {
    return array_merge(array(
        'ref' => $ref,
        'recorded_condition' => 'PF02 (standards of conduct) is recorded as not met.',
        'control_purpose' => 'The criterion is intended to establish expected behavior.',
        'risk_scenarios' => array(
            array(
                'scenario' => 'If conduct expectations are unwritten, staff could apply them inconsistently.',
                'consequence' => 'Misconduct could go unaddressed.'
            ),
            array(
                'scenario' => 'If breaches of conduct are not defined, discipline could be challenged.',
                'consequence' => 'The organization could be unable to act on misconduct.'
            )
        ),
        'suggested_approach' => array(
            'Consider publishing a code of conduct and obtaining staff acknowledgement.'
        ),
        'confirm' => array(
            'Whether conduct expectations exist informally.'
        ),
        'expected_outcome' => 'A code of conduct is published and acknowledged.',
        'validation_criteria' => array(
            'Sample acknowledgements for current staff.'
        ),
        'source_refs' => array(
            'CC1.1',
            'PF02'
        )
    ), $overrides);
};

return array(

    'hash ignores key order but not values, section or prompt inputs' => function () {
        $a = array(
            'b' => 1,
            'a' => array(
                'y' => 'two',
                'x' => 'one'
            )
        );
        $b = array(
            'a' => array(
                'x' => 'one',
                'y' => 'two'
            ),
            'b' => 1
        );
        Assert::same(ReportAI::hash($a, 'executive'), ReportAI::hash($b, 'executive'), 'key order does not change the hash');
        Assert::true(ReportAI::hash($a, 'executive') !== ReportAI::hash($a, 'patterns'), 'the section key is part of the hash');
        $b['b'] = 2;
        Assert::true(ReportAI::hash($a, 'executive') !== ReportAI::hash($b, 'executive'), 'a changed value changes the hash');
        Assert::same(64, strlen(ReportAI::hash($a, 'executive')), 'sha256 hex');
    },

    'list order is part of the hash' => function () {
        Assert::true(ReportAI::hash(array(
            'refs' => array(
                'A',
                'B'
            )
        ), 'x') !== ReportAI::hash(array(
            'refs' => array(
                'B',
                'A'
            )
        ), 'x'), 'lists are not re-sorted');
    },

    'figures must appear in the facts' => function () {
        $known = ReportAI::known_tokens(array(
            'score' => 52,
            'refs' => 'CC6.1-PF03',
            'framework' => 'SOC 2 Trust Services Criteria',
            'id' => 'R-01'
        ));
        Assert::same(null, ReportAI::unsupported_figure('The score is 52% under SOC 2.', $known), 'known score and framework number pass');
        Assert::same(null, ReportAI::unsupported_figure('CC6.1 and PF03 are open; see R-01.', $known), 'refs and their parts pass');
        Assert::same(null, ReportAI::unsupported_figure('No figures in this sentence.', $known), 'plain prose passes');
        Assert::same('72', ReportAI::unsupported_figure('Notify within 72 hours.', $known), 'an invented duration is caught');
        Assert::same('SHA-256', ReportAI::unsupported_figure('Use SHA-256 hashing.', $known), 'an invented version number is caught');
        Assert::same('CC9.9', ReportAI::unsupported_figure('CC9.9 is also affected.', $known), 'an invented ref is caught');
    },

    'markdown is stripped from prose' => function () {
        Assert::same('Bold text and code', ReportAI::strip_markdown('**Bold** text and `code`'), 'emphasis and code marks');
        Assert::same("Heading\nFirst\nSecond", ReportAI::strip_markdown("## Heading\n- First\n2. Second"), 'headings, bullets and numbering');
        Assert::same('See the policy for detail.', ReportAI::strip_markdown('See the [policy](https://x.test) for *detail*.'), 'links and single emphasis');
    },

    'ref index records objectives under their control' => function () use ($domain_input) {
        $index = ReportAI::ref_index($domain_input());
        Assert::same('CC1.1-PF02', $index['refs']['cc1.1-pf02'], 'objective ref indexed');
        Assert::same('CC1.1', $index['parent']['CC1.1-PF02'], 'objective parent recorded');
        Assert::same('CC1.1-PF01', $index['short']['cc1.1|pf01'], 'short id resolves within its control');
        Assert::same('CC1.2', $index['refs']['cc1.2'], 'implemented controls are refs too');
        Assert::true(!isset($index['parent']['CC1.2']), 'a control has no parent');
    },

    'domain validation drops unknown refs, repairs short ids and rejects invented figures' => function () use ($domain_input, $domain_entry) {
        $output = array(
            'narrative' => array(
                'The **category** has two gaps.',
                'A second paragraph.',
                'A third paragraph.',
                'A fourth paragraph.'
            ),
            'controls' => array(
                $domain_entry('CC1.1', array(
                    'recorded_condition' => 'PF02 is not met; retrain within 30 days.',
                    'source_refs' => array(
                        'CC1.1',
                        'PF02',
                        'XX1'
                    )
                )),
                $domain_entry('CC9.9'),
                $domain_entry('cc1.1')
            )
        );
        $result = ReportAI::validate('domain', $output, $domain_input());
        $content = $result['content'];

        Assert::same(1, count($content['controls']), 'unknown and repeated refs are dropped');
        Assert::same(null, $content['controls'][0]['recorded_condition'], 'a field with an invented figure is nulled');
        Assert::same(array(
            'CC1.1',
            'CC1.1-PF02'
        ), $content['controls'][0]['source_refs'], 'short id repaired, unknown ref dropped');
        Assert::same(2, count($content['narrative']), 'narrative bounded to two paragraphs');
        Assert::same('The category has two gaps.', $content['narrative'][0], 'markdown stripped');

        $log = implode("\n", $result['rejected']);
        Assert::true(strpos($log, 'unsupported figure "30"') !== false, 'the invented figure is recorded');
        Assert::true(strpos($log, 'no entry for CC1.4') !== false, 'a missing control is recorded');
        Assert::true(strpos($log, 'unknown ref "XX1"') !== false, 'the dropped ref is recorded');
        Assert::true(strpos($log, 'CC9.9') !== false, 'the unknown control is recorded');
    },

    'domain validation bounds scenarios and keeps figures from the facts' => function () use ($domain_input, $domain_entry) {
        $scenario = array(
            'scenario' => 'If training records are missing, competence could not be shown.',
            'consequence' => 'The 2026 training matrix could not be relied on.'
        );
        $output = array(
            'narrative' => array(
                'The category scores 40%.'
            ),
            'controls' => array(
                $domain_entry('CC1.4', array(
                    'risk_scenarios' => array(
                        $scenario,
                        $scenario,
                        $scenario,
                        $scenario
                    )
                ))
            )
        );
        $result = ReportAI::validate('domain', $output, $domain_input());

        Assert::same(3, count($result['content']['controls'][0]['risk_scenarios']), 'scenarios trimmed to three');
        Assert::same('The category scores 40%.', $result['content']['narrative'][0], 'a score from the facts is kept');
        Assert::same('The 2026 training matrix could not be relied on.', $result['content']['controls'][0]['risk_scenarios'][0]['consequence'], 'a year from an evidence title is kept');
    },

    'remediation validation enforces coverage, unique controls and known dependencies' => function () {
        $input = array(
            'gaps' => array(
                array(
                    'ref' => 'CC6.1',
                    'open_objectives' => array(
                        array(
                            'ref' => 'CC6.1-PF01',
                            'title' => 'Inventory'
                        )
                    )
                ),
                array(
                    'ref' => 'CC6.2',
                    'open_objectives' => array()
                ),
                array(
                    'ref' => 'CC7.1',
                    'open_objectives' => array()
                ),
                array(
                    'ref' => 'CC8.1',
                    'open_objectives' => array()
                )
            )
        );
        $initiative = function (string $title, string $phase, array $controls, array $points, array $depends) {
            return array(
                'title' => $title,
                'phase' => $phase,
                'problem' => 'Access is not governed.',
                'activities' => array(
                    'Define a joiner-mover-leaver process.'
                ),
                'deliverables' => array(
                    'Access procedure'
                ),
                'implementation_considerations' => 'Coordinate with HR.',
                'validation' => array(
                    'Sample leavers.'
                ),
                'controls' => $controls,
                'points' => $points,
                'depends_on' => $depends
            );
        };
        $output = array(
            'initiatives' => array(
                $initiative('Mature access reviews', 'Program maturity', array(
                    'CC6.2',
                    'CC6.1'
                ), array(
                    'CC6.1-PF01'
                ), array(
                    'Establish monitoring'
                )),
                $initiative('Establish monitoring', 'Immediate priorities', array(
                    'CC6.1',
                    'ZZ1',
                    'CC7.1'
                ), array(
                    'CC6.1-PF01'
                ), array(
                    'Unknown initiative'
                )),
                $initiative('Empty initiative', 'Program maturity', array(
                    'ZZ2'
                ), array(), array())
            )
        );
        $result = ReportAI::validate('remediation', $output, $input);
        $initiatives = $result['content']['initiatives'];
        $log = implode("\n", $result['rejected']);

        Assert::same(2, count($initiatives), 'an initiative without valid controls is dropped');
        Assert::same('Establish monitoring', $initiatives[0]['title'], 'sorted by phase');
        Assert::same(array(
            'CC7.1'
        ), $initiatives[0]['controls'], 'a control already covered and an unknown ref are removed');
        Assert::same(array(), $initiatives[0]['points'], 'a point outside the covered controls is removed');
        Assert::same(array(
            'CC6.1-PF01'
        ), $initiatives[1]['points'], 'a point of a covered control is kept');
        Assert::same(array(
            'Establish monitoring'
        ), $initiatives[1]['depends_on'], 'a known dependency is kept');
        Assert::same(array(), $initiatives[0]['depends_on'], 'an unknown dependency is dropped');
        Assert::same(array(
            'CC8.1'
        ), $result['content']['uncovered'], 'uncovered gaps are listed');
        Assert::true(strpos($log, 'already covered') !== false, 'the duplicate is recorded');
        Assert::true(strpos($log, 'not covered CC8.1') !== false, 'the coverage gap is recorded');
    },

    'patterns need at least two supporting controls' => function () {
        $input = array(
            'gaps' => array(
                array(
                    'ref' => 'CC6.1',
                    'open_objectives' => array(
                        array(
                            'ref' => 'CC6.1-PF01'
                        ),
                        array(
                            'ref' => 'CC6.1-PF02'
                        )
                    )
                ),
                array(
                    'ref' => 'CC7.2',
                    'open_objectives' => array()
                )
            )
        );
        $output = array(
            'patterns' => array(
                array(
                    'label' => 'One control only',
                    'kind' => 'technical',
                    'description' => 'Both objectives belong to one control.',
                    'refs' => array(
                        'CC6.1-PF01',
                        'CC6.1-PF02'
                    )
                ),
                array(
                    'label' => 'Reviews not performed',
                    'kind' => 'operational',
                    'description' => 'Reviews are defined but not performed.',
                    'refs' => array(
                        'CC6.1-PF01',
                        'CC7.2',
                        'XX9'
                    )
                )
            )
        );
        $result = ReportAI::validate('patterns', $output, $input);

        Assert::same(1, count($result['content']['patterns']), 'a single-control pattern is dropped');
        Assert::same(array(
            'CC6.1-PF01',
            'CC7.2'
        ), $result['content']['patterns'][0]['refs'], 'unknown refs are removed');
    },

    'executive refs resolve to controls and lists are bounded' => function () {
        $input = array(
            'results' => array(
                'implementation_score' => 52
            ),
            'gaps' => array(
                array(
                    'ref' => 'CC6.1',
                    'open_objectives' => array(
                        array(
                            'ref' => 'CC6.1-PF01'
                        )
                    )
                )
            )
        );
        $output = array(
            'overview' => array(
                'The implementation score is 52%.',
                'Remediation would take 6 months.'
            ),
            'key_findings' => array(
                array(
                    'title' => 'Access is not governed',
                    'explanation' => 'Leavers keep access.',
                    'refs' => array(
                        'CC6.1-PF01',
                        'CC6.1'
                    )
                )
            ),
            'strengths' => array(
                'One',
                'Two',
                'Three',
                'Four',
                'Five',
                'Six'
            ),
            'improvements' => array(),
            'strategic_recommendations' => array(),
            'management_considerations' => array()
        );
        $result = ReportAI::validate('executive', $output, $input);

        Assert::same(array(
            'The implementation score is 52%.'
        ), $result['content']['overview'], 'a paragraph with an invented figure is dropped');
        Assert::same(array(
            'CC6.1'
        ), $result['content']['key_findings'][0]['refs'], 'objective refs fold into their control');
        Assert::same(4, count($result['content']['strengths']), 'strengths bounded to four');
    },

    'assessor edits must keep the section shape' => function () use ($domain_input, $domain_entry) {
        $content = ReportAI::validate('domain', array(
            'narrative' => array(
                'Text.'
            ),
            'controls' => array(
                $domain_entry('CC1.1')
            )
        ), $domain_input())['content'];

        Assert::same(array(), ReportAI::shape_errors('domain', $content), 'validated content fits its schema');

        $content['controls'][0]['extra'] = 'x';
        $content['controls'][0]['suggested_approach'] = 'not a list';
        $errors = ReportAI::shape_errors('domain', $content);
        Assert::same(2, count($errors), 'an extra key and a wrong type are both reported');

        Assert::same(array(
            'review.issues[0].severity must be one of High, Medium, Low'
        ), ReportAI::shape_errors('review', array(
            'issues' => array(
                array(
                    'severity' => 'Severe',
                    'category' => 'other',
                    'section' => 'executive',
                    'description' => 'x',
                    'refs' => array()
                )
            )
        )), 'enums are checked');
    },

    'scenarios that are not written as possibilities are dropped' => function () use ($domain_input, $domain_entry) {
        $output = array(
            'narrative' => array(
                'Text.'
            ),
            'controls' => array(
                $domain_entry('CC1.1', array(
                    'risk_scenarios' => array(
                        array(
                            'scenario' => 'Staff apply conduct expectations inconsistently.',
                            'consequence' => 'Misconduct goes unaddressed.'
                        ),
                        array(
                            'scenario' => 'If conduct expectations are unwritten, staff could apply them inconsistently.',
                            'consequence' => 'Misconduct could go unaddressed.'
                        )
                    )
                ))
            )
        );
        $result = ReportAI::validate('domain', $output, $domain_input());

        Assert::same(1, count($result['content']['controls'][0]['risk_scenarios']), 'the unhedged scenario is dropped');
        Assert::true(strpos(implode("\n", $result['rejected']), 'not written as a possibility') !== false, 'the drop is recorded');
        Assert::same(array(
            'Whether conduct expectations exist informally.'
        ), $result['content']['controls'][0]['confirm'], 'items to confirm are kept');
    },

    'product and vendor names absent from the facts are rejected' => function () use ($domain_input, $domain_entry) {
        $input = $domain_input();
        $input['gap_controls'][1]['evidence'] = array(
            'Okta access policy export'
        );
        $output = array(
            'narrative' => array(
                'The organization stores customer records in Salesforce.',
                'Access is administered through Okta, as the linked evidence title indicates.'
            ),
            'controls' => array(
                $domain_entry('CC1.1', array(
                    'suggested_approach' => array(
                        'Consider configuring conditional access in Azure AD.',
                        'Consider a periodic review of access.'
                    )
                ))
            )
        );
        $result = ReportAI::validate('domain', $output, $input);

        Assert::same(array(
            'Access is administered through Okta, as the linked evidence title indicates.'
        ), $result['content']['narrative'], 'a product named in the facts is kept, one that is not is dropped');
        Assert::same(array(
            'Consider a periodic review of access.'
        ), $result['content']['controls'][0]['suggested_approach'], 'an approach naming a product is dropped');
        Assert::true(strpos(implode("\n", $result['rejected']), 'unsupported product or vendor name "salesforce"') !== false, 'the rejection is recorded');
    },

    'review issues carry a category and known refs' => function () {
        $input = array(
            'gaps' => array(
                array(
                    'ref' => 'CC6.1',
                    'objectives' => array(
                        array(
                            'ref' => 'CC6.1-PF01'
                        )
                    )
                )
            ),
            'sections' => array(
                'executive' => array()
            )
        );
        $result = ReportAI::validate('review', array(
            'issues' => array(
                array(
                    'severity' => 'High',
                    'category' => 'contradiction',
                    'section' => 'executive',
                    'description' => 'CC6.1-PF01 is described as absent.',
                    'refs' => array(
                        'CC6.1-PF01',
                        'ZZ9'
                    )
                ),
                array(
                    'severity' => 'Low',
                    'category' => 'nonsense',
                    'section' => 'domain:Nowhere',
                    'description' => 'Vague.',
                    'refs' => array()
                )
            )
        ), $input);
        $issues = $result['content']['issues'];

        Assert::same('contradiction', $issues[0]['category'], 'a known category is kept');
        Assert::same(array(
            'CC6.1-PF01'
        ), $issues[0]['refs'], 'unknown refs are dropped');
        Assert::same('other', $issues[1]['category'], 'an unknown category becomes other');
        Assert::same('general', $issues[1]['section'], 'an unknown section becomes general');
    },

    'the executive summary carries a separate exposure overview' => function () {
        $result = ReportAI::validate('executive', array(
            'overview' => array(
                'The result is recorded.'
            ),
            'exposure_overview' => array(
                'If access gaps persist, data could be exposed.',
                'Where records are incomplete, oversight may be harder.',
                'A third paragraph.'
            ),
            'key_findings' => array(),
            'strengths' => array(),
            'improvements' => array(),
            'strategic_recommendations' => array(),
            'management_considerations' => array()
        ), array());

        Assert::same(2, count($result['content']['exposure_overview']), 'exposure overview bounded to two paragraphs');
        Assert::same(array(), ReportAI::shape_errors('executive', $result['content']), 'the validated summary fits its schema');
    },

    'every prompt version was bumped for the precision rework' => function () {
        foreach (ReportAI::KINDS as $kind) {
            Assert::true(strcmp(ReportAI::PROMPT_VERSIONS[$kind], '2026-11-26') > 0, $kind.' prompt version is current');
        }
        Assert::true(strpos(ReportAI::SYSTEM_PROMPT, 'the organization uses Microsoft 365') !== false, 'the prohibited patterns are spelled out');
    },

    'every section schema is strict' => function () {
        $check = function (array $schema, string $path) use (&$check) {
            if ($schema['type'] === 'object') {
                Assert::same(false, $schema['additionalProperties'], $path.' forbids extra keys');
                Assert::same(array_keys($schema['properties']), $schema['required'], $path.' requires every key');
                foreach ($schema['properties'] as $key => $child) {
                    $check($child, $path.'.'.$key);
                }
            }
            if ($schema['type'] === 'array') {
                $check($schema['items'], $path.'[]');
            }
        };
        foreach (ReportAI::KINDS as $kind) {
            $check(ReportAI::schema($kind), $kind);
        }
    }
);
