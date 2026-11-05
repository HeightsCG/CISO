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

    /** Characters above which a narrative paragraph is divided in two. */
    const LONG_PARAGRAPH = 700;

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
            $this->dashboard(),
            $this->key_findings(),
            $this->improvements(),
            $this->strengths(),
            $this->recommendations(),
            $this->considerations(),
            $this->scope()
        );
    }

    /* ----------------------------------------------------------- overview */

    private function overview(): array
    {
        $blocks = array(
            self::h1('Overall assessment')
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
            $blocks[] = self::text(implode("\n\n", self::readable($ai)));
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

        $blocks[] = self::text(implode("\n\n", self::readable($paragraphs)));

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
                .(count($initiatives) === 1 ? '; it is set out under Priority recommendations.' : '; the most significant are set out under Priority recommendations.');
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

        $rows = array_merge(array(
            array(
                'Framework',
                $this->framework_name()
            )
        ), array(
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
        ));

        $basis = array(
            'Counts, the implementation score and evidence coverage are calculated from the answers recorded for each '.$this->term('control').($objective_total > 0 ? ' and '.$this->term('objective') : '').'.',
            'The implementation score counts implemented '.$this->term('controls').' in full and partially implemented ones at half, over the assessed '.$this->term('controls').' that apply. It measures progress and is not a compliance opinion or certification result.',
            'Remediation priority is derived from each gap\'s result, the exposure of its area and the share of '.$this->term('objectives').' still open. It is a planning aid, not an assessed risk rating.',
            'Themes, implications and recommendations interpret the recorded results using general security practice and add no facts about '.$this->client_name().'. Text recorded by the assessor is labelled as such.'
        );

        if (isset($a['executive_ai']) || isset($a['patterns_ai']) || isset($a['initiatives_ai'])) {
            $basis[] = 'Some narrative passages were drafted with AI assistance from the same recorded results.';
        }

        $rows[] = array(
            'Basis of conclusions',
            $basis
        );

        return array(
            self::h1('Scope, basis and limitations'),
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
            'Results reflect the responses recorded up to the reporting cutoff and may change as '.$this->term('controls').' are implemented or modified.'
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

        $metrics = array(
            array(
                'value' => $a['score'] === null ? 'Not scored' : $a['score'].'%',
                'label' => 'Implementation score',
                'sub' => 'Progress measure, not a compliance opinion',
                'tone' => 'brand'
            )
        );

        if ($a['assessed'] < $a['total']) {
            $metrics[] = array(
                'value' => $a['assessed'].' of '.$a['total'],
                'label' => ucfirst($this->term('controls')).' assessed',
                'sub' => self::pct($a['assessed'], $a['total']).' complete; the rest are excluded from the score',
                'tone' => 'warning'
            );
        }

        $metrics[] = array(
            'value' => (string) $gaps,
            'label' => 'Not fully in place',
            'sub' => $c['Partially Implemented'].' partial, '.$c['Not Implemented'].' not implemented, of '.$scored.' scored',
            'tone' => $gaps > 0 ? 'warning' : 'positive'
        );
        $metrics[] = array(
            'value' => (string) $priorities['High'],
            'label' => 'High-priority gaps',
            'sub' => 'Derived; '.$priorities['Medium'].' medium, '.$priorities['Low'].' low',
            'tone' => $priorities['High'] > 0 ? 'critical' : 'positive'
        );
        $metrics[] = array(
            'value' => $scored > 0 ? self::pct($a['with_evidence'], $scored) : '0%',
            'label' => 'Evidence coverage',
            'sub' => $a['with_evidence'].' of '.$scored.' with linked evidence',
            'tone' => $scored > 0 && $a['with_evidence'] * 2 >= $scored ? 'positive' : ($a['with_evidence'] === 0 ? 'critical' : 'warning')
        );

        $blocks = array(
            self::h1('Results at a glance'),
            array(
                'kind' => 'metrics',
                'value' => array(
                    'items' => $metrics
                )
            )
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
        if (count($segments) > 1) {
            $blocks[] = self::h3('Recorded results');
            $blocks[] = array(
                'kind' => 'stackbar',
                'value' => array(
                    'segments' => $segments
                )
            );
        }

        return array_merge($blocks, $this->family_table());
    }

    /**
     * Where the gaps sit: one row per family with its results, score and
     * high-priority gaps, ordered so the families needing most attention come
     * first. A table, because the counts behind each score matter more to the
     * reader than a bar of near-equal lengths.
     */
    private function family_table(): array
    {
        $a = $this->a;

        $scored = count(array_filter($a['families'], function ($family) {
            return $family['score'] !== null;
        }));

        if (count($a['families']) < 2 || $scored < 2) {
            return array();
        }

        $high = array();
        foreach ($a['findings'] as $finding) {
            if ($finding['priority'] === 'High') {
                $family = $finding['domain'];
                $high[$family] = ($high[$family] ?? 0) + 1;
            }
        }

        $unassessed = $a['counts']['Not Assessed'] > 0;
        $names = array_keys($a['families']);
        usort($names, function ($x, $y) use ($a, $high) {
            $rank = ($high[$y] ?? 0) <=> ($high[$x] ?? 0);
            if ($rank !== 0) {
                return $rank;
            }
            $rank = count($a['families'][$y]['gaps']) <=> count($a['families'][$x]['gaps']);
            if ($rank !== 0) {
                return $rank;
            }
            return ($a['families'][$x]['score'] ?? 101) <=> ($a['families'][$y]['score'] ?? 101);
        });

        $rows = array();
        foreach ($names as $name) {
            $family = $a['families'][$name];
            $counts = $family['counts'];
            $row = array(
                $name,
                (string) count($family['controls']),
                (string) $counts['Implemented'],
                (string) $counts['Partially Implemented'],
                (string) $counts['Not Implemented']
            );
            if ($unassessed) {
                $row[] = (string) $counts['Not Assessed'];
            }
            $row[] = $family['score'] !== null ? $family['score'].'%' : '';
            $row[] = (string) ($high[$name] ?? 0);
            $rows[] = $row;
        }

        $head = array(
            ucfirst($this->term('family')),
            ucfirst($this->term('controls')),
            'Implemented',
            'Partial',
            'Not impl.'
        );
        $widths = array(
            36,
            11,
            11,
            9,
            10
        );
        if ($unassessed) {
            $head[] = 'Not assessed';
            $widths[] = 10;
            $widths[0] -= 10;
        }
        $head[] = 'Score';
        $widths[] = 9;
        $head[] = 'High priority';
        $widths[] = 14;

        return array(
            self::h3('Results by '.$this->term('family')),
            self::table($head, $widths, $rows)
        );
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
        $heading = self::h1('Key findings and themes');

        if (count($ai) > 0) {
            $blocks = array(
                $heading
            );
            foreach ($ai as $entry) {
                $body = implode("\n\n", self::readable(array(
                    $entry['explanation']
                )));
                if (count($entry['refs'] ?? array()) > 0) {
                    $body .= "\n\nRelated ".$this->term('controls').': '.implode(', ', array_slice($entry['refs'], 0, 6)).(count($entry['refs']) > 6 ? ' and '.(count($entry['refs']) - 6).' more' : '').'.';
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

        $total = count($this->a['findings']);
        $themes = $this->key_themes();

        if (count($themes) >= 2) {
            $blocks = array(
                $heading
            );
            $covered = 0;
            foreach ($themes as $theme) {
                $covered += count($theme['findings']);
                $blocks[] = array(
                    'kind' => 'panel',
                    'value' => array(
                        'title' => $theme['name'],
                        'tone' => $theme['high'] > 0 ? 'critical' : 'warning',
                        'body' => $this->theme_body($theme)
                    )
                );
            }
            if ($covered < $total) {
                $blocks[] = self::note('These themes account for '.$covered.' of the '.$total.' gaps. Every gap is set out individually in the Gap Analysis and Risk Assessment reports.');
            }
            return $blocks;
        }

        $selected = $this->select_findings();

        if (count($selected) === 0) {
            return array();
        }

        $blocks = array(
            $heading
        );

        foreach ($selected as $finding) {
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => $finding['control']['title'].' ('.$finding['control']['ref'].')',
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

    /**
     * The subject-area themes that carry the gaps, as key findings: those
     * with high-priority gaps first, then the largest.
     */
    private function key_themes(): array
    {
        $by_ref = array();
        foreach ($this->a['findings'] as $finding) {
            $by_ref[$finding['control']['ref']] = $finding;
        }

        $themes = array();
        foreach ($this->a['themes'] as $theme) {
            $findings = array();
            foreach ($theme['refs'] as $ref) {
                if (isset($by_ref[$ref])) {
                    $findings[] = $by_ref[$ref];
                }
            }
            if (count($findings) === 0) {
                continue;
            }
            usort($findings, function ($x, $y) {
                $rank = ReportAnalysis::PRIORITY_RANK[$y['priority']] <=> ReportAnalysis::PRIORITY_RANK[$x['priority']];
                if ($rank !== 0) {
                    return $rank;
                }
                return count($y['control']['open']) <=> count($x['control']['open']);
            });
            $themes[] = array(
                'topic' => $theme['topic'],
                'name' => $theme['name'],
                'families' => $theme['families'],
                'findings' => $findings,
                'high' => count(array_filter($findings, function ($finding) {
                    return $finding['priority'] === 'High';
                }))
            );
        }

        usort($themes, function ($x, $y) {
            $rank = $y['high'] <=> $x['high'];
            return $rank !== 0 ? $rank : count($y['findings']) <=> count($x['findings']);
        });

        return array_slice($themes, 0, self::KEY_FINDINGS);
    }

    /** A theme in three short parts: what is recorded, why it matters, how it is addressed. */
    private function theme_body(array $theme): string
    {
        $findings = $theme['findings'];
        $count = count($findings);
        $absent = count(array_filter($findings, function ($finding) {
            return $finding['status'] === 'Not Implemented';
        }));

        $text = $count.' '.($count === 1 ? $this->term('control').' is' : $this->term('controls').' are').' not fully in place'
            .(count($theme['families']) <= 2 ? ' in '.self::join_list($theme['families']) : ' across '.count($theme['families']).' '.$this->term('families'))
            .($absent > 0 ? ', '.($absent === $count ? ($count === 1 ? 'and it is' : 'all of them') : $absent.' of them').' not implemented at all' : ', each partly in place')
            .'.';

        $named = array();
        foreach (array_slice($findings, 0, 2) as $finding) {
            $control = $finding['control'];
            $all = count($control['objectives']);
            $open = count($control['open']);
            $named[] = $control['title'].' ('.$control['ref'].($finding['status'] === 'Not Implemented'
                ? ', not implemented'
                : ($all > 0 ? ', '.$open.' of '.$all.' '.$this->term('objectives').' open' : '')).')';
        }
        $text .= ' '.($count === 1 ? 'It is ' : 'The most significant are ').self::join_list($named).'.';

        $paragraphs = array(
            $text
        );

        $topic = ReportKnowledge::topic($theme['topic']);
        if (isset($topic['consequences'][0])) {
            $paragraphs[] = 'Why it matters: '.$topic['consequences'][0];
        }

        $split = array();
        foreach (array(
            'High',
            'Medium',
            'Low'
        ) as $priority) {
            $n = count(array_filter($findings, function ($finding) use ($priority) {
                return $finding['priority'] === $priority;
            }));
            if ($n > 0) {
                $split[] = $n.' '.strtolower($priority);
            }
        }
        $ids = array();
        foreach ($findings as $finding) {
            foreach (ReportRemediationPlan::initiative_of($this->initiatives())[$finding['control']['ref']] ?? array() as $id) {
                $ids[$id] = true;
            }
        }
        $ids = array_keys($ids);
        sort($ids);
        $paragraphs[count($paragraphs) - 1] .= "\n".'Remediation priority (derived): '.implode(', ', $split).(count($ids) > 0 ? ' · Addressed by '.implode(', ', array_slice($ids, 0, 4)).(count($ids) > 4 ? ' and others' : '') : '');

        return implode("\n\n", $paragraphs);
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
            $consequence = 'Potential exposure: '.$finding['risk_scenarios_ai'][0]['consequence'];
        } else {
            $topic = ReportKnowledge::topic($control['topic']);
            if (isset($topic['consequences'][0])) {
                $consequence = 'Potential exposure: '.$topic['consequences'][0];
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

        if ($ai === null && is_array($this->a['executive_ai']['strengths'] ?? null)) {
            $blocks[] = self::text('The recorded results do not yet support identifying specific strengths. Strengths will be reported once they are supported by assessed results.');
            return $blocks;
        }

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

    /**
     * What the results mean for the organization: the stored AI view of the
     * areas requiring improvement when it exists; otherwise implications
     * worked out from the record (the character of the gaps, the
     * consequences in the areas with high-priority gaps, the recurring
     * weakness and the strength of the evidence base).
     */
    private function improvements(): array
    {
        $a = $this->a;

        if (count($a['gaps']) === 0) {
            return array();
        }

        $blocks = array(
            self::h1('Organizational implications')
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
            return $blocks;
        }

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

        $blocks[] = array(
            'kind' => 'panel',
            'value' => array(
                'title' => '',
                'tone' => 'warning',
                'body' => $this->implications()
            )
        );

        return $blocks;
    }

    private function implications(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $lines = array();

        if ($c['Partially Implemented'] >= 3 && $c['Partially Implemented'] >= 3 * $c['Not Implemented']) {
            $lines[] = 'Most gaps are partial rather than absent, so the work ahead is largely completing, formalizing and evidencing practices that already exist in part, rather than building new capability.';
        } elseif ($c['Not Implemented'] >= 3 && $c['Not Implemented'] > $c['Partially Implemented']) {
            $lines[] = 'More '.$this->term('controls').' are absent than partly in place, so parts of the security program still need to be established before they can be refined.';
        }

        $used = array();
        foreach ($this->key_themes() as $theme) {
            if ($theme['high'] === 0 || count($used) >= 2) {
                continue;
            }
            $topic = ReportKnowledge::topic($theme['topic']);
            $line = $topic['consequences'][1] ?? ($topic['consequences'][0] ?? null);
            if ($line === null || isset($used[$line])) {
                continue;
            }
            $used[$line] = true;
            $lines[] = $theme['name'].': '.self::lower_first($line);
        }

        if (count($a['patterns']) > 0) {
            $lines[] = 'A weakness recurs across '.$this->term('families').' rather than sitting in one area. '.$this->pattern_line($a['patterns'][0]).' Fixing it once, at its root, closes several gaps together.';
        }

        $scored = $a['assessed'] - $c['Not Applicable'];
        if ($scored > 0 && $a['with_evidence'] * 2 < $scored) {
            $lines[] = 'With '.($a['with_evidence'] === 0 ? 'no evidence' : 'evidence for only '.$a['with_evidence'].' of '.$scored.' '.$this->term('controls')).' linked, '.$this->client_name().' could not yet demonstrate the recorded position to customers, auditors or regulators, including for the '.$this->term('controls').' recorded as implemented.';
        }

        $caution = $this->coverage_caution();
        if ($caution !== '') {
            $lines[] = 'The picture is provisional: '.self::lower_first(self::first_sentence($caution));
        }

        return $lines;
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
            self::h1('Priority recommendations')
        );

        if (count($ai) > 0) {
            $number = 0;
            foreach ($ai as $entry) {
                $number++;
                $body = $entry['rationale'];
                if (count($entry['refs'] ?? array()) > 0) {
                    $body .= "\n\nRelated ".$this->term('controls').': '.implode(', ', array_slice($entry['refs'], 0, 6)).(count($entry['refs']) > 6 ? ' and '.(count($entry['refs']) - 6).' more' : '').'.';
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

    /**
     * Paragraphs kept to a readable length: one longer than LONG_PARAGRAPH
     * characters is divided at the sentence boundary nearest its middle.
     * The wording is unchanged.
     */
    private static function readable(array $paragraphs): array
    {
        $out = array();

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if (mb_strlen($paragraph) <= self::LONG_PARAGRAPH) {
                $out[] = $paragraph;
                continue;
            }
            $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $paragraph);
            if ($sentences === false || count($sentences) < 4) {
                $out[] = $paragraph;
                continue;
            }
            $half = mb_strlen($paragraph) / 2;
            $length = 0;
            $first = array();
            foreach ($sentences as $index => $sentence) {
                if ($index > 0 && $length + mb_strlen($sentence) / 2 > $half) {
                    break;
                }
                $first[] = $sentence;
                $length += mb_strlen($sentence) + 1;
            }
            $out[] = implode(' ', $first);
            $out[] = implode(' ', array_slice($sentences, count($first)));
        }

        return $out;
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
