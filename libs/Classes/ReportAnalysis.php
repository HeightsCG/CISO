<?php
/**
 * Reads one assessment and works out everything the reports say about it:
 * per-control records with their open objectives, evidence and assessor
 * fields, derived gap priorities, family narratives, cross-cutting themes,
 * consolidated remediation initiatives, numbered findings and readiness
 * issues. Nothing here invents client facts: results come from the recorded
 * answers, assessor text is passed through as recorded, and general security
 * knowledge comes from ReportKnowledge and is phrased as such.
 */
class ReportAnalysis
{
    const GAP_RESULTS = array(
        'Not Implemented',
        'Partially Implemented'
    );

    const PHASES = array(
        'Immediate priorities',
        'Foundational improvements',
        'Operational control enhancements',
        'Program maturity'
    );

    const ASSESSOR_FIELDS = array(
        'notes',
        'gap_description',
        'business_impact',
        'recommendation',
        'owner_name',
        'target_date',
        'implementation_description'
    );

    const PRIORITY_RANK = array(
        'High' => 3,
        'Medium' => 2,
        'Low' => 1
    );

    /** The most references written inline in a sentence; a longer list gets a sentence of its own or a pointer to a complete table. */
    const INLINE_REFS = 12;

    private $assessment_id;
    private $company_id;

    public function __construct(int $assessment_id, int $company_id)
    {
        $this->assessment_id = $assessment_id;
        $this->company_id = $company_id;
    }

    /** The full analysis, or null when the assessment is not this company's. */
    public function build(): ?array
    {
        $assessments = new AssessmentsModel();
        $rows = $assessments->get_assessment($this->assessment_id, $this->company_id);

        if (count($rows) !== 1) {
            return null;
        }

        $assessment = $rows[0];
        $definition = FrameworkLibrary::get((string) $assessment['framework_key']);
        $terms = self::terms($definition);
        $items = $assessments->load_items($this->assessment_id, $this->company_id);
        $evidence = $this->evidence_by_item();

        $objectives_of = array();
        foreach ($items as $item) {
            if ($item['control_kind'] === 'objective') {
                $objectives_of[$item['parent_identifier']][] = $item;
            }
        }

        $controls = array();
        foreach ($items as $item) {
            if ($item['control_kind'] === 'control') {
                $controls[] = self::control_record($item, $objectives_of[$item['control_identifier']] ?? array(), $evidence, $terms);
            }
        }

        $counts = array_fill_keys(array(
            'Implemented',
            'Partially Implemented',
            'Not Implemented',
            'Not Applicable',
            'Not Assessed'
        ), 0);

        foreach ($controls as $control) {
            $counts[$control['result']]++;
        }

        $total = count($controls);
        $applicable = $total - $counts['Not Applicable'];
        $assessed = $total - $counts['Not Assessed'];
        $with_evidence = count(array_filter($controls, function ($control) {
            return $control['evidence_count'] > 0;
        }));
        $evidence_files = array();
        foreach ($evidence as $list) {
            foreach ($list as $file) {
                $evidence_files[(int) $file['evidence_id']] = $file;
            }
        }

        $analysis = array(
            'assessment' => $assessment,
            'definition' => $definition,
            'terms' => $terms,
            'name' => self::plain($assessment['assessment_name']),
            'client' => self::plain($assessment['client_name'] ?? ''),
            'standard' => self::plain($assessment['standard_name']),
            'period' => self::period($assessment),
            'periods' => self::periods($assessment, $assessments->response_window($this->assessment_id, $this->company_id)),
            'counts' => $counts,
            'total' => $total,
            'applicable' => $applicable,
            'assessed' => $assessed,
            'score' => self::score($counts),
            'with_evidence' => $with_evidence,
            'evidence_files' => array_values($evidence_files),
            'controls' => $controls,
            'assessor' => self::assessment_texts($assessment)
        );

        $analysis['gaps'] = self::gaps($controls);
        $analysis['families'] = self::families($controls, $terms);
        $analysis['themes'] = self::themes($analysis['gaps'], $terms);
        $analysis['patterns'] = self::patterns($analysis['gaps'], $terms);
        $analysis['initiatives'] = self::initiatives($analysis['gaps'], $terms);
        $analysis['findings'] = self::findings($analysis['gaps'], $analysis['initiatives'], $terms);
        $analysis['readiness'] = self::readiness($analysis);
        $analysis['narratives'] = self::narratives($analysis);

        return $analysis;
    }

    /* ------------------------------------------------------------ records */

    private function evidence_by_item(): array
    {
        $out = array();

        foreach ((new ReadyReportsModel())->evidence_by_item($this->assessment_id, $this->company_id) as $row) {
            $out[(int) $row['assessment_item_id']][] = $row;
        }

        return $out;
    }

    private static function terms(?array $definition): array
    {
        $terms = array(
            'control' => 'control',
            'controls' => 'controls',
            'family' => 'family',
            'families' => 'families',
            'objective' => 'objective',
            'objectives' => 'objectives'
        );

        foreach ($terms as $key => $value) {
            if (!empty($definition['terminology'][$key])) {
                $terms[$key] = (string) $definition['terminology'][$key];
            }
        }

        return $terms;
    }

    private static function control_record(array $item, array $objective_rows, array $evidence, array $terms): array
    {
        $ref = self::plain($item['control_identifier']);
        $objectives = array();
        $open = array();

        foreach ($objective_rows as $row) {
            $id = self::plain($row['control_identifier']);
            $short = strpos($id, $ref) === 0 ? ltrim(substr($id, strlen($ref)), '-.') : $id;
            $objective = array(
                'id' => (int) $row['id'],
                'ref' => $id,
                'short' => $short === '' ? $id : $short,
                'title' => self::sentence(self::plain($row['control_title'])),
                'description' => self::sentence(self::plain($row['description'])),
                'result' => $row['item_result'],
                'evidence' => self::titles($evidence[(int) $row['id']] ?? array()),
                'assessor' => self::assessor_fields($row)
            );
            $objectives[] = $objective;

            if (in_array($row['item_result'], array(
                'Not Implemented',
                'Partially Implemented',
                'Not Assessed'
            ), true)) {
                $open[] = $objective;
            }
        }

        $control_evidence = self::titles($evidence[(int) $item['id']] ?? array());
        $all_evidence = $control_evidence;
        foreach ($objectives as $objective) {
            $all_evidence = array_merge($all_evidence, $objective['evidence']);
        }
        $all_evidence = array_values(array_unique($all_evidence));

        $topic_keys = ReportKnowledge::topics_for($item, $objective_rows);
        $topic_key = $topic_keys[0];
        $result = in_array($item['item_result'], array(
            'Implemented',
            'Partially Implemented',
            'Not Implemented',
            'Not Applicable',
            'Not Assessed'
        ), true) ? $item['item_result'] : 'Not Assessed';

        $updated = (string) ($item['date_updated'] ?? '');
        foreach ($objective_rows as $row) {
            if ((string) ($row['date_updated'] ?? '') > $updated) {
                $updated = (string) $row['date_updated'];
            }
        }

        $record = array(
            'id' => (int) $item['id'],
            'ref' => $ref,
            'updated' => $updated,
            'title' => self::plain($item['control_title']),
            'description' => self::plain($item['description']),
            'family' => self::plain($item['family']) !== '' ? self::plain($item['family']) : 'General',
            'result' => $result,
            'applicability' => (string) $item['applicability'],
            'justification' => self::plain($item['applicability_justification']),
            'expectations' => self::expectations((string) $item['guidance']),
            'topic' => $topic_key,
            'topics' => $topic_keys,
            'suggested_evidence' => ReportKnowledge::suggested_evidence($topic_keys, self::evidence_text($item, count($open) > 0 ? $open : $objectives)),
            'evidence' => $all_evidence,
            'evidence_count' => count($all_evidence),
            'objectives' => $objectives,
            'open' => $open,
            'assessor' => self::assessor_fields($item),
            'risk_rating' => in_array($item['risk_rating'] ?? '', array(
                'Low',
                'Moderate',
                'High',
                'Critical'
            ), true) ? $item['risk_rating'] : null,
            'is_gap' => in_array($result, self::GAP_RESULTS, true)
        );

        $record['priority'] = null;
        $record['priority_basis'] = '';

        if ($record['is_gap']) {
            $priority = self::priority($record, $terms);
            $record['priority'] = $priority['priority'];
            $record['priority_basis'] = $priority['basis'];
        }

        return $record;
    }

    /** The requirement wording that suggested evidence is matched against: the control and the objectives given. */
    private static function evidence_text(array $item, array $objectives): string
    {
        $parts = array(
            self::plain((string) $item['control_title']),
            self::plain((string) $item['description'])
        );

        foreach ($objectives as $objective) {
            $parts[] = $objective['title'];
            $parts[] = $objective['description'];
        }

        return implode(' ', $parts);
    }

    private static function assessor_fields(array $row): array
    {
        $out = array();

        foreach (self::ASSESSOR_FIELDS as $field) {
            $value = trim(self::plain($row[$field] ?? ''));
            if ($value !== '' && $value !== '0000-00-00') {
                $out[$field] = $value;
            }
        }

        return $out;
    }

    private static function assessment_texts(array $assessment): array
    {
        $out = array();

        foreach (array(
            'assessment_scope',
            'assessment_method',
            'posture_text',
            'strengths_text',
            'concerns_text',
            'readiness_text',
            'decisions_text'
        ) as $field) {
            $value = trim(self::plain($assessment[$field] ?? ''));
            if ($value !== '') {
                $out[$field] = $value;
            }
        }

        return $out;
    }

    /** Guidance lines "Topic: expectation" as [topic, expectation] pairs. */
    private static function expectations(string $guidance): array
    {
        $out = array();

        foreach (preg_split("/\n/", trim($guidance)) as $line) {
            $line = trim(self::plain($line));
            if ($line === '') {
                continue;
            }
            if (preg_match('/^([^:]{2,60}):\s*(.+)$/', $line, $match)) {
                $out[] = array(
                    $match[1],
                    self::sentence($match[2])
                );
            } else {
                $out[] = array(
                    '',
                    self::sentence($line)
                );
            }
        }

        return $out;
    }

    private static function titles(array $files): array
    {
        $out = array();

        foreach ($files as $file) {
            $out[] = self::plain($file['evidence_title']);
        }

        return $out;
    }

    /**
     * Derived priority for a gap: status (not implemented weighs more), how
     * exposed the topic area is (ReportKnowledge criticality) and the share
     * of objectives still open. This is a remediation priority, not an
     * assessed risk; an assessor's risk rating is reported separately.
     */
    private static function priority(array $record, array $terms): array
    {
        $topic = ReportKnowledge::topic($record['topic']);
        $criticality = (int) ($topic['criticality'] ?? 2);
        $all = count($record['objectives']);
        $share = $all > 0 ? count($record['open']) / $all : 1.0;
        $points = ($record['result'] === 'Not Implemented' ? 2 : 1) + ($criticality - 1) + ($share >= 0.5 ? 1 : 0);
        $priority = $points >= 4 ? 'High' : ($points >= 2 ? 'Medium' : 'Low');

        $parts = array(
            strtolower($record['result'])
        );
        $parts[] = $criticality === 3 ? 'in a high-exposure area ('.strtolower($topic['name']).')' : ($criticality === 1 ? 'in a lower-exposure area ('.strtolower($topic['name']).')' : 'in '.strtolower($topic['name']));
        if ($all > 0) {
            $parts[] = count($record['open']).' of '.$all.' '.$terms['objectives'].' open';
        }

        return array(
            'priority' => $priority,
            'basis' => 'Derived from the recorded result: '.implode(', ', $parts).'.'
        );
    }

    private static function gaps(array $controls): array
    {
        $gaps = array_values(array_filter($controls, function ($control) {
            return $control['is_gap'];
        }));

        usort($gaps, function ($x, $y) {
            $rank = self::PRIORITY_RANK[$y['priority']] <=> self::PRIORITY_RANK[$x['priority']];
            if ($rank !== 0) {
                return $rank;
            }
            $status = ($x['result'] === 'Not Implemented' ? 0 : 1) <=> ($y['result'] === 'Not Implemented' ? 0 : 1);
            if ($status !== 0) {
                return $status;
            }
            return count($y['open']) <=> count($x['open']);
        });

        return $gaps;
    }

    /* ----------------------------------------------------------- families */

    private static function families(array $controls, array $terms): array
    {
        $families = array();

        foreach ($controls as $control) {
            $name = $control['family'];
            if (!isset($families[$name])) {
                $families[$name] = array(
                    'name' => $name,
                    'controls' => array(),
                    'counts' => array_fill_keys(array(
                        'Implemented',
                        'Partially Implemented',
                        'Not Implemented',
                        'Not Applicable',
                        'Not Assessed'
                    ), 0)
                );
            }
            $families[$name]['controls'][] = $control;
            $families[$name]['counts'][$control['result']]++;
        }

        foreach ($families as $name => $family) {
            $families[$name]['score'] = self::score($family['counts']);
            $families[$name]['gaps'] = array_values(array_filter($family['controls'], function ($control) {
                return $control['is_gap'];
            }));
            $families[$name]['narrative'] = self::family_narrative($families[$name], $terms);
        }

        return $families;
    }

    private static function family_narrative(array $family, array $terms): string
    {
        $total = count($family['controls']);
        $c = $family['counts'];
        $topics = array();
        foreach ($family['controls'] as $control) {
            $topics[$control['topic']] = ReportKnowledge::topic($control['topic'])['name'];
        }
        $topic_names = array_values($topics);

        $parts = array();
        $parts[] = 'This '.$terms['family'].' covers '.self::join_list(array_map('strtolower', array_slice($topic_names, 0, 3))).' across '.$total.' '.($total === 1 ? $terms['control'] : $terms['controls']).'.';

        $assessed = $total - $c['Not Assessed'];
        if ($assessed === 0) {
            $parts[] = 'None of its '.$terms['controls'].' have been assessed yet, so no conclusion can be drawn.';
            return implode(' ', $parts);
        }

        $results = array();
        foreach (array(
            'Implemented' => 'implemented',
            'Partially Implemented' => 'partially implemented',
            'Not Implemented' => 'not implemented',
            'Not Applicable' => 'not applicable'
        ) as $key => $label) {
            if ($c[$key] > 0) {
                $results[] = $c[$key].' '.$label;
            }
        }
        $parts[] = 'Recorded results: '.self::join_list($results).($family['score'] !== null ? ', an implementation score of '.$family['score'].'%' : '').'.';

        if ($c['Not Assessed'] > 0) {
            $parts[] = $c['Not Assessed'].' '.($c['Not Assessed'] === 1 ? $terms['control'].' has' : $terms['controls'].' have').' not been assessed yet.';
        }

        $gaps = $family['gaps'];
        if (count($gaps) === 0) {
            $parts[] = 'No gaps were recorded among the assessed '.$terms['controls'].'.';
        } else {
            usort($gaps, function ($x, $y) {
                return count($y['open']) <=> count($x['open']);
            });
            $named = array();
            foreach (array_slice($gaps, 0, 2) as $gap) {
                $all = count($gap['objectives']);
                $named[] = $gap['ref'].' '.$gap['title'].($all > 0 ? ' ('.count($gap['open']).' of '.$all.' '.$terms['objectives'].' open)' : ' ('.strtolower($gap['result']).')');
            }
            $parts[] = 'The largest gaps are in '.self::join_list($named).'.';

            $gap_topics = array();
            foreach ($gaps as $gap) {
                $gap_topics[$gap['topic']] = ($gap_topics[$gap['topic']] ?? 0) + 1;
            }
            arsort($gap_topics);
            $top = array_key_first($gap_topics);
            if ($gap_topics[$top] >= 2) {
                $parts[] = 'Gaps concentrate in '.strtolower(ReportKnowledge::topic($top)['name']).', where '.$gap_topics[$top].' '.$terms['controls'].' are not fully in place.';
            }

            $high = count(array_filter($gaps, function ($gap) {
                return $gap['priority'] === 'High';
            }));
            if ($high > 0) {
                $parts[] = $high.' '.($high === 1 ? 'gap carries' : 'gaps carry').' a high remediation priority.';
            }
        }

        $without = count(array_filter($family['controls'], function ($control) {
            return $control['evidence_count'] === 0 && $control['result'] !== 'Not Applicable' && $control['result'] !== 'Not Assessed';
        }));
        if ($without === $assessed - $c['Not Applicable'] && $without > 0) {
            $parts[] = 'No evidence is linked to these '.$terms['controls'].', so the results rest on the recorded answers and need supporting evidence before they can be relied on.';
        } elseif ($without > 0) {
            $parts[] = $without.' assessed '.($without === 1 ? $terms['control'].' has' : $terms['controls'].' have').' no linked evidence.';
        }

        return implode(' ', $parts);
    }

    /* ------------------------------------------------------ themes, patterns */

    private static function themes(array $gaps, array $terms): array
    {
        $by_topic = array();

        foreach ($gaps as $gap) {
            $by_topic[$gap['topic']][] = $gap;
        }

        uasort($by_topic, function ($x, $y) {
            return count($y) <=> count($x);
        });

        $themes = array();

        foreach ($by_topic as $key => $list) {
            if (count($list) < 2) {
                continue;
            }
            $topic = ReportKnowledge::topic($key);
            $families = array_values(array_unique(array_column($list, 'family')));
            $themes[] = array(
                'topic' => $key,
                'name' => $topic['name'],
                'refs' => array_column($list, 'ref'),
                'families' => $families,
                'sentence' => count($list).' '.$terms['controls'].' related to '.strtolower($topic['name']).' are not fully implemented ('.implode(', ', array_column($list, 'ref')).')'
                    .(count($families) > 1 ? ', spanning '.count($families).' '.$terms['families'] : '').'.'
            );
        }

        return $themes;
    }

    /**
     * Cross-cutting patterns in the open objectives, from their own wording:
     * documentation, periodic review, monitoring, training, approval and
     * third-party coverage. Only patterns with at least three open
     * objectives across at least two controls are reported.
     */
    private static function patterns(array $gaps, array $terms): array
    {
        $rules = array(
            'documentation' => array(
                'label' => 'Documented policies and procedures',
                'words' => '/\b(document|documented|written|policy|policies|procedure|procedures|plan|standard)\b/i',
                'sentence' => 'Several open %s depend on written policies, procedures or plans that are not yet in place or not yet complete.'
            ),
            'review' => array(
                'label' => 'Periodic review and reassessment',
                'words' => '/\b(review|reviews|reviewed|periodic|periodically|annual|annually|reassess|evaluate|evaluates)\b/i',
                'sentence' => 'Open %s repeatedly call for periodic review or reassessment, suggesting that recurring governance activities are not yet operating consistently.'
            ),
            'monitoring' => array(
                'label' => 'Monitoring and detection',
                'words' => '/\b(monitor|monitors|monitoring|detect|detects|detection|alert|alerts|log|logs|logging)\b/i',
                'sentence' => 'Open %s relate to monitoring, logging or detection, which limits the ability to identify problems as they occur.'
            ),
            'training' => array(
                'label' => 'Training and awareness',
                'words' => '/\b(train|trains|training|awareness|educate|competence|skills)\b/i',
                'sentence' => 'Open %s concern training, awareness or competence of personnel.'
            ),
            'approval' => array(
                'label' => 'Authorization and approval',
                'words' => '/\b(approve|approves|approved|approval|authorize|authorizes|authorized|authorization)\b/i',
                'sentence' => 'Open %s relate to formal authorization or approval steps.'
            ),
            'third_party' => array(
                'label' => 'Third parties and vendors',
                'words' => '/\b(vendor|vendors|third[- ]part(y|ies)|supplier|suppliers|contractor|contractors|service provider|service providers|business partner)\b/i',
                'sentence' => 'Open %s extend to vendors, contractors or other third parties.'
            )
        );

        $patterns = array();

        foreach ($rules as $key => $rule) {
            $points = array();
            $controls = array();
            foreach ($gaps as $gap) {
                foreach ($gap['open'] as $objective) {
                    if ($objective['result'] === 'Not Assessed') {
                        continue;
                    }
                    if (preg_match($rule['words'], $objective['title'].' '.$objective['description'])) {
                        $points[] = $objective['ref'];
                        $controls[$gap['ref']] = true;
                    }
                }
            }
            if (count($points) >= 3 && count($controls) >= 2) {
                $patterns[] = array(
                    'key' => $key,
                    'label' => $rule['label'],
                    'points' => $points,
                    'controls' => array_keys($controls),
                    'sentence' => sprintf($rule['sentence'], $terms['objectives']).' This applies to '.count($points).' '.$terms['objectives'].' across '.count($controls).' '.$terms['controls'].(count($controls) > self::INLINE_REFS ? '. The '.$terms['controls'].' concerned are '.self::join_list(array_keys($controls)).'.' : ' ('.implode(', ', array_keys($controls)).').')
                );
            }
        }

        usort($patterns, function ($x, $y) {
            return count($y['points']) <=> count($x['points']);
        });

        return $patterns;
    }

    /* -------------------------------------------------------- initiatives */

    private static function initiatives(array $gaps, array $terms): array
    {
        $groups = array();

        foreach ($gaps as $gap) {
            $topic = ReportKnowledge::topic($gap['topic']);
            $title = $topic['initiative']['title'];
            if (!isset($groups[$title])) {
                $groups[$title] = array(
                    'title' => $title,
                    'phase' => $topic['initiative']['phase'],
                    'topics' => array(),
                    'gaps' => array()
                );
            }
            $groups[$title]['topics'][$gap['topic']] = true;
            $groups[$title]['gaps'][] = $gap;
        }

        $merged = true;
        while ($merged && count($groups) > 8) {
            $merged = false;
            foreach ($groups as $title => $group) {
                if (count($group['gaps']) !== 1 || $group['gaps'][0]['priority'] === 'High') {
                    continue;
                }
                $target = null;
                foreach (array_keys($group['topics']) as $topic_key) {
                    foreach (ReportKnowledge::topic($topic_key)['depends_on'] as $dependency) {
                        foreach ($groups as $other_title => $other) {
                            if ($other_title !== $title && isset($other['topics'][$dependency])) {
                                $target = $other_title;
                                break 3;
                            }
                        }
                    }
                }
                if ($target === null) {
                    continue;
                }
                $groups[$target]['topics'] = $groups[$target]['topics'] + $group['topics'];
                $groups[$target]['gaps'] = array_merge($groups[$target]['gaps'], $group['gaps']);
                unset($groups[$title]);
                $merged = true;
                break;
            }
        }

        foreach ($groups as $title => $group) {
            $priority = 'Low';
            foreach ($group['gaps'] as $gap) {
                if (self::PRIORITY_RANK[$gap['priority']] > self::PRIORITY_RANK[$priority]) {
                    $priority = $gap['priority'];
                }
            }
            $groups[$title]['priority'] = $priority;
            if ($priority === 'High' && $group['phase'] !== 'Immediate priorities' && count(array_filter($group['gaps'], function ($gap) {
                return $gap['result'] === 'Not Implemented' && $gap['priority'] === 'High';
            })) > 0) {
                $groups[$title]['phase'] = 'Immediate priorities';
            }
        }

        uasort($groups, function ($x, $y) {
            $phase = array_search($x['phase'], self::PHASES, true) <=> array_search($y['phase'], self::PHASES, true);
            if ($phase !== 0) {
                return $phase;
            }
            $rank = self::PRIORITY_RANK[$y['priority']] <=> self::PRIORITY_RANK[$x['priority']];
            if ($rank !== 0) {
                return $rank;
            }
            return count($y['gaps']) <=> count($x['gaps']);
        });

        $ids = array();
        $number = 0;
        foreach ($groups as $title => $group) {
            $number++;
            $ids[$title] = 'R-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            foreach (array_keys($group['topics']) as $topic_key) {
                $ids['topic:'.$topic_key] = $ids[$title];
            }
        }

        $initiatives = array();

        foreach ($groups as $title => $group) {
            $topic_keys = array_keys($group['topics']);
            $activities = array();
            $deliverables = array();
            $validation = array();
            $depends = array();
            $names = array();

            foreach ($topic_keys as $topic_key) {
                $topic = ReportKnowledge::topic($topic_key);
                $names[] = strtolower($topic['name']);
                $activities = array_merge($activities, $topic['activities']);
                $deliverables = array_merge($deliverables, $topic['deliverables']);
                $validation = array_merge($validation, $topic['validation']);
                foreach ($topic['depends_on'] as $dependency) {
                    if (isset($ids['topic:'.$dependency]) && $ids['topic:'.$dependency] !== $ids[$title]) {
                        $depends[] = $ids['topic:'.$dependency];
                    }
                }
            }

            $points = array();
            $not = 0;
            $partial = 0;
            foreach ($group['gaps'] as $gap) {
                if ($gap['result'] === 'Not Implemented') {
                    $not++;
                } else {
                    $partial++;
                }
                foreach ($gap['open'] as $objective) {
                    if ($objective['result'] !== 'Not Assessed') {
                        $points[] = $objective;
                    }
                }
            }

            $problem = count($group['gaps']).' '.(count($group['gaps']) === 1 ? $terms['control'].' in ' : $terms['controls'].' in ').self::join_list(array_unique($names)).' '.(count($group['gaps']) === 1 ? 'is' : 'are').' not fully in place';
            $states = array();
            if ($not > 0) {
                $states[] = $not.' not implemented';
            }
            if ($partial > 0) {
                $states[] = $partial.' partially implemented';
            }
            $problem .= ' ('.implode(', ', $states).').';
            if (count($points) > 0) {
                $examples = array();
                foreach (array_slice($points, 0, 3) as $point) {
                    $examples[] = rtrim($point['description'] !== '' ? $point['description'] : $point['title'], '.');
                }
                $problem .= ' Open '.$terms['objectives'].' include: '.implode('; ', array_map(function ($text) {
                    return preg_match('/^[A-Z][a-z]/', $text) ? lcfirst($text) : $text;
                }, $examples)).'.';
            }

            $owners = array_values(array_unique(array_filter(array_map(function ($gap) {
                return $gap['assessor']['owner_name'] ?? '';
            }, $group['gaps']))));
            $targets = array_values(array_unique(array_filter(array_map(function ($gap) {
                return $gap['assessor']['target_date'] ?? '';
            }, $group['gaps']))));

            $initiatives[] = array(
                'id' => $ids[$title],
                'title' => $title,
                'phase' => $group['phase'],
                'priority' => $group['priority'],
                'problem' => $problem,
                'activities' => array_values(array_unique($activities)),
                'deliverables' => array_values(array_unique($deliverables)),
                'validation' => array_values(array_unique($validation)),
                'controls' => array_column($group['gaps'], 'ref'),
                'gaps' => $group['gaps'],
                'points' => array_column($points, 'ref'),
                'depends_on' => array_values(array_unique($depends)),
                'owner' => count($owners) === 1 ? $owners[0] : null,
                'target' => count($targets) === 1 ? $targets[0] : null,
                'outcome' => ReportKnowledge::topic($topic_keys[0])['outcome']
            );
        }

        $position = array_flip(array_column($initiatives, 'id'));
        $depends_of = array_column($initiatives, 'depends_on', 'id');

        foreach ($initiatives as $index => $initiative) {
            $initiatives[$index]['depends_on'] = array_values(array_filter($initiative['depends_on'], function ($dependency) use ($initiative, $position, $depends_of) {
                if (!in_array($initiative['id'], $depends_of[$dependency] ?? array(), true)) {
                    return true;
                }
                return $position[$dependency] < $position[$initiative['id']];
            }));
        }

        return $initiatives;
    }

    /* ----------------------------------------------------------- findings */

    private static function findings(array $gaps, array $initiatives, array $terms): array
    {
        $initiative_of = array();
        foreach ($initiatives as $initiative) {
            foreach ($initiative['controls'] as $ref) {
                $initiative_of[$ref] = $initiative['id'];
            }
        }

        $findings = array();
        $number = 0;

        foreach ($gaps as $gap) {
            $number++;
            $topic = ReportKnowledge::topic($gap['topic']);
            $all = count($gap['objectives']);
            $answered_open = array_values(array_filter($gap['open'], function ($objective) {
                return $objective['result'] !== 'Not Assessed';
            }));
            $unanswered = count($gap['open']) - count($answered_open);

            $observation = array();
            if (isset($gap['assessor']['gap_description'])) {
                $observation[] = 'Assessor observation: '.$gap['assessor']['gap_description'];
            }
            if (isset($gap['assessor']['notes'])) {
                $observation[] = 'Assessor note: '.$gap['assessor']['notes'];
            }
            $recorded = $gap['ref'].' was recorded as '.strtolower($gap['result']).'.';
            if ($all > 0) {
                if ($all === 1) {
                    $recorded .= ' Its single '.$terms['objective'].' is '.($unanswered > 0 ? 'not yet answered' : 'not fully met').':';
                } elseif (count($answered_open) === 0) {
                    $recorded .= ' '.$unanswered.' of its '.$all.' '.$terms['objectives'].' '.($unanswered === 1 ? 'has' : 'have').' not been answered yet:';
                } elseif (count($answered_open) === $all) {
                    $recorded .= ' None of its '.$all.' '.$terms['objectives'].' is fully met:';
                } else {
                    $recorded .= ' '.count($answered_open).' of its '.$all.' '.$terms['objectives'].' '.(count($answered_open) === 1 ? 'is' : 'are').' not fully met'.($unanswered > 0 ? ' and '.$unanswered.' '.($unanswered === 1 ? 'has' : 'have').' not been answered' : '').':';
                }
                $observation[] = $recorded;
                foreach ($gap['open'] as $objective) {
                    $state = array(
                        'Not Implemented' => 'not met',
                        'Partially Implemented' => 'partially met',
                        'Not Assessed' => 'not yet assessed'
                    )[$objective['result']];
                    $line = '- '.$objective['short'].' '.rtrim($objective['title'], '.').' ('.$state.')';
                    if ($objective['description'] !== '' && $objective['description'] !== $objective['title']) {
                        $line .= ': '.rtrim($objective['description'], '.').'.';
                    }
                    $observation[] = $line;
                }
            } else {
                $observation[] = $recorded.' No individual '.$terms['objectives'].' are defined for this '.$terms['control'].', so the specific deficiency needs to be confirmed with the assessor.';
            }
            if (isset($gap['assessor']['implementation_description'])) {
                $observation[] = 'Current implementation (as recorded): '.$gap['assessor']['implementation_description'];
            }

            $expectation = array();
            if ($gap['description'] !== '') {
                $expectation[] = self::sentence($gap['description']);
            }
            foreach ($gap['expectations'] as $pair) {
                $expectation[] = '- '.($pair[0] !== '' ? $pair[0].': ' : '').$pair[1];
            }
            if (count($expectation) === 0) {
                $expectation[] = $topic['purpose'];
            }

            $consequences = array();
            if (isset($gap['assessor']['business_impact'])) {
                $consequences[] = 'Business impact (as recorded): '.$gap['assessor']['business_impact'];
            }
            $consequences[] = 'If this gap remains, the following could occur:';
            foreach (self::pick($topic['consequences'], $number, $gap['result'] === 'Not Implemented' ? 3 : 2) as $consequence) {
                $consequences[] = '- '.$consequence;
            }

            $recommendation = array();
            if (isset($gap['assessor']['recommendation'])) {
                $recommendation[] = 'Assessor recommendation: '.$gap['assessor']['recommendation'];
            }
            if (count($answered_open) > 0) {
                $recommendation[] = 'Put the missing elements in place so that each of the following can be demonstrated:';
                foreach ($answered_open as $objective) {
                    $recommendation[] = '- '.$objective['short'].': '.rtrim($objective['description'] !== '' ? $objective['description'] : $objective['title'], '.').'.';
                }
                $recommendation[] = 'Supporting activities:';
            }
            foreach (self::pick($topic['activities'], $number, 3) as $activity) {
                $recommendation[] = '- '.$activity;
            }
            if ($unanswered > 0) {
                $recommendation[] = 'Complete the assessment of the '.$unanswered.' unanswered '.($unanswered === 1 ? $terms['objective'] : $terms['objectives']).' to confirm the full extent of the gap.';
            }

            $validation = self::pick($topic['validation'], $number, 3);
            if (count($answered_open) > 0) {
                $validation[] = 'Evidence linked to each open '.$terms['objective'].' ('.implode(', ', array_column($answered_open, 'short')).') showing the condition is met.';
            }

            $findings[] = array(
                'ref' => 'F-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'anchor' => 'finding-'.$number,
                'control' => $gap,
                'title' => $gap['ref'].' '.$gap['title'],
                'domain' => $gap['family'],
                'topic' => $topic['name'],
                'status' => $gap['result'],
                'priority' => $gap['priority'],
                'priority_basis' => $gap['priority_basis'],
                'risk_rating' => $gap['risk_rating'],
                'observation' => implode("\n", $observation),
                'expectation' => implode("\n", $expectation),
                'consequences' => implode("\n", $consequences),
                'recommendation' => implode("\n", $recommendation),
                'outcome' => $topic['outcome'],
                'validation' => implode("\n", array_map(function ($line) {
                    return '- '.$line;
                }, $validation)),
                'evidence' => count($gap['evidence']) > 0 ? implode("\n", array_map(function ($title) {
                    return '- '.$title;
                }, $gap['evidence'])) : 'No evidence is linked to this '.$terms['control'].' or its '.$terms['objectives'].'; the recorded result requires confirmation against supporting evidence.',
                'owner' => $gap['assessor']['owner_name'] ?? null,
                'target' => $gap['assessor']['target_date'] ?? null,
                'initiative' => $initiative_of[$gap['ref']] ?? null
            );
        }

        return $findings;
    }

    /* ---------------------------------------------------------- readiness */

    private static function readiness(array $a): array
    {
        $issues = array();
        $terms = $a['terms'];

        if ($a['counts']['Not Assessed'] > 0) {
            $issues[] = array(
                'High',
                $a['counts']['Not Assessed'].' '.($a['counts']['Not Assessed'] === 1 ? $terms['control'].' has' : $terms['controls'].' have').' not been assessed; results and findings are incomplete.'
            );
        }

        $open_objectives = 0;
        foreach ($a['controls'] as $control) {
            foreach ($control['objectives'] as $objective) {
                if ($objective['result'] === 'Not Assessed') {
                    $open_objectives++;
                }
            }
        }
        if ($open_objectives > 0) {
            $issues[] = array(
                'Medium',
                $open_objectives.' '.($open_objectives === 1 ? $terms['objective'].' has' : $terms['objectives'].' have').' not been answered.'
            );
        }

        $no_evidence = count(array_filter($a['controls'], function ($control) {
            return $control['evidence_count'] === 0 && !in_array($control['result'], array(
                'Not Applicable',
                'Not Assessed'
            ), true);
        }));
        if ($no_evidence > 0) {
            $issues[] = array(
                $no_evidence === $a['assessed'] - $a['counts']['Not Applicable'] ? 'High' : 'Medium',
                $no_evidence.' assessed '.($no_evidence === 1 ? $terms['control'].' has' : $terms['controls'].' have').' no linked evidence.'
            );
        }

        $unjustified = count(array_filter($a['controls'], function ($control) {
            return ($control['result'] === 'Not Applicable' || $control['applicability'] === 'Excluded') && $control['justification'] === '';
        }));
        if ($unjustified > 0) {
            $issues[] = array(
                'Medium',
                $unjustified.' '.$terms['controls'].' marked not applicable have no recorded justification.'
            );
        }

        $no_notes = count(array_filter($a['gaps'], function ($gap) {
            return !isset($gap['assessor']['notes']) && !isset($gap['assessor']['gap_description']);
        }));
        if ($no_notes > 0) {
            $issues[] = array(
                'Low',
                $no_notes.' of '.count($a['gaps']).' gaps have no assessor observation recorded; findings are described from the recorded answers only.'
            );
        }

        foreach (array(
            'assessment_scope' => 'The assessment scope has not been recorded.',
            'assessment_method' => 'The assessment method has not been recorded.'
        ) as $field => $message) {
            if (!isset($a['assessor'][$field])) {
                $issues[] = array(
                    'Low',
                    $message
                );
            }
        }

        return $issues;
    }

    /* --------------------------------------------------------- narratives */

    private static function narratives(array $a): array
    {
        $t = $a['terms'];
        $c = $a['counts'];
        $gap_total = $c['Not Implemented'] + $c['Partially Implemented'];
        $client = $a['client'] !== '' ? $a['client'] : 'The organization';
        $subject = 'the '.$a['name'].' assessment'.(strcasecmp($a['name'], $a['standard']) === 0 ? '' : ' against '.$a['standard']);

        $scored = array();
        foreach ($a['families'] as $name => $family) {
            if ($family['score'] !== null) {
                $scored[$name] = $family['score'];
            }
        }
        arsort($scored);
        $strongest = array_slice($scored, 0, 3, true);
        $weakest = array_slice(array_reverse($scored, true), 0, 3, true);

        $overview = array();
        if (isset($a['assessor']['posture_text'])) {
            $overview[] = $a['assessor']['posture_text'];
        }

        $p1 = 'This report summarizes '.$subject.' for '.$client.'. ';
        $p1 .= $a['assessed'].' of '.$a['total'].' '.$t['controls'].' ('.self::pct($a['assessed'], $a['total']).') have been assessed';
        $p1 .= $a['score'] !== null ? ', giving an implementation score of '.$a['score'].'%.' : '.';
        if ($a['score'] !== null) {
            $p1 .= ' The implementation score measures how fully the assessed '.$t['controls'].' are in place (implemented counts in full, partially implemented counts half); it is not a compliance opinion or certification result.';
        }
        $overview[] = $p1;

        if ($gap_total > 0) {
            $states = array();
            if ($c['Not Implemented'] > 0) {
                $states[] = $c['Not Implemented'].' not implemented';
            }
            if ($c['Partially Implemented'] > 0) {
                $states[] = $c['Partially Implemented'].' partially implemented';
            }
            $p2 = $gap_total.' '.($gap_total === 1 ? $t['control'].' is' : $t['controls'].' are').' not fully in place ('.implode(' and ', $states).').';
            if ($gap_total >= 5 && $c['Partially Implemented'] > $c['Not Implemented'] * 3) {
                $p2 .= ' Most gaps are partial rather than absent controls, which indicates that '.($client === 'The organization' ? 'the organization' : $client).' has foundations in many areas but has not yet completed or formalized them.';
            } elseif ($gap_total >= 5 && $c['Not Implemented'] > $c['Partially Implemented']) {
                $p2 .= ' More '.$t['controls'].' are absent than partially in place, which points to areas of the program that have not yet been established.';
            }
            if (count($a['themes']) > 0) {
                $names = array_map(function ($theme) {
                    return strtolower($theme['name']);
                }, array_slice($a['themes'], 0, 3));
                $p2 .= ' The gaps cluster around '.self::join_list($names).'.';
            }
            $overview[] = $p2;
        } elseif ($a['assessed'] > 0) {
            $overview[] = 'No gaps were recorded among the assessed '.$t['controls'].'.';
        }

        if ($a['with_evidence'] === 0 && $a['assessed'] > 0) {
            $overview[] = 'No evidence has been linked to the assessed '.$t['controls'].'. The results therefore reflect the recorded answers and should be confirmed against supporting documentation before they are relied on for external reporting.';
        } elseif ($a['assessed'] > 0) {
            $overview[] = $a['with_evidence'].' of '.$a['assessed'].' assessed '.$t['controls'].' have linked evidence ('.self::pct($a['with_evidence'], $a['assessed']).').';
        }

        $strengths = array();
        if (isset($a['assessor']['strengths_text'])) {
            $strengths[] = $a['assessor']['strengths_text'];
        }
        foreach ($strongest as $name => $score) {
            $family = $a['families'][$name];
            if ($score >= 60 || $family['counts']['Implemented'] > 0) {
                $implemented = array_values(array_filter($family['controls'], function ($control) {
                    return $control['result'] === 'Implemented';
                }));
                $strengths[] = $name.' scores '.$score.'%'.(count($implemented) > 0 ? ', with '.self::join_list(array_map(function ($control) {
                    return $control['ref'].' '.$control['title'];
                }, array_slice($implemented, 0, 3))).' recorded as implemented.' : ', the highest among the '.$t['families'].'.');
            }
        }

        $improvements = array();
        if (isset($a['assessor']['concerns_text'])) {
            $improvements[] = $a['assessor']['concerns_text'];
        }
        foreach ($weakest as $name => $score) {
            $family = $a['families'][$name];
            if (count($family['gaps']) > 0) {
                $improvements[] = $name.' ('.$score.'%): '.count($family['gaps']).' of '.count($family['controls']).' '.$t['controls'].' not fully in place.';
            }
        }
        foreach (array_slice($a['patterns'], 0, 3) as $pattern) {
            $improvements[] = $pattern['label'].': '.$pattern['sentence'];
        }

        $recommendations = array();
        foreach (array_slice($a['initiatives'], 0, 5) as $initiative) {
            $recommendations[] = array(
                'id' => $initiative['id'],
                'title' => $initiative['title'],
                'text' => $initiative['problem'].' '.$initiative['outcome']
            );
        }

        $considerations = array();
        if (isset($a['assessor']['decisions_text'])) {
            $considerations[] = $a['assessor']['decisions_text'];
        }
        $unowned = count(array_filter($a['initiatives'], function ($initiative) {
            return $initiative['owner'] === null;
        }));
        if (count($a['initiatives']) > 0) {
            $considerations[] = 'Assign an accountable owner and target date to each remediation initiative'.($unowned > 0 ? '; '.($unowned === count($a['initiatives']) ? (count($a['initiatives']) === 1 ? 'it has no owner recorded' : 'no initiative has an owner recorded') : $unowned.' of '.count($a['initiatives']).($unowned === 1 ? ' has' : ' have').' no owner recorded') : '').'.';
            $high = count(array_filter($a['initiatives'], function ($initiative) {
                return $initiative['priority'] === 'High';
            }));
            if ($high > 0) {
                $considerations[] = 'Authorize the resources needed for the '.$high.' high-priority '.($high === 1 ? 'initiative' : 'initiatives').' first, and track them at management level until closed.';
            }
        }
        if ($a['with_evidence'] < $a['assessed']) {
            $considerations[] = 'Direct control owners to supply supporting evidence for the assessed '.$t['controls'].' so that the results can be validated.';
        }
        if ($c['Not Assessed'] > 0) {
            $considerations[] = 'Complete the remaining '.$c['Not Assessed'].' '.$t['controls'].' before the results are used for decisions or external reporting.';
        }
        $considerations[] = 'Decide whether any gap will be accepted rather than remediated, and record the rationale and approver for each accepted gap.';
        $considerations[] = 'Schedule a follow-up assessment after the high-priority initiatives are complete to confirm the improvements are operating.';

        return array(
            'subject' => $subject,
            'overview' => $overview,
            'strengths' => $strengths,
            'improvements' => $improvements,
            'recommendations' => $recommendations,
            'considerations' => $considerations
        );
    }

    /* ------------------------------------------------------------ helpers */

    /** A stable rotation through a list so neighbouring findings do not repeat the same lines first. */
    private static function pick(array $list, int $seed, int $count): array
    {
        $n = count($list);

        if ($n <= $count) {
            return $list;
        }

        $out = array();
        for ($i = 0; $i < $count; $i++) {
            $out[] = $list[($seed + $i) % $n];
        }

        return $out;
    }

    public static function score(array $counts): ?int
    {
        $applicable = $counts['Implemented'] + $counts['Partially Implemented'] + $counts['Not Implemented'];

        if ($applicable === 0) {
            return null;
        }

        return (int) round(100 * ($counts['Implemented'] + 0.5 * $counts['Partially Implemented']) / $applicable);
    }

    /**
     * The three dates a reader needs kept apart: the planned assessment
     * period, the window in which responses were actually recorded, and the
     * reporting cutoff (results as they stood when the report was generated).
     */
    private static function periods(array $assessment, array $window): array
    {
        $date = function ($value) {
            return empty($value) ? '' : date('F j, Y', strtotime((string) $value));
        };

        $first = $date($window['first_response'] ?? '');
        $last = $date($window['last_response'] ?? '');

        return array(
            'planned' => empty($assessment['start_date'])
                ? 'Ongoing (no fixed period planned)'
                : (empty($assessment['end_date']) ? 'From '.$date($assessment['start_date']) : $date($assessment['start_date']).' to '.$date($assessment['end_date'])),
            'responses' => $first === '' ? 'No responses recorded yet' : ($first === $last ? $first : $first.' to '.$last),
            'cutoff' => date('F j, Y H:i').' UTC',
            'assessment_status' => self::plain($assessment['assessment_status'] ?? '')
        );
    }

    private static function period(array $assessment): string
    {
        if (empty($assessment['start_date'])) {
            return 'Ongoing';
        }

        $start = date('F j, Y', strtotime($assessment['start_date']));

        return empty($assessment['end_date']) ? 'From '.$start : $start.' to '.date('F j, Y', strtotime($assessment['end_date']));
    }

    public static function join_list(array $items): string
    {
        $items = array_values(array_filter($items, function ($item) {
            return $item !== '';
        }));
        $n = count($items);

        if ($n === 0) {
            return '';
        }

        if ($n === 1) {
            return $items[0];
        }

        return implode(', ', array_slice($items, 0, $n - 1)).' and '.$items[$n - 1];
    }

    public static function pct(int $part, int $whole): string
    {
        return $whole === 0 ? '0%' : round(100 * $part / $whole).'%';
    }

    private static function sentence(string $text): string
    {
        $text = trim($text);

        return $text === '' ? '' : ucfirst($text);
    }

    public static function plain($value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'));
    }
}
