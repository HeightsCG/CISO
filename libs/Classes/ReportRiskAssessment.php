<?php
/**
 * The cybersecurity risk assessment: the flagship deliverable that
 * interprets the recorded results as exposure. Executive summary, scope and
 * method, an honest rating methodology (derived remediation priority, never
 * an invented likelihood or impact), the consolidated findings register,
 * an analysis of each domain followed by its finding cards, prioritized
 * recommendations, management response and sign-off, and supporting
 * references. Stored AI interpretation is preferred where it exists and is
 * marked as interpretation; assessor-recorded text is always attributed.
 */
class ReportRiskAssessment extends ReportBuilder
{
    const VERSION = '1.0';

    const RESULT_COLORS = array(
        'Implemented' => '#067647',
        'Partially Implemented' => '#B54708',
        'Not Implemented' => '#B42318',
        'Not Applicable' => '#7A8794',
        'Not Assessed' => '#B8C2CD'
    );

    const RESULT_LABELS = array(
        'Implemented' => 'Implemented',
        'Partially Implemented' => 'Partially implemented',
        'Not Implemented' => 'Not implemented',
        'Not Applicable' => 'Not applicable',
        'Not Assessed' => 'Not assessed'
    );

    const OBJECTIVE_STATES = array(
        'Not Implemented' => 'not met',
        'Partially Implemented' => 'partially met',
        'Not Assessed' => 'not yet answered'
    );

    private $client_row = null;
    private $plan = null;
    private $used_purposes = array();
    private $used_lines = array();

    protected function title(): string
    {
        return 'Cybersecurity Risk Assessment';
    }

    protected function doc_options(): array
    {
        return array(
            'toc' => true,
            'numbered' => true,
            'chapter_breaks' => true
        );
    }

    public function build(): array
    {
        $built = parent::build();

        if (isset($built['doc'])) {
            $built['doc']['file_base'] = ($this->a['client'] !== '' ? $this->a['client'].' - ' : '').$this->title().' v'.$this->options['report_version'];
        }

        return $built;
    }

    /**
     * The cover rows the risk report has always carried, with assessment
     * completion and report approval as separate rows.
     */
    protected function cover_rows(): array
    {
        $assessment = $this->a['assessment'];
        $rows = array(
            'Report date' => date('F j, Y')
        );

        if (!empty($assessment['start_date']) && !empty($assessment['end_date'])) {
            $rows['Planned assessment period'] = date('F j', strtotime($assessment['start_date'])).' to '.date('F j, Y', strtotime($assessment['end_date']));
        }

        $rows['Assessment status'] = ReportAnalysis::plain($assessment['assessment_status'] ?? '');
        $rows['Report status'] = $this->report_status_text();
        $rows['Version'] = (string) $this->options['report_version'];
        $rows['Prepared by'] = ReportAnalysis::plain($this->company['company_name'] ?? '');

        return $rows;
    }

    protected function blocks(): array
    {
        $this->a['findings'] = $this->prepared_findings();
        $full = $this->detail() !== 'executive';
        $blocks = array_merge(
            $this->executive_summary(),
            $this->background(),
            $this->register($full)
        );

        if ($full) {
            $blocks = array_merge($blocks, $this->domain_analyses());
        }

        return array_merge(
            $blocks,
            $this->recommendations(),
            $this->management_response(),
            $this->acknowledgment(),
            $this->references(),
            $this->detail() === 'comprehensive' ? $this->results_appendix() : array()
        );
    }

    /* ------------------------------------------------- executive summary */

    private function executive_summary(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $ai = $a['executive_ai'] ?? array();
        $blocks = array(
            self::h1('Executive summary')
        );

        $paragraphs = array();
        if (isset($a['assessor']['posture_text'])) {
            $paragraphs[] = 'Assessor\'s overall view: '.$a['assessor']['posture_text'];
        }
        $exposure = self::strings($ai['exposure_overview'] ?? ($a['exposure_overview_ai'] ?? array()));
        $paragraphs = array_merge($paragraphs, count($exposure) > 0 ? array_slice($exposure, 0, 2) : $this->exposure_summary());
        $blocks[] = self::text(implode("\n\n", $paragraphs));

        $blocks[] = $this->summary_metrics();

        if ($a['total'] > 0) {
            $blocks[] = self::h3('Implementation status of all '.$this->term('controls'));
            $segments = array();
            foreach (self::RESULT_LABELS as $result => $label) {
                if ($c[$result] > 0) {
                    $segments[] = array(
                        'label' => $label,
                        'value' => $c[$result],
                        'color' => self::RESULT_COLORS[$result]
                    );
                }
            }
            $blocks[] = array(
                'kind' => 'stackbar',
                'value' => array(
                    'segments' => $segments
                )
            );
        }

        $reliance = $this->reliance();
        if (count($reliance) > 0) {
            $blocks[] = self::subhead('Basis and reliance');
            $blocks[] = self::panel('', 'warning', 'The following limit how far these results can be relied on:'."\n".self::bullets($reliance));
        }

        $messages = $this->key_messages();
        if (count($messages) > 0) {
            $blocks[] = self::subhead('Key messages');
            $blocks[] = self::panel('', 'brand', self::bullets($messages));
        }

        $bars = array();
        $max_findings = 0;
        foreach ($a['families'] as $family) {
            if ($family['score'] !== null) {
                $max_findings = max($max_findings, count($family['gaps']));
            }
        }
        foreach ($a['families'] as $name => $family) {
            if ($family['score'] === null) {
                continue;
            }
            $gap_count = count($family['gaps']);
            $bars[] = array(
                'label' => $name,
                'value' => $family['score'],
                'max' => 100,
                'text' => $family['score'].'%'.($gap_count > 0 ? ' · '.self::count_of($gap_count, 'finding', 'findings') : ''),
                'tone' => $family['score'] >= 85 ? 'positive' : ($family['score'] >= 60 ? 'brand' : ($family['score'] >= 30 ? 'warning' : 'critical'))
            );
        }
        if (count($bars) >= 3 && $this->scores_vary()) {
            $blocks[] = self::subhead('Implementation score by '.$this->term('family'));
            $blocks[] = array(
                'kind' => 'bars',
                'value' => array(
                    'items' => $bars,
                    'max_label' => 'Implementation score, 0 to 100%'
                )
            );
            $unscored = count($a['families']) - count($bars);
            $blocks[] = self::note('The implementation score counts implemented '.$this->term('controls').' in full and partially implemented '.$this->term('controls').' at half, over the assessed '.$this->term('controls').' that apply. It describes how fully the '.$this->term('controls').' are in place and is not a compliance opinion or certification result.'
                .($unscored > 0 ? ' '.self::count_of($unscored, $this->term('family'), $this->term('families')).' with no assessed '.$this->term('controls').' '.($unscored === 1 ? 'is' : 'are').' not shown.' : ''));
        }


        return $blocks;
    }

    /**
     * The exposure the findings describe, used when no stored exposure
     * overview exists: where the gaps leave the organization exposed, by
     * remediation priority and subject area, worded as possibilities.
     */
    private function exposure_summary(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $gap_total = count($a['findings']);

        if ($a['assessed'] === 0) {
            return $this->overview();
        }

        if ($gap_total === 0) {
            return array(
                'No '.$this->term('control').' among the '.$a['assessed'].' assessed is recorded as not fully in place, so this report identifies no exposure arising from control gaps.'
            );
        }

        $p1 = 'The recorded results leave '.self::count_of($gap_total, $this->term('control'), $this->term('controls')).' short of what '.$a['standard'].' expects';
        $p1 .= $c['Not Implemented'] > 0
            ? ($c['Partially Implemented'] > 0 ? ': the protection is absent in '.$c['Not Implemented'].' and incomplete in '.$c['Partially Implemented'].'.' : ', and in each the protection is absent.')
            : ', each partly in place, so the protection they are intended to provide is incomplete rather than absent.';
        $p1 .= ' Each gap is a point where an event the '.$this->term('control').' is meant to prevent or detect could go unchecked; the findings in this report set out what could follow and are possibilities, not observed events.';

        $high = array_values(array_filter($a['findings'], function ($finding) {
            return $finding['priority'] === 'High';
        }));
        $pool = count($high) > 0 ? $high : $a['findings'];
        $topics = array();
        foreach ($pool as $finding) {
            $key = $finding['control']['topic'];
            $topics[$key] = ($topics[$key] ?? 0) + 1;
        }
        arsort($topics);

        $areas = array();
        foreach (array_slice($topics, 0, 3, true) as $key => $count) {
            $areas[] = strtolower(ReportKnowledge::topic($key)['name']).' ('.$count.')';
        }

        $p2 = count($high) > 0
            ? 'The greatest exposure lies in the '.self::count_of(count($high), 'high-priority remediation finding', 'high-priority remediation findings').', concentrated in '.self::join_list($areas).'.'
            : 'No finding carries a high remediation priority; the gaps concentrate in '.self::join_list($areas).'.';
        $lead = ReportKnowledge::topic((string) array_key_first($topics));
        if (count($lead['consequences']) > 0) {
            $p2 .= ' In '.strtolower($lead['name']).', for example: '.self::lower_first($lead['consequences'][0]);
        }
        $medium = $this->priority_count('Medium');
        $low = $this->priority_count('Low');
        if ($medium + $low > 0) {
            $p2 .= ' The remaining '.($medium > 0 ? $medium.' medium' : '').($medium > 0 && $low > 0 ? ' and ' : '').($low > 0 ? $low.' low' : '').'-priority remediation findings widen this exposure but are less direct.';
        }

        return array(
            $p1,
            $p2
        );
    }

    /**
     * Prose with each over-long paragraph split at sentence boundaries into
     * shorter ones, so stored text reads in steps rather than one block.
     * Bullet lines and short paragraphs are left as they are.
     */
    public static function paragraphs(string $text, int $limit = 420, int $target = 300): string
    {
        $out = array();

        foreach (preg_split("/\n\s*\n/", trim($text)) as $chunk) {
            if (strlen($chunk) <= $limit || strpos($chunk, "\n") !== false) {
                $out[] = $chunk;
                continue;
            }
            $sentences = preg_split('/(?<!\be\.g\.)(?<!\bi\.e\.)(?<!\betc\.)(?<!\bvs\.)(?<!\bNo\.)(?<=[.!?])\s+(?=[A-Z(])/', $chunk);
            if ($sentences === false || count($sentences) < 2) {
                $out[] = $chunk;
                continue;
            }
            $groups = array();
            $current = '';
            foreach ($sentences as $sentence) {
                if ($current !== '' && strlen($current) + strlen($sentence) > $target) {
                    $groups[] = $current;
                    $current = $sentence;
                    continue;
                }
                $current = $current === '' ? $sentence : $current.' '.$sentence;
            }
            if (count($groups) > 0 && strlen($current) < 90) {
                $groups[count($groups) - 1] .= ' '.$current;
            } else {
                $groups[] = $current;
            }
            $out[] = implode("\n\n", $groups);
        }

        return implode("\n\n", $out);
    }

    /** At most $count sentences of $text, so a recorded condition stays brief. */
    private static function first_sentences(string $text, int $count): string
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z(])/', $text);

        if ($parts === false || count($parts) <= $count) {
            return $text;
        }

        return implode(' ', array_slice($parts, 0, $count));
    }

    private static function lower_first(string $text): string
    {
        return preg_match('/^(A\s|[A-Z][a-z])/', $text) ? lcfirst($text) : $text;
    }

    /** Deterministic overview paragraphs, used when nothing has been assessed yet. */
    private function overview(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $firm = $this->firm();
        $client = $a['client'] !== '' ? $a['client'] : 'the organization';
        $gap_total = count($a['findings']);
        $paragraphs = array();

        $p1 = ($firm !== '' ? $firm : 'The assessor').' assessed '.$client.'\'s cybersecurity controls against '.$a['standard'];
        $p1 .= $this->period_phrase() !== '' ? ' '.$this->period_phrase().'. ' : '. ';
        if ($a['assessed'] === 0) {
            $p1 .= 'None of the '.$a['total'].' '.$this->term('controls').' has been assessed yet, so this report cannot draw conclusions about the control environment.';
            $paragraphs[] = $p1;
            return $paragraphs;
        }
        $p1 .= ($a['assessed'] === $a['total'] ? 'All '.$a['total'].' '.$this->term('controls').' were assessed' : $a['assessed'].' of '.$a['total'].' '.$this->term('controls').' ('.self::pct($a['assessed'], $a['total']).') have been assessed so far').'. ';
        $parts = array();
        foreach (array(
            'Implemented' => 'implemented',
            'Partially Implemented' => 'partially implemented',
            'Not Implemented' => 'not implemented',
            'Not Applicable' => 'not applicable'
        ) as $result => $label) {
            if ($c[$result] > 0) {
                $parts[] = count($parts) === 0
                    ? $c[$result].' '.($c[$result] === 1 ? 'is' : 'are').' recorded as '.$label
                    : $c[$result].' as '.$label;
            }
        }
        $p1 .= ucfirst(self::join_list($parts)).'.';
        if ($a['score'] !== null) {
            $p1 .= ' The resulting implementation score of '.$a['score'].'% measures how fully the assessed '.$this->term('controls').' are in place; it is not a compliance opinion or certification result.';
        }
        $paragraphs[] = $p1;

        if ($gap_total > 0) {
            $p2 = 'This report raises '.self::count_of($gap_total, 'finding', 'findings').', one for each '.$this->term('control').' that is not fully in place.';
            if ($c['Not Implemented'] === 0) {
                $p2 .= ' None of them is entirely absent: '.($gap_total === 1 ? 'the gap is partial, which points to a control that exists but is incomplete or not yet formalized.' : 'every gap is partial, which points to controls that exist but are incomplete or not yet formalized.');
            } elseif ($c['Partially Implemented'] >= $c['Not Implemented'] * 3) {
                $p2 .= ' Most are partial rather than absent '.$this->term('controls').', which indicates foundations that exist in many areas but are not yet complete or formalized.';
            } elseif ($c['Not Implemented'] > $c['Partially Implemented']) {
                $p2 .= ' More '.$this->term('controls').' are absent than partly in place, which points to parts of the program that have not yet been established.';
            }
            $themes = array_slice($a['themes'], 0, 3);
            if (count($themes) > 0) {
                $p2 .= ' The gaps concentrate in '.self::join_list(array_map(function ($theme) {
                    return strtolower($theme['name']).' ('.count($theme['refs']).')';
                }, $themes)).'.';
            }
            $high = $this->priority_count('High');
            if ($high > 0) {
                $p2 .= ' '.($high === 1 ? 'One finding carries' : $high.' findings carry').' a high remediation priority and should be addressed first.';
            }
            $paragraphs[] = $p2;
        } else {
            $paragraphs[] = 'No gaps were recorded among the assessed '.$this->term('controls').', so this report raises no findings.';
        }

        return $paragraphs;
    }

    private function summary_metrics(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $items = array();
        $gap_total = count($a['findings']);
        $applicable_assessed = $a['assessed'] - $c['Not Applicable'];

        if ($a['score'] !== null) {
            $items[] = array(
                'value' => $a['score'].'%',
                'label' => 'Implementation score',
                'sub' => $a['assessed'] < $a['total'] ? 'Over '.$a['assessed'].' assessed · not an opinion' : 'Not a compliance opinion',
                'tone' => 'brand'
            );
        }

        $items[] = array(
            'value' => $a['assessed'].' of '.$a['total'],
            'label' => ucfirst($this->term('controls')).' assessed',
            'sub' => self::pct($a['assessed'], $a['total']).' complete',
            'tone' => $a['assessed'] === $a['total'] ? 'positive' : 'warning'
        );

        $split = array();
        foreach (array(
            'High',
            'Medium',
            'Low'
        ) as $priority) {
            $count = $this->priority_count($priority);
            if ($count > 0) {
                $split[] = $count.' '.strtolower($priority);
            }
        }
        $items[] = array(
            'value' => (string) $gap_total,
            'label' => $gap_total === 1 ? 'Finding' : 'Findings',
            'sub' => count($split) > 0 ? implode(' · ', $split).' remediation priority' : 'No gaps recorded',
            'tone' => $this->priority_count('High') > 0 ? 'critical' : ($gap_total > 0 ? 'warning' : 'positive')
        );

        if ($applicable_assessed > 0) {
            $items[] = array(
                'value' => self::pct($a['with_evidence'], $applicable_assessed),
                'label' => 'Evidence coverage',
                'sub' => $a['with_evidence'].' of '.$applicable_assessed.' assessed',
                'tone' => $a['with_evidence'] === 0 ? 'critical' : ($a['with_evidence'] < $applicable_assessed ? 'warning' : 'positive')
            );
        }

        return array(
            'kind' => 'metrics',
            'value' => array(
                'items' => $items
            )
        );
    }

    private function key_messages(): array
    {
        $a = $this->a;
        $messages = array();
        $by_ref = $this->findings_by_control();

        foreach (array_slice($a['themes'], 0, 3) as $theme) {
            $lead = null;
            foreach ($theme['refs'] as $ref) {
                if (isset($by_ref[$ref]) && ($lead === null || ReportAnalysis::PRIORITY_RANK[$by_ref[$ref]['priority']] > ReportAnalysis::PRIORITY_RANK[$lead['priority']])) {
                    $lead = $by_ref[$ref];
                }
            }
            $message = ucfirst(strtolower($theme['name'])).': '.count($theme['refs']).' '.$this->term('controls').' are not fully in place'
                .(count($theme['families']) > 1 ? ' across '.count($theme['families']).' '.$this->term('families') : '');
            if ($lead !== null) {
                $message .= ', led by '.$lead['control']['ref'].' '.self::short_title($lead['control']['title']).' ('.strtolower(self::RESULT_LABELS[$lead['status']]).', '.strtolower($lead['priority']).' remediation priority)';
            }
            $messages[] = $message.'.';
        }

        $high = array_values(array_filter($a['findings'], function ($finding) {
            return $finding['priority'] === 'High';
        }));
        $themed = array();
        foreach (array_slice($a['themes'], 0, 3) as $theme) {
            $themed = array_merge($themed, $theme['refs']);
        }
        $outside = array_values(array_filter($high, function ($finding) use ($themed) {
            return !in_array($finding['control']['ref'], $themed, true);
        }));
        if (count($outside) > 0) {
            $messages[] = 'High-priority remediation findings outside those areas: '.self::join_list(array_map(function ($finding) {
                return $finding['control']['ref'].' '.self::short_title($finding['control']['title']);
            }, $outside)).'.';
        } elseif (count($a['themes']) === 0 && count($a['findings']) > 0) {
            $first = $a['findings'][0];
            $messages[] = 'The highest-priority finding is '.$first['ref'].': '.$first['control']['ref'].' '.self::short_title($first['control']['title']).', '.strtolower(self::RESULT_LABELS[$first['status']]).'.';
        }

        if (count($a['patterns']) > 0) {
            $pattern = $a['patterns'][0];
            $messages[] = 'One weakness recurs across the '.$this->term('families').', '.strtolower($pattern['label']).', which affects '.count($pattern['points']).' open '.$this->term('objectives').' in '.count($pattern['controls']).' '.$this->term('controls').'; addressing it once will help close several findings together.';
        }

        $initiatives = $this->initiatives();
        $immediate = array_values(array_filter($initiatives, function ($initiative) {
            return $initiative['phase'] === 'Immediate priorities';
        }));
        if (count($initiatives) >= 3) {
            $messages[] = 'The findings consolidate into '.self::count_of(count($initiatives), 'remediation initiative', 'remediation initiatives')
                .(count($immediate) > 0 ? ', '.count($immediate).' of them immediate priorities ('.self::compact_refs(array_column($immediate, 'id')).')' : '').'.';
        }

        return $messages;
    }

    /** Conditions that limit reliance on the results, worded from the data. */
    private function reliance(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $items = array();
        $applicable_assessed = $a['assessed'] - $c['Not Applicable'];

        if ($c['Not Assessed'] > 0) {
            $items[] = self::count_of($c['Not Assessed'], $this->term('control'), $this->term('controls')).' of '.$a['total'].' '.($c['Not Assessed'] === 1 ? 'has' : 'have').' not been assessed; conclusions cover the assessed '.$this->term('controls').' only.';
        }

        $unanswered = 0;
        foreach ($a['controls'] as $control) {
            if ($control['result'] === 'Not Assessed') {
                continue;
            }
            foreach ($control['objectives'] as $objective) {
                if ($objective['result'] === 'Not Assessed') {
                    $unanswered++;
                }
            }
        }
        if ($unanswered > 0) {
            $items[] = self::count_of($unanswered, $this->term('objective'), $this->term('objectives')).' within assessed '.$this->term('controls').' '.($unanswered === 1 ? 'has' : 'have').' not been answered.';
        }

        if ($applicable_assessed > 0 && $a['with_evidence'] < $applicable_assessed) {
            $without = $applicable_assessed - $a['with_evidence'];
            $items[] = $without === $applicable_assessed
                ? 'No evidence is linked to any assessed '.$this->term('control').'; every result rests on the recorded answer.'
                : self::count_of($without, 'assessed '.$this->term('control'), 'assessed '.$this->term('controls')).' '.($without === 1 ? 'has' : 'have').' no linked evidence.';
        }

        $observed = count(array_filter($a['findings'], function ($finding) {
            return isset($finding['control']['assessor']['notes']) || isset($finding['control']['assessor']['gap_description']);
        }));
        if (count($a['findings']) > 0 && $observed < count($a['findings'])) {
            $items[] = $observed === 0
                ? 'No assessor observation is recorded for any finding; the findings are described from the recorded results only.'
                : (count($a['findings']) - $observed).' of '.count($a['findings']).' findings have no assessor observation recorded; those are described from the recorded results only.';
        }

        $unjustified = count(array_filter($a['controls'], function ($control) {
            return ($control['result'] === 'Not Applicable' || $control['applicability'] === 'Excluded') && $control['justification'] === '';
        }));
        if ($unjustified > 0) {
            $items[] = self::count_of($unjustified, $this->term('control'), $this->term('controls')).' marked not applicable '.($unjustified === 1 ? 'has' : 'have').' no recorded justification.';
        }

        return $items;
    }

    /* ----------------------------------------------- background and scope */

    private function background(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $firm = $this->firm();
        $client = $a['client'] !== '' ? $a['client'] : 'The organization';
        $blocks = array(
            self::h1('Background, scope and approach'),
            self::h2('Purpose'),
            self::text($client.' engaged '.($firm !== '' ? $firm : 'the assessor').' to assess its cybersecurity controls against '.$a['standard'].'. This report interprets the recorded results as exposure: it identifies each '.$this->term('control').' that is not fully in place, explains what could follow from the gap, sets a remediation priority and consolidates the corrective work into initiatives for management to authorize and track.'),
            self::h2('Scope')
        );

        $scope = array();
        if (isset($a['assessor']['assessment_scope'])) {
            $scope[] = 'Scope as recorded by the assessor: '.$a['assessor']['assessment_scope'];
        } else {
            $scope[] = 'A scope statement has not been recorded for this assessment; it should be confirmed with the assessor before the report is issued. The description below is drawn from the assessment data.';
        }
        $line = 'The assessment covers '.$a['total'].' '.$this->term('controls').' of '.$a['standard'].' in '.self::count_of(count($a['families']), $this->term('family'), $this->term('families')).'.';
        if ($c['Not Applicable'] > 0) {
            $line .= ' '.self::count_of($c['Not Applicable'], $this->term('control'), $this->term('controls')).' '.($c['Not Applicable'] === 1 ? 'is' : 'are').' recorded as not applicable.';
        }
        $scope[] = $line;
        $blocks[] = self::text(implode("\n\n", $scope));
        $blocks[] = array(
            'kind' => 'kv',
            'value' => array(
                'rows' => $this->period_rows()
            )
        );

        $rows = array();
        $partial = $c['Not Assessed'] > 0;
        foreach ($a['families'] as $name => $family) {
            $topics = array();
            foreach ($family['controls'] as $control) {
                $topics[$control['topic']] = ($topics[$control['topic']] ?? 0) + 1;
            }
            arsort($topics);
            $names = array();
            foreach (array_slice(array_keys($topics), 0, 3) as $key) {
                $names[] = ReportKnowledge::topic($key)['name'];
            }
            $total = count($family['controls']);
            $assessed = $total - $family['counts']['Not Assessed'];
            $row = array(
                $name,
                ucfirst(strtolower(self::join_list($names))),
                (string) $total
            );
            if ($partial) {
                $row[] = $assessed === $total ? 'All' : ($assessed === 0 ? 'None' : $assessed.' of '.$total);
            }
            $row[] = (string) count($family['gaps']);
            $row[] = $family['score'] !== null ? $family['score'].'%' : '';
            $rows[] = $row;
        }
        $head = array(
            ucfirst($this->term('family')),
            'Subject areas',
            ucfirst($this->term('controls'))
        );
        $widths = array(
            28,
            40,
            11
        );
        if ($partial) {
            $head[] = 'Assessed';
            $widths[] = 11;
            $widths[1] -= 11;
        }
        $head[] = 'Findings';
        $widths[] = 11;
        $head[] = 'Score';
        $widths[] = 10;
        $blocks[] = self::table($head, $widths, $rows);

        $blocks[] = self::h2('Approach');
        $approach = array();
        if (isset($a['assessor']['assessment_method'])) {
            $approach[] = 'Method as recorded by the assessor: '.$a['assessor']['assessment_method'];
        } else {
            $approach[] = 'The assessment method has not been recorded and should be confirmed with the assessor. The assessment data shows the following approach.';
        }
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
        $steps = array();
        $steps[] = 'Each '.$this->term('control').' is given one recorded result: implemented, partially implemented, not implemented or not applicable.';
        if ($objective_total > 0) {
            $steps[] = 'Where the framework defines '.$this->term('objectives').', each is answered separately; '.$objective_answered.' of '.$objective_total.' '.$this->term('objectives').' have been answered. The '.$this->term('objectives').' that are not met identify the specific deficiency behind each finding.';
        }
        $files = count($a['evidence_files']);
        $steps[] = $files > 0
            ? self::count_of($files, 'evidence file is', 'evidence files are').' linked to '.self::count_of($a['with_evidence'], $this->term('control'), $this->term('controls')).' or their '.$this->term('objectives').' (Appendix A).'
            : 'No evidence files are linked to the assessment.';
        $steps[] = 'Each '.$this->term('control').' not fully implemented becomes a finding. What the '.$this->term('control').' is intended to achieve, what could follow from the gap and how to correct it are drawn from the framework text and general security practice, and are worded as such; they are not statements of fact about the organization.';
        $approach[] = self::bullets($steps);
        $blocks[] = self::text(implode("\n\n", $approach));

        $blocks[] = self::h2('Rating methodology');
        $rated = count(array_filter($a['findings'], function ($finding) {
            return $finding['risk_rating'] !== null;
        }));
        $blocks[] = self::text('This assessment did not evaluate the likelihood or impact of threat scenarios, so the report assigns no formal risk ratings and presents no risk heat map. Findings are ordered by a remediation priority derived from the recorded results, as set out below.');
        $blocks[] = array(
            'kind' => 'kv',
            'value' => array(
                'rows' => array(
                    array(
                        'Implementation status',
                        'The result recorded for each '.$this->term('control').'. A finding is raised for every '.$this->term('control').' recorded as not implemented or partially implemented.'
                    ),
                    array(
                        'Remediation priority (derived)',
                        'High, Medium or Low, derived by a fixed rule from three recorded facts: the status (not implemented weighs more than partially implemented), how directly the subject area of the '.$this->term('control').' exposes systems and data (for example, access control weighs more than documentation), and the share of its '.$this->term('objectives').' that remain open. It indicates the order in which to remediate. It is not an assessed risk rating.'
                    ),
                    array(
                        'Assessed risk',
                        $rated > 0
                            ? 'Shown for the '.self::count_of($rated, 'finding', 'findings').' where the assessor recorded a risk rating, alongside the derived priority.'
                            : 'Shown only where the assessor records a risk rating. None has been recorded for this assessment.'
                    ),
                    array(
                        'Implementation score',
                        'Implemented '.$this->term('controls').' count in full and partially implemented '.$this->term('controls').' at half, over the assessed '.$this->term('controls').' that apply. Not applicable and unassessed '.$this->term('controls').' are excluded. The score is not a compliance opinion or certification result.'
                    ),
                    array(
                        ucfirst($this->term('family')).' rating',
                        'Derived from the '.$this->term('family').'\'s implementation score: 85% or more Satisfactory, 60% or more Satisfactory with exceptions, 30% or more Needs improvement, below 30% Unsatisfactory. Given only where at least half of the '.$this->term('family').'\'s '.$this->term('controls').' have been assessed.'
                    )
                )
            )
        );

        $blocks[] = self::h2('Limitations and responsibilities');
        $blocks[] = self::text(self::limitations($firm !== '' ? $firm : 'The assessor'));

        return $blocks;
    }

    private static function limitations(string $firm): string
    {
        return 'This assessment reflects conditions recorded during fieldwork and is based on information and access provided by management and its service providers. '
            .'It is not an audit, and '.$firm.' does not express an opinion on the effectiveness of internal controls or on compliance with any regulation. '
            .'Conditions may change after the date of this report, and controls that are effective today may become less effective over time.'
            ."\n\n"
            .'Management is responsible for the design and operation of the cybersecurity program, for deciding how each risk is treated, and for the accuracy of the information it provided. '
            .$firm.'\'s procedures were limited to those described in this report and were not designed to detect fraud or every weakness that may exist.';
    }

    /* --------------------------------------------------- findings register */

    private function register(bool $linked): array
    {
        $a = $this->a;
        $blocks = array(
            self::h1('Consolidated findings register')
        );

        if (count($a['findings']) === 0) {
            $blocks[] = self::text($a['assessed'] === 0
                ? 'No findings can be raised until '.$this->term('controls').' have been assessed.'
                : 'No findings were raised: no assessed '.$this->term('control').' is recorded as not implemented or partially implemented.');
            return $blocks;
        }

        $rated = count(array_filter($a['findings'], function ($finding) {
            return $finding['risk_rating'] !== null;
        })) > 0;

        $blocks[] = self::text('Each '.$this->term('control').' not fully implemented is one finding, listed in order of derived remediation priority. The initiative column shows the remediation initiative in Section '.($linked ? '5' : '4').' that addresses it'.($linked ? '; each reference links to the finding\'s detail in Section 4' : '').'.');

        $rows = array();
        $anchors = array();
        foreach ($a['findings'] as $finding) {
            $row = array(
                $finding['ref'],
                $finding['control']['ref'].' '.self::short_title($finding['control']['title']),
                $finding['domain'],
                self::RESULT_LABELS[$finding['status']],
                $finding['priority']
            );
            if ($rated) {
                $row[] = (string) ($finding['risk_rating'] ?? '');
            }
            $row[] = (string) $this->initiative_of($finding);
            $rows[] = $row;
            $anchors[] = $linked ? $finding['anchor'] : '';
        }

        $head = array(
            'Ref.',
            'Finding',
            ucfirst($this->term('family')),
            'Status',
            'Remediation priority'
        );
        $widths = $rated
            ? array(
                6,
                28,
                19,
                20,
                11,
                8,
                8
            )
            : array(
                6,
                32,
                20,
                20,
                12,
                10
            );
        if ($rated) {
            $head[] = 'Assessed risk';
        }
        $head[] = 'Initiative';

        $blocks[] = array(
            'kind' => 'register',
            'value' => array(
                'head' => $head,
                'widths' => $widths,
                'rows' => $rows,
                'small' => true,
                'anchors' => $anchors,
                'link_col' => 0
            )
        );

        return $blocks;
    }

    /* --------------------------------------------------- domain analyses */

    private function domain_analyses(): array
    {
        $a = $this->a;

        if (count($a['findings']) === 0 && $a['assessed'] === 0) {
            return array();
        }

        $blocks = array(
            self::h1('Domain analyses and findings')
        );

        $by_domain = array();
        foreach ($a['findings'] as $finding) {
            $by_domain[$finding['domain']][] = $finding;
        }

        $with = array();
        $clean = array();
        $unassessed = array();
        foreach ($a['families'] as $name => $family) {
            $assessed = count($family['controls']) - $family['counts']['Not Assessed'];
            if (isset($by_domain[$name])) {
                $with[] = $name;
            } elseif ($assessed > 0) {
                $clean[] = $name;
            } else {
                $unassessed[] = $name;
            }
        }

        $intro = 'Each '.$this->term('family').' with findings is analysed in turn: what its results show taken together, then a card for each finding. Every card follows the same order and leaves out parts with nothing to say: the recorded condition (the recorded answers and any assessor text, which are fact), the control expectation, the potential exposure and consequences (possible scenarios, not observed events), the suggested approach, the expected outcome, validation criteria, supporting references and the facts still to be confirmed with the organization. Exposure, approach, outcome and validation interpret the record using the framework and general security practice. Requirement-level detail for every finding is in the Gap Analysis.';
        if (count($a['evidence_files']) === 0 && count($with) > 0) {
            $intro .= ' No evidence is linked to any '.$this->term('control').' in this assessment, so every recorded result still requires confirmation against supporting evidence.';
        }
        $observed = count(array_filter($a['findings'], function ($finding) {
            return count($finding['control']['assessor']) > 0;
        }));
        if (count($a['findings']) > 0 && $observed === 0) {
            $intro .= ' No assessor notes are recorded for the findings, so the facts behind each partial result are listed for confirmation where the record does not show them.';
        }
        if (count($unassessed) > 0) {
            $controls = 0;
            foreach ($unassessed as $name) {
                $controls += count($a['families'][$name]['controls']);
            }
            $intro .= "\n\n".(count($unassessed) === count($a['families']) - count($with) - count($clean) && count($with) + count($clean) <= 2
                ? 'Only '.self::count_of(count($with) + count($clean), $this->term('family'), $this->term('families')).' of '.count($a['families']).' '.(count($with) + count($clean) === 1 ? 'has' : 'have').' assessed '.$this->term('controls').'. '
                : '')
                .'No '.$this->term('control').' has been assessed in '.(count($unassessed) === 1 ? 'the following '.$this->term('family') : 'the following '.count($unassessed).' '.$this->term('families')).' ('.self::count_of($controls, $this->term('control'), $this->term('controls')).'), so no conclusion is drawn and no finding can yet be raised: '.self::join_list($unassessed).'.';
        }
        if (count($with) === 0) {
            $intro = 'No '.$this->term('family').' has findings. The assessed '.$this->term('families').' are summarized below.';
        }
        $blocks[] = self::text($intro);

        $patterns = $this->patterns();
        if (count($patterns) > 0) {
            $blocks[] = self::h2('Cross-cutting patterns');
            $blocks[] = self::text('These weaknesses recur across several '.$this->term('controls').' and '.$this->term('families').', so the exposure they create is shared and is best addressed once, at its root.');
            $blocks[] = array(
                'kind' => 'kv',
                'value' => array(
                    'rows' => array_map(function ($pattern) {
                        return array(
                            $pattern['label'],
                            $pattern['text']
                        );
                    }, $patterns)
                )
            );
        }

        foreach ($with as $index => $name) {
            $blocks = array_merge($blocks, $this->domain_section($name, $by_domain[$name], $index + 1, $patterns));
        }

        if (count($clean) > 0) {
            $blocks[] = self::h2(ucfirst($this->term('families')).' without findings');
            $rows = array();
            foreach ($clean as $name) {
                $family = $a['families'][$name];
                $total = count($family['controls']);
                $assessed = $total - $family['counts']['Not Assessed'];
                $rows[] = array(
                    $name,
                    $assessed === $total ? 'All '.$total : $assessed.' of '.$total,
                    (string) $family['counts']['Implemented'],
                    (string) $family['counts']['Not Applicable'],
                    $family['score'] !== null ? $family['score'].'%' : 'Not scored'
                );
            }
            $blocks[] = self::text('No gaps were recorded among the assessed '.$this->term('controls').' of these '.$this->term('families').'.');
            $blocks[] = self::table(array(
                ucfirst($this->term('family')),
                'Assessed',
                'Implemented',
                'Not applicable',
                'Score'
            ), array(
                40,
                16,
                16,
                16,
                12
            ), $rows);
        }

        return $blocks;
    }

    private function domain_section(string $name, array $findings, int $number, array $patterns): array
    {
        $family = $this->a['families'][$name];
        $total = count($family['controls']);
        $assessed = $total - $family['counts']['Not Assessed'];
        $counts = array();

        foreach (array(
            'High',
            'Medium',
            'Low'
        ) as $priority) {
            $count = count(array_filter($findings, function ($finding) use ($priority) {
                return $finding['priority'] === $priority;
            }));
            if ($count > 0) {
                $counts[$priority.'-priority remediation '.($count === 1 ? 'finding' : 'findings')] = $count;
            }
        }

        $this->used_lines = array();

        $blocks = array(
            array(
                'kind' => 'domain_head',
                'anchor' => 'domain-'.$number,
                'value' => array(
                    'title' => $name,
                    'rating' => $assessed * 2 >= $total ? self::domain_rating($family['score']) : null,
                    'counts' => $counts
                )
            ),
            self::text($this->domain_narrative($name, $findings, $patterns))
        );

        if (count($findings) >= 3) {
            $with_objectives = count(array_filter($findings, function ($finding) {
                return count($finding['control']['objectives']) > 0;
            })) > 0;
            $rows = array();
            $anchors = array();
            foreach ($findings as $finding) {
                $control = $finding['control'];
                $row = array(
                    $finding['ref'],
                    $control['ref'].' '.self::short_title($control['title']),
                    self::RESULT_LABELS[$finding['status']],
                    $finding['priority']
                );
                if ($with_objectives) {
                    $row[] = count($control['objectives']) > 0 ? count($control['open']).' of '.count($control['objectives']) : '';
                }
                $rows[] = $row;
                $anchors[] = $finding['anchor'];
            }
            $head = array(
                'Ref.',
                ucfirst($this->term('control')),
                'Status',
                'Remediation priority'
            );
            if ($with_objectives) {
                $head[] = 'Open';
            }
            $blocks[] = array(
                'kind' => 'register',
                'value' => array(
                    'head' => $head,
                    'widths' => $with_objectives
                        ? array(
                            8,
                            48,
                            20,
                            14,
                            10
                        )
                        : array(
                            8,
                            56,
                            21,
                            15
                        ),
                    'rows' => $rows,
                    'small' => true,
                    'anchors' => $anchors,
                    'link_col' => 0
                )
            );
        }

        foreach ($findings as $finding) {
            $blocks[] = $this->finding_card($finding);
        }

        return $blocks;
    }

    /**
     * The domain introduction: what the family covers and what its results
     * mean taken together (the character of the gaps, the kinds of
     * objective left open, where they concentrate and what could follow),
     * then where remediation sits. The findings themselves are listed in
     * the table and the cards below, so they are not restated here.
     */
    private function domain_narrative(string $name, array $findings, array $patterns): string
    {
        $a = $this->a;
        $family = $a['families'][$name];
        $c = $family['counts'];
        $total = count($family['controls']);
        $assessed = $total - $c['Not Assessed'];
        $applicable = $assessed - $c['Not Applicable'];
        $gaps = count($findings);
        $paragraphs = array();

        $ai = trim((string) ($family['narrative_ai'] ?? ''));
        if ($ai !== '') {
            $paragraphs[] = self::paragraphs($ai);
        } else {
            $topics = array();
            foreach ($family['controls'] as $control) {
                $topics[$control['topic']] = ($topics[$control['topic']] ?? 0) + 1;
            }
            arsort($topics);
            $names = array();
            foreach (array_slice(array_keys($topics), 0, 3) as $key) {
                $names[] = strtolower(ReportKnowledge::topic($key)['name']);
            }
            $p1 = 'The '.self::count_of($total, $this->term('control'), $this->term('controls')).' in this '.$this->term('family').' concern '.self::join_list($names).'.';
            foreach (array_keys($topics) as $key) {
                if (!isset($this->used_purposes[$key])) {
                    $this->used_purposes[$key] = true;
                    $p1 .= ' '.ReportKnowledge::topic($key)['purpose'];
                    break;
                }
            }
            $paragraphs[] = $p1;
            $paragraphs[] = $this->domain_reading($family, $findings, $patterns, $applicable);
        }

        $closing = array();

        $initiatives = array();
        foreach ($findings as $finding) {
            $id = $this->initiative_of($finding);
            if ($id !== null) {
                $initiatives[$id] = true;
            }
        }
        $titles = $this->initiative_titles();
        $ids = array_keys($initiatives);
        sort($ids);
        if (count($ids) > 0) {
            $closing[] = 'Remediation is planned under '.self::join_list(array_map(function ($id) use ($titles) {
                return $id.' ('.lcfirst($titles[$id] ?? '').')';
            }, $ids)).'.';
        }

        if ($c['Not Assessed'] > 0) {
            $closing[] = $c['Not Assessed'].' of the '.$total.' '.$this->term('controls').' '.($c['Not Assessed'] === 1 ? 'has' : 'have').' not been assessed, so this picture is incomplete.';
        }

        if (count($a['evidence_files']) > 0) {
            $without = count(array_filter($family['controls'], function ($control) {
                return $control['evidence_count'] === 0 && !in_array($control['result'], array(
                    'Not Applicable',
                    'Not Assessed'
                ), true);
            }));
            if ($without > 0) {
                $closing[] = ($without === $applicable ? 'None of the assessed '.$this->term('controls').' here has' : $without.' assessed '.($without === 1 ? $this->term('control').' here has' : $this->term('controls').' here have')).' linked evidence.';
            }
        }

        if (count($closing) > 0) {
            $paragraphs[] = implode(' ', $closing);
        }

        return implode("\n\n", $paragraphs);
    }

    /**
     * What a family's results show taken together, from the record: whether
     * its gaps are absent or partial controls, which recurring kinds of
     * objective are open, whether one control holds most of the open
     * objectives, and the consequence that the area most exposed could carry.
     */
    private function domain_reading(array $family, array $findings, array $patterns, int $applicable): string
    {
        $c = $family['counts'];
        $gaps = count($findings);
        $sentences = array();

        if ($c['Implemented'] === 0 && $gaps === $applicable && $gaps > 1) {
            $sentences[] = $c['Not Implemented'] === 0
                ? 'Every applicable '.$this->term('control').' here is partly in place and none is complete, which points to practices that exist but have not been finished or formalized across the '.$this->term('family').'.'
                : ($c['Partially Implemented'] === 0
                    ? 'None of the applicable '.$this->term('controls').' here is in place, so this part of the program has yet to be established.'
                    : 'None of the applicable '.$this->term('controls').' here is complete: '.$c['Not Implemented'].' '.($c['Not Implemented'] === 1 ? 'is' : 'are').' absent and '.$c['Partially Implemented'].' partly in place.');
        } elseif ($c['Implemented'] > 0) {
            $sentences[] = $c['Implemented'].' of the '.$applicable.' applicable '.$this->term('controls').' '.($c['Implemented'] === 1 ? 'is' : 'are').' recorded as implemented, so the gaps sit alongside working practice that remediation can extend.';
        } elseif ($gaps === 1 && $findings[0]['status'] === 'Not Implemented') {
            $sentences[] = 'The '.$this->term('control').' with a gap is absent rather than incomplete.';
        }

        $refs = array_map(function ($finding) {
            return $finding['control']['ref'];
        }, $findings);
        $kinds = array();
        foreach ($patterns as $pattern) {
            $hits = array_values(array_intersect($pattern['refs'], $refs));
            if (count($hits) >= 2 || (count($hits) === 1 && $gaps === 1)) {
                $kinds[] = strtolower($pattern['label']).' ('.self::join_list($hits).')';
            }
        }
        if (count($kinds) > 0) {
            $sentences[] = 'The open '.$this->term('objectives').' share recurring themes: '.self::join_list(array_slice($kinds, 0, 3)).'.';
        }

        $open = 0;
        $leader = null;
        foreach ($findings as $finding) {
            $count = count($finding['answered_open']);
            $open += $count;
            if ($leader === null || $count > count($leader['answered_open'])) {
                $leader = $finding;
            }
        }
        if ($gaps >= 3 && $leader !== null && count($leader['answered_open']) * 2 >= $open && $open >= 4) {
            $sentences[] = $leader['control']['ref'].' '.self::short_title($leader['control']['title']).' accounts for '.count($leader['answered_open']).' of the '.$open.' open '.$this->term('objectives').', so it is where most of the remediation effort here lies.';
        }

        $lead = $findings[0];
        foreach ($findings as $finding) {
            if (ReportAnalysis::PRIORITY_RANK[$finding['priority']] > ReportAnalysis::PRIORITY_RANK[$lead['priority']]) {
                $lead = $finding;
            }
        }
        $topic = ReportKnowledge::topic($lead['control']['topic']);
        $line = $this->pick_fresh($topic['consequences'], 1, count($this->used_purposes), 'intro');
        if (count($line) > 0) {
            $sentences[] = 'Taken together, the main exposure is in '.strtolower($topic['name']).': '.self::lower_first($line[0]);
        }

        return implode(' ', $sentences);
    }

    /* ------------------------------------------------------ finding cards */

    /**
     * One finding in a fixed order, each part omitted when there is nothing
     * to say: recorded condition, control expectation, potential exposure,
     * suggested approach, expected outcome, validation criteria, supporting
     * references and items to be confirmed. The header carries the
     * requirement, the priority and its basis, and the initiative, so the
     * parts below never restate them.
     */
    private function finding_card(array $finding): array
    {
        $control = $finding['control'];
        $assessor = $control['assessor'];
        $topic = ReportKnowledge::topic($control['topic']);
        $unanswered = $finding['unanswered'];
        $full = $finding['priority'] === 'High';

        $meta = array(
            array(
                ucfirst($this->term('control')),
                $control['ref']
            ),
            array(
                'Priority basis',
                $this->priority_basis($finding)
            )
        );
        if ($finding['risk_rating'] !== null) {
            $meta[] = array(
                'Assessed risk',
                $finding['risk_rating']
            );
        }
        $initiative = $this->initiative_of($finding);
        if ($initiative !== null) {
            $meta[] = array(
                'Initiative',
                $initiative
            );
        }
        if (count($control['evidence']) === 0) {
            $meta[] = array(
                'Evidence',
                'None linked'
            );
        }
        if (isset($assessor['owner_name'])) {
            $meta[] = array(
                'Owner',
                $assessor['owner_name']
            );
        }
        if (isset($assessor['target_date'])) {
            $meta[] = array(
                'Target',
                self::date_text($assessor['target_date'])
            );
        }

        $sections = array();

        $recorded = array();
        if (isset($assessor['gap_description'])) {
            $recorded[] = 'Assessor observation: '.$assessor['gap_description'];
        }
        if (isset($assessor['notes'])) {
            $recorded[] = 'Assessor note: '.$assessor['notes'];
        }
        if (isset($assessor['implementation_description'])) {
            $recorded[] = 'Current implementation, as recorded: '.$assessor['implementation_description'];
        }
        $condition = self::first_sentences(trim((string) ($finding['recorded_condition_ai'] ?? '')), 3);
        $recorded[] = $condition !== '' ? self::paragraphs($condition) : $this->recorded_condition($finding);
        $sections[] = array(
            'Recorded condition',
            implode("\n\n", $recorded)
        );

        $expectation = $this->expectation($finding);
        if ($expectation !== '') {
            $sections[] = array(
                'Control expectation',
                $expectation
            );
        }

        $exposure = array();
        if (isset($assessor['business_impact'])) {
            $exposure[] = self::paragraphs('Business impact, as recorded by the assessor: '.$assessor['business_impact']);
        }
        $scenarios = array_slice(self::items($finding['risk_scenarios_ai'] ?? array()), 0, $full ? 3 : 2);
        if (count($scenarios) > 0) {
            $exposure[] = self::bullets(array_map(function ($pair) {
                $consequence = trim((string) $pair['consequence']);
                return rtrim($pair['scenario'], '. ').'.'.($consequence !== '' ? "\n  ".$consequence : '');
            }, $scenarios));
        } else {
            $exposure[] = $this->consequences($finding, $topic);
        }
        $sections[] = array(
            'Potential exposure and consequences',
            implode("\n\n", array_filter($exposure, function ($part) {
                return $part !== '';
            }))
        );

        $approach = array();
        if (isset($assessor['recommendation'])) {
            $approach[] = 'Assessor recommendation: '.$assessor['recommendation'];
        }
        $suggested = self::strings($finding['suggested_approach_ai'] ?? array());
        if (count($suggested) === 0) {
            $suggested = self::strings($finding['actions_ai'] ?? array());
        }
        $suggested = array_slice($suggested, 0, $full ? 3 : 2);
        if (count($suggested) > 0) {
            $approach[] = self::bullets($suggested);
        } else {
            $approach[] = $this->corrective_actions($finding, $topic);
        }
        if ($unanswered > 0) {
            $approach[] = 'Complete the assessment of the '.($unanswered === 1 ? 'unanswered '.$this->term('objective') : $unanswered.' unanswered '.$this->term('objectives')).' ('.implode(', ', array_column(array_filter($control['open'], function ($objective) {
                return $objective['result'] === 'Not Assessed';
            }), 'short')).') to confirm the full extent of the gap.';
        }
        $sections[] = array(
            'Suggested approach',
            implode("\n\n", $approach)
        );

        $outcome = trim((string) ($finding['outcome_ai'] ?? ''));
        if ($outcome === '') {
            $line = trim((string) ($topic['outcome'] ?? ''));
            $outcome = $line !== '' && $this->fresh($line, $finding['ref']) ? $line : '';
        }
        if ($outcome !== '') {
            $sections[] = array(
                'Expected outcome',
                self::paragraphs($outcome)
            );
        }

        $validation = array_slice(self::strings($finding['validation_ai'] ?? array()), 0, $full ? 3 : 2);
        if (count($validation) === 0) {
            $validation = $this->pick_fresh($topic['validation'], 2, (int) substr($finding['ref'], 2), $finding['ref']);
        }
        if (count($validation) === 0) {
            $shorts = array_column($finding['answered_open'], 'short');
            $validation[] = count($shorts) > 0
                ? 'Evidence linked to '.(count($shorts) === 1 ? $shorts[0] : 'each of '.$this->open_refs($shorts)).' shows the condition is met.'
                : 'Evidence linked to '.$control['ref'].' shows the '.$this->term('control').' operating as intended.';
        }
        $sections[] = array(
            'Validation criteria',
            self::bullets($validation)
        );

        $references = $this->supporting_references($finding);
        if ($references !== '') {
            $sections[] = array(
                'Supporting references',
                $references
            );
        }

        $confirm = array_slice(self::strings($finding['confirm_ai'] ?? array()), 0, 3);
        if (count($confirm) === 0 && trim((string) ($finding['recorded_condition_ai'] ?? '')) === '') {
            $confirm = $this->to_confirm($finding);
        }
        if (count($confirm) > 0) {
            $sections[] = array(
                'To be confirmed',
                self::bullets($confirm)
            );
        }

        $response = trim((string) ($finding['management_response'] ?? ''));
        if ($response !== '') {
            $sections[] = array(
                'Management response',
                self::paragraphs($response)
            );
        }

        return array(
            'kind' => 'finding',
            'value' => array(
                'ref' => $finding['ref'],
                'heading' => $control['ref'].' '.self::short_title($control['title']),
                'severity' => $finding['priority'],
                'rating' => $finding['risk_rating'] !== null ? 'Assessed risk: '.$finding['risk_rating'] : $finding['priority'].' remediation priority',
                'meta' => $meta,
                'sections' => $sections,
                'anchor' => $finding['anchor'],
                'spaced' => true
            )
        );
    }

    /**
     * The basis of the derived priority without its standard preamble,
     * e.g. "partially implemented, in a lower-exposure area (roles,
     * responsibilities and accountability), 5 of 6 points of focus open".
     */
    private function priority_basis(array $finding): string
    {
        $basis = trim((string) ($finding['control']['priority_basis'] ?? ''));
        $basis = rtrim(preg_replace('/^Derived from the recorded result:\s*/', '', $basis), '.');

        return $basis !== '' ? $basis : strtolower(self::RESULT_LABELS[$finding['status']]);
    }

    /**
     * What the control is meant to achieve, in a sentence or two: the
     * stored interpretation (its first sentence; the expected outcome
     * carries the detail), otherwise the framework's own statement of the
     * expected condition, otherwise the control description.
     */
    private function expectation(array $finding): string
    {
        $control = $finding['control'];
        $ai = trim((string) ($finding['purpose_ai'] ?? ''));

        if ($ai !== '') {
            return self::first_sentences($ai, 1);
        }

        $expected = trim((string) preg_split("/\n\s*-\s/", (string) ($finding['expectation'] ?? ''))[0]);

        if ($expected !== '' && strpos(self::normal($expected), self::normal($control['title'])) === 0) {
            return '';
        }

        if ($expected !== '') {
            return self::first_sentences($expected, 2);
        }

        $description = trim((string) $control['description']);

        if ($description === '' || self::normal($description) === self::normal($control['title'])) {
            return '';
        }

        return self::first_sentences(self::sentence($description), 2);
    }

    /**
     * The sources a finding rests on: the requirement and the objectives
     * it cites, the related requirements the interpretation draws on, and
     * any evidence linked to the control.
     */
    private function supporting_references(array $finding): string
    {
        $control = $finding['control'];
        $refs = array();

        foreach (self::strings($finding['source_refs_ai'] ?? array()) as $source) {
            $refs[$source] = true;
        }

        if (count($refs) === 0) {
            $refs[$control['ref']] = true;
            foreach ($control['open'] as $objective) {
                $refs[$control['ref'].'-'.$objective['short']] = true;
            }
        }

        $own = array();
        $other = array();
        foreach (array_keys($refs) as $ref) {
            if ($ref === $control['ref']) {
                continue;
            }
            if (strpos($ref, $control['ref']) === 0) {
                $own[] = ltrim(substr($ref, strlen($control['ref'])), '-. ');
            } else {
                $other[] = $ref;
            }
        }

        $parts = array(
            $control['ref'].(count($own) > 0 ? ' '.implode(', ', $own) : '')
        );
        if (count($other) > 0) {
            $parts[] = 'related: '.ReportGapAnalysis::grouped_refs($other, $this->a['controls']);
        }
        if (count($control['evidence']) > 0) {
            $parts[] = 'linked evidence: '.implode('; ', $control['evidence']);
        }

        return ucfirst(implode(' · ', $parts)).'.';
    }

    /**
     * The recorded condition in brief: each open objective by reference
     * with its one-line title and recorded state. The status and the count
     * of open objectives are in the card header; the Gap Analysis carries
     * the requirement-level detail.
     */
    private function recorded_condition(array $finding): string
    {
        $control = $finding['control'];
        $all = count($control['objectives']);
        $open = $finding['answered_open'];

        if ($all === 0) {
            return 'Recorded as '.strtolower(self::RESULT_LABELS[$finding['status']]).'; no '.$this->term('objectives').' were assessed individually.';
        }

        if (count($open) === 0) {
            return $this->recorded_line($finding);
        }

        $lines = array();
        foreach ($open as $objective) {
            $lines[] = $objective['short'].' '.self::short_title(rtrim($objective['title'], '.')).' ('.self::OBJECTIVE_STATES[$objective['result']].')';
        }

        $met = $all - count($control['open']);
        $lead = count($open) === $all
            ? 'None of the '.$all.' '.$this->term('objectives').' is fully met:'
            : ($met > 0 ? $met.' of '.$all.' '.$this->term('objectives').' '.($met === 1 ? 'is' : 'are').' recorded as met; open:' : 'Open '.$this->term('objectives').':');

        return $lead."\n".self::bullets($lines);
    }

    /**
     * Facts to confirm before the finding is relied on, when no stored
     * list exists: what a partial result without an assessor note lacks,
     * and the deficiency behind a result with no objectives.
     */
    private function to_confirm(array $finding): array
    {
        $control = $finding['control'];
        $lines = array();

        if (count($control['objectives']) === 0 && count($control['assessor']) === 0) {
            $lines[] = 'Which element of '.$control['ref'].' is missing; no '.$this->term('objectives').' or assessor note record it.';
        }

        $unspecified = array();
        foreach ($finding['answered_open'] as $objective) {
            if ($objective['result'] === 'Partially Implemented' && count($objective['assessor'] ?? array()) === 0) {
                $unspecified[] = $objective;
            }
        }
        foreach (array_slice($unspecified, 0, 2) as $objective) {
            $lines[] = 'Which parts of '.$objective['short'].' ('.self::short_title(rtrim($objective['title'], '.')).') are already in place, and which are missing.';
        }
        if (count($unspecified) > 2) {
            $lines[] = 'The same for '.self::join_list(array_column(array_slice($unspecified, 2), 'short')).', also recorded as partially met.';
        }

        return $lines;
    }

    /** One compact line of recorded facts, shown under an interpreted observation. */
    private function recorded_line(array $finding): string
    {
        $control = $finding['control'];
        $parts = array();

        foreach (self::OBJECTIVE_STATES as $result => $label) {
            $refs = array();
            foreach ($control['open'] as $objective) {
                if ($objective['result'] === $result) {
                    $refs[] = $objective['short'];
                }
            }
            if (count($refs) > 0) {
                $parts[] = $label.': '.implode(', ', $refs);
            }
        }

        return 'Recorded result: '.strtolower(self::RESULT_LABELS[$finding['status']]).(count($parts) > 0 ? '; '.implode('; ', $parts) : '').'.';
    }

    private function consequences(array $finding, array $topic): string
    {
        $control = $finding['control'];
        $shorts = array_column($finding['answered_open'], 'short');
        $count = $finding['status'] === 'Not Implemented' ? 2 : 1;
        $lines = $this->pick_fresh($topic['consequences'], $count, (int) substr($finding['ref'], 2), $finding['ref']);

        if (count($lines) === 0) {
            $first = $this->first_use($topic['consequences'][0] ?? '');
            if ($first === null) {
                return '';
            }
            return 'The consequences '.($first === 'intro' ? 'described in the introduction to this section' : 'set out under '.$first).' for '.strtolower($topic['name']).' apply here as well'
                .(count($shorts) > 0 ? ', through '.$this->open_refs($shorts) : '').'.';
        }

        if ($finding['status'] === 'Not Implemented') {
            return 'If the '.$this->term('control').' remains unimplemented:'."\n".self::bullets($lines);
        }

        return 'If '.(count($shorts) > 0 ? $this->open_refs($shorts).' remain'.(count($shorts) === 1 ? 's' : '').' open' : 'the gap remains').':'."\n".self::bullets($lines);
    }

    private function corrective_actions(array $finding, array $topic): string
    {
        $support = $this->pick_fresh($topic['activities'], count($finding['answered_open']) > 0 ? 2 : 3, (int) substr($finding['ref'], 2), $finding['ref']);

        if (count($support) === 0) {
            $support = array_slice($topic['activities'], 0, 2);
        }

        return self::bullets($support);
    }

    /**
     * Up to $count lines from $list not yet used in the current domain,
     * rotated by $seed; each line returned is marked as used by $ref.
     */
    private function pick_fresh(array $list, int $count, int $seed, string $ref): array
    {
        $n = count($list);
        $out = array();

        for ($i = 0; $i < $n && count($out) < $count; $i++) {
            $line = $list[($seed + $i) % $n];
            if ($this->fresh($line, $ref)) {
                $out[] = $line;
            }
        }

        return $out;
    }

    private function fresh(string $line, string $ref): bool
    {
        if ($line === '' || isset($this->used_lines[$line])) {
            return false;
        }

        $this->used_lines[$line] = $ref;

        return true;
    }

    /** Open objective refs: listed when few, counted when many. */
    private function open_refs(array $shorts): string
    {
        if (count($shorts) <= 3) {
            return self::join_list($shorts);
        }

        return 'the '.count($shorts).' '.$this->term('objectives');
    }

    private function first_use(string $line): ?string
    {
        return $this->used_lines[$line] ?? null;
    }

    /* ----------------------------------------------------- recommendations */

    private function recommendations(): array
    {
        $initiatives = $this->initiatives();

        if (count($initiatives) === 0) {
            return array();
        }

        $blocks = array(
            self::h1('Prioritized recommendations')
        );

        $blocks[] = self::text('The '.self::count_of(count($this->a['findings']), 'finding is', 'findings are').' consolidated into '.self::count_of(count($initiatives), 'remediation initiative', 'remediation initiatives').', so that related gaps are closed by one piece of work rather than '.$this->term('control').' by '.$this->term('control').'. Initiatives are ordered by phase and then by the highest derived priority among the findings they address. Owners and target dates are shown only where recorded.');

        $finding_of = array();
        foreach ($this->a['findings'] as $finding) {
            $finding_of[$finding['control']['ref']] = $finding['ref'];
        }

        $owned = count(array_filter($initiatives, function ($initiative) {
            return $initiative['owner'] !== null || $initiative['target'] !== null;
        })) > 0;

        $with_depends = count(array_filter($initiatives, function ($initiative) {
            return count($this->depends_of($initiative)) > 0;
        })) > 0;

        $rows = array();
        foreach ($initiatives as $initiative) {
            $findings = array();
            foreach ($initiative['controls'] as $ref) {
                if (isset($finding_of[$ref])) {
                    $findings[] = $finding_of[$ref];
                }
            }
            sort($findings);
            $row = array(
                $initiative['id'],
                $initiative['title'],
                $initiative['phase'],
                $initiative['priority'],
                self::compact_refs($findings)
            );
            if ($with_depends) {
                $row[] = implode(', ', $this->depends_of($initiative));
            }
            if ($owned) {
                $row[] = trim(($initiative['owner'] ?? '').($initiative['target'] !== null ? ', '.self::date_text($initiative['target']) : ''), ', ');
            }
            $rows[] = $row;
        }

        $head = array(
            'ID',
            'Initiative',
            'Phase',
            'Remediation priority',
            'Findings'
        );
        $widths = array(
            7,
            32,
            19,
            12,
            19
        );
        if ($with_depends) {
            $head[] = 'Builds on';
            $widths[] = 11;
        }
        if ($owned) {
            $head[] = 'Owner, target';
            $widths[] = 14;
            $widths[1] -= 8;
            $widths[4] -= 6;
        }
        if (!$with_depends) {
            $widths[1] += 11;
        }
        $blocks[] = self::table($head, $widths, $rows);
        if ($with_depends) {
            $blocks[] = self::note('Builds on: initiatives that provide a foundation this one relies on, such as an approved policy or defined roles, and that should be started first or run alongside.');
        }

        $phases = array();
        foreach ($initiatives as $initiative) {
            $phases[$initiative['phase']][] = $initiative;
        }
        $lines = array();
        foreach (ReportAnalysis::PHASES as $phase) {
            if (!isset($phases[$phase])) {
                continue;
            }
            $controls = 0;
            foreach ($phases[$phase] as $initiative) {
                $controls += count($initiative['controls']);
            }
            $lines[] = array(
                $phase,
                self::compact_refs(array_column($phases[$phase], 'id')).' ('.self::count_of($controls, $this->term('control'), $this->term('controls')).'). '.self::phase_purpose($phase)
            );
        }
        $blocks[] = self::h3('Phasing');
        $blocks[] = array(
            'kind' => 'kv',
            'value' => array(
                'rows' => $lines
            )
        );

        return $blocks;
    }

    /** Ids of the initiatives this one builds on. */
    private function depends_of(array $initiative): array
    {
        return $initiative['depends'] ?? array();
    }

    private static function phase_purpose(string $phase): string
    {
        $purposes = array(
            'Immediate priorities' => 'These close gaps with the most direct exposure and should start first.',
            'Foundational improvements' => 'These establish the governance, policy and risk foundations that later work depends on.',
            'Operational control enhancements' => 'These bring day-to-day operating processes to the expected standard.',
            'Program maturity' => 'These embed review, communication and continuous improvement.'
        );

        return $purposes[$phase] ?? '';
    }

    /* ------------------------------------------------ management response */

    private function management_response(): array
    {
        $a = $this->a;
        $blocks = array(
            self::h1('Management response')
        );

        $recorded = array_values(array_filter($a['findings'], function ($finding) {
            return trim((string) ($finding['management_response'] ?? '')) !== '';
        }));
        $owned = array_values(array_filter($a['findings'], function ($finding) {
            return $finding['owner'] !== null || $finding['target'] !== null;
        }));

        if (count($a['findings']) === 0) {
            $blocks[] = self::text('No findings require a management response.');
        } else {
            $text = count($recorded) === 0
                ? 'Management responses are pending: none has been recorded for the findings in this report. For each finding, management is asked to record its treatment decision (remediate, accept or transfer), the accountable owner and a target date. A gap that management decides to accept should be recorded with the rationale and the name of the approver.'
                : 'Management has recorded a response for '.count($recorded).' of '.count($a['findings']).' findings; the remaining responses are pending. Recorded responses appear on each finding card.';
            $blocks[] = self::text($text);
        }

        if (count($owned) > 0) {
            $rows = array();
            foreach ($owned as $finding) {
                $rows[] = array(
                    $finding['ref'],
                    $finding['control']['ref'].' '.self::short_title($finding['control']['title']),
                    (string) ($finding['owner'] ?? ''),
                    $finding['target'] !== null ? self::date_text($finding['target']) : ''
                );
            }
            $blocks[] = self::h2('Owners and target dates recorded');
            $blocks[] = self::table(array(
                'Ref.',
                'Finding',
                'Owner',
                'Target date'
            ), array(
                8,
                52,
                22,
                18
            ), $rows);
        }

        if (count($a['findings']) > 0) {
            $blocks[] = self::note('The decisions these findings ask of management (authorizing the initiatives, assigning owners and deciding which risks to accept) are set out in the Executive Summary.');
        }

        return $blocks;
    }

    private function acknowledgment(): array
    {
        $client = $this->client_row();

        return array(
            self::h1('Management acknowledgment'),
            self::text('I have reviewed this risk assessment, including the findings, the remediation priorities and the recommendations set out above.'),
            array(
                'kind' => 'signature',
                'value' => array(
                    'name' => ReportAnalysis::plain($client['contact_name'] ?? ''),
                    'title' => ReportAnalysis::plain($client['contact_title'] ?? '')
                )
            )
        );
    }

    /* --------------------------------------------------------- appendices */

    private function references(): array
    {
        $a = $this->a;
        $heading = array(
            'kind' => 'h1',
            'value' => 'Appendix A. Supporting references',
            'numbered' => false
        );

        if (count($a['evidence_files']) === 0) {
            return array(
                $heading,
                self::text('No evidence files are linked to this assessment, so the findings in this report are supported by the recorded results only. Supporting evidence should be linked to each '.$this->term('control').' before the results are relied on.')
            );
        }

        $linked = array();
        foreach ($a['controls'] as $control) {
            foreach ($control['evidence'] as $title) {
                $linked[$title][$control['ref']] = true;
            }
        }

        $rows = array();
        foreach ($a['evidence_files'] as $file) {
            $title = ReportAnalysis::plain($file['evidence_title']);
            $rows[] = array(
                $title,
                self::file_kind($file),
                self::date_text($file['date_created'] ?? ''),
                self::compact_refs(array_keys($linked[$title] ?? array()))
            );
        }

        return array(
            $heading,
            self::text(self::count_of(count($rows), 'evidence file is', 'evidence files are').' linked to the assessment. Inclusion here means the file is linked to the '.$this->term('controls').' shown; this report does not record whether its content was reviewed or found sufficient.'),
            self::table(array(
                'Evidence',
                'Type',
                'Added',
                ucfirst($this->term('controls'))
            ), array(
                42,
                10,
                18,
                30
            ), $rows)
        );
    }

    private function results_appendix(): array
    {
        $a = $this->a;
        $finding_of = array();
        foreach ($a['findings'] as $finding) {
            $finding_of[$finding['control']['ref']] = $finding;
        }

        $rows = array();
        $anchors = array();
        foreach ($a['controls'] as $control) {
            $finding = $finding_of[$control['ref']] ?? null;
            $rows[] = array(
                $control['ref'],
                self::short_title($control['title']),
                $control['family'],
                self::RESULT_LABELS[$control['result']],
                $finding !== null ? $finding['ref'] : ''
            );
            $anchors[] = $finding !== null ? $finding['anchor'] : '';
        }

        return array(
            array(
                'kind' => 'h1',
                'value' => 'Appendix B. Results for all '.$this->term('controls'),
                'numbered' => false
            ),
            self::text('The recorded result for every '.$this->term('control').' in scope, with the finding raised for each gap.'),
            array(
                'kind' => 'register',
                'value' => array(
                    'head' => array(
                        ucfirst($this->term('control')),
                        'Title',
                        ucfirst($this->term('family')),
                        'Result',
                        'Finding'
                    ),
                    'widths' => array(
                        13,
                        37,
                        22,
                        19,
                        9
                    ),
                    'rows' => $rows,
                    'small' => true,
                    'anchors' => $anchors,
                    'link_col' => 4
                )
            )
        );
    }

    /* ------------------------------------------------------------ helpers */

    /** Findings with their answered-open objectives and unanswered count worked out once. */
    private function prepared_findings(): array
    {
        $out = array();

        foreach ($this->a['findings'] as $finding) {
            $answered = array_values(array_filter($finding['control']['open'], function ($objective) {
                return $objective['result'] !== 'Not Assessed';
            }));
            $finding['answered_open'] = $answered;
            $finding['unanswered'] = count($finding['control']['open']) - count($answered);
            $out[] = $finding;
        }

        return $out;
    }

    private function findings_by_control(): array
    {
        $out = array();

        foreach ($this->a['findings'] as $finding) {
            $out[$finding['control']['ref']] = $finding;
        }

        return $out;
    }

    private function priority_count(string $priority): int
    {
        return count(array_filter($this->a['findings'], function ($finding) use ($priority) {
            return $finding['priority'] === $priority;
        }));
    }

    /** The Remediation Plan's initiatives, so R-IDs match across reports. */
    private function initiatives(): array
    {
        if ($this->plan === null) {
            $this->plan = ReportRemediationPlan::plan_initiatives($this->a);
        }

        return $this->plan;
    }

    private function initiative_titles(): array
    {
        $out = array();

        foreach ($this->initiatives() as $initiative) {
            $out[$initiative['id']] = $initiative['title'];
        }

        return $out;
    }

    private function initiative_of(array $finding): ?string
    {
        foreach ($this->initiatives() as $initiative) {
            if (in_array($finding['control']['ref'], $initiative['controls'], true)) {
                return $initiative['id'];
            }
        }

        return null;
    }

    /** Stored AI patterns when present, otherwise the calculated ones, as label, text and control refs. */
    private function patterns(): array
    {
        $out = array();
        $ai = $this->a['patterns_ai'] ?? array();

        if (count($ai) > 0) {
            foreach ($ai as $pattern) {
                $out[] = array(
                    'label' => $pattern['label'],
                    'text' => self::paragraphs($pattern['description']).(count($pattern['refs']) > 0 ? "\n\n".'Involves '.ReportGapAnalysis::grouped_refs($pattern['refs'], $this->a['controls']).'.' : ''),
                    'refs' => $pattern['refs']
                );
            }
            return $out;
        }

        foreach (array_slice($this->a['patterns'], 0, 5) as $pattern) {
            $out[] = array(
                'label' => $pattern['label'],
                'text' => $pattern['sentence'],
                'refs' => $pattern['controls']
            );
        }

        return $out;
    }

    private function client_row(): array
    {
        if ($this->client_row === null) {
            $rows = (new ClientsModel())->get_client((int) $this->a['assessment']['client_id'], $this->company_id);
            $this->client_row = $rows[0] ?? array();
        }

        return $this->client_row;
    }

    private function firm(): string
    {
        return ReportAnalysis::plain($this->company['company_name'] ?? '');
    }

    private static function domain_rating(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        if ($score >= 85) {
            return 'Satisfactory';
        }

        if ($score >= 60) {
            return 'Satisfactory with exceptions';
        }

        if ($score >= 30) {
            return 'Needs improvement';
        }

        return 'Unsatisfactory';
    }

    /**
     * True when the family scores differ enough (a standard deviation of
     * ten points or more) for a bar chart to say something; near-equal bars
     * are left to the scope table, which shows each score.
     */
    private function scores_vary(): bool
    {
        $scores = array();

        foreach ($this->a['families'] as $family) {
            if ($family['score'] !== null) {
                $scores[] = $family['score'];
            }
        }

        if (count($scores) < 3) {
            return false;
        }

        $mean = array_sum($scores) / count($scores);
        $spread = 0;
        foreach ($scores as $score) {
            $spread += ($score - $mean) ** 2;
        }

        return sqrt($spread / count($scores)) >= 10;
    }

    /** A sub-heading kept out of the numbering and the contents page. */
    private static function subhead(string $text): array
    {
        return self::h3($text);
    }

    /** The period as it reads mid-sentence. */
    private function period_phrase(): string
    {
        $period = $this->a['period'];

        if ($period === 'Ongoing') {
            return '';
        }

        return strpos($period, 'From ') === 0 ? 'beginning '.substr($period, 5) : 'for the period '.$period;
    }

    private static function panel(string $title, string $tone, string $body): array
    {
        return array(
            'kind' => 'panel',
            'value' => array(
                'title' => $title,
                'tone' => $tone,
                'body' => $body
            )
        );
    }

    private static function count_of(int $n, string $one, string $many): string
    {
        return $n.' '.($n === 1 ? $one : $many);
    }

    /** A control title without a trailing full stop, cut at a sensible length for tables and headings. */
    private static function short_title(string $title): string
    {
        $title = rtrim(trim($title), '.');

        if (mb_strlen($title) <= 110) {
            return $title;
        }

        $cut = mb_substr($title, 0, 105);
        $space = mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $space !== false ? $space : 105), ' ,;').'…';
    }

    /** F-01, F-02, F-03, F-07 as "F-01 to F-03, F-07". */
    private static function compact_refs(array $refs): string
    {
        $parsed = array();
        foreach ($refs as $ref) {
            if (preg_match('/^([A-Z]+-)(\d+)$/', $ref, $match)) {
                $parsed[] = array(
                    $match[1],
                    (int) $match[2],
                    strlen($match[2])
                );
            } else {
                return implode(', ', $refs);
            }
        }

        $out = array();
        $count = count($parsed);
        for ($i = 0; $i < $count; $i++) {
            $start = $parsed[$i];
            $end = $start;
            while ($i + 1 < $count && $parsed[$i + 1][1] === $end[1] + 1 && $parsed[$i + 1][0] === $end[0]) {
                $i++;
                $end = $parsed[$i];
            }
            $first = $start[0].str_pad((string) $start[1], $start[2], '0', STR_PAD_LEFT);
            $last = $end[0].str_pad((string) $end[1], $end[2], '0', STR_PAD_LEFT);
            if ($end[1] - $start[1] >= 2) {
                $out[] = $first.' to '.$last;
            } elseif ($end[1] !== $start[1]) {
                $out[] = $first;
                $out[] = $last;
            } else {
                $out[] = $first;
            }
        }

        return implode(', ', $out);
    }

    private static function file_kind(array $file): string
    {
        $extension = strtoupper(pathinfo(ReportAnalysis::plain($file['file_name'] ?? ''), PATHINFO_EXTENSION));

        if ($extension !== '') {
            return $extension;
        }

        $type = ReportAnalysis::plain($file['file_type'] ?? '');

        return strtoupper(strpos($type, '/') !== false ? substr($type, strpos($type, '/') + 1) : $type);
    }

    private static function date_text($value): string
    {
        $time = strtotime((string) $value);

        return $time ? date('F j, Y', $time) : '';
    }

    private static function normal(string $text): string
    {
        return strtolower(rtrim(trim($text), '.'));
    }

    private static function sentence(string $text): string
    {
        $text = rtrim(trim($text));

        return $text === '' ? '' : ucfirst($text).(preg_match('/[.!?]$/', $text) ? '' : '.');
    }

    private static function strings($value): array
    {
        if (!is_array($value)) {
            return array();
        }

        return array_values(array_filter(array_map(function ($item) {
            return is_string($item) ? trim($item) : '';
        }, $value), function ($item) {
            return $item !== '';
        }));
    }

    private static function items($value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : array();
    }
}
