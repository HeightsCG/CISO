<?php
/**
 * The remediation plan: the assessment's gaps consolidated into remediation
 * initiatives and laid out as a roadmap by phase, a prioritized action
 * register and a description of each initiative. Uses the AI-written
 * initiatives when they are stored and current, otherwise the calculated
 * ones; either way every gap is placed in at least one initiative. Priority
 * here is a proposed implementation priority derived from the recorded
 * results, not an assessed risk. Dates and owners come only from the
 * assessor's fields.
 */
class ReportRemediationPlan extends ReportBuilder
{
    const PHASE_PURPOSE = array(
        'Immediate priorities' => 'Absent or high-priority controls in exposed areas, to start first.',
        'Foundational improvements' => 'Governance, policy, role and process foundations that later initiatives build on.',
        'Operational control enhancements' => 'Improvements to day-to-day operating controls, best started once the foundations they rely on are in place.',
        'Program maturity' => 'Activities that keep the program working over time: communication, oversight and continuous improvement.'
    );

    protected function title(): string
    {
        return 'Remediation Plan';
    }

    protected function doc_options(): array
    {
        return array(
            'toc' => true,
            'numbered' => true
        );
    }

    /**
     * The initiatives the plan uses, in roadmap order: the AI-written ones
     * when present, else the calculated ones, plus a final initiative for any
     * gap no other initiative covers. Each has depends (initiative ids).
     */
    public static function plan_initiatives(array $a): array
    {
        $source = isset($a['initiatives_ai']) && count($a['initiatives_ai']) > 0 ? $a['initiatives_ai'] : $a['initiatives'];
        $gaps = array();

        foreach ($a['gaps'] as $gap) {
            $gaps[$gap['ref']] = $gap;
        }

        $initiatives = array();
        $covered = array();
        $last = 0;

        foreach ($source as $initiative) {
            $initiative['depends'] = array_values(array_unique($initiative['depends_on_ids'] ?? $initiative['depends_on'] ?? array()));
            if (!in_array($initiative['phase'], ReportAnalysis::PHASES, true)) {
                $initiative['phase'] = ReportAnalysis::PHASES[1];
            }
            $initiative['controls'] = array_values(array_filter($initiative['controls'], function ($ref) use ($gaps) {
                return isset($gaps[$ref]);
            }));
            if (count($initiative['controls']) === 0) {
                continue;
            }
            foreach ($initiative['controls'] as $ref) {
                $covered[$ref] = true;
            }
            $last = max($last, (int) substr($initiative['id'], 2));
            $initiatives[] = $initiative;
        }

        $uncovered = array_values(array_diff(array_keys($gaps), array_keys($covered)));

        if (count($uncovered) > 0) {
            $records = array();
            $priority = 'Low';
            foreach ($uncovered as $ref) {
                $records[] = $gaps[$ref];
                if (ReportAnalysis::PRIORITY_RANK[$gaps[$ref]['priority']] > ReportAnalysis::PRIORITY_RANK[$priority]) {
                    $priority = $gaps[$ref]['priority'];
                }
            }
            $owners = array_values(array_unique(array_filter(array_map(function ($gap) {
                return $gap['assessor']['owner_name'] ?? '';
            }, $records))));
            $targets = array_values(array_unique(array_filter(array_map(function ($gap) {
                return $gap['assessor']['target_date'] ?? '';
            }, $records))));
            $initiatives[] = array(
                'id' => 'R-'.str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT),
                'title' => 'Close the remaining gaps outside the other initiatives',
                'phase' => $priority === 'High' ? ReportAnalysis::PHASES[0] : ReportAnalysis::PHASES[2],
                'priority' => $priority,
                'problem' => null,
                'activities' => array(),
                'deliverables' => array(),
                'validation' => array(),
                'controls' => $uncovered,
                'gaps' => $records,
                'points' => array(),
                'depends' => array(),
                'owner' => count($owners) === 1 ? $owners[0] : null,
                'target' => count($targets) === 1 ? $targets[0] : null,
                'remainder' => true
            );
        }

        usort($initiatives, function ($x, $y) {
            $phase = array_search($x['phase'], ReportAnalysis::PHASES, true) <=> array_search($y['phase'], ReportAnalysis::PHASES, true);
            if ($phase !== 0) {
                return $phase;
            }
            $rank = ReportAnalysis::PRIORITY_RANK[$y['priority']] <=> ReportAnalysis::PRIORITY_RANK[$x['priority']];
            if ($rank !== 0) {
                return $rank;
            }
            return strnatcmp($x['id'], $y['id']);
        });

        return $initiatives;
    }

    /** Control ref => list of initiative ids that cover it. */
    public static function initiative_of(array $initiatives): array
    {
        $out = array();

        foreach ($initiatives as $initiative) {
            foreach ($initiative['controls'] as $ref) {
                $out[$ref][] = $initiative['id'];
            }
        }

        return $out;
    }

    protected function blocks(): array
    {
        $initiatives = self::plan_initiatives($this->a);

        if (count($initiatives) === 0) {
            return $this->empty_plan();
        }

        return array_merge(
            $this->overview($initiatives),
            $this->roadmap($initiatives),
            $this->register($initiatives),
            $this->details($initiatives)
        );
    }

    /* ----------------------------------------------------------- sections */

    private function empty_plan(): array
    {
        $c = $this->a['counts'];
        $text = 'No remediation initiatives are needed at this stage: none of the assessed '.$this->term('controls').' is recorded as not implemented or partially implemented.';

        if ($c['Not Assessed'] > 0) {
            $text .= ' '.$c['Not Assessed'].' of '.$this->a['total'].' '.$this->term('controls').' have not been assessed yet; this plan should be regenerated once they are, as they may contain gaps that need remediation.';
        }

        return array(
            self::h1('Plan overview'),
            self::text($text)
        );
    }

    private function overview(array $initiatives): array
    {
        $a = $this->a;
        $gap_total = count($a['gaps']);
        $ai = isset($a['initiatives_ai']) && count($a['initiatives_ai']) > 0;
        $high = count(array_filter($initiatives, function ($initiative) {
            return $initiative['priority'] === 'High';
        }));
        $owned = count(array_filter($initiatives, function ($initiative) {
            return ($initiative['owner'] ?? null) !== null;
        }));
        $dated = count(array_filter($initiatives, function ($initiative) {
            return ($initiative['target'] ?? null) !== null;
        }));
        $multi = count(array_filter(self::initiative_of($initiatives), function ($ids) {
            return count($ids) > 1;
        }));

        $p1 = 'This plan sets out how '.($a['client'] !== '' ? $a['client'] : 'the organization').' can close the '.$gap_total.' '.($gap_total === 1 ? $this->term('control') : $this->term('controls')).' found not fully implemented in '.$a['narratives']['subject'].'. ';
        if (count($initiatives) === 1) {
            $p1 .= ($gap_total === 1 ? 'The gap is' : 'The gaps share a common underlying need and are').' addressed by a single remediation initiative.';
        } else {
            $p1 .= 'Rather than one action per '.$this->term('control').', related gaps are consolidated into '.count($initiatives).' remediation initiatives, each addressing a common underlying need, and the initiatives are sequenced into phases so that foundations are laid before the controls that depend on them.';
        }
        if ($ai) {
            $remainder = array_values(array_filter($initiatives, function ($initiative) {
                return !empty($initiative['remainder']);
            }));
            $p1 .= ' The initiatives were drafted with AI assistance from the recorded results, and every gap has been checked against them'
                .(count($remainder) > 0 ? '; the '.count($remainder[0]['controls']).' '.(count($remainder[0]['controls']) === 1 ? $this->term('control') : $this->term('controls')).' the draft did not cover are collected in '.$remainder[0]['id'].'.' : ': each belongs to at least one initiative.');
        }

        $p2 = 'Each initiative carries a proposed implementation priority. It is derived from the recorded results: the highest remediation priority among its '.$this->term('controls').', which in turn reflects whether a '.$this->term('control').' is absent or partial, how exposed its subject area typically is and how many of its '.$this->term('objectives').' remain open. It is a planning aid, not an assessed risk rating; '.(count(array_filter($a['gaps'], function ($gap) {
            return $gap['risk_rating'] !== null;
        })) > 0 ? 'risk ratings recorded by the assessor are shown separately as assessed risk.' : 'no risk ratings have been recorded by the assessor.');

        $p3 = $owned === 0 && $dated === 0
            ? 'No owners or target dates have been recorded for these gaps, so the plan does not propose dates or effort estimates. Management should assign an accountable owner and a realistic target date to each initiative before the plan is adopted; the accompanying Excel workbook provides Owner, Target date and Status columns for this.'
            : $owned.' of '.count($initiatives).' initiatives have an owner and '.$dated.' a target date recorded by the assessor; the rest are shown as unassigned or not set, and no dates have been estimated.';

        $no_evidence = count(array_filter($a['gaps'], function ($gap) {
            return $gap['evidence_count'] === 0;
        }));
        $p3 .= "\n\n".'An initiative is complete when evidence is linked to each of its '.$this->term('controls').' and a reassessment records them as implemented; the validation requirements in each description say what that evidence should show.'
            .($no_evidence > 0 ? ' '.($no_evidence === $gap_total ? 'None of the '.$gap_total : $no_evidence.' of the '.$gap_total).' '.$this->term('controls').' in this plan currently '.($no_evidence === 1 && $gap_total === 1 ? 'has' : 'have').' linked evidence.' : '');

        if ($a['counts']['Not Assessed'] > 0) {
            $p3 .= ' '.$a['counts']['Not Assessed'].' of '.$a['total'].' '.$this->term('controls').' have not been assessed yet; they may add work to this plan once assessed.';
        }

        $blocks = array(
            self::h1('Plan overview'),
            self::text($p1."\n\n".$p2."\n\n".$p3),
            array(
                'kind' => 'metrics',
                'value' => array(
                    'items' => array(
                        array(
                            'value' => (string) count($initiatives),
                            'label' => 'Remediation initiatives',
                            'sub' => count(array_unique(array_column($initiatives, 'phase'))) === 1 ? 'in one phase' : 'across '.count(array_unique(array_column($initiatives, 'phase'))).' phases'
                        ),
                        array(
                            'value' => (string) $gap_total,
                            'label' => ucfirst($this->term('controls')).' to remediate',
                            'sub' => $multi > 0 ? $multi.' shared by more than one initiative' : 'each in one initiative'
                        ),
                        array(
                            'value' => (string) $high,
                            'label' => 'High-priority initiatives',
                            'sub' => 'proposed implementation priority',
                            'tone' => $high > 0 ? 'critical' : 'neutral'
                        ),
                        array(
                            'value' => $owned.' of '.count($initiatives),
                            'label' => 'Initiatives with an owner',
                            'sub' => $dated.' with a target date',
                            'tone' => $owned < count($initiatives) ? 'warning' : 'positive'
                        )
                    )
                )
            )
        );

        return $blocks;
    }

    private function roadmap(array $initiatives): array
    {
        $mutual = self::mutual($initiatives);
        $phases = array();
        $lines = array();

        foreach (ReportAnalysis::PHASES as $phase) {
            $phases[$phase] = array();
        }

        foreach ($initiatives as $initiative) {
            $parts = array(
                $initiative['priority'].' priority',
                count($initiative['controls']).' '.(count($initiative['controls']) === 1 ? $this->term('control') : $this->term('controls'))
            );
            $after = array_values(array_diff($initiative['depends'], $mutual[$initiative['id']] ?? array()));
            if (count($after) > 0) {
                $parts[] = 'after '.implode(', ', $after);
            }
            if (count($mutual[$initiative['id']] ?? array()) > 0) {
                $parts[] = 'alongside '.implode(', ', $mutual[$initiative['id']]);
            }
            $phases[$initiative['phase']][] = array(
                'title' => $initiative['title'],
                'detail' => implode(' · ', $parts),
                'refs' => array(
                    $initiative['id']
                )
            );
        }

        foreach ($phases as $phase => $items) {
            if (count($items) > 0) {
                $lines[] = '- '.$phase.' ('.count($items).'): '.self::PHASE_PURPOSE[$phase];
            }
        }

        $segments = array();
        $colors = array(
            '#B42318',
            '#B54708',
            '#075985',
            '#475467'
        );
        foreach (ReportAnalysis::PHASES as $index => $phase) {
            $refs = array();
            foreach ($initiatives as $initiative) {
                if ($initiative['phase'] === $phase) {
                    $refs = array_merge($refs, $initiative['controls']);
                }
            }
            if (count($refs) > 0) {
                $segments[] = array(
                    'label' => $phase,
                    'value' => count(array_unique($refs)),
                    'color' => $colors[$index]
                );
            }
        }

        $blocks = array(
            self::h1('Executive roadmap'),
            self::text('The initiatives fall into the phases below. A phase indicates sequence, not a date: later phases can start while earlier ones are still under way, provided the initiatives they depend on are far enough along.'."\n\n".implode("\n", $lines)),
            self::h3(ucfirst($this->term('controls')).' to remediate in each phase'),
            array(
                'kind' => 'stackbar',
                'value' => array(
                    'segments' => $segments
                )
            ),
            array(
                'kind' => 'roadmap',
                'value' => $phases
            )
        );

        if (count($segments) < 2) {
            array_splice($blocks, 2, 2);
        }

        $dependency_text = $this->dependency_text($initiatives, $mutual);
        if ($dependency_text !== '') {
            $blocks[] = self::h2('Dependencies');
            $blocks[] = self::text($dependency_text);
        }

        $dated = array_values(array_filter($initiatives, function ($initiative) {
            return ($initiative['target'] ?? null) !== null;
        }));
        if (count($dated) > 0) {
            usort($dated, function ($x, $y) {
                return strtotime($x['target']) <=> strtotime($y['target']);
            });
            $rows = array();
            $anchors = array();
            foreach ($dated as $initiative) {
                $rows[] = array(
                    self::date($initiative['target']),
                    $initiative['id'],
                    $initiative['title'],
                    $initiative['owner'] ?? 'Unassigned'
                );
                $anchors[] = 'initiative-'.$initiative['id'];
            }
            $table = self::table(array(
                'Target date',
                'ID',
                'Initiative',
                'Owner'
            ), array(
                16,
                9,
                53,
                22
            ), $rows);
            $table['kind'] = 'register';
            $table['value']['anchors'] = $anchors;
            $table['value']['link_col'] = 1;
            $blocks[] = self::h2('Timeline of recorded target dates');
            $blocks[] = self::text(count($dated) === count($initiatives)
                ? 'Target dates as recorded by the assessor for each initiative.'
                : 'Only '.count($dated).' of '.count($initiatives).' initiatives have a target date recorded by the assessor; the others need dates before the timeline is complete.');
            $blocks[] = $table;
        }

        return $blocks;
    }

    private function register(array $initiatives): array
    {
        $rows = array();
        $anchors = array();
        $ordered = $initiatives;

        usort($ordered, function ($x, $y) {
            $rank = ReportAnalysis::PRIORITY_RANK[$y['priority']] <=> ReportAnalysis::PRIORITY_RANK[$x['priority']];
            if ($rank !== 0) {
                return $rank;
            }
            $phase = array_search($x['phase'], ReportAnalysis::PHASES, true) <=> array_search($y['phase'], ReportAnalysis::PHASES, true);
            if ($phase !== 0) {
                return $phase;
            }
            return count($y['controls']) <=> count($x['controls']);
        });

        foreach ($ordered as $initiative) {
            $rows[] = array(
                $initiative['id'],
                $initiative['title'],
                $initiative['phase'],
                $initiative['priority'],
                (string) count($initiative['controls']),
                $initiative['owner'] ?? 'Unassigned',
                ($initiative['target'] ?? null) !== null ? self::date($initiative['target']) : 'Not set'
            );
            $anchors[] = 'initiative-'.$initiative['id'];
        }

        $table = self::table(array(
            'ID',
            'Initiative',
            'Phase',
            'Priority',
            ucfirst($this->term('controls')),
            'Owner',
            'Target'
        ), array(
            7,
            37,
            19,
            9,
            9,
            11,
            8
        ), $rows);
        $table['kind'] = 'register';
        $table['value']['anchors'] = $anchors;

        return array(
            self::h1('Prioritized action register'),
            self::text('All initiatives ordered by proposed implementation priority, then by phase. Select an ID to go to the initiative\'s description.'),
            $table
        );
    }

    private function details(array $initiatives): array
    {
        $gaps = array();
        foreach ($this->a['gaps'] as $gap) {
            $gaps[$gap['ref']] = $gap;
        }

        $mutual = self::mutual($initiatives);
        $titles = array();
        foreach ($initiatives as $initiative) {
            $titles[$initiative['id']] = $initiative['title'];
        }

        $blocks = array(
            self::h1('Initiative descriptions')
        );
        $phase = null;

        foreach ($initiatives as $initiative) {
            if ($initiative['phase'] !== $phase) {
                $phase = $initiative['phase'];
                $blocks[] = self::h2($phase);
            }
            $blocks[] = $this->initiative_card($initiative, $gaps, $titles, $mutual[$initiative['id']] ?? array());
        }

        return $blocks;
    }

    private function initiative_card(array $initiative, array $gaps, array $titles, array $mutual): array
    {
        $records = array();
        foreach ($initiative['controls'] as $ref) {
            $records[] = $gaps[$ref];
        }
        usort($records, function ($x, $y) {
            return strnatcmp($x['ref'], $y['ref']);
        });

        $sections = array();
        $sections[] = array(
            'Problem addressed',
            $this->problem($initiative, $records)
        );

        $activities = self::relevant($initiative['activities'], $records, 6);
        if (!empty($initiative['remainder'])) {
            $activities[] = 'Decide for each '.$this->term('control').' listed below whether it belongs in one of the other initiatives or needs its own, and assign an owner.';
            $activities[] = 'Close the open '.$this->term('objectives').' shown for each, using the detailed analysis in the Gap Analysis report.';
        }
        foreach ($records as $gap) {
            if (isset($gap['assessor']['recommendation'])) {
                $activities[] = 'Assessor recommendation for '.$gap['ref'].': '.$gap['assessor']['recommendation'];
            }
        }
        if (count($activities) > 0) {
            $sections[] = array(
                'Suggested approach',
                $activities
            );
        }

        if (count($initiative['deliverables']) > 0) {
            $sections[] = array(
                'Expected deliverables',
                self::relevant($initiative['deliverables'], $records, 5)
            );
        }

        if (($initiative['implementation_considerations'] ?? null) !== null) {
            $sections[] = array(
                'Implementation considerations',
                $initiative['implementation_considerations']
            );
        }

        $sections[] = array(
            'Related '.$this->term('controls').' and '.$this->term('objectives'),
            $this->related($initiative, $records)
        );

        $sections[] = array(
            'Priority basis',
            $this->priority_text($initiative, $records)
        );

        $dependencies = $this->dependencies_line($initiative, $titles, $mutual);
        if ($dependencies !== '') {
            $sections[] = array(
                'Dependencies',
                $dependencies
            );
        }

        $sections[] = array(
            'Validation requirements',
            self::relevant($initiative['validation'], $records, 4)
        );

        return array(
            'kind' => 'finding',
            'value' => array(
                'ref' => $initiative['id'],
                'heading' => $initiative['title'],
                'severity' => $initiative['priority'],
                'rating' => $initiative['priority'].' priority',
                'anchor' => 'initiative-'.$initiative['id'],
                'meta' => array(
                    array(
                        'Phase',
                        $initiative['phase']
                    ),
                    array(
                        ucfirst($this->term('controls')),
                        (string) count($records)
                    ),
                    array(
                        'Owner',
                        $initiative['owner'] ?? 'Unassigned'
                    ),
                    array(
                        'Target',
                        ($initiative['target'] ?? null) !== null ? self::date($initiative['target']) : 'Not set'
                    )
                ),
                'sections' => $sections
            )
        );
    }

    /* ------------------------------------------------------------ helpers */

    private function problem(array $initiative, array $records): string
    {
        if (($initiative['problem'] ?? null) !== null && $initiative['problem'] !== '') {
            $text = preg_replace_callback('/\b([a-z])([A-Z]{2,})\b/', function ($match) {
                return strtoupper($match[1]).$match[2];
            }, $initiative['problem']);
        } else {
            $not = count(array_filter($records, function ($gap) {
                return $gap['result'] === 'Not Implemented';
            }));
            $text = count($records).' '.(count($records) === 1 ? $this->term('control').' is' : $this->term('controls').' are').' not fully in place'.($not > 0 ? ' ('.$not.' not implemented)' : '').' and '.(count($records) === 1 ? 'is' : 'are').' not covered by any of the other initiatives, so '.(count($records) === 1 ? 'it is' : 'they are').' collected here to keep the plan complete.';
        }

        $observed = array();
        foreach ($records as $gap) {
            if (isset($gap['assessor']['gap_description'])) {
                $observed[] = $gap['ref'].': '.$gap['assessor']['gap_description'];
            }
        }

        if (count($observed) > 0) {
            $text .= "\n\nAssessor observations:\n".self::bullets($observed);
        }

        return $text;
    }

    /** One line per related control: ref, short title, result and its open objectives. */
    private function related(array $initiative, array $records): array
    {
        $chosen = array_flip($initiative['points'] ?? array());
        $lines = array();

        foreach ($records as $gap) {
            $open = array();
            foreach ($gap['open'] as $objective) {
                if ($objective['result'] === 'Not Assessed') {
                    continue;
                }
                if (count($chosen) === 0 || isset($chosen[$objective['ref']])) {
                    $open[] = $objective['short'];
                }
            }
            $unanswered = count(array_filter($gap['open'], function ($objective) {
                return $objective['result'] === 'Not Assessed';
            }));
            $line = $gap['ref'].' '.self::short_title($gap['title']).' ('.strtolower($gap['result']);
            if (count($open) > 0) {
                $line .= '; open: '.implode(', ', $open);
            }
            if ($unanswered > 0) {
                $line .= '; '.$unanswered.' not yet assessed';
            }
            $lines[] = $line.')';
        }

        return $lines;
    }

    private function priority_text(array $initiative, array $records): string
    {
        $drivers = array_values(array_filter($records, function ($gap) use ($initiative) {
            return $gap['priority'] === $initiative['priority'];
        }));
        $others = count($records) - count($drivers);

        if (count($records) === 1) {
            $text = ucfirst(preg_replace('/^Derived from the recorded result: /', '', $records[0]['priority_basis']));
        } elseif (count($drivers) <= 2) {
            $named = array();
            foreach ($drivers as $gap) {
                $named[] = $gap['ref'].' ('.preg_replace('/^Derived from the recorded result: |\.$/', '', $gap['priority_basis']).')';
            }
            $text = 'Set by '.self::join_list($named).($others > 0 ? '; the other '.$others.' '.($others === 1 ? $this->term('control').' is' : $this->term('controls').' are').' lower' : '').'.';
        } else {
            $not = count(array_filter($drivers, function ($gap) {
                return $gap['result'] === 'Not Implemented';
            }));
            $text = count($drivers).' of its '.count($records).' '.$this->term('controls').' carry a '.strtolower($initiative['priority']).' derived priority'.($not > 0 ? ', '.$not.' of them not implemented at all' : '').($others > 0 ? '; '.$others.' '.($others === 1 ? 'is' : 'are').' lower' : '').'.';
        }

        $rated = array();
        foreach ($records as $gap) {
            if ($gap['risk_rating'] !== null) {
                $rated[] = $gap['ref'].' '.$gap['risk_rating'];
            }
        }
        if (count($rated) > 0) {
            $text .= ' Assessed risk recorded by the assessor: '.implode(', ', $rated).'.';
        }

        return $text;
    }

    private function dependencies_line(array $initiative, array $titles, array $mutual): string
    {
        $before = array();
        foreach ($initiative['depends'] as $id) {
            if (isset($titles[$id]) && !in_array($id, $mutual, true)) {
                $before[] = $id.' '.$titles[$id];
            }
        }
        $along = array();
        foreach ($mutual as $id) {
            $along[] = $id.' '.$titles[$id];
        }

        $parts = array();
        if (count($before) > 0) {
            $parts[] = 'Builds on '.self::join_list($before).'; start once '.(count($before) === 1 ? 'it is' : 'they are').' under way.';
        }
        if (count($along) > 0) {
            $parts[] = 'Interdependent with '.self::join_list($along).'; plan and run them together.';
        }
        return implode(' ', $parts);
    }

    private function dependency_text(array $initiatives, array $mutual): string
    {
        $by_id = array();
        foreach ($initiatives as $initiative) {
            $by_id[$initiative['id']] = $initiative;
        }

        $lines = array();
        $pairs = array();

        foreach ($initiatives as $initiative) {
            foreach ($mutual[$initiative['id']] ?? array() as $other) {
                $key = min($initiative['id'], $other).'|'.max($initiative['id'], $other);
                if (!isset($pairs[$key])) {
                    $pairs[$key] = true;
                    $lines[] = '- '.min($initiative['id'], $other).' and '.max($initiative['id'], $other).' depend on each other ('.$by_id[min($initiative['id'], $other)]['title'].'; '.$by_id[max($initiative['id'], $other)]['title'].') and should be planned as one effort.';
                }
            }
            foreach ($initiative['depends'] as $id) {
                if (isset($by_id[$id]) && array_search($by_id[$id]['phase'], ReportAnalysis::PHASES, true) > array_search($initiative['phase'], ReportAnalysis::PHASES, true)) {
                    $lines[] = '- '.$initiative['id'].' ('.$initiative['phase'].') relies on '.$id.' ('.$by_id[$id]['phase'].'): bring forward the part of '.$id.' that '.$initiative['id'].' needs.';
                }
            }
        }

        if (count($lines) === 0) {
            return '';
        }

        return 'The roadmap shows which initiatives each one builds on. The following need attention when scheduling:'."\n\n".implode("\n", $lines);
    }

    /**
     * At most $limit lines, those sharing the most words with the open
     * objectives of the initiative's gaps first; order kept among equals.
     */
    private static function relevant(array $lines, array $records, int $limit): array
    {
        if (count($lines) <= $limit) {
            return $lines;
        }

        $text = '';
        foreach ($records as $gap) {
            $text .= ' '.$gap['title'].' '.$gap['description'];
            foreach ($gap['open'] as $objective) {
                $text .= ' '.$objective['title'].' '.$objective['description'];
            }
        }
        $words = array_flip(self::words($text));

        $scored = array();
        foreach (array_values($lines) as $index => $line) {
            $score = 0;
            foreach (array_unique(self::words($line)) as $word) {
                if (isset($words[$word])) {
                    $score++;
                }
            }
            $scored[] = array(
                'line' => $line,
                'score' => $score,
                'index' => $index
            );
        }

        usort($scored, function ($x, $y) {
            return $y['score'] <=> $x['score'] ?: $x['index'] <=> $y['index'];
        });

        $kept = array_slice($scored, 0, $limit);
        usort($kept, function ($x, $y) {
            return $x['index'] <=> $y['index'];
        });

        return array_column($kept, 'line');
    }

    private static function words(string $text): array
    {
        preg_match_all('/[a-z]{5,}/', strtolower($text), $match);

        return array_values(array_filter($match[0], function ($word) {
            return !in_array($word, array(
                'which',
                'their',
                'there',
                'these',
                'those',
                'where',
                'other',
                'about',
                'organization',
                'entity'
            ), true);
        }));
    }

    /** Initiative id => ids it depends on that also depend on it. */
    private static function mutual(array $initiatives): array
    {
        $depends = array();
        foreach ($initiatives as $initiative) {
            $depends[$initiative['id']] = $initiative['depends'];
        }

        $out = array();
        foreach ($depends as $id => $list) {
            foreach ($list as $other) {
                if (in_array($id, $depends[$other] ?? array(), true)) {
                    $out[$id][] = $other;
                }
            }
        }

        return $out;
    }

    private static function short_title(string $title): string
    {
        $title = rtrim($title, '.');

        if (strlen($title) <= 70) {
            return $title;
        }

        $cut = substr($title, 0, 70);
        $space = strrpos($cut, ' ');

        return ($space !== false ? substr($cut, 0, $space) : $cut).'...';
    }

    private static function date(string $value): string
    {
        $time = strtotime($value);

        return $time === false ? $value : date('M j, Y', $time);
    }

    /* -------------------------------------------------------------- excel */

    protected function sheets(): array
    {
        $initiatives = self::plan_initiatives($this->a);
        $rows = array();
        $titles = array();

        foreach ($initiatives as $initiative) {
            $titles[$initiative['id']] = $initiative['title'];
            $rows[] = array(
                $initiative['id'],
                $initiative['title'],
                $initiative['phase'],
                $initiative['priority'],
                count($initiative['controls']),
                implode(', ', $initiative['controls']),
                (string) ($initiative['problem'] ?? ''),
                implode("\n", $initiative['activities']),
                implode("\n", $initiative['deliverables']),
                implode("\n", $initiative['validation']),
                implode(', ', $initiative['depends']),
                (string) ($initiative['owner'] ?? ''),
                (string) ($initiative['target'] ?? ''),
                ''
            );
        }

        $of = self::initiative_of($initiatives);
        $actions = array();
        $gaps = $this->a['gaps'];
        usort($gaps, function ($x, $y) use ($of) {
            $first = strnatcmp($of[$x['ref']][0] ?? '', $of[$y['ref']][0] ?? '');
            return $first !== 0 ? $first : strnatcmp($x['ref'], $y['ref']);
        });

        foreach ($gaps as $gap) {
            $ids = $of[$gap['ref']] ?? array();
            $answered = array_values(array_filter($gap['open'], function ($objective) {
                return $objective['result'] !== 'Not Assessed';
            }));
            $unanswered = count($gap['open']) - count($answered);
            $action = isset($gap['assessor']['recommendation']) ? 'Assessor recommendation: '.$gap['assessor']['recommendation'] : '';
            if ($action === '' && count($answered) > 0) {
                $action = 'Bring to the expected condition: '.implode('; ', array_map(function ($objective) {
                    return $objective['short'].' '.rtrim($objective['title'], '.');
                }, $answered)).'.';
            }
            if ($action === '') {
                $action = 'Confirm the specific shortfall with the assessor and bring the '.$this->term('control').' to the expected condition.';
            }
            if ($unanswered > 0) {
                $action .= ' Assess the '.$unanswered.' unanswered '.($unanswered === 1 ? $this->term('objective') : $this->term('objectives')).'.';
            }
            $actions[] = array(
                implode(', ', $ids),
                implode('; ', array_map(function ($id) use ($titles) {
                    return $titles[$id] ?? '';
                }, $ids)),
                $gap['priority'],
                $gap['family'],
                $gap['ref'],
                $gap['title'],
                $gap['result'],
                implode(', ', array_column($answered, 'short')),
                $action,
                (string) ($gap['assessor']['owner_name'] ?? ''),
                (string) ($gap['assessor']['target_date'] ?? ''),
                ''
            );
        }

        return array(
            array(
                'name' => 'Initiatives',
                'head' => array(
                    'ID',
                    'Initiative',
                    'Phase',
                    'Priority',
                    ucfirst($this->term('controls')),
                    'Identifiers',
                    'Problem addressed',
                    'Suggested approach',
                    'Expected deliverables',
                    'Validation',
                    'Depends on',
                    'Owner',
                    'Target date',
                    'Status'
                ),
                'widths' => array(
                    8,
                    45,
                    28,
                    10,
                    10,
                    30,
                    60,
                    70,
                    50,
                    60,
                    14,
                    18,
                    14,
                    14
                ),
                'rows' => $rows
            ),
            array(
                'name' => 'Control actions',
                'head' => array(
                    'Initiative',
                    'Initiative title',
                    'Priority',
                    ucfirst($this->term('family')),
                    'Identifier',
                    ucfirst($this->term('control')),
                    'Current result',
                    'Open '.$this->term('objectives'),
                    'Action',
                    'Owner',
                    'Target date',
                    'Status'
                ),
                'widths' => array(
                    10,
                    40,
                    10,
                    24,
                    14,
                    45,
                    20,
                    24,
                    70,
                    18,
                    14,
                    14
                ),
                'rows' => $actions
            )
        );
    }
}
