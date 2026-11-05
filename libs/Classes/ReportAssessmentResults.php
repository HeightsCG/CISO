<?php
/**
 * The Assessment Results Report: the definitive record of what was recorded
 * for every control and objective, with aggregate results, results by
 * family, how the score is calculated and, from the standard detail level,
 * a compact record of each control. Comprehensive detail adds expected
 * practice, objective descriptions and the finding and evidence indexes.
 * Excel carries the same record as Controls, Objectives and By family sheets.
 */
class ReportAssessmentResults extends ReportBuilder
{
    const SCALE_KEYS = array(
        'met' => 'Implemented',
        'partial' => 'Partially Implemented',
        'not_met' => 'Not Implemented',
        'na' => 'Not Applicable'
    );

    const ASSESSOR_LABELS = array(
        'gap_description' => 'Assessor observation',
        'notes' => 'Assessor note',
        'implementation_description' => 'Current implementation (assessor)',
        'business_impact' => 'Business impact (assessor)',
        'recommendation' => 'Assessor recommendation',
        'owner_name' => 'Owner',
        'target_date' => 'Target date'
    );

    const RESULT_SEVERITY = array(
        'Implemented' => 'Low',
        'Partially Implemented' => 'Medium',
        'Not Implemented' => 'High'
    );

    const RULE_TYPES = array(
        'rollup',
        'na_handling',
        'partial_credit',
        'scoring'
    );

    private $findings_by_control = null;

    private $initiative_of = null;

    protected function title(): string
    {
        return 'Assessment Results Report';
    }

    protected function doc_options(): array
    {
        return array(
            'toc' => true,
            'numbered' => true,
            'chapter_breaks' => true
        );
    }

    protected function blocks(): array
    {
        $blocks = $this->overview();

        if ($this->detail() !== 'executive') {
            $blocks = array_merge($blocks, $this->detailed());
        }

        if ($this->detail() === 'comprehensive' && !empty($this->options['appendices'])) {
            $blocks = array_merge($blocks, $this->appendices());
        }

        return $blocks;
    }

    /* ----------------------------------------------------------- overview */

    private function overview(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $tally = $this->objective_tally();

        $completion = $a['assessed'].' of '.$a['total'].' '.$this->term('controls').' assessed ('.self::pct($a['assessed'], $a['total']).')';
        if ($tally['total'] > 0) {
            $completion .= '; '.$tally['answered'].' of '.$tally['total'].' '.$this->term('objectives').' answered ('.self::pct($tally['answered'], $tally['total']).')';
        }

        $rows = array_merge(array(
            array(
                'Framework',
                $this->framework_name()
            )
        ), $this->period_rows(), array(
            array(
                'Scope',
                isset($a['assessor']['assessment_scope']) ? 'As recorded by the assessor: '.$a['assessor']['assessment_scope'] : 'Not recorded; to be confirmed with the assessor.'
            ),
            array(
                'Method',
                isset($a['assessor']['assessment_method']) ? 'As recorded by the assessor: '.$a['assessor']['assessment_method'] : 'Not recorded; results were entered per '.$this->term('control').($tally['total'] > 0 ? ' and per '.$this->term('objective') : '').', and the techniques used need to be confirmed with the assessor.'
            ),
            array(
                'Completion',
                $completion.'.'
            ),
            array(
                'Evidence',
                $this->evidence_sentence()
            )
        ));

        $rows = array_values(array_filter($rows, function ($row) {
            return $row[1] !== '';
        }));

        $metrics = array(
            array(
                'value' => $a['score'] === null ? 'Not scored' : $a['score'].'%',
                'label' => 'Implementation score',
                'sub' => 'See the scoring method',
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
                'label' => 'Implemented',
                'sub' => '',
                'tone' => 'positive'
            ),
            array(
                'value' => (string) $c['Partially Implemented'],
                'label' => 'Partially implemented',
                'sub' => '',
                'tone' => 'warning'
            ),
            array(
                'value' => (string) $c['Not Implemented'],
                'label' => 'Not implemented',
                'sub' => '',
                'tone' => 'critical'
            )
        );

        foreach (array(
            'Not Applicable' => 'Not applicable',
            'Not Assessed' => 'Not yet assessed'
        ) as $result => $label) {
            if ($c[$result] > 0) {
                $metrics[] = array(
                    'value' => (string) $c[$result],
                    'label' => $label,
                    'sub' => 'Excluded from the score',
                    'tone' => 'neutral'
                );
            }
        }

        if ($tally['total'] > 0) {
            $metrics[] = array(
                'value' => $tally['answered'].' of '.$tally['total'],
                'label' => ucfirst($this->term('objectives')).' answered',
                'sub' => self::pct($tally['answered'], $tally['total']).' answered',
                'tone' => $tally['answered'] === $tally['total'] ? 'positive' : 'warning'
            );
        }

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

        $intro = 'This report is the record of the results of '.$a['narratives']['subject'].($a['client'] !== '' ? ' for '.$a['client'] : '').'. '
            .'It sets out the aggregate results, the results for each '.$this->term('family').' and how they are scored'
            .($this->detail() !== 'executive' ? ', followed by the recorded result of every '.$this->term('control').($tally['total'] > 0 ? ' and '.$this->term('objective') : '').' with its evidence, assessor comments and related findings' : '')
            .'. It records results; interpretation of risk and remediation is provided in the Risk Assessment, Gap Analysis and Remediation Plan reports.';

        $blocks = array(
            self::h1('Assessment overview and results'),
            self::text($intro),
            array(
                'kind' => 'metrics',
                'value' => array(
                    'items' => $metrics
                )
            ),
            self::h2('Distribution of '.$this->term('control').' results'),
            array(
                'kind' => 'stackbar',
                'value' => array(
                    'segments' => $segments
                )
            )
        );

        $blocks = array_merge($blocks, $this->family_results(), $this->family_bars());

        $issues = array();
        foreach ($a['readiness'] as $issue) {
            if (stripos($issue[1], 'narrative') === false) {
                $issues[] = $issue[1];
            }
        }

        if (count($issues) > 0) {
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => 'Completeness of the record',
                    'tone' => 'neutral',
                    'body' => $issues
                )
            );
        }

        $blocks = array_merge($blocks, $this->scoring());

        $blocks[] = self::h2('Assessment record');
        $blocks[] = self::text('The framework, scope, method and completeness of the assessment as recorded.');
        $blocks[] = array(
            'kind' => 'kv',
            'value' => array(
                'rows' => $rows
            )
        );

        return $blocks;
    }

    /* ------------------------------------------------------ family results */

    private function family_results(): array
    {
        $a = $this->a;
        $rows = array();
        $totals = array_fill_keys(ReadyReports::RESULTS, 0);

        foreach ($a['families'] as $name => $family) {
            $rows[] = array(
                $name,
                (string) count($family['controls']),
                (string) $family['counts']['Implemented'],
                (string) $family['counts']['Partially Implemented'],
                (string) $family['counts']['Not Implemented'],
                (string) $family['counts']['Not Applicable'],
                (string) $family['counts']['Not Assessed'],
                $family['score'] === null ? '' : $family['score'].'%'
            );
            foreach (ReadyReports::RESULTS as $result) {
                $totals[$result] += $family['counts'][$result];
            }
        }

        $rows[] = array(
            'Total',
            (string) $a['total'],
            (string) $totals['Implemented'],
            (string) $totals['Partially Implemented'],
            (string) $totals['Not Implemented'],
            (string) $totals['Not Applicable'],
            (string) $totals['Not Assessed'],
            $a['score'] === null ? '' : $a['score'].'%'
        );

        $head = array(
            ucfirst($this->term('family')),
            ucfirst($this->term('controls')),
            'Implemented',
            'Partial',
            'Not impl.',
            'N/A',
            'Not assessed',
            'Score'
        );
        $widths = array(
            31,
            11,
            11,
            9,
            10,
            7,
            11,
            10
        );
        $legend = 'Partial: partially implemented. Not impl.: not implemented.';

        foreach (array(
            6 => 'Not Assessed',
            5 => 'Not Applicable'
        ) as $column => $result) {
            if ($totals[$result] === 0) {
                $widths[0] += $widths[$column];
                foreach ($rows as $index => $row) {
                    array_splice($rows[$index], $column, 1);
                }
                array_splice($head, $column, 1);
                array_splice($widths, $column, 1);
            }
        }

        if ($totals['Not Applicable'] > 0) {
            $legend .= ' N/A: not applicable.';
        } else {
            $legend .= ' No '.$this->term('control').' is recorded as not applicable or not yet assessed, so those columns are omitted.';
            if ($totals['Not Assessed'] > 0) {
                $legend = 'Partial: partially implemented. Not impl.: not implemented. No '.$this->term('control').' is recorded as not applicable, so that column is omitted.';
            }
        }

        if ($totals['Not Assessed'] === 0 && $totals['Not Applicable'] > 0) {
            $legend .= ' Every '.$this->term('control').' has been assessed, so the not assessed column is omitted.';
        }

        $table = self::table($head, $widths, $rows);
        $table['value']['total'] = true;

        $blocks = array(
            self::h2('Results by '.$this->term('family')),
            $table,
            self::note('A '.$this->term('family').' with no assessed '.$this->term('controls').' that apply has no score. '.$legend)
        );

        return $blocks;
    }

    private function family_bars(): array
    {
        $bars = array();

        foreach ($this->a['families'] as $name => $family) {
            if ($family['score'] !== null) {
                $assessed = count($family['controls']) - $family['counts']['Not Assessed'] - $family['counts']['Not Applicable'];
                $bars[] = array(
                    'label' => $name,
                    'value' => $family['score'],
                    'max' => 100,
                    'text' => $family['score'].'% ('.$assessed.')',
                    'tone' => 'brand'
                );
            }
        }

        if (count($bars) < 2) {
            return array();
        }

        return array(
            self::h2('Implementation score by '.$this->term('family')),
            array(
                'kind' => 'bars',
                'value' => array(
                    'items' => $bars
                )
            ),
            self::note('Score out of 100%; the number of scored '.$this->term('controls').' in each '.$this->term('family').' is shown in brackets.')
        );
    }

    /* ------------------------------------------------------------ scoring */

    private function scoring(): array
    {
        $a = $this->a;
        $c = $a['counts'];
        $controls = $this->term('controls');

        $text = 'The implementation score is calculated over the '.$controls.' that have been assessed and apply. Implemented counts as 1, Partially Implemented as 0.5 and Not Implemented as 0. Not Applicable '.$controls.' and '.$controls.' not yet assessed are excluded, so the score describes only the assessed part of the framework.';
        $scored = $c['Implemented'] + $c['Partially Implemented'] + $c['Not Implemented'];

        if ($scored > 0) {
            $text .= "\n\nScore = (Implemented + 0.5 × Partially Implemented) ÷ (Implemented + Partially Implemented + Not Implemented). For this assessment: ("
                .$c['Implemented'].' + 0.5 × '.$c['Partially Implemented'].') ÷ '.$scored.' = '.$a['score'].'%.';
        } else {
            $text .= "\n\nNo applicable ".$controls.' have been assessed yet, so there is no score.';
        }

        if ($this->objective_tally()['total'] > 0) {
            $text .= "\n\nResults for ".$this->term('objectives').' are recorded to show how each '.$this->term('control').' result was reached; the score itself uses the '.$this->term('control').' results.';
        }

        $text .= ' The implementation score measures implementation progress. It is not a compliance opinion, attestation or certification result.';

        $blocks = array(
            self::h2('How results are scored'),
            self::text($text)
        );

        $framework = array();
        $scale = $this->scale_sentence();
        if ($scale !== '') {
            $framework[] = $scale;
        }
        $summary = trim((string) ($a['definition']['scoring']['summary'] ?? ''));
        if ($summary !== '') {
            $framework[] = 'Scoring: '.$summary;
        }
        foreach ($a['definition']['rules'] ?? array() as $rule) {
            if (in_array($rule['type'] ?? '', self::RULE_TYPES, true) && trim((string) ($rule['summary'] ?? '')) !== '') {
                $framework[] = trim($rule['summary']).(($rule['cite'] ?? '') !== '' ? ' ('.$rule['cite'].')' : '');
            }
        }

        if (count($framework) > 0) {
            $blocks[] = self::h3($a['standard'].' scoring notes');
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => '',
                    'tone' => 'neutral',
                    'body' => $framework
                )
            );
        }

        return $blocks;
    }

    private function scale_sentence(): string
    {
        $labels = array();

        foreach ($this->a['definition']['result_scale'] ?? array() as $entry) {
            if (isset(self::SCALE_KEYS[$entry['key'] ?? '']) && ($entry['label'] ?? '') !== '') {
                $labels[$entry['key']] = $entry['label'];
            }
        }

        if (count($labels) === 0) {
            return '';
        }

        $pairs = array();
        foreach ($labels as $key => $label) {
            if (strcasecmp($label, self::SCALE_KEYS[$key]) !== 0) {
                $pairs[] = self::SCALE_KEYS[$key].' corresponds to '.$label;
            }
        }

        $text = 'Result scale: the framework\'s own results are '.self::join_list(array_values($labels)).'.';

        if (count($pairs) > 0) {
            $text .= ' In this report, '.self::join_list($pairs).'.';
        }

        if (!isset($labels['partial'])) {
            $text .= ' The framework has no partial result; Partially Implemented records a '.$this->term('control').' that is in place only in part.';
        }

        return $text;
    }

    /* ----------------------------------------------------- detailed record */

    private function detailed(): array
    {
        $tally = $this->objective_tally();
        $intro = 'Each '.$this->term('control').' that has a recorded result or supporting information is shown with its result, '
            .($tally['total'] > 0 ? 'the result of each of its '.$this->term('objectives').', ' : '')
            .($this->a['with_evidence'] > 0 ? 'linked evidence, ' : '').'any assessor comments and, where it is not fully in place, the related finding. '
            .($this->a['with_evidence'] > 0 ? '' : 'No evidence is linked to any '.$this->term('control').' in this assessment, so evidence is not repeated on each record. ')
            .'Finding references (F-01 and so on) match the numbered findings in the Risk Assessment and Gap Analysis reports. '
            .ucfirst($this->term('controls')).' with nothing recorded yet are listed at the end of their '.$this->term('family').'.';

        $blocks = array(
            self::h1('Detailed '.$this->term('control').' assessments'),
            self::text($intro)
        );

        foreach ($this->a['families'] as $name => $family) {
            $blocks[] = self::h2($name);
            $blocks[] = self::note($this->family_line($family));

            $pending = array();
            foreach ($family['controls'] as $control) {
                if ($this->has_record($control)) {
                    $blocks = array_merge($blocks, $this->control_blocks($control));
                } else {
                    $pending[] = $control;
                }
            }

            if (count($pending) > 0) {
                $rows = array();
                foreach ($pending as $control) {
                    $row = array(
                        $control['ref'],
                        $control['title']
                    );
                    if ($tally['total'] > 0) {
                        $row[] = (string) count($control['objectives']);
                    }
                    $rows[] = $row;
                }
                $head = array(
                    'ID',
                    ucfirst($this->term('control'))
                );
                $widths = array(
                    16,
                    84
                );
                if ($tally['total'] > 0) {
                    $head[] = ucfirst($this->term('objectives'));
                    $widths = array(
                        16,
                        66,
                        18
                    );
                }
                $blocks[] = self::h3(count($pending) === count($family['controls']) ? 'Not yet assessed' : 'Not yet assessed in this '.$this->term('family'));
                $blocks[] = self::table($head, $widths, $rows);
            }
        }

        return $blocks;
    }

    private function family_line(array $family): string
    {
        $parts = array(
            count($family['controls']).' '.(count($family['controls']) === 1 ? $this->term('control') : $this->term('controls'))
        );

        if ($family['score'] !== null) {
            $parts[] = 'implementation score '.$family['score'].'%';
        }

        $results = array();
        foreach (ReadyReports::RESULTS as $result) {
            if ($family['counts'][$result] > 0) {
                $results[] = $family['counts'][$result].' '.strtolower($result);
            }
        }
        $parts[] = implode(', ', $results);

        return ucfirst(implode(' · ', $parts)).'.';
    }

    private function has_record(array $control): bool
    {
        if ($control['result'] !== 'Not Assessed' || $control['evidence_count'] > 0 || count($control['assessor']) > 0 || $control['justification'] !== '') {
            return true;
        }

        foreach ($control['objectives'] as $objective) {
            if ($objective['result'] !== 'Not Assessed' || count($objective['evidence']) > 0 || count($objective['assessor']) > 0) {
                return true;
            }
        }

        return false;
    }

    private function control_blocks(array $control): array
    {
        $comprehensive = $this->detail() === 'comprehensive';
        $meta = array();

        if (count($control['objectives']) > 0) {
            $meta[] = array(
                ucfirst($this->term('objectives')),
                $this->objective_counts($control)
            );
        }

        if ($this->a['with_evidence'] > 0) {
            $meta[] = array(
                'Evidence',
                $control['evidence_count'] === 0 ? 'none linked' : $control['evidence_count'].' '.($control['evidence_count'] === 1 ? 'item' : 'items')
            );
        }

        $finding = $this->findings_by_control()[$control['ref']] ?? null;
        if ($finding !== null) {
            $initiative = $this->initiative_ids($control['ref']);
            $meta[] = array(
                'Finding',
                $finding['ref'].', '.strtolower($finding['priority']).' remediation priority (derived)'.($initiative !== null ? ', '.$initiative : '')
            );
        }

        if ($control['risk_rating'] !== null) {
            $meta[] = array(
                'Assessed risk',
                $control['risk_rating']
            );
        }

        $sections = array();

        if ($control['description'] !== '' && $control['description'] !== $control['title']) {
            $sections[] = array(
                'Requirement',
                $control['description']
            );
        }

        if ($comprehensive && count($control['expectations']) > 0) {
            $lines = array();
            foreach ($control['expectations'] as $pair) {
                $lines[] = ($pair[0] !== '' && preg_match('/^Ex\d+$/', $pair[0]) !== 1 ? $pair[0].': ' : '').$pair[1];
            }
            $sections[] = array(
                'Expected practice',
                $lines
            );
        }

        $applicability = $this->applicability_line($control);
        if ($applicability !== '') {
            $sections[] = array(
                'Applicability',
                $applicability
            );
        }

        foreach (self::ASSESSOR_LABELS as $field => $label) {
            if (isset($control['assessor'][$field])) {
                $sections[] = array(
                    $label,
                    $field === 'target_date' ? date('F j, Y', strtotime($control['assessor'][$field])) : $control['assessor'][$field]
                );
            }
        }

        if ($control['evidence_count'] > 0) {
            $sections[] = array(
                'Evidence',
                $control['evidence']
            );
        }

        $blocks = array(
            array(
                'kind' => 'finding',
                'value' => array(
                    'ref' => $control['ref'],
                    'heading' => $control['title'],
                    'severity' => self::RESULT_SEVERITY[$control['result']] ?? '',
                    'rating' => $control['result'],
                    'meta' => $meta,
                    'sections' => $sections
                )
            )
        );

        if (count($control['objectives']) > 0) {
            $with_evidence = false;
            foreach ($control['objectives'] as $objective) {
                if (count($objective['evidence']) > 0) {
                    $with_evidence = true;
                }
            }
            $table_rows = array();
            foreach ($control['objectives'] as $objective) {
                $text = $objective['title'];
                if ($comprehensive && $objective['description'] !== '' && $objective['description'] !== $objective['title']) {
                    $text = rtrim($objective['title'], '.').'. '.$objective['description'];
                }
                foreach (self::ASSESSOR_LABELS as $field => $label) {
                    if (isset($objective['assessor'][$field])) {
                        $text .= "\n".$label.': '.$objective['assessor'][$field];
                    }
                }
                $row = array(
                    $objective['short'],
                    $text,
                    $objective['result']
                );
                if ($with_evidence) {
                    $row[] = (string) count($objective['evidence']);
                }
                $table_rows[] = $row;
            }
            $head = array(
                'ID',
                ucfirst($this->term('objective')),
                'Result'
            );
            $widths = array(
                11,
                66,
                23
            );
            if ($with_evidence) {
                $head[] = 'Evidence';
                $widths = array(
                    11,
                    57,
                    21,
                    11
                );
            }
            $blocks[] = self::table($head, $widths, $table_rows);
        }

        return $blocks;
    }

    private function objective_counts(array $control): string
    {
        $counts = array();
        foreach ($control['objectives'] as $objective) {
            $counts[$objective['result']] = ($counts[$objective['result']] ?? 0) + 1;
        }

        $parts = array();
        foreach (ReadyReports::RESULTS as $result) {
            if (isset($counts[$result])) {
                $parts[] = $counts[$result].' '.strtolower($result);
            }
        }

        return implode(', ', $parts);
    }

    private function applicability_line(array $control): string
    {
        $excluded = $control['applicability'] === 'Excluded' || $control['result'] === 'Not Applicable';

        if (!$excluded) {
            return $control['justification'] !== '' ? 'Applicable. Note: '.$control['justification'] : '';
        }

        $state = $control['applicability'] === 'Excluded' ? 'Excluded from scope' : 'Not applicable';

        return $control['justification'] !== ''
            ? $state.'. Justification: '.$control['justification']
            : $state.'. No justification has been recorded; one needs to be documented.';
    }

    private function findings_by_control(): array
    {
        if ($this->findings_by_control === null) {
            $this->findings_by_control = array();
            foreach ($this->a['findings'] as $finding) {
                $this->findings_by_control[$finding['control']['ref']] = $finding;
            }
        }

        return $this->findings_by_control;
    }

    /** The Remediation Plan initiative ids covering one control, joined, so R-IDs match that report. */
    private function initiative_ids(string $ref): ?string
    {
        if ($this->initiative_of === null) {
            $this->initiative_of = ReportRemediationPlan::initiative_of(ReportRemediationPlan::plan_initiatives($this->a));
        }

        $ids = $this->initiative_of[$ref] ?? array();

        return count($ids) > 0 ? implode(', ', $ids) : null;
    }

    /* ---------------------------------------------------------- appendices */

    private function appendices(): array
    {
        $blocks = array();
        $letter = 'A';

        if (count($this->a['findings']) > 0) {
            $rows = array();
            foreach ($this->a['findings'] as $finding) {
                $initiative = $this->initiative_ids($finding['control']['ref']);
                $rows[] = array(
                    $finding['ref'],
                    $finding['control']['ref'].' '.$finding['control']['title'],
                    $finding['status'],
                    $finding['priority'],
                    $initiative ?? ''
                );
            }
            $blocks[] = array(
                'kind' => 'h1',
                'value' => 'Appendix '.$letter.': Finding references',
                'numbered' => false
            );
            $blocks[] = self::text('Every '.$this->term('control').' not fully in place has a numbered finding. Remediation priority is derived from the recorded result, the exposure of the area and the share of open '.$this->term('objectives').'; it is not an assessed risk rating.');
            $blocks[] = self::table(array(
                'Finding',
                ucfirst($this->term('control')),
                'Result',
                'Priority',
                'Initiative'
            ), array(
                10,
                52,
                18,
                10,
                10
            ), $rows);
            $letter++;
        }

        $blocks[] = array(
            'kind' => 'h1',
            'value' => 'Appendix '.$letter.': Evidence index',
            'numbered' => false
        );

        $files = $this->a['evidence_files'];

        if (count($files) === 0) {
            $blocks[] = self::text('No evidence files are linked to this assessment. The results in this report rest on the recorded answers and have not been validated against supporting documentation.');
            return $blocks;
        }

        $linked = array();
        foreach ($this->a['controls'] as $control) {
            foreach ($control['evidence'] as $title) {
                $linked[$title][] = $control['ref'];
            }
        }

        $rows = array();
        foreach ($files as $file) {
            $title = ReportAnalysis::plain($file['evidence_title']);
            $rows[] = array(
                $title,
                ReportAnalysis::plain($file['file_type'] ?? ''),
                ReportAnalysis::plain($file['uploaded_by_name'] ?? ''),
                empty($file['date_created']) ? '' : date('M j, Y', strtotime($file['date_created'])),
                implode(', ', array_unique($linked[$title] ?? array()))
            );
        }

        $blocks[] = self::text(count($files).' evidence '.(count($files) === 1 ? 'file is' : 'files are').' linked to this assessment, directly or through '.$this->term('objectives').'.');
        $blocks[] = self::table(array(
            'Evidence',
            'Type',
            'Uploaded by',
            'Date',
            ucfirst($this->term('controls'))
        ), array(
            36,
            12,
            18,
            14,
            20
        ), $rows);

        return $blocks;
    }

    /* -------------------------------------------------------------- Excel */

    protected function sheets(): array
    {
        $control_rows = array();
        $objective_rows = array();

        foreach ($this->a['controls'] as $control) {
            $finding = $this->findings_by_control()[$control['ref']] ?? null;
            $open = array();
            foreach ($control['open'] as $objective) {
                $open[] = $objective['short'];
            }
            $notes = array();
            foreach (self::ASSESSOR_LABELS as $field => $label) {
                if (isset($control['assessor'][$field])) {
                    $notes[] = $label.': '.$control['assessor'][$field];
                }
            }

            $control_rows[] = array(
                $control['family'],
                $control['ref'],
                $control['title'],
                $control['result'],
                $control['applicability'],
                $control['justification'],
                count($control['objectives']),
                count($control['open']),
                implode(', ', $open),
                $control['evidence_count'],
                implode('; ', $control['evidence']),
                $finding['ref'] ?? '',
                $finding['priority'] ?? '',
                $control['risk_rating'] ?? '',
                implode("\n", $notes)
            );

            foreach ($control['objectives'] as $objective) {
                $objective_notes = array();
                foreach (self::ASSESSOR_LABELS as $field => $label) {
                    if (isset($objective['assessor'][$field])) {
                        $objective_notes[] = $label.': '.$objective['assessor'][$field];
                    }
                }
                $objective_rows[] = array(
                    $control['family'],
                    $control['ref'],
                    $objective['ref'],
                    $objective['title'],
                    $objective['description'] !== $objective['title'] ? $objective['description'] : '',
                    $objective['result'],
                    count($objective['evidence']),
                    implode('; ', $objective['evidence']),
                    implode("\n", $objective_notes)
                );
            }
        }

        $sheets = array(
            array(
                'name' => substr(ucfirst($this->term('controls')), 0, 31),
                'head' => array(
                    ucfirst($this->term('family')),
                    'Identifier',
                    ucfirst($this->term('control')),
                    'Result',
                    'Applicability',
                    'Justification',
                    ucfirst($this->term('objectives')),
                    'Open '.$this->term('objectives'),
                    'Open references',
                    'Evidence count',
                    'Evidence',
                    'Finding',
                    'Remediation priority (derived)',
                    'Assessed risk',
                    'Assessor comments'
                ),
                'widths' => array(
                    24,
                    14,
                    44,
                    20,
                    13,
                    30,
                    12,
                    12,
                    24,
                    10,
                    40,
                    9,
                    14,
                    12,
                    40
                ),
                'rows' => $control_rows
            )
        );

        if (count($objective_rows) > 0) {
            $sheets[] = array(
                'name' => substr(ucfirst($this->term('objectives')), 0, 31),
                'head' => array(
                    ucfirst($this->term('family')),
                    ucfirst($this->term('control')),
                    'Identifier',
                    ucfirst($this->term('objective')),
                    'Description',
                    'Result',
                    'Evidence count',
                    'Evidence',
                    'Assessor comments'
                ),
                'widths' => array(
                    24,
                    14,
                    16,
                    44,
                    60,
                    20,
                    10,
                    40,
                    40
                ),
                'rows' => $objective_rows
            );
        }

        $family_rows = array();
        foreach ($this->a['families'] as $name => $family) {
            $family_rows[] = array(
                $name,
                count($family['controls']),
                $family['counts']['Implemented'],
                $family['counts']['Partially Implemented'],
                $family['counts']['Not Implemented'],
                $family['counts']['Not Applicable'],
                $family['counts']['Not Assessed'],
                $family['score'] ?? ''
            );
        }

        $sheets[] = array(
            'name' => substr('By '.$this->term('family'), 0, 31),
            'head' => array(
                ucfirst($this->term('family')),
                ucfirst($this->term('controls')),
                'Implemented',
                'Partially implemented',
                'Not implemented',
                'Not applicable',
                'Not assessed',
                'Score %'
            ),
            'widths' => array(
                34,
                12,
                14,
                20,
                16,
                15,
                14,
                10
            ),
            'rows' => $family_rows
        );

        return $sheets;
    }

    /* ------------------------------------------------------------ helpers */

    private function objective_tally(): array
    {
        $total = 0;
        $answered = 0;

        foreach ($this->a['controls'] as $control) {
            foreach ($control['objectives'] as $objective) {
                $total++;
                if ($objective['result'] !== 'Not Assessed') {
                    $answered++;
                }
            }
        }

        return array(
            'total' => $total,
            'answered' => $answered
        );
    }

    private function framework_name(): string
    {
        $name = $this->a['standard'];
        $full = trim((string) ($this->a['definition']['full_name'] ?? ''));

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

        return $files.' evidence '.($files === 1 ? 'file is' : 'files are').' linked, supporting '.$a['with_evidence'].' of '.$scored.' assessed '.$this->term('controls').'.';
    }
}
