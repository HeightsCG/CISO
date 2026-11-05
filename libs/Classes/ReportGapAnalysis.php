<?php
/**
 * The gap analysis: a diagnostic of where the recorded results fall short of
 * the framework's requirements. A summary with results by family and a
 * navigable gap register, the patterns that recur across the gaps (only
 * those the results support), then each gap by family: the requirement,
 * the current result, the deficiency, each open objective against its
 * expected condition, a brief suggested improvement, what evidence would
 * close it and its references. Risk and consequences belong to the risk
 * assessment and are not repeated here.
 */
class ReportGapAnalysis extends ReportBuilder
{
    const STATE = array(
        'Implemented' => 'Met',
        'Partially Implemented' => 'Partially met',
        'Not Implemented' => 'Not met',
        'Not Assessed' => 'Not answered'
    );

    private $initiative_of;
    private $initiative_titles;
    private $finding_of;

    protected function title(): string
    {
        return 'Gap Analysis';
    }

    protected function doc_options(): array
    {
        return array(
            'toc' => true,
            'numbered' => true
        );
    }

    protected function blocks(): array
    {
        $this->index();

        $blocks = $this->summary();

        if (count($this->a['gaps']) === 0) {
            return $blocks;
        }

        return array_merge($blocks, $this->patterns(), $this->details());
    }

    private function index(): void
    {
        $initiatives = ReportRemediationPlan::plan_initiatives($this->a);
        $this->initiative_of = ReportRemediationPlan::initiative_of($initiatives);
        $this->initiative_titles = array();

        foreach ($initiatives as $initiative) {
            $this->initiative_titles[$initiative['id']] = $initiative['title'];
        }

        $this->finding_of = array();

        foreach ($this->a['findings'] as $finding) {
            $this->finding_of[$finding['control']['ref']] = $finding;
        }
    }

    /* ------------------------------------------------------------ summary */

    private function summary(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $gaps = $a['gaps'];
        $gap_total = count($gaps);
        $client = $a['client'] !== '' ? $a['client'] : 'the organization';

        $open = 0;
        $not_met = 0;
        foreach ($gaps as $gap) {
            foreach ($gap['open'] as $objective) {
                if ($objective['result'] !== 'Not Assessed') {
                    $open++;
                }
                if ($objective['result'] === 'Not Implemented') {
                    $not_met++;
                }
            }
        }

        $p1 = 'This gap analysis compares the results recorded in '.$a['narratives']['subject'].' with what the framework requires, and sets out, '.$this->term('control').' by '.$this->term('control').', what is missing for '.$client.' to meet each requirement. ';
        $p1 .= 'It is a diagnostic of requirements; the exposure these gaps create is interpreted in the Risk Assessment, and the corrective work is sequenced in the Remediation Plan.';

        if ($gap_total === 0) {
            $p2 = 'No gaps are recorded: every assessed '.$this->term('control').' is implemented or not applicable.';
        } else {
            $p2 = $gap_total.' of the '.($a['assessed'] - $c['Not Applicable']).' assessed, applicable '.$this->term('controls').' are not fully implemented: '.$c['Not Implemented'].' not implemented and '.$c['Partially Implemented'].' partially implemented, across '.count(array_filter($a['families'], function ($family) {
                return count($family['gaps']) > 0;
            })).' of '.count($a['families']).' '.$this->term('families').'.';
            if ($open > 0) {
                $p2 .= ' Within them, '.$open.' '.($open === 1 ? $this->term('objective').' is' : $this->term('objectives').' are').' not fully met'.($not_met > 0 ? ', '.$not_met.' of '.($not_met === 1 ? 'it' : 'them').' not met at all' : '').'.';
            }
        }

        if ($c['Not Assessed'] > 0) {
            $p2 .= ' '.$c['Not Assessed'].' of '.$a['total'].' '.$this->term('controls').' have not been assessed yet. They are outside this analysis and may contain further gaps, so the analysis is '.($c['Not Assessed'] > $a['total'] / 2 ? 'preliminary' : 'incomplete').' until they are.';
        }

        $p3 = 'Results come from the answers recorded in the assessment. Text recorded by the assessor is labelled as such; descriptions of what is missing and how to close it are derived from the recorded answers and the framework\'s requirements. The remediation priority shown for each gap is derived from its result and subject area, not an assessed risk rating.';

        $blocks = array(
            self::h1('Summary'),
            self::text($p1."\n\n".$p2."\n\n".$p3)
        );

        $metrics = array(
            array(
                'value' => (string) $gap_total,
                'label' => ucfirst($this->term('controls')).' with gaps',
                'sub' => 'of '.$a['total'].' in scope',
                'tone' => $gap_total > 0 ? 'warning' : 'positive'
            ),
            array(
                'value' => (string) $c['Not Implemented'],
                'label' => 'Not implemented',
                'tone' => $c['Not Implemented'] > 0 ? 'critical' : 'neutral'
            ),
            array(
                'value' => (string) $c['Partially Implemented'],
                'label' => 'Partially implemented',
                'tone' => $c['Partially Implemented'] > 0 ? 'warning' : 'neutral'
            )
        );

        if ($open > 0) {
            $metrics[] = array(
                'value' => (string) $open,
                'label' => 'Open '.$this->term('objectives'),
                'sub' => $not_met.' not met, '.($open - $not_met).' partially met'
            );
        } elseif ($c['Not Assessed'] > 0) {
            $metrics[] = array(
                'value' => (string) $c['Not Assessed'],
                'label' => 'Not yet assessed',
                'tone' => 'neutral'
            );
        }

        $blocks[] = array(
            'kind' => 'metrics',
            'value' => array(
                'items' => $metrics
            )
        );

        $segments = array();
        foreach (array(
            'Implemented' => 'positive',
            'Partially Implemented' => 'warning',
            'Not Implemented' => 'critical',
            'Not Applicable' => 'neutral',
            'Not Assessed' => null
        ) as $result => $tone) {
            if ($c[$result] === 0) {
                continue;
            }
            $segment = array(
                'label' => $result,
                'value' => $c[$result]
            );
            if ($tone === null) {
                $segment['color'] = '#D0D5DD';
            } else {
                $segment['tone'] = $tone;
            }
            $segments[] = $segment;
        }

        $blocks[] = self::h3('Results of all '.$a['total'].' '.$this->term('controls'));
        $blocks[] = array(
            'kind' => 'stackbar',
            'value' => array(
                'segments' => $segments
            )
        );

        if ($a['score'] !== null) {
            $blocks[] = self::note('Implementation score: '.$a['score'].'% (implemented counts in full, partially implemented counts half, over the assessed applicable '.$this->term('controls').'). It measures how fully the '.$this->term('controls').' are in place and is not a compliance opinion or certification result.');
        }

        $blocks = array_merge($blocks, $this->family_table());

        if ($gap_total > 0) {
            $blocks = array_merge($blocks, $this->register());
        }

        return $blocks;
    }

    private function family_table(): array
    {
        $c = $this->a['counts'];
        $show_na = $c['Not Applicable'] > 0;
        $show_unassessed = $c['Not Assessed'] > 0;
        $rows = array();
        $totals = array(
            'controls' => 0,
            'Implemented' => 0,
            'Partially Implemented' => 0,
            'Not Implemented' => 0,
            'Not Applicable' => 0,
            'Not Assessed' => 0,
            'open' => 0
        );

        $unassessed = array();

        foreach ($this->a['families'] as $name => $family) {
            if ($family['counts']['Not Assessed'] === count($family['controls'])) {
                $unassessed[] = $name;
            }
        }

        $skip = count($unassessed) >= 3;

        foreach ($this->a['families'] as $name => $family) {
            $totals['controls'] += count($family['controls']);
            foreach (array(
                'Implemented',
                'Partially Implemented',
                'Not Implemented',
                'Not Applicable',
                'Not Assessed'
            ) as $result) {
                $totals[$result] += $family['counts'][$result];
            }
            if ($skip && in_array($name, $unassessed, true)) {
                continue;
            }
            $open = 0;
            foreach ($family['gaps'] as $gap) {
                $open += count(array_filter($gap['open'], function ($objective) {
                    return $objective['result'] !== 'Not Assessed';
                }));
            }
            $row = array(
                $name,
                (string) count($family['controls']),
                (string) $family['counts']['Implemented'],
                (string) $family['counts']['Partially Implemented'],
                (string) $family['counts']['Not Implemented']
            );
            if ($show_na) {
                $row[] = (string) $family['counts']['Not Applicable'];
            }
            if ($show_unassessed) {
                $row[] = (string) $family['counts']['Not Assessed'];
            }
            $row[] = (string) $open;
            $row[] = $family['score'] !== null ? $family['score'].'%' : 'Not scored';
            $rows[] = $row;
            $totals['open'] += $open;
        }

        $total_row = array(
            $skip ? 'Total, all '.$this->term('families') : 'Total',
            (string) $totals['controls'],
            (string) $totals['Implemented'],
            (string) $totals['Partially Implemented'],
            (string) $totals['Not Implemented']
        );
        $head = array(
            ucfirst($this->term('family')),
            ucfirst($this->term('controls')),
            'Implemented',
            'Partial',
            'Not impl.'
        );
        $widths = array(
            33,
            10,
            11,
            9,
            9
        );
        if ($show_na) {
            $head[] = 'Not applicable';
            $widths[] = 10;
            $total_row[] = (string) $totals['Not Applicable'];
        }
        if ($show_unassessed) {
            $head[] = 'Not assessed';
            $widths[] = 10;
            $total_row[] = (string) $totals['Not Assessed'];
        }
        $head[] = 'Open '.$this->term('objectives');
        $widths[] = 10;
        $total_row[] = (string) $totals['open'];
        $head[] = 'Score';
        $widths[] = 9;
        $total_row[] = $this->a['score'] !== null ? $this->a['score'].'%' : 'Not scored';
        $rows[] = $total_row;

        $sum = array_sum($widths);
        $widths[0] += 100 - $sum;

        $table = self::table($head, $widths, $rows);
        $table['value']['total'] = true;

        $blocks = array(
            self::h2('Results by '.$this->term('family'))
        );

        if ($skip) {
            $listed = count($this->a['families']) - count($unassessed);
            $blocks[] = self::text('The table lists '.($listed === 1 ? 'the one '.$this->term('family') : 'the '.$listed.' '.$this->term('families')).' with at least one assessed '.$this->term('control').'; the totals cover all '.count($this->a['families']).'. No '.$this->term('control').' has been assessed yet in the other '.count($unassessed).': '.self::join_list($unassessed).'.');
        }

        $blocks[] = $table;

        return $blocks;
    }

    private function register(): array
    {
        $rows = array();
        $anchors = array();

        foreach ($this->a['gaps'] as $gap) {
            $all = count($gap['objectives']);
            $rows[] = array(
                $gap['ref'],
                self::short($gap['title'], 80),
                $gap['family'],
                $gap['result'] === 'Not Implemented' ? 'Not implemented' : 'Partial',
                $all > 0 ? count($gap['open']).' of '.$all : 'Not itemized',
                $gap['priority']
            );
            $anchors[] = 'gap-'.$gap['ref'];
        }

        $table = self::table(array(
            'ID',
            ucfirst($this->term('control')),
            ucfirst($this->term('family')),
            'Result',
            'Open',
            'Priority'
        ), array(
            11,
            38,
            21,
            13,
            8,
            9
        ), $rows);
        $table['kind'] = 'register';
        $table['value']['anchors'] = $anchors;

        return array(
            self::h2('Gap register'),
            self::text('Every gap, highest derived remediation priority first. "Open" counts the '.$this->term('objectives').' not fully met or not yet answered. Select an ID to go to its detailed analysis.'),
            $table
        );
    }

    /* ----------------------------------------------------------- patterns */

    private function patterns(): array
    {
        $a = $this->a;
        $gaps = $a['gaps'];
        $blocks = array();

        $partial = array_values(array_filter($gaps, function ($gap) {
            return $gap['result'] === 'Partially Implemented';
        }));
        $met = 0;
        $defined = 0;
        foreach ($partial as $gap) {
            foreach ($gap['objectives'] as $objective) {
                $defined++;
                if ($objective['result'] === 'Implemented') {
                    $met++;
                }
            }
        }

        $nature = array();
        if (count($gaps) >= 3) {
            if (count($partial) === count($gaps)) {
                $nature[] = 'Every gap is a partial implementation: no '.$this->term('control').' is entirely absent.';
            } elseif (count($partial) >= 0.75 * count($gaps)) {
                $nature[] = count($partial).' of the '.count($gaps).' gaps are partial implementations, so most requirements are addressed in part and need completing or formalizing rather than building from nothing.';
            } elseif (count($partial) <= 0.25 * count($gaps)) {
                $nature[] = (count($gaps) - count($partial)).' of the '.count($gaps).' gaps are '.$this->term('controls').' with no implementation recorded, so the work is mostly to establish controls rather than complete them.';
            }
            if ($defined > 0) {
                $nature[] = 'Across the partially implemented '.$this->term('controls').', '.$met.' of '.$defined.' '.$this->term('objectives').' ('.self::pct($met, $defined).') are already met, which shows how much of each requirement is in place to build on.';
            }
            $no_evidence = count(array_filter($gaps, function ($gap) {
                return $gap['evidence_count'] === 0;
            }));
            if ($no_evidence === count($gaps)) {
                $nature[] = 'None of the gaps has linked evidence, so the deficiencies below rest on the recorded answers alone; the exact shortfall should be confirmed when evidence is gathered.';
            } elseif ($no_evidence > 0) {
                $nature[] = $no_evidence.' of the '.count($gaps).' gaps have no linked evidence; for those, the exact shortfall should be confirmed when evidence is gathered.';
            }
        }

        $ai = $a['patterns_ai'] ?? array();
        $deficiency = array();

        if (count($ai) > 0) {
            foreach ($ai as $pattern) {
                $deficiency[] = array(
                    'title' => $pattern['label'],
                    'body' => $pattern['description'].(count($pattern['refs']) > 0 ? "\n\nRelated: ".implode(', ', $pattern['refs']).'.' : '')
                );
            }
        } else {
            foreach ($a['patterns'] as $pattern) {
                $deficiency[] = array(
                    'title' => $pattern['label'],
                    'body' => $pattern['sentence']
                );
            }
        }

        $themes = $a['themes'];

        if (count($gaps) < 3 || (count($nature) === 0 && count($deficiency) === 0 && count($themes) === 0)) {
            return $blocks;
        }

        $blocks[] = self::h1('Common patterns');
        $blocks[] = self::text('The patterns below recur across several gaps and are supported by the recorded results. Addressing them at their common cause is usually more effective than treating each '.$this->term('control').' separately.');

        if (count($nature) > 0) {
            $blocks[] = self::h2('Nature of the gaps');
            $blocks[] = self::text(implode("\n\n", $nature));
        }

        if (count($deficiency) > 0) {
            $blocks[] = self::h2('Recurring deficiencies');
            if (count($ai) === 0) {
                $blocks[] = self::text('Identified from the wording of the open '.$this->term('objectives').'; a pattern is reported only when it appears in at least three '.$this->term('objectives').' across two or more '.$this->term('controls').'.');
            }
            foreach ($deficiency as $item) {
                $blocks[] = array(
                    'kind' => 'panel',
                    'value' => array(
                        'title' => $item['title'],
                        'tone' => 'neutral',
                        'body' => $item['body']
                    )
                );
            }
        }

        if (count($themes) > 0) {
            $rows = array();
            foreach ($themes as $theme) {
                $rows[] = array(
                    $theme['name'],
                    (string) count($theme['refs']),
                    implode(', ', $theme['refs']),
                    implode(', ', $theme['families'])
                );
            }
            $blocks[] = self::h2('Concentration by subject area');
            $blocks[] = self::text('Subject areas with two or more gaps. A subject area can cut across '.$this->term('families').', which is where a single remediation effort can close several gaps.');
            $blocks[] = self::table(array(
                'Subject area',
                'Gaps',
                ucfirst($this->term('controls')),
                ucfirst($this->term('families'))
            ), array(
                27,
                7,
                36,
                30
            ), $rows);
        }

        return $blocks;
    }

    /* ------------------------------------------------------------ details */

    private function details(): array
    {
        $blocks = array(
            self::h1('Detailed gap analysis'),
            self::text('Each gap below is grouped by '.$this->term('family').' and gives the requirement, the current result and deficiency, the '.$this->term('objectives').' that are not fully met against their expected condition, a brief suggested improvement and the evidence needed to show the gap is closed. Exposure, scenarios and suggested approaches are in the Risk Assessment.'
                .($this->unspecified() ? "\n\n".'Where a '.$this->term('objective').' is recorded as partially met and no assessor note says what is lacking, "What is missing" reads "Part, not specified": the element that is missing has to be confirmed with the control owner before remediation is scoped.' : ''))
        );

        foreach ($this->a['families'] as $name => $family) {
            if (count($family['gaps']) === 0) {
                continue;
            }

            $gaps = $family['gaps'];
            usort($gaps, function ($x, $y) {
                return strnatcmp($x['ref'], $y['ref']);
            });

            $open = 0;
            $all = 0;
            foreach ($gaps as $gap) {
                $all += count($gap['objectives']);
                $open += count(array_filter($gap['open'], function ($objective) {
                    return $objective['result'] !== 'Not Assessed';
                }));
            }

            $lead = count($gaps).' of '.count($family['controls']).' '.$this->term('controls').' in this '.$this->term('family').' '.(count($gaps) === 1 ? 'is' : 'are').' not fully implemented';
            if ($all > 0) {
                $lead .= '; '.$open.' of their '.$all.' '.$this->term('objectives').' are not fully met';
            }
            $lead .= '.';
            if ($family['counts']['Not Assessed'] > 0) {
                $lead .= ' '.$family['counts']['Not Assessed'].' '.($family['counts']['Not Assessed'] === 1 ? $this->term('control').' has' : $this->term('controls').' have').' not been assessed.';
            }

            $blocks[] = self::h2($name);
            $blocks[] = self::text($lead);

            foreach ($gaps as $gap) {
                $blocks = array_merge($blocks, $this->gap_blocks($gap));
            }
        }

        return $blocks;
    }

    /** Whether any partially met objective lacks an assessor note saying what is missing. */
    private function unspecified(): bool
    {
        foreach ($this->a['gaps'] as $gap) {
            foreach ($gap['open'] as $objective) {
                if ($objective['result'] === 'Partially Implemented' && count($objective['assessor']) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function gap_blocks(array $gap): array
    {
        $finding = $this->finding_of[$gap['ref']] ?? array();
        $initiatives = $this->initiative_of[$gap['ref']] ?? array();
        $all = count($gap['objectives']);
        $answered = array_values(array_filter($gap['open'], function ($objective) {
            return $objective['result'] !== 'Not Assessed';
        }));

        $meta = array();
        if ($all > 0) {
            $unanswered = count($gap['open']) - count($answered);
            $meta[] = array(
                'Open '.$this->term('objectives'),
                count($gap['open']).' of '.$all.($unanswered > 0 ? ' ('.$unanswered.' not answered)' : '')
            );
        }
        $meta[] = array(
            'Priority',
            $gap['priority'].' (derived)'
        );
        if ($gap['risk_rating'] !== null) {
            $meta[] = array(
                'Assessed risk',
                $gap['risk_rating']
            );
        }
        if (isset($finding['ref'])) {
            $meta[] = array(
                'Risk Assessment',
                $finding['ref']
            );
        }
        if (count($initiatives) > 0) {
            $meta[] = array(
                'Remediation Plan',
                implode(', ', $initiatives)
            );
        }

        $sections = array();
        $requirement = $this->requirement($gap);
        if ($requirement !== '') {
            $sections[] = array(
                'Requirement and expected condition',
                $requirement
            );
        }
        $sections[] = array(
            'Identified deficiency',
            $this->deficiency($gap, $finding)
        );

        $after = array(
            array(
                'Suggested improvement',
                $this->improvement($gap, $finding, $initiatives)
            ),
            array(
                'Evidence needed',
                $this->validation($gap, $finding, $answered)
            )
        );

        $sources = array_values(array_filter($finding['source_refs_ai'] ?? array(), function ($source) use ($gap) {
            return stripos($source, $gap['ref']) === false;
        }));
        if (count($sources) > 0) {
            $after[] = array(
                'Related references',
                implode('; ', $sources).'.'
            );
        }

        $blocks = array(
            array(
                'kind' => 'finding',
                'value' => array(
                    'ref' => $this->a['standard'].' '.$gap['ref'],
                    'heading' => self::short($gap['title'], 140),
                    'severity' => $gap['result'] === 'Not Implemented' ? 'High' : 'Medium',
                    'rating' => $gap['result'],
                    'anchor' => 'gap-'.$gap['ref'],
                    'meta' => $meta,
                    'sections' => $sections
                )
            )
        );

        if (count($gap['open']) > 0) {
            $blocks[] = $this->objective_table($gap);
        }

        $blocks[] = array(
            'kind' => 'kv',
            'value' => array(
                'rows' => $after
            )
        );

        return $blocks;
    }

    private function requirement(array $gap): string
    {
        $parts = array();

        if ($gap['description'] !== '' && rtrim($gap['description'], '.') !== rtrim($gap['title'], '.')) {
            $parts[] = rtrim($gap['description'], '.').'.';
        }

        $lines = array();
        foreach (count($gap['objectives']) > 0 ? array() : $gap['expectations'] as $pair) {
            $lines[] = ($pair[0] !== '' && !preg_match('/^Ex\d+$/i', $pair[0]) ? $pair[0].': ' : '').rtrim($pair[1], '.').'.';
        }

        if (count($lines) > 0) {
            $parts[] = 'Expected in practice:'."\n".self::bullets($lines);
        }

        return implode("\n\n", $parts);
    }

    private function deficiency(array $gap, array $finding): string
    {
        $t = $this->a['terms'];
        $parts = array();

        if (isset($gap['assessor']['gap_description'])) {
            $parts[] = 'Assessor observation: '.$gap['assessor']['gap_description'];
        }
        if (isset($gap['assessor']['notes'])) {
            $parts[] = 'Assessor note: '.$gap['assessor']['notes'];
        }
        if (isset($gap['assessor']['implementation_description'])) {
            $parts[] = 'Current implementation (as recorded): '.$gap['assessor']['implementation_description'];
        }

        $counts = array_fill_keys(array_keys(self::STATE), array());
        foreach ($gap['objectives'] as $objective) {
            $counts[$objective['result']][] = $objective['short'];
        }

        $all = count($gap['objectives']);
        $text = 'Recorded as '.strtolower($gap['result']).'.';

        if ($all === 0) {
            $text .= ' No '.$t['objectives'].' are assessed individually for this '.$t['control'];
            $text .= count($parts) > 0 ? '.' : ' and no assessor observation is recorded, so the specific shortfall has to be confirmed with the assessor.';
        } else {
            $states = array();
            foreach (array(
                'Implemented' => 'met',
                'Partially Implemented' => 'partially met',
                'Not Implemented' => 'not met',
                'Not Assessed' => 'not yet answered'
            ) as $result => $label) {
                $n = count($counts[$result]);
                if ($n > 0) {
                    $states[] = $n.' '.$label.($result === 'Implemented' && $n <= 4 ? ' ('.implode(', ', $counts[$result]).')' : '');
                }
            }
            $text .= ' Of its '.$all.' '.($all === 1 ? $t['objective'] : $t['objectives']).', '.self::join_list($states).'.';
        }

        $parts[] = $text;

        return implode("\n\n", $parts);
    }

    private function objective_table(array $gap): array
    {
        $rows = array();

        foreach ($gap['open'] as $objective) {
            $title = rtrim($objective['title'], '.');
            $condition = $objective['description'] !== '' ? $objective['description'] : $objective['title'];
            $label = $objective['short'];
            if ($title !== '' && $title !== rtrim($condition, '.')) {
                $label .= ' '.$title;
            }
            $rows[] = array(
                $label,
                $condition,
                self::STATE[$objective['result']],
                $this->missing($objective)
            );
        }

        return self::table(array(
            ucfirst($this->term('objective')),
            'Expected condition',
            'Recorded answer',
            'What is missing'
        ), array(
            29,
            47,
            11,
            13
        ), $rows);
    }

    private function missing(array $objective): string
    {
        $recorded = array();
        foreach (array(
            'gap_description' => '',
            'notes' => 'Assessor note: ',
            'implementation_description' => 'In place: '
        ) as $field => $label) {
            if (isset($objective['assessor'][$field])) {
                $recorded[] = $label.$objective['assessor'][$field];
            }
        }

        if (count($recorded) > 0) {
            return implode(' ', $recorded);
        }

        $text = array(
            'Not Implemented' => 'Entire condition',
            'Partially Implemented' => 'Part, not specified',
            'Not Assessed' => 'Not yet known'
        )[$objective['result']];

        if (count($objective['evidence']) > 0) {
            $text .= '; evidence linked: '.implode('; ', $objective['evidence']);
        }

        return $text;
    }

    private function improvement(array $gap, array $finding, array $initiatives): string
    {
        $t = $this->a['terms'];
        $parts = array();

        if (isset($gap['assessor']['recommendation'])) {
            $parts[] = 'Assessor recommendation: '.$gap['assessor']['recommendation'];
        }

        $not = array();
        $partial = array();
        $unanswered = 0;
        foreach ($gap['open'] as $objective) {
            if ($objective['result'] === 'Not Implemented') {
                $not[] = $objective['short'];
            } elseif ($objective['result'] === 'Partially Implemented') {
                $partial[] = $objective['short'];
            } else {
                $unanswered++;
            }
        }

        $steps = array();
        if (count($not) > 0) {
            $steps[] = 'Establish '.(count($not) === 1 ? $not[0] : implode(', ', $not)).', which '.(count($not) === 1 ? 'has' : 'have').' nothing in place.';
        }
        if (count($partial) > 0) {
            $steps[] = 'Confirm what is missing from '.implode(', ', $partial).' and complete '.(count($partial) === 1 ? 'it' : 'them').'.';
        }
        if ($unanswered > 0) {
            $steps[] = 'Assess the '.$unanswered.' unanswered '.($unanswered === 1 ? $t['objective'] : $t['objectives']).' to establish the full extent of the gap.';
        }
        if (count($gap['objectives']) === 0 || count($gap['open']) === 0) {
            $steps[] = 'Agree with the assessor which elements of the requirement are missing, then put them in place.';
        }
        if (count($steps) > 0) {
            $parts[] = implode(' ', $steps);
        }

        if (count($initiatives) > 0) {
            $names = array();
            foreach ($initiatives as $id) {
                $names[] = $id.' '.($this->initiative_titles[$id] ?? '');
            }
            $parts[] = 'Remediation Plan: '.self::join_list($names).'.';
        }

        return implode("\n\n", $parts);
    }

    private function validation(array $gap, array $finding, array $answered): string
    {
        $t = $this->a['terms'];
        $parts = array();

        if (count($gap['evidence']) > 0) {
            $parts[] = 'Linked evidence: '.implode('; ', $gap['evidence']).'.';
        }

        if (count($answered) > 0) {
            $need = 'Evidence showing each open '.$t['objective'].' ('.implode(', ', array_column($answered, 'short')).') meets its expected condition';
        } elseif (count($gap['open']) > 0) {
            $need = 'Evidence for each '.$t['objective'].' still to be answered ('.implode(', ', array_column($gap['open'], 'short')).'), to support its result when assessed';
        } else {
            $need = 'Evidence showing the '.$t['control'].' is in place as required';
        }
        $any = count(array_filter($this->a['gaps'], function ($other) {
            return $other['evidence_count'] > 0;
        })) > 0;
        $parts[] = $need.($gap['evidence_count'] === 0 && ($any || count($this->a['gaps']) < 3) ? '; nothing is linked yet.' : '.');

        return implode("\n\n", $parts);
    }

    /* ------------------------------------------------------------ helpers */

    private static function short(string $text, int $length): string
    {
        $text = rtrim($text, '.');

        if (strlen($text) <= $length) {
            return $text;
        }

        $cut = substr($text, 0, $length);
        $space = strrpos($cut, ' ');

        return ($space !== false ? substr($cut, 0, $space) : $cut).'...';
    }

    /* -------------------------------------------------------------- excel */

    protected function sheets(): array
    {
        $this->index();
        $gaps = array();
        $points = array();

        foreach ($this->a['gaps'] as $gap) {
            $finding = $this->finding_of[$gap['ref']] ?? array();
            $initiatives = $this->initiative_of[$gap['ref']] ?? array();
            $answered = array_values(array_filter($gap['open'], function ($objective) {
                return $objective['result'] !== 'Not Assessed';
            }));
            $gaps[] = array(
                $gap['priority'],
                $gap['family'],
                $gap['ref'],
                $gap['title'],
                $gap['result'],
                count($gap['objectives']) > 0 ? count($gap['open']).' of '.count($gap['objectives']) : '',
                $this->requirement($gap),
                $this->deficiency($gap, $finding),
                $this->improvement($gap, $finding, $initiatives),
                $this->validation($gap, $finding, $answered),
                implode(', ', $initiatives),
                (string) ($gap['risk_rating'] ?? '')
            );
            foreach ($gap['open'] as $objective) {
                $points[] = array(
                    $gap['ref'],
                    $objective['ref'],
                    rtrim($objective['title'], '.'),
                    $objective['description'] !== '' ? $objective['description'] : $objective['title'],
                    self::STATE[$objective['result']],
                    $this->missing($objective)
                );
            }
        }

        $sheets = array(
            array(
                'name' => 'Gaps',
                'head' => array(
                    'Priority',
                    ucfirst($this->term('family')),
                    'Identifier',
                    ucfirst($this->term('control')),
                    'Current result',
                    'Open '.$this->term('objectives'),
                    'Requirement and expected condition',
                    'Identified deficiency',
                    'Suggested improvement',
                    'Evidence needed',
                    'Initiative',
                    'Assessed risk'
                ),
                'widths' => array(
                    10,
                    24,
                    14,
                    45,
                    20,
                    12,
                    70,
                    60,
                    70,
                    60,
                    12,
                    14
                ),
                'rows' => $gaps
            ),
            array(
                'name' => 'Open '.$this->term('objectives'),
                'head' => array(
                    ucfirst($this->term('control')),
                    'Identifier',
                    ucfirst($this->term('objective')),
                    'Expected condition',
                    'Current answer',
                    'What is missing'
                ),
                'widths' => array(
                    14,
                    18,
                    45,
                    70,
                    16,
                    50
                ),
                'rows' => $points
            )
        );

        if (count($points) === 0) {
            array_pop($sheets);
        }

        return $sheets;
    }
}
