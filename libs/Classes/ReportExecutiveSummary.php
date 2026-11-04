<?php
/**
 * The Executive Summary: what the assessment means for leadership, told in
 * panels, charts and short narrative rather than tables. Stored AI
 * narratives (executive_ai, patterns_ai, initiatives_ai) are used when they
 * exist; otherwise every passage is derived from the recorded results.
 * Assessor-recorded text is always labelled as the assessor's.
 */
class ReportExecutiveSummary extends ReportBuilder
{
    const PRIORITY_TONES = array(
        'High' => 'critical',
        'Medium' => 'warning',
        'Low' => 'neutral'
    );

    const KEY_FINDINGS = 4;

    const RECOMMENDATIONS = 4;

    private $plan = null;

    protected function title(): string
    {
        return 'Executive Summary';
    }

    protected function doc_options(): array
    {
        return array(
            'toc' => false,
            'numbered' => true
        );
    }

    protected function blocks(): array
    {
        return array_merge(
            $this->overview(),
            $this->scope(),
            $this->dashboard(),
            $this->key_findings(),
            $this->strengths(),
            $this->improvements(),
            $this->recommendations(),
            $this->considerations()
        );
    }

    /* ----------------------------------------------------------- overview */

    private function overview(): array
    {
        $blocks = array(
            self::h1('Executive overview')
        );

        if (isset($this->a['assessor']['posture_text'])) {
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => 'Assessor\'s overall view',
                    'tone' => 'brand',
                    'body' => $this->a['assessor']['posture_text']
                )
            );
        }

        $ai = $this->ai_list('overview');

        if ($ai !== null) {
            $blocks[] = self::text(implode("\n\n", $ai));
            return $blocks;
        }

        $paragraphs = array();
        foreach ($this->a['narratives']['overview'] as $paragraph) {
            if ($paragraph !== ($this->a['assessor']['posture_text'] ?? null)) {
                $paragraphs[] = $paragraph;
            }
        }

        $caution = $this->coverage_caution();
        if ($caution !== '') {
            $c = $this->a['counts'];
            $gap_total = $c['Not Implemented'] + $c['Partially Implemented'];
            $paragraphs = array_values(array_filter($paragraphs, function ($paragraph) use ($gap_total) {
                return strpos($paragraph, $gap_total.' '.$this->term('controls').' are not fully in place') !== 0;
            }));
            $caution .= $gap_total > 0
                ? ' Of the '.$this->a['assessed'].' assessed, '.$gap_total.' '.($gap_total === 1 ? 'is' : 'are').' not fully in place ('.$c['Partially Implemented'].' partially implemented, '.$c['Not Implemented'].' not implemented).'
                : '';
            array_splice($paragraphs, 1, 0, array(
                $caution
            ));
        }

        $priorities = $this->priority_paragraph();
        if ($priorities !== '') {
            $paragraphs[] = $priorities;
        }

        $blocks[] = self::text(implode("\n\n", $paragraphs));

        return $blocks;
    }

    private function coverage_caution(): string
    {
        $a = $this->a;

        if ($a['total'] === 0 || $a['assessed'] * 2 >= $a['total']) {
            return '';
        }

        return 'Only '.self::pct($a['assessed'], $a['total']).' of the '.$this->term('controls').' have been assessed, so the results are provisional: they describe the '.$this->term('controls').' answered so far and cannot yet be read as the overall position of '.$this->client_name().'. The score and findings will change as the remaining '.$a['counts']['Not Assessed'].' '.$this->term('controls').' are assessed.';
    }

    private function priority_paragraph(): string
    {
        $findings = $this->a['findings'];
        $total = count($findings);

        if ($total === 0) {
            return '';
        }

        $high = array_values(array_filter($findings, function ($finding) {
            return $finding['priority'] === 'High';
        }));

        $text = 'Each gap has been given a remediation priority derived from its result, the exposure of the area it covers and the share of '.$this->term('objectives').' still open; this is a planning aid rather than an assessed risk rating.';

        if (count($high) === 0) {
            $text .= ' None of the '.$total.' '.($total === 1 ? 'gap is' : 'gaps are').' rated high priority.';
        } else {
            $topics = array();
            foreach ($high as $finding) {
                $topics[$finding['topic']] = ($topics[$finding['topic']] ?? 0) + 1;
            }
            arsort($topics);
            $names = array_map('strtolower', array_slice(array_keys($topics), 0, 3));
            $text .= ' '.count($high).' of the '.$total.' '.($total === 1 ? 'gap is' : 'gaps are').' high priority'.(count($high) > 1 && count($names) > 0 ? ', mostly in '.self::join_list($names) : '').'.';
        }

        $initiatives = $this->initiatives();
        if (count($initiatives) > 0) {
            $immediate = count(array_filter($initiatives, function ($initiative) {
                return $initiative['phase'] === ReportAnalysis::PHASES[0];
            }));
            $text .= ' The response is consolidated into '.count($initiatives).' remediation '.(count($initiatives) === 1 ? 'initiative' : 'initiatives')
                .($immediate > 0 ? ', '.$immediate.' of which '.($immediate === 1 ? 'is an immediate priority' : 'are immediate priorities') : '')
                .(count($initiatives) === 1 ? '; it is set out under Strategic recommendations.' : '; the most significant are set out under Strategic recommendations.');
        }

        return $text;
    }

    /* -------------------------------------------------------------- scope */

    private function scope(): array
    {
        $a = $this->a;
        $objective_total = 0;
        $objective_answered = 0;

        foreach ($a['controls'] as $control) {
            foreach ($control['objectives'] as $objective) {
                $objective_total++;
                if ($objective['result'] !== 'Not Assessed') {
                    $objective_answered++;
                }
            }
        }

        $coverage = $a['total'].' '.$this->term('controls').' in '.count($a['families']).' '.(count($a['families']) === 1 ? $this->term('family') : $this->term('families'));
        if ($objective_total > 0) {
            $coverage .= ', with '.$objective_total.' '.$this->term('objectives');
        }
        $coverage .= '. '.$a['assessed'].' of '.$a['total'].' '.$this->term('controls').' assessed ('.self::pct($a['assessed'], $a['total']).')';
        if ($objective_total > 0) {
            $coverage .= '; '.$objective_answered.' of '.$objective_total.' '.$this->term('objectives').' answered';
        }
        $coverage .= '.';

        $rows = array(
            array(
                'Framework',
                $this->framework_name()
            ),
            array(
                'Assessment period',
                $a['period']
            ),
            array(
                'Scope',
                isset($a['assessor']['assessment_scope'])
                    ? 'As recorded by the assessor: '.$a['assessor']['assessment_scope']
                    : 'Not recorded; the systems, locations and services in scope need to be confirmed with the assessor.'
            ),
            array(
                'Method',
                isset($a['assessor']['assessment_method'])
                    ? 'As recorded by the assessor: '.$a['assessor']['assessment_method']
                    : 'Not recorded; results were entered per '.$this->term('control').($objective_total > 0 ? ' and '.$this->term('objective') : '').', and the techniques used (interviews, document review, testing) need to be confirmed with the assessor.'
            ),
            array(
                'Coverage',
                $coverage.' '.$this->evidence_sentence()
            )
        );

        $basis = 'Counts, the implementation score and evidence coverage are calculated from the answers recorded for each '.$this->term('control')
            .($objective_total > 0 ? ' and '.$this->term('objective') : '').'. '
            .'Themes, remediation priorities and recommendations are derived from those results using general security practice; they interpret the record and add no facts about '.$this->client_name().'. '
            .'The implementation score counts implemented '.$this->term('controls').' in full and partially implemented ones at half, over the assessed '.$this->term('controls').' that apply; it measures progress and is not a compliance opinion or certification result. '
            .'Remediation priority is derived from each gap\'s result, the exposure of its area and the share of '.$this->term('objectives').' still open; it is not an assessed risk rating. '
            .'Text recorded by the assessor is labelled as such.';

        if (isset($a['executive_ai']) || isset($a['patterns_ai']) || isset($a['initiatives_ai'])) {
            $basis .= ' Some narrative passages were drafted with AI assistance from the same recorded results.';
        }

        $rows[] = array(
            'Basis of conclusions',
            $basis
        );

        return array(
            self::h1('Scope and methodology'),
            self::text('What was assessed, how, and the basis on which the conclusions in this summary rest.'),
            array(
                'kind' => 'kv',
                'value' => array(
                    'rows' => array_values($rows)
                )
            ),
            array(
                'kind' => 'panel',
                'value' => array(
                    'title' => 'Limitations',
                    'tone' => 'neutral',
                    'body' => $this->limitations($objective_total - $objective_answered)
                )
            )
        );
    }

    private function limitations(int $unanswered): array
    {
        $a = $this->a;
        $lines = array(
            'Results reflect the position recorded for the assessment period ('.$a['period'].') and may change as '.$this->term('controls').' are implemented or modified.'
        );

        if ($a['counts']['Not Assessed'] > 0) {
            $lines[] = $a['counts']['Not Assessed'].' '.$this->term('controls').' have not been assessed; conclusions cover only the '.$a['assessed'].' that have.';
        }

        if ($unanswered > 0) {
            $lines[] = $unanswered.' '.($unanswered === 1 ? $this->term('objective').' has' : $this->term('objectives').' have').' not been answered.';
        }

        $scored = $a['assessed'] - $a['counts']['Not Applicable'];
        if ($scored > 0 && $a['with_evidence'] === 0) {
            $lines[] = 'No evidence is linked to the assessed '.$this->term('controls').', so the results rest on the recorded answers and have not been validated against supporting documentation.';
        } elseif ($scored > $a['with_evidence']) {
            $lines[] = ($scored - $a['with_evidence']).' of '.$scored.' assessed '.$this->term('controls').' have no linked evidence.';
        }

        $without = count(array_filter($a['gaps'], function ($gap) {
            return !isset($gap['assessor']['notes']) && !isset($gap['assessor']['gap_description']);
        }));
        if ($without > 0) {
            $lines[] = 'No assessor observation is recorded for '.(count($a['gaps']) === 1 ? 'the gap' : ($without === count($a['gaps']) ? 'any of the '.$without.' gaps' : $without.' of the '.count($a['gaps']).' gaps')).'; findings are described from the recorded results.';
        }

        $lines[] = 'This summary is not an audit opinion, attestation or certification.';

        return $lines;
    }

    /* ---------------------------------------------------------- dashboard */

    private function dashboard(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $gaps = $c['Partially Implemented'] + $c['Not Implemented'];
        $scored = $a['assessed'] - $c['Not Applicable'];
        $priorities = $this->priority_counts();
        $initiatives = $this->initiatives();

        $metrics = array(
            array(
                'value' => $a['score'] === null ? 'Not scored' : $a['score'].'%',
                'label' => 'Implementation score',
                'sub' => 'Progress measure, not a compliance opinion',
                'tone' => 'brand'
            ),
            array(
                'value' => $a['assessed'].' of '.$a['total'],
                'label' => ucfirst($this->term('controls')).' assessed',
                'sub' => self::pct($a['assessed'], $a['total']).' complete',
                'tone' => $a['assessed'] === $a['total'] ? 'positive' : 'warning'
            ),
            array(
                'value' => (string) $c['Implemented'],
                'label' => 'Fully implemented',
                'sub' => $scored > 0 ? self::pct($c['Implemented'], $scored).' of those scored' : '',
                'tone' => 'positive'
            ),
            array(
                'value' => (string) $gaps,
                'label' => 'Not fully in place',
                'sub' => $c['Partially Implemented'].' partial, '.$c['Not Implemented'].' not implemented',
                'tone' => $gaps > 0 ? 'warning' : 'positive'
            ),
            array(
                'value' => $scored > 0 ? self::pct($a['with_evidence'], $scored) : '0%',
                'label' => 'Evidence coverage',
                'sub' => $a['with_evidence'].' of '.$scored.' with linked evidence',
                'tone' => $scored > 0 && $a['with_evidence'] * 2 >= $scored ? 'positive' : ($a['with_evidence'] === 0 ? 'critical' : 'warning')
            ),
            array(
                'value' => (string) $priorities['High'],
                'label' => 'High-priority findings',
                'sub' => 'Derived; '.$priorities['Medium'].' medium, '.$priorities['Low'].' low',
                'tone' => $priorities['High'] > 0 ? 'critical' : 'positive'
            ),
            array(
                'value' => (string) count($initiatives),
                'label' => 'Remediation initiatives',
                'sub' => 'Consolidated from the gaps',
                'tone' => 'brand'
            )
        );

        if ($c['Not Assessed'] > 0) {
            $metrics[] = array(
                'value' => (string) $c['Not Assessed'],
                'label' => 'Not yet assessed',
                'sub' => 'Excluded from the score',
                'tone' => 'neutral'
            );
        } elseif ($c['Not Applicable'] > 0) {
            $metrics[] = array(
                'value' => (string) $c['Not Applicable'],
                'label' => 'Not applicable',
                'sub' => 'Excluded from the score',
                'tone' => 'neutral'
            );
        }


        $blocks = array(
            self::h1('Results at a glance'),
            array(
                'kind' => 'metrics',
                'value' => array(
                    'items' => $metrics
                )
            ),
            self::h2('Distribution of results')
        );

        $segments = array();
        foreach (ReadyReports::RESULTS as $result) {
            if ($c[$result] > 0) {
                $segments[] = array(
                    'label' => $result,
                    'value' => $c[$result],
                    'color' => ReadyReports::COLORS[$result]
                );
            }
        }
        $blocks[] = array(
            'kind' => 'stackbar',
            'value' => array(
                'segments' => $segments
            )
        );


        $scored_families = array();
        foreach ($a['families'] as $name => $family) {
            if ($family['score'] !== null) {
                $scored_families[$name] = $family;
            }
        }

        $family_title = 'Implementation score by '.$this->term('family');

        if (count($scored_families) >= 3) {
            uasort($scored_families, function ($x, $y) {
                return $y['score'] <=> $x['score'];
            });
            $bars = array();
            foreach ($scored_families as $name => $family) {
                $bars[] = array(
                    'label' => $name,
                    'value' => $family['score'],
                    'max' => 100,
                    'text' => $family['score'].'%',
                    'tone' => $family['score'] >= 75 ? 'positive' : ($family['score'] >= 50 ? 'brand' : 'warning')
                );
            }
            $blocks[] = self::h2(ucfirst($family_title));
            $blocks[] = array(
                'kind' => 'bars',
                'value' => array(
                    'items' => $bars
                )
            );
        } elseif (count($a['families']) > 1) {
            $blocks[] = self::h2(ucfirst($family_title));
            $blocks[] = self::text('Only '.count($scored_families).' of the '.count($a['families']).' '.$this->term('families').' '.(count($scored_families) === 1 ? 'has' : 'have').' scored results so far'
                .(count($scored_families) > 0 ? ' ('.self::join_list(array_map(function ($name) use ($scored_families) {
                    return $name.', '.$scored_families[$name]['score'].'%';
                }, array_keys($scored_families))).')' : '')
                .'. A comparison across '.$this->term('families').' becomes meaningful once more of the assessment is complete.');
        }


        return $blocks;
    }

    private function priority_counts(): array
    {
        $counts = array(
            'High' => 0,
            'Medium' => 0,
            'Low' => 0
        );

        foreach ($this->a['findings'] as $finding) {
            $counts[$finding['priority']]++;
        }

        return $counts;
    }

    /* ------------------------------------------------------- key findings */

    private function key_findings(): array
    {
        $ai = array_slice($this->a['executive_ai']['key_findings'] ?? array(), 0, self::KEY_FINDINGS + 1);

        if (count($ai) > 0) {
            $blocks = array(
                self::h1('Key findings')
            );
            foreach ($ai as $entry) {
                $body = $entry['explanation'];
                if (count($entry['refs'] ?? array()) > 0) {
                    $body .= "\n\nRelated ".$this->term('controls').': '.implode(', ', $entry['refs']).'.';
                }
                $blocks[] = array(
                    'kind' => 'panel',
                    'value' => array(
                        'title' => $entry['title'],
                        'tone' => 'warning',
                        'body' => $body
                    )
                );
            }
            return $blocks;
        }

        $selected = $this->select_findings();

        if (count($selected) === 0) {
            return array();
        }

        $total = count($this->a['findings']);
        $blocks = array(
            self::h1('Key findings')
        );

        foreach ($selected as $finding) {
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => $finding['ref'].'  '.$finding['control']['title'].' ('.$finding['control']['ref'].')',
                    'tone' => self::PRIORITY_TONES[$finding['priority']],
                    'body' => $this->finding_body($finding)
                )
            );
        }

        if (count($selected) < $total) {
            $blocks[] = self::note('These '.count($selected).' findings are the most significant of the '.$total.' gaps, chosen by remediation priority so that each represents a different area. Every gap is set out in the Gap Analysis and Risk Assessment reports.');
        }

        return $blocks;
    }

    private function select_findings(): array
    {
        $selected = array();
        $topics = array();

        foreach ($this->a['findings'] as $finding) {
            if (count($selected) >= self::KEY_FINDINGS) {
                break;
            }
            if (isset($topics[$finding['topic']])) {
                continue;
            }
            $topics[$finding['topic']] = true;
            $selected[$finding['ref']] = $finding;
        }

        foreach ($this->a['findings'] as $finding) {
            if (count($selected) >= min(3, count($this->a['findings']))) {
                break;
            }
            $selected[$finding['ref']] = $finding;
        }

        return array_values($selected);
    }

    private function finding_body(array $finding): string
    {
        $control = $finding['control'];
        $all = count($control['objectives']);
        $answered_open = array_values(array_filter($control['open'], function ($objective) {
            return $objective['result'] !== 'Not Assessed';
        }));

        $paragraphs = array();

        if (isset($control['assessor']['gap_description'])) {
            $paragraphs[] = 'Assessor observation: '.$control['assessor']['gap_description'];
        }

        $first = 'Assessed as '.strtolower($control['result']);
        if ($all > 0 && count($answered_open) > 0) {
            $first .= $all === 1
                ? '; its single '.$this->term('objective').' is not met.'
                : ': '.count($answered_open).' of '.$all.' '.$this->term('objectives').' '.(count($answered_open) === 1 ? 'is' : 'are').' not fully met.';
            $missing = array();
            $length = 0;
            foreach ($answered_open as $objective) {
                $line = self::lower_first(rtrim($objective['description'] !== '' ? $objective['description'] : $objective['title'], '.'));
                if (count($missing) > 0 && $length + strlen($line) > 200) {
                    break;
                }
                $missing[] = $line;
                $length += strlen($line);
            }
            $first .= ' Not yet in place: '.implode('; ', $missing).(count($answered_open) > count($missing) ? '; and '.(count($answered_open) - count($missing)).' more' : '').'.';
        } elseif ($all > 0 && count($control['open']) > 0) {
            $unanswered = count($control['open']);
            $first .= ': '.($all - $unanswered).' of '.$all.' '.$this->term('objectives').' answered as met or partly met, and '.$unanswered.' not yet answered, so the extent of the gap needs to be confirmed. Still to be assessed: '
                .self::lower_first(rtrim($control['open'][0]['description'] !== '' ? $control['open'][0]['description'] : $control['open'][0]['title'], '.'))
                .($unanswered > 1 ? '; and '.($unanswered - 1).' more' : '').'.';
        } else {
            $first .= '.';
            if ($control['description'] !== '' && $control['description'] !== $control['title']) {
                $first .= ' The '.$this->term('control').' expects that '.self::lower_first(rtrim($control['description'], '.')).'.';
            }
            if ($all === 0) {
                $first .= ' No '.$this->term('objectives').' are defined to show which part is missing, so the specific deficiency needs to be confirmed with the assessor.';
            }
        }
        $paragraphs[] = $first;

        $consequence = null;
        if (isset($control['assessor']['business_impact'])) {
            $consequence = 'Business impact (as recorded by the assessor): '.$control['assessor']['business_impact'];
        } elseif (isset($finding['risk_scenarios_ai'][0])) {
            $consequence = 'Why it matters: '.$finding['risk_scenarios_ai'][0]['consequence'];
        } else {
            $topic = ReportKnowledge::topic($control['topic']);
            if (isset($topic['consequences'][0])) {
                $consequence = 'Why it matters: '.$topic['consequences'][0];
            }
        }
        if ($consequence !== null) {
            $paragraphs[] = $consequence;
        }

        $meta = array(
            'Remediation priority: '.$finding['priority'].' (derived)'
        );
        if ($control['risk_rating'] !== null) {
            $meta[] = 'Assessed risk: '.$control['risk_rating'];
        }
        $initiative = $this->initiative_ids($control['ref']);
        if ($initiative !== null) {
            $meta[] = 'Addressed by '.$initiative;
        }
        if ($consequence !== null) {
            $paragraphs[count($paragraphs) - 1] .= "\n".implode(' · ', $meta);
        } else {
            $paragraphs[] = implode(' · ', $meta);
        }

        return implode("\n\n", $paragraphs);
    }

    /* ---------------------------------------------------------- strengths */

    private function strengths(): array
    {
        $blocks = array(
            self::h1('Areas of strength')
        );

        $ai = $this->ai_list('strengths');

        if ($ai !== null) {
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => '',
                    'tone' => 'positive',
                    'body' => $ai
                )
            );
            return $blocks;
        }

        $a = $this->a;
        $c = $a['counts'];
        $lines = array();

        if (isset($a['assessor']['strengths_text'])) {
            $lines[] = 'Assessor observation: '.$a['assessor']['strengths_text'];
        }

        $implemented = array_values(array_filter($a['controls'], function ($control) {
            return $control['result'] === 'Implemented';
        }));
        if (count($implemented) > 0) {
            $named = array();
            foreach (array_slice($implemented, 0, 6) as $control) {
                $named[] = $control['title'].' ('.$control['ref'].')';
            }
            $lines[] = count($implemented).' '.(count($implemented) === 1 ? $this->term('control').' is' : $this->term('controls').' are').' recorded as fully implemented: '
                .self::join_list($named).(count($implemented) > 6 ? ' and '.(count($implemented) - 6).' others' : '').'.';
        }

        foreach ($a['families'] as $name => $family) {
            $assessed = count($family['controls']) - $family['counts']['Not Assessed'] - $family['counts']['Not Applicable'];
            if ($family['score'] !== null && $family['score'] >= 75 && $assessed >= 2 && $family['counts']['Not Implemented'] === 0) {
                $lines[] = $name.' has an implementation score of '.$family['score'].'%, with '.$family['counts']['Implemented'].' of '.$assessed.' assessed '.$this->term('controls').' fully implemented and none found absent.';
            }
        }

        $scored = $a['assessed'] - $c['Not Applicable'];
        if ($scored >= 5 && $c['Partially Implemented'] > 0 && $c['Not Implemented'] * 10 <= $scored) {
            $lines[] = 'Only '.$c['Not Implemented'].' of '.$scored.' assessed '.$this->term('controls').' '.($c['Not Implemented'] === 1 ? 'was' : 'were').' found absent; the '.$c['Partially Implemented'].' partially implemented ones already have elements in place for remediation to build on.';
        }

        if ($scored > 0 && $a['with_evidence'] * 5 >= $scored * 4) {
            $lines[] = 'Evidence is linked for '.$a['with_evidence'].' of '.$scored.' assessed '.$this->term('controls').', which supports the reliability of the recorded results.';
        }

        if (count($lines) === 0) {
            $reason = $a['assessed'] * 2 < $a['total']
                ? 'only '.$a['assessed'].' of '.$a['total'].' '.$this->term('controls').' have been assessed and none of them is fully implemented'
                : 'no '.$this->term('control').' or '.$this->term('family').' stands out as fully in place';
            $blocks[] = self::text('The recorded results do not yet support identifying specific strengths: '.$reason.'. Strengths will be reported once they are supported by assessed results.');
            return $blocks;
        }

        $blocks[] = array(
            'kind' => 'panel',
            'value' => array(
                'title' => '',
                'tone' => 'positive',
                'body' => $lines
            )
        );

        return $blocks;
    }

    /* ------------------------------------------------------- improvements */

    private function improvements(): array
    {
        $a = $this->a;

        if (count($a['gaps']) === 0) {
            return array();
        }

        $blocks = array(
            self::h1('Areas requiring improvement')
        );

        $ai = $this->ai_list('improvements');

        if ($ai !== null) {
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => '',
                    'tone' => 'warning',
                    'body' => $ai
                )
            );
        } else {
            if (isset($a['assessor']['concerns_text'])) {
                $blocks[] = array(
                    'kind' => 'panel',
                    'value' => array(
                        'title' => 'Assessor observation',
                        'tone' => 'brand',
                        'body' => $a['assessor']['concerns_text']
                    )
                );
            }

            $concentrated = array();
            foreach (array_slice($a['themes'], 0, 3) as $theme) {
                $concentrated[] = $theme['name'].': '.count($theme['refs']).' '.$this->term('controls').' not fully in place'
                    .(count($theme['families']) > 1 ? ', spanning '.self::join_list($theme['families']) : ', in '.$theme['families'][0]).'.';
            }

            if (count($concentrated) === 0) {
                $families = array_filter($a['families'], function ($family) {
                    return count($family['gaps']) > 0;
                });
                uasort($families, function ($x, $y) {
                    $rank = count($y['gaps']) <=> count($x['gaps']);
                    return $rank !== 0 ? $rank : ($x['score'] <=> $y['score']);
                });
                foreach (array_slice($families, 0, 3, true) as $name => $family) {
                    $concentrated[] = $name.': '.count($family['gaps']).' of '.count($family['controls']).' '.$this->term('controls').' not fully in place'.($family['score'] !== null ? ' (implementation score '.$family['score'].'%)' : '').'.';
                }
            }

            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => 'Where the gaps concentrate',
                    'tone' => 'warning',
                    'body' => $concentrated
                )
            );
        }

        $recurring = array();
        if ($ai !== null) {
            return $blocks;
        }
        foreach (array_slice($a['patterns_ai'] ?? array(), 0, 3) as $pattern) {
            $recurring[] = $pattern['label'].': '.self::first_sentence($pattern['description']);
        }
        if (count($recurring) === 0) {
            foreach (array_slice($a['patterns'], 0, 3) as $pattern) {
                $recurring[] = $this->pattern_line($pattern);
            }
        }

        if (count($recurring) > 0) {
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => 'Recurring weaknesses',
                    'tone' => 'warning',
                    'body' => $recurring
                )
            );
        }

        return $blocks;
    }

    private function pattern_line(array $pattern): string
    {
        $phrases = array(
            'documentation' => 'depend on written policies, procedures or plans that are missing or incomplete',
            'review' => 'call for periodic review or reassessment that is not yet operating consistently',
            'monitoring' => 'concern monitoring, logging or detection, limiting the ability to spot problems as they occur',
            'training' => 'concern the training, awareness or competence of personnel',
            'approval' => 'concern formal authorization or approval steps',
            'third_party' => 'extend to vendors, contractors or other third parties'
        );
        $count = count($pattern['points']).' open '.$this->term('objectives').' across '.count($pattern['controls']).' '.$this->term('controls');

        if (!isset($phrases[$pattern['key']])) {
            return $pattern['label'].': '.$pattern['sentence'];
        }

        return $pattern['label'].': '.$count.' '.$phrases[$pattern['key']].'.';
    }

    /* ---------------------------------------------------- recommendations */

    private function recommendations(): array
    {
        $ai = $this->a['executive_ai']['strategic_recommendations'] ?? array();
        $blocks = array(
            self::h1('Strategic recommendations')
        );

        if (count($ai) > 0) {
            $number = 0;
            foreach ($ai as $entry) {
                $number++;
                $body = $entry['rationale'];
                if (count($entry['refs'] ?? array()) > 0) {
                    $body .= "\n\nRelated ".$this->term('controls').': '.implode(', ', $entry['refs']).'.';
                }
                $blocks[] = array(
                    'kind' => 'panel',
                    'value' => array(
                        'title' => $number.'. '.$entry['title'],
                        'tone' => 'brand',
                        'body' => $body
                    )
                );
            }
            return $blocks;
        }

        $extras = $this->program_recommendations();
        $initiatives = array_values(array_filter($this->initiatives(), function ($initiative) {
            return empty($initiative['remainder']);
        }));

        usort($initiatives, function ($x, $y) {
            $rank = ReportAnalysis::PRIORITY_RANK[$y['priority']] <=> ReportAnalysis::PRIORITY_RANK[$x['priority']];
            if ($rank !== 0) {
                return $rank;
            }
            return count($y['controls']) <=> count($x['controls']);
        });

        $take = max(min(3, count($initiatives)), self::RECOMMENDATIONS - count($extras));
        $chosen = array_slice($initiatives, 0, $take);

        if (count($chosen) + count($extras) === 0) {
            return array();
        }

        $number = 0;
        foreach ($chosen as $initiative) {
            $number++;
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => $number.'. '.$initiative['title'],
                    'tone' => 'brand',
                    'body' => $this->initiative_body($initiative)
                )
            );
        }

        foreach ($extras as $extra) {
            $number++;
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => $number.'. '.$extra[0],
                    'tone' => 'brand',
                    'body' => $extra[1]
                )
            );
        }

        return $blocks;
    }

    private function initiative_body(array $initiative): string
    {
        $gaps = $initiative['gaps'];
        $families = array_values(array_unique(array_column($gaps, 'family')));
        $named = array();

        usort($gaps, function ($x, $y) {
            return ReportAnalysis::PRIORITY_RANK[$y['priority']] <=> ReportAnalysis::PRIORITY_RANK[$x['priority']];
        });

        foreach (array_slice($gaps, 0, 2) as $gap) {
            $named[] = $gap['title'].' ('.$gap['ref'].')';
        }

        $count = count($initiative['controls']);
        $text = 'Addresses '.$count.' '.($count === 1 ? $this->term('control') : $this->term('controls')).' not fully in place in '.self::join_list(array_slice($families, 0, 3)).(count($families) > 3 ? ' and '.(count($families) - 3).' other '.$this->term('families') : '');
        $text .= $count > 2 ? '.' : ': '.self::join_list($named).'.';

        $outcome = $initiative['outcome'] ?? null;
        if ($outcome !== null && $outcome !== '') {
            $text .= ' Intended outcome: '.self::lower_first($outcome);
        }

        $meta = $initiative['id'].' · '.$initiative['phase'].' · Remediation priority: '.$initiative['priority'].' (derived)';
        if ($initiative['owner'] !== null) {
            $meta .= ' · Owner: '.$initiative['owner'];
        }

        return $text."\n".$meta.'.';
    }

    /** Programme-level recommendations the record itself supports: finish the assessment, substantiate it. */
    private function program_recommendations(): array
    {
        $a = $this->a;
        $out = array();

        if ($a['counts']['Not Assessed'] > 0) {
            $out[] = array(
                'Complete the assessment before relying on the results',
                $a['counts']['Not Assessed'].' of '.$a['total'].' '.$this->term('controls').' have not been assessed. Completing them will confirm the full extent of the gaps, make the implementation score representative and allow remediation to be prioritized across the whole framework rather than the part assessed so far.',
                'complete the remaining'
            );
        }

        $scored = $a['assessed'] - $a['counts']['Not Applicable'];
        if ($scored > 0 && $a['with_evidence'] * 2 < $scored) {
            $out[] = array(
                'Substantiate the recorded results with evidence',
                ($scored === 1 ? 'The assessed '.$this->term('control').' has no' : ($a['with_evidence'] === 0 ? 'None of the '.$scored.' assessed '.$this->term('controls').' have' : 'Only '.$a['with_evidence'].' of the '.$scored.' assessed '.$this->term('controls').' '.($a['with_evidence'] === 1 ? 'has' : 'have'))).' linked evidence. Asking control owners to supply policies, records and system output for each '.$this->term('control').' would allow the results to be validated and relied on for customers, auditors or regulators.',
                'supporting evidence'
            );
        }

        return $out;
    }

    /* ----------------------------------------------------- considerations */

    private function considerations(): array
    {
        $ai = $this->ai_list('management_considerations');
        $items = array();

        if ($ai !== null) {
            $items = $ai;
        } else {
            $covered = array();
            foreach ($this->program_recommendations() as $extra) {
                $covered[] = $extra[2];
            }
            foreach ($this->a['narratives']['considerations'] as $line) {
                if ($line === ($this->a['assessor']['decisions_text'] ?? null)) {
                    $items[] = 'Recorded by the assessor: '.$line;
                    continue;
                }
                if (($this->a['executive_ai']['strategic_recommendations'] ?? array()) === array() && self::mentions($line, $covered)) {
                    continue;
                }
                $items[] = $line;
            }
        }

        if (count($items) === 0) {
            return array();
        }

        return array(
            self::h1('Management considerations'),
            array(
                'kind' => 'panel',
                'value' => array(
                    'title' => 'Decisions and actions for management',
                    'tone' => 'brand',
                    'body' => $items
                )
            )
        );
    }

    /* ------------------------------------------------------------ helpers */

    /** The initiatives in use: the AI remediation plan when it exists, else the calculated one. */
    private function initiatives(): array
    {
        if ($this->plan === null) {
            $this->plan = ReportRemediationPlan::plan_initiatives($this->a);
        }

        return $this->plan;
    }

    /** The Remediation Plan initiative ids covering one control, joined. */
    private function initiative_ids(string $ref): ?string
    {
        $ids = ReportRemediationPlan::initiative_of($this->initiatives())[$ref] ?? array();

        return count($ids) > 0 ? implode(', ', $ids) : null;
    }

    private function ai_list(string $key): ?array
    {
        $list = $this->a['executive_ai'][$key] ?? null;

        if (!is_array($list)) {
            return null;
        }

        $list = array_values(array_filter($list, function ($line) {
            return is_string($line) && trim($line) !== '';
        }));

        return count($list) > 0 ? $list : null;
    }

    private function client_name(): string
    {
        return $this->a['client'] !== '' ? $this->a['client'] : 'the organization';
    }

    private function framework_name(): string
    {
        $definition = $this->a['definition'] ?? array();
        $name = $this->a['standard'];
        $full = trim((string) ($definition['full_name'] ?? ''));

        if ($full !== '' && strcasecmp($full, $name) !== 0) {
            $name .= "\n".$full;
        }

        return $name;
    }

    private function evidence_sentence(): string
    {
        $a = $this->a;
        $files = count($a['evidence_files']);
        $scored = $a['assessed'] - $a['counts']['Not Applicable'];

        if ($files === 0) {
            return 'No evidence files are linked to this assessment.';
        }

        return $files.' evidence '.($files === 1 ? 'file is' : 'files are').' linked, supporting '.$a['with_evidence'].' of '.$scored.' assessed '.$this->term('controls').' ('.self::pct($a['with_evidence'], max(1, $scored)).').';
    }

    private static function first_sentence(string $text): string
    {
        if (preg_match('/^.{40,}?[.!?](?=\s+[A-Z])/s', trim($text), $match) === 1) {
            return $match[0];
        }

        return trim($text);
    }

    private static function mentions(string $line, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (stripos($line, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    /** Lower-cases the first letter unless the word is an acronym (USB, MFA). */
    private static function lower_first(string $text): string
    {
        if (preg_match('/^[A-Z][a-z]/', $text) === 1) {
            return lcfirst($text);
        }

        return $text;
    }
}
