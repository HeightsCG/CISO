<?php
/**
 * Report knowledge base: every topic is complete and distinct, and the
 * classifier puts real controls from the shipped frameworks in sensible topics.
 */
require_once ROOT.'/libs/Classes/ReportKnowledge.php';

$framework = function (string $key) {
    return json_decode(file_get_contents(ROOT.'/frameworks/'.$key.'.json'), true);
};

/* A framework control shaped the way it is stored in assessment_items, with its objectives. */
$stored = function (array $control, string $family) {
    $text = trim($control['text'] ?? '');
    $title = trim($control['title'] ?? '');
    $objectives = array();

    foreach ($control['objectives'] ?? array() as $objective) {
        $objectives[] = array(
            'control_title' => $objective['text'],
            'description' => trim($objective['description'] ?? '') !== '' ? trim($objective['description']) : $objective['text']
        );
    }

    return array(
        'control' => array(
            'control_title' => mb_substr($title !== '' ? $title : $text, 0, 400),
            'description' => $text !== '' ? $text : trim($control['description'] ?? ''),
            'guidance' => trim($control['guidance'] ?? ''),
            'family' => $family
        ),
        'objectives' => $objectives
    );
};

$classify = function (string $key, string $id) use ($framework, $stored) {
    foreach ($framework($key)['families'] as $family) {
        foreach ($family['controls'] as $control) {
            if ($control['id'] === $id) {
                $row = $stored($control, $family['name']);

                return ReportKnowledge::topic_for($row['control'], $row['objectives']);
            }
        }
    }

    throw new RuntimeException($key.' has no control '.$id);
};

$sentences = function (array $topic) {
    return array_merge(
        array(
            $topic['purpose'],
            $topic['outcome']
        ),
        $topic['consequences'],
        $topic['activities'],
        $topic['deliverables'],
        $topic['validation']
    );
};

return array(
    'Knowledge: between 20 and 26 topics, each keyed by its own key' => function () {
        $topics = ReportKnowledge::topics();
        Assert::true(count($topics) >= 20 && count($topics) <= 26, 'topic count '.count($topics));

        foreach ($topics as $key => $topic) {
            Assert::same($key, $topic['key'], 'key of '.$key);
            Assert::same($topic, ReportKnowledge::topic($key), 'topic('.$key.')');
        }
    },

    'Knowledge: every topic has all fields with the required counts' => function () {
        $counts = array(
            'consequences' => array(
                2,
                4
            ),
            'activities' => array(
                3,
                6
            ),
            'deliverables' => array(
                2,
                5
            ),
            'validation' => array(
                2,
                4
            )
        );

        foreach (ReportKnowledge::topics() as $key => $topic) {
            $text_fields = array(
                'name',
                'purpose',
                'outcome'
            );

            foreach ($text_fields as $field) {
                Assert::true(is_string($topic[$field] ?? null) && trim($topic[$field]) !== '', $key.' '.$field.' is empty');
            }

            foreach ($counts as $field => $range) {
                $items = $topic[$field] ?? null;
                Assert::true(is_array($items), $key.' '.$field.' is not a list');
                Assert::true(count($items) >= $range[0] && count($items) <= $range[1], $key.' has '.count($items).' '.$field);

                foreach ($items as $item) {
                    Assert::true(is_string($item) && trim($item) !== '', $key.' has an empty '.$field.' item');
                }
            }

            Assert::true(is_int($topic['criticality']) && $topic['criticality'] >= 1 && $topic['criticality'] <= 3, $key.' criticality');
            Assert::true(is_string($topic['initiative']['title'] ?? null) && $topic['initiative']['title'] !== '', $key.' initiative title');
            Assert::true(in_array($topic['initiative']['phase'] ?? null, ReportKnowledge::PHASES, true), $key.' initiative phase');
            Assert::true(is_array($topic['depends_on']), $key.' depends_on');
        }
    },

    'Knowledge: every phase is used by at least one topic' => function () {
        $phases = array();

        foreach (ReportKnowledge::topics() as $topic) {
            $phases[$topic['initiative']['phase']] = true;
        }

        foreach (ReportKnowledge::PHASES as $phase) {
            Assert::true(isset($phases[$phase]), 'no topic in '.$phase);
        }
    },

    'Knowledge: dependencies name real topics and contain no cycles' => function () {
        $topics = ReportKnowledge::topics();

        foreach ($topics as $key => $topic) {
            foreach ($topic['depends_on'] as $dependency) {
                Assert::true(isset($topics[$dependency]), $key.' depends on unknown topic '.$dependency);
                Assert::true($dependency !== $key, $key.' depends on itself');
            }
        }

        $state = array();
        $visit = function (string $key) use (&$visit, &$state, $topics) {
            if (($state[$key] ?? '') === 'done') {
                return;
            }

            Assert::true(($state[$key] ?? '') !== 'open', 'dependency cycle through '.$key);
            $state[$key] = 'open';

            foreach ($topics[$key]['depends_on'] as $dependency) {
                $visit($dependency);
            }

            $state[$key] = 'done';
        };

        foreach (array_keys($topics) as $key) {
            $visit($key);
        }
    },

    'Knowledge: no sentence, deliverable or initiative is repeated across topics' => function () use ($sentences) {
        $seen = array();
        $titles = array();

        foreach (ReportKnowledge::topics() as $key => $topic) {
            foreach ($sentences($topic) as $sentence) {
                $normal = strtolower(trim($sentence));
                Assert::true(!isset($seen[$normal]), '"'.$sentence.'" appears in '.$key.' and '.($seen[$normal] ?? ''));
                $seen[$normal] = $key;
            }

            $title = strtolower($topic['initiative']['title']);
            Assert::true(!isset($titles[$title]), 'initiative "'.$title.'" is repeated');
            $titles[$title] = $key;
        }
    },

    'Knowledge: wording is conditional, sentence case and framework-neutral' => function () use ($sentences) {
        $frameworks = '/\b(soc ?2|iso|27001|nist|cmmc|pci|hipaa|nydfs|ftc|fedramp|easa|tsa|cui|cardholder)\b/i';

        foreach (ReportKnowledge::topics() as $key => $topic) {
            foreach ($topic['consequences'] as $consequence) {
                Assert::true(preg_match('/\b(could|may|might)\b/', $consequence) === 1, $key.' consequence is not conditional: '.$consequence);
            }

            $texts = array_merge(
                array(
                    $topic['name'],
                    $topic['initiative']['title']
                ),
                $sentences($topic)
            );

            foreach ($texts as $text) {
                Assert::true(preg_match('/^[A-Z]/', $text) === 1, $key.' text does not start with a capital: '.$text);
                Assert::true(preg_match($frameworks, $text) === 0, $key.' names a framework: '.$text);
            }

            Assert::true(substr($topic['purpose'], -1) === '.', $key.' purpose is not a sentence');
            Assert::true(substr($topic['outcome'], -1) === '.', $key.' outcome is not a sentence');
        }
    },

    'Knowledge: unknown topic and empty control fall back to governance' => function () {
        Assert::same('governance', ReportKnowledge::topic('no-such-topic')['key'], 'unknown topic');
        Assert::same('governance', ReportKnowledge::topic_for(array()), 'empty control');
        $general = array(
            'control_title' => 'General'
        );
        Assert::same('governance', ReportKnowledge::topic_for($general), 'no keywords');
    },

    'Knowledge: hand-picked controls from different frameworks land in the right topic' => function () use ($classify) {
        $expected = array(
            'soc2 CC1.1' => 'ethics',
            'soc2 CC1.2' => 'governance',
            'soc2 CC2.3' => 'communication',
            'soc2 CC6.1' => 'access',
            'soc2 CC6.4' => 'physical',
            'soc2 CC9.2' => 'third_party',
            'soc2 A1.3' => 'continuity',
            'soc2 P4.2' => 'privacy',
            'nist-800-53-r5 IR-4' => 'incident',
            'nist-800-53-r5 CP-9' => 'continuity',
            'nist-800-53-r5 AC-6(5)' => 'privileged',
            'nist-800-53-r5 RA-5' => 'vulnerability',
            'nist-800-53-r5 AU-6' => 'logging',
            'nist-800-53-r5 CM-6' => 'configuration',
            'nist-800-53-r5 SI-3' => 'endpoint',
            'nist-800-53-r5 PS-3' => 'personnel',
            'nist-800-53-r5 CA-2' => 'control_monitoring',
            'pci-dss-4 3.5.1' => 'data_protection',
            'pci-dss-4 4.2.1' => 'data_protection',
            'pci-dss-4 7.2.4' => 'access',
            'pci-dss-4 8.4.2' => 'identity',
            'cmmc-l2 IA.L2-3.5.3' => 'identity',
            'cmmc-l2 AT.L2-3.2.1' => 'awareness',
            'cmmc-l2 SC.L2-3.13.6' => 'network',
            'iso-27001-2022 5.2' => 'policy',
            'iso-27001-2022 A.5.9' => 'assets',
            'iso-27001-2022 A.8.8' => 'vulnerability',
            'iso-27001-2022 A.8.28' => 'sdlc',
            'iso-27001-2022 A.8.32' => 'change',
            'iso-27001-2022 A.5.19' => 'third_party',
            'nist-csf-2 GV.RR-02' => 'roles',
            'nist-csf-2 ID.RA-05' => 'risk',
            'nist-csf-2 PR.DS-11' => 'continuity',
            'hipaa-security 164.308(a)(7)(ii)(A)' => 'continuity',
            'tsa-aviation-cyber TSA-SEG-01' => 'network'
        );

        foreach ($expected as $control => $topic) {
            $parts = explode(' ', $control);
            Assert::same($topic, $classify($parts[0], $parts[1]), $control);
        }
    },

    'Knowledge: every shipped framework classifies cleanly with no topic above 35%' => function () use ($stored) {
        $topics = ReportKnowledge::topics();

        foreach (glob(ROOT.'/frameworks/*.json') as $file) {
            $definition = json_decode(file_get_contents($file), true);
            $counts = array();
            $total = 0;

            foreach ($definition['families'] as $family) {
                foreach ($family['controls'] as $control) {
                    $row = $stored($control, $family['name']);
                    $key = ReportKnowledge::topic_for($row['control'], $row['objectives']);
                    Assert::true(isset($topics[$key]), basename($file).' '.$control['id'].' got unknown topic '.$key);
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                    $total++;
                }
            }

            Assert::true($total > 0, basename($file).' has no controls');
            arsort($counts);
            $top = array_key_first($counts);
            Assert::true($counts[$top] / $total <= 0.35, basename($file).': '.$top.' takes '.$counts[$top].' of '.$total);
        }
    },

    'Knowledge: every topic has its own suggested evidence examples' => function () {
        $seen = array();

        foreach (ReportKnowledge::topics() as $key => $topic) {
            Assert::true(count($topic['evidence']) >= 3 && count($topic['evidence']) <= 8, $key.' has '.count($topic['evidence']).' evidence examples');

            foreach ($topic['evidence'] as $example) {
                Assert::true(preg_match('/^[A-Z]/', $example) === 1, $key.' evidence does not start with a capital: '.$example);
                Assert::true(preg_match('/\b(soc ?2|iso|27001|nist|cmmc|pci|hipaa|cui)\b/i', $example) === 0, $key.' evidence names a framework: '.$example);
                $normal = strtolower($example);
                Assert::true(!isset($seen[$normal]), '"'.$example.'" appears in '.$key.' and '.($seen[$normal] ?? ''));
                $seen[$normal] = $key;
            }
        }
    },

    'Knowledge: suggested evidence follows the wording of the requirement' => function () {
        $termination = ReportKnowledge::suggested_evidence(array(
            'identity'
        ), 'Access is removed promptly when employment is terminated.', 3);
        Assert::same('Leaver records showing access removed within the defined timeframe', $termination[0], 'termination evidence first');

        $backup = ReportKnowledge::suggested_evidence(array(
            'continuity'
        ), 'Backups are performed and restoration is tested.', 3);
        Assert::true(in_array('Backup job logs and backup schedule for in-scope systems', $backup, true), 'backup logs suggested');
        Assert::true(in_array('Restore test records showing data recovered successfully', $backup, true), 'restore tests suggested');

        $scan = ReportKnowledge::suggested_evidence(array(
            'vulnerability'
        ), 'Vulnerability scans are run and identified flaws are remediated.', 2);
        Assert::same(array(
            'Vulnerability scan results for the period, internal and external',
            'Remediation tracking records showing vulnerabilities closed within target timeframes'
        ), $scan, 'scan and remediation evidence');

        Assert::true($termination !== $backup && $backup !== $scan, 'different requirements get different evidence');
    },

    'Knowledge: suggested evidence always includes the primary topic and respects the limit' => function () {
        $examples = ReportKnowledge::suggested_evidence(array(
            'incident',
            'continuity'
        ), 'Unrelated wording with no matching terms at all.', 5);
        Assert::true(count($examples) >= 1 && count($examples) <= 5, 'count '.count($examples));
        Assert::true(in_array($examples[0], ReportKnowledge::topic('incident')['evidence'], true), 'primary topic first');

        foreach ($examples as $example) {
            Assert::true(!in_array($example, ReportKnowledge::topic('continuity')['evidence'], true), 'secondary topic without a keyword match is not suggested');
        }

        Assert::true(count(ReportKnowledge::suggested_evidence(array(), 'Anything', 1)) === 1, 'falls back to a topic when none is given');
        Assert::true(count(ReportKnowledge::suggested_evidence(array(
            'unknown_topic'
        ), 'Anything', 3)) >= 1, 'unknown topic falls back');
    },

    'Knowledge: topics_for puts the control topic first and adds objective topics without repeats' => function () {
        $control = array(
            'control_title' => 'Incident Response',
            'description' => 'The entity responds to identified security incidents.',
            'family' => 'System Operations'
        );
        $objectives = array(
            array(
                'control_title' => 'Restores operations from backup',
                'description' => 'Data is restored from backups and recovery is tested.'
            ),
            array(
                'control_title' => 'Contains security incidents',
                'description' => 'Incidents are contained and resolved.'
            )
        );
        $keys = ReportKnowledge::topics_for($control, $objectives);
        Assert::same(ReportKnowledge::topic_for($control, $objectives), $keys[0], 'primary topic first');
        Assert::same(count($keys), count(array_unique($keys)), 'no repeats');
        Assert::true(in_array('continuity', $keys, true), 'backup objective adds continuity: '.implode(', ', $keys));
    }
);
