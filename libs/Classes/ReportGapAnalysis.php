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
            $blocks[] = self::note('Implementation score: '.$a['score'].'%. '.$a['score_method']['method'].($a['score_method']['points'] !== null ? ' '.$a['score_method']['points']['sentence'] : '').($a['score_method']['outcome'] !== null ? ' '.$a['score_method']['outcome']['sentence'] : ''));
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
            'Remediation priority'
        ), array(
            11,
            35,
            20,
            13,
            8,
            13
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
        $condensed = array();

        if (count($ai) > 0) {
            foreach ($ai as $pattern) {
                $condensed[] = array(
                    $pattern['label'],
                    self::grouped_refs($pattern['refs'], $a['controls'])
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

        if (count($gaps) < 3 || (count($nature) === 0 && count($deficiency) === 0 && count($condensed) === 0 && count($themes) === 0)) {
            return $blocks;
        }

        $blocks[] = self::h1('Common patterns');
        $blocks[] = self::text('The patterns below recur across several gaps and are supported by the recorded results. Addressing them together is usually more effective than treating each '.$this->term('control').' separately.');

        if (count($nature) > 0) {
            $blocks[] = self::h2('Nature of the gaps');
            $blocks[] = self::text(implode("\n\n", $nature));
        }

        if (count($condensed) > 0) {
            $blocks[] = self::h2('Recurring deficiencies');
            $blocks[] = self::text('Each pattern below groups open '.$this->term('objectives').' that have recorded results in common, with every '.$this->term('control').' and '.$this->term('objective').' it involves. The analysis of each pattern, the exposure it creates and the shared remedy are set out in the Risk Assessment under Cross-cutting patterns; the open elements are detailed requirement by requirement in the next chapter.');
            $blocks[] = self::table(array(
                'Pattern',
                ucfirst($this->term('controls')).' and '.$this->term('objectives').' involved'
            ), array(
                40,
                60
            ), $condensed);
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
            self::text('Gaps are grouped by '.$this->term('family').'. Each gives the requirement, the recorded result, every '.$this->term('objective').' not fully met against its expected condition and the implementation gap, a recommended improvement and the evidence that would show the gap is closed. Exposure and consequences are interpreted in the Risk Assessment and are not repeated here.'
                ."\n\n".'Suggested evidence lists examples of records that would typically demonstrate the requirement. They are suggestions for future collection, not evidence held.'
                .($this->unspecified() ? ' Where '.(preg_match('/^[aeiou]/i', $this->term('objective')) === 1 ? 'an ' : 'a ').$this->term('objective').' is partially met and no assessor note says what is lacking, the missing element is shown as not recorded and has to be confirmed with the control owner before remediation is scoped.' : ''))
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
                $lead .= '; '.$open.' of their '.$all.' '.$this->term('objectives').' '.($open === 1 ? 'is' : 'are').' not fully met';
            }
            $lead .= '.';
            if ($family['counts']['Not Assessed'] > 0) {
                $lead .= ' '.$family['counts']['Not Assessed'].' '.($family['counts']['Not Assessed'] === 1 ? $this->term('control').' has' : $this->term('controls').' have').' not been assessed.';
            }

            $shared = $this->shared_initiatives($gaps);
            if (count($shared) > 0) {
                $names = array();
                foreach ($shared as $id) {
                    $names[] = $id.' '.($this->initiative_titles[$id] ?? '');
                }
                $lead .= ' '.(count($gaps) === 1 ? 'It is' : 'All are').' addressed in the Remediation Plan by '.self::join_list($names).'.';
            }

            $blocks[] = self::h2($name);
            $blocks[] = self::text($lead);

            foreach ($gaps as $gap) {
                $blocks = array_merge($blocks, $this->gap_blocks($gap, count($shared) > 0));
            }
        }

        return $blocks;
    }

    /** Initiative ids shared by every gap in the list, when every gap has the same ones. */
    private function shared_initiatives(array $gaps): array
    {
        $first = null;

        foreach ($gaps as $gap) {
            $ids = $this->initiative_of[$gap['ref']] ?? array();
            sort($ids);
            if ($first === null) {
                $first = $ids;
            } elseif ($ids !== $first) {
                return array();
            }
        }

        return $first ?? array();
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

    private function gap_blocks(array $gap, bool $shared = false): array
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
            'Remediation priority',
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
        if (count($initiatives) > 0 && !$shared) {
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
            'Recorded result and deficiency',
            $this->deficiency($gap, $finding)
        );

        $after = array(
            array(
                'Recommended improvement',
                $this->improvement($gap, $finding, $shared ? array() : $initiatives)
            ),
            array(
                'Evidence and validation',
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
        $text = '';

        if ($all === 0) {
            $text = 'Recorded as '.strtolower($gap['result']).'. No '.$t['objectives'].' are assessed individually for this '.$t['control'];
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
            $text = 'Of its '.$all.' '.($all === 1 ? $t['objective'] : $t['objectives']).', '.self::join_list($states).'.';
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
                $this->implementation_gap($objective)
            );
        }

        return self::table(array(
            (count(array_filter($gap['open'], function ($objective) {
                return $objective['result'] === 'Not Assessed';
            })) > 0 ? 'Open ' : 'Deficient ').$this->term('objective'),
            'Expected condition',
            'Recorded result and implementation gap'
        ), array(
            30,
            46,
            24
        ), $rows);
    }

    /** The recorded answer with what is missing, in one cell. */
    private function implementation_gap(array $objective): string
    {
        $missing = $this->missing($objective);
        $plain = array(
            'Entire condition' => 'nothing in place',
            'Part, not specified' => 'missing element not recorded',
            'Not yet known' => 'gap not yet known'
        );

        foreach ($plain as $from => $to) {
            if (strpos($missing, $from) === 0) {
                $missing = $to.substr($missing, strlen($from));
            }
        }

        if ($objective['result'] === 'Not Assessed' && $missing === 'gap not yet known') {
            return 'Not yet answered';
        }

        return self::STATE[$objective['result']].': '.$missing;
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
            $steps[] = 'Put in place '.self::join_list($not).', which '.(count($not) === 1 ? 'has' : 'have').' nothing recorded as in place.';
        }
        if (count($partial) > 0) {
            $described = count(array_filter($gap['open'], function ($objective) {
                return $objective['result'] === 'Partially Implemented' && count($objective['assessor']) > 0;
            })) > 0;
            $steps[] = $described
                ? 'Complete '.self::join_list($partial).' as described in the assessor notes.'
                : 'Confirm with the control owner what is missing from '.self::join_list($partial).', then complete '.(count($partial) === 1 ? 'it' : 'them').' to the expected condition.';
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
            $parts[] = 'Addressed in the Remediation Plan by '.self::join_list($names).'.';
        }

        return implode("\n\n", $parts);
    }

    private function validation(array $gap, array $finding, array $answered): string
    {
        $t = $this->a['terms'];
        $parts = array();

        if (count($gap['evidence']) > 0) {
            $parts[] = 'Evidence provided (linked, review not recorded): '.implode('; ', $gap['evidence']).'.';
        }

        $suggested = array_slice($gap['suggested_evidence'] ?? array(), 0, 3);
        if (count($suggested) > 0) {
            $parts[] = 'Suggested evidence to collect: '.implode('; ', array_map(function ($line) {
                return lcfirst($line);
            }, $suggested)).'.';
        }

        if (count($answered) > 0) {
            $parts[] = 'Closed when the evidence shows '.(count($answered) === 1 ? $answered[0]['short'].' meets its' : 'each of '.self::join_list(array_column($answered, 'short')).' meets its').' expected condition and a reassessment records the '.$t['control'].' as implemented.';
        } elseif (count($gap['open']) > 0) {
            $parts[] = 'The '.(count($gap['open']) === 1 ? $t['objective'] : $t['objectives']).' still to be answered ('.self::join_list(array_column($gap['open'], 'short')).') need a result before the gap can be closed.';
        } else {
            $parts[] = 'Closed when the evidence shows the '.$t['control'].' is in place as required and a reassessment records it as implemented.';
        }

        return implode("\n\n", $parts);
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * A complete list of references with each objective grouped under its
     * control, e.g. "CC7.3 (PF06, PF07); CC7.4 (PF12); P6.3", in order of
     * first appearance. References that are neither a control nor one of
     * its objectives are kept as given.
     */
    public static function grouped_refs(array $refs, array $controls): string
    {
        $objective_of = array();
        $known = array();

        foreach ($controls as $control) {
            $known[$control['ref']] = true;
            foreach ($control['objectives'] ?? array() as $objective) {
                $objective_of[$objective['ref']] = array(
                    $control['ref'],
                    $objective['short']
                );
            }
        }

        $groups = array();

        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref === '') {
                continue;
            }
            if (isset($known[$ref])) {
                $groups[$ref] = $groups[$ref] ?? array();
                continue;
            }
            if (isset($objective_of[$ref])) {
                $groups[$objective_of[$ref][0]][] = $objective_of[$ref][1];
                continue;
            }
            $groups[$ref] = $groups[$ref] ?? array();
        }

        $parts = array();

        foreach ($groups as $ref => $shorts) {
            $shorts = array_values(array_unique($shorts));
            $parts[] = $ref.(count($shorts) > 0 ? ' ('.implode(', ', $shorts).')' : '');
        }

        return implode('; ', $parts);
    }

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
                    'Remediation priority',
                    ucfirst($this->term('family')),
                    'Identifier',
                    ucfirst($this->term('control')),
                    'Current result',
                    'Open '.$this->term('objectives'),
                    'Requirement and expected condition',
                    'Recorded result and deficiency',
                    'Recommended improvement',
                    'Evidence and validation',
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
