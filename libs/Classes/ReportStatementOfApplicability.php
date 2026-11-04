<?php
/**
 * Statement of Applicability: every control of the framework, whether it
 * applies, the justification recorded for that decision, its implementation
 * status and supporting evidence, with exclusions listed separately. The
 * explanation of what the statement means depends on the framework.
 */
class ReportStatementOfApplicability extends ReportBuilder
{
    const MISSING_JUSTIFICATION = 'Justification not recorded';

    protected function title(): string
    {
        return 'Statement of Applicability';
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
        return array_merge($this->scope_blocks(), $this->purpose_blocks(), $this->family_blocks(), $this->exclusion_blocks(), $this->reference_blocks());
    }

    protected function sheets(): array
    {
        $rows = array();

        foreach ($this->a['controls'] as $control) {
            $rows[] = array(
                $control['family'],
                $control['ref'],
                $control['title'],
                self::excluded($control) ? 'No' : 'Yes',
                $this->justification($control),
                self::excluded($control) ? 'Excluded' : $control['result'],
                $control['evidence_count'],
                implode('; ', $control['evidence'])
            );
        }

        return array(
            array(
                'name' => 'Statement of Applicability',
                'head' => array(
                    ucfirst($this->term('family')),
                    'Identifier',
                    ucfirst($this->term('control')),
                    'Applicable',
                    'Justification',
                    'Implementation status',
                    'Evidence items',
                    'Evidence'
                ),
                'widths' => array(
                    26,
                    14,
                    50,
                    12,
                    50,
                    22,
                    12,
                    50
                ),
                'rows' => $rows
            )
        );
    }

    /* ------------------------------------------------------------ sections */

    private function scope_blocks(): array
    {
        $assessment = $this->a['assessment'];
        $excluded = $this->excluded_controls();
        $controls = $this->term('controls');
        $rows = array(
            array(
                'Framework',
                $this->a['standard']
            )
        );

        if (strcasecmp($this->a['name'], $this->a['standard']) !== 0) {
            $rows[] = array(
                'Assessment',
                $this->a['name']
            );
        }

        if ($this->a['client'] !== '') {
            $rows[] = array(
                'Organization',
                $this->a['client']
            );
        }

        $rows[] = array(
            'Assessment period',
            $this->a['period']
        );
        $rows[] = array(
            'Scope',
            $this->a['assessor']['assessment_scope'] ?? 'The scope of the assessment (systems, locations, services and organizational units) is not recorded and should be confirmed with the assessor.'
        );
        $rows[] = array(
            ucfirst($controls).' in framework',
            $this->a['total'].' across '.count($this->families()).' '.$this->term('families')
        );
        $rows[] = array(
            'Applicability',
            ($this->a['total'] - count($excluded)).' applicable, '.count($excluded).' excluded'
        );

        return array(
            self::h1('Framework and scope'),
            array(
                'kind' => 'kv',
                'value' => array(
                    'rows' => $rows
                )
            )
        );
    }

    private function purpose_blocks(): array
    {
        return array_merge($this->summary_blocks(), array(
            self::h1('Purpose of this statement'),
            self::text($this->significance())
        ));
    }

    private function summary_blocks(): array
    {
        $excluded = $this->excluded_controls();
        $applicable = $this->a['total'] - count($excluded);
        $counts = $this->a['counts'];
        $unjustified = count(array_filter($excluded, function ($control) {
            return $control['justification'] === '';
        }));
        $controls = $this->term('controls');

        $metrics = array(
            array(
                'value' => (string) $applicable,
                'label' => 'Applicable '.$controls,
                'sub' => 'of '.$this->a['total'].' in the framework',
                'tone' => 'brand'
            ),
            array(
                'value' => (string) count($excluded),
                'label' => 'Excluded '.$controls,
                'sub' => count($excluded) > 0 ? ($unjustified > 0 ? $unjustified.' without justification' : 'all justified') : 'none excluded',
                'tone' => $unjustified > 0 ? 'critical' : 'neutral'
            ),
            array(
                'value' => (string) $counts['Implemented'],
                'label' => 'Implemented',
                'sub' => self::pct($counts['Implemented'], $applicable).' of applicable',
                'tone' => 'positive'
            ),
            array(
                'value' => (string) $this->a['with_evidence'],
                'label' => ucfirst($controls).' with evidence',
                'sub' => self::pct($this->a['with_evidence'], $applicable).' of applicable',
                'tone' => $this->a['with_evidence'] === 0 ? 'warning' : 'neutral'
            )
        );

        $segments = array();
        $tones = array(
            'Implemented' => 'positive',
            'Partially Implemented' => 'warning',
            'Not Implemented' => 'critical',
            'Not Assessed' => 'neutral'
        );

        foreach ($tones as $result => $tone) {
            if ($counts[$result] > 0) {
                $segments[] = array(
                    'label' => $result.' ('.$counts[$result].')',
                    'value' => $counts[$result],
                    'tone' => $tone
                );
            }
        }

        $text = 'Of the '.$applicable.' applicable '.$controls.', '.$counts['Implemented'].' '.($counts['Implemented'] === 1 ? 'is' : 'are').' recorded as implemented, '.$counts['Partially Implemented'].' as partially implemented and '.$counts['Not Implemented'].' as not implemented'
            .($counts['Not Assessed'] > 0 ? '; '.$counts['Not Assessed'].' have not yet been assessed' : '').'. '
            .(count($excluded) === 0 ? 'No '.$this->term('control').' has been excluded.' : count($excluded).' '.$this->term(count($excluded) === 1 ? 'control' : 'controls').' '.(count($excluded) === 1 ? 'is' : 'are').' excluded'.($unjustified > 0 ? ', '.$unjustified.' of them without a recorded justification' : ', each with a recorded justification').'.');

        return array(
            self::h1('Summary'),
            self::text($text),
            array(
                'kind' => 'metrics',
                'value' => array(
                    'items' => $metrics
                )
            ),
            array(
                'kind' => 'stackbar',
                'value' => array(
                    'segments' => $segments
                )
            ),
            self::note('Implementation status is the result recorded in the assessment. It is not a compliance opinion or certification.')
        );
    }

    private function family_blocks(): array
    {
        $show_justification = count(array_filter($this->a['controls'], function ($control) {
            return $control['justification'] !== '' || self::excluded($control);
        })) > 0;
        $show_evidence = $this->a['with_evidence'] > 0;
        $controls = $this->term('controls');

        $notes = array();

        if (!$show_justification) {
            $notes[] = 'No applicability justification is recorded for any '.$this->term('control').'. Every '.$this->term('control').' is treated as applicable, so the justification column is omitted.';
        }

        if (!$show_evidence) {
            $notes[] = 'No evidence is linked to any '.$this->term('control').', so the evidence column is omitted; see the Evidence Register for outstanding evidence requirements.';
        }

        $blocks = array(
            self::h1('Applicability by '.$this->term('family'))
        );

        $blocks[] = self::text('The tables below list every '.$this->term('control').' in '.$this->a['standard'].', grouped by '.$this->term('family').'.'.(count($notes) > 0 ? "\n\n".implode(' ', $notes) : ''));

        $head = array(
            'ID',
            ucfirst($this->term('control')),
            'Applicable',
            'Implementation status'
        );
        $widths = array(
            11,
            47,
            14,
            28
        );

        if ($show_justification && $show_evidence) {
            $head = array(
                'ID',
                ucfirst($this->term('control')),
                'Applicable',
                'Justification',
                'Status',
                'Evidence'
            );
            $widths = array(
                9,
                25,
                10,
                22,
                14,
                20
            );
        } elseif ($show_justification) {
            $head = array(
                'ID',
                ucfirst($this->term('control')),
                'Applicable',
                'Justification',
                'Implementation status'
            );
            $widths = array(
                10,
                32,
                11,
                27,
                20
            );
        } elseif ($show_evidence) {
            $head = array(
                'ID',
                ucfirst($this->term('control')),
                'Applicable',
                'Implementation status',
                'Evidence'
            );
            $widths = array(
                10,
                34,
                11,
                19,
                26
            );
        }

        $longest = max(array_map(function ($control) {
            return strlen($control['ref']);
        }, $this->a['controls']));

        if ($longest > 8) {
            $widths[0] += 4;
            $widths[1] -= 4;
        }

        foreach ($this->families() as $family => $list) {
            $rows = array();
            foreach ($list as $control) {
                $excluded = self::excluded($control);
                $row = array(
                    $control['ref'],
                    $control['title'],
                    $excluded ? 'No' : 'Yes'
                );
                if ($show_justification) {
                    $row[] = $control['justification'] !== '' ? $control['justification'] : ($excluded ? self::MISSING_JUSTIFICATION : 'Applicable by default');
                }
                $row[] = $excluded ? 'Excluded' : $control['result'];
                if ($show_evidence) {
                    $row[] = $this->evidence_cell($control);
                }
                $rows[] = $row;
            }
            $implemented = count(array_filter($list, function ($control) {
                return $control['result'] === 'Implemented';
            }));
            $blocks[] = self::h2($family);
            $blocks[] = self::note(count($list).' '.$this->term(count($list) === 1 ? 'control' : 'controls').', '.$implemented.' implemented.');
            $blocks[] = self::table($head, $widths, $rows);
        }

        return $blocks;
    }

    private function exclusion_blocks(): array
    {
        $excluded = $this->excluded_controls();
        $controls = $this->term('controls');

        if (count($excluded) === 0) {
            return array(
                self::h1('Exclusions'),
                self::text('No '.$this->term('control').' of '.$this->a['standard'].' has been excluded from this assessment; all '.$this->a['total'].' '.$controls.' are treated as applicable. If any '.$this->term('control').' does not apply to the organization, the exclusion and its justification should be recorded so that the decision is documented and can be reviewed.')
            );
        }

        $rows = array();
        $unjustified = 0;

        foreach ($excluded as $control) {
            if ($control['justification'] === '') {
                $unjustified++;
            }
            $rows[] = array(
                $control['ref'],
                $control['title'],
                $control['family'],
                $control['justification'] !== '' ? $control['justification'] : self::MISSING_JUSTIFICATION,
                $control['justification'] !== '' ? 'Recorded' : 'Requires confirmation'
            );
        }

        $blocks = array(
            self::h1('Exclusions'),
            self::text(count($excluded).' '.$this->term(count($excluded) === 1 ? 'control' : 'controls').' '.(count($excluded) === 1 ? 'is' : 'are').' excluded from the assessment.'
                .($unjustified > 0 ? ' '.$unjustified.' '.($unjustified === 1 ? 'has' : 'have').' no recorded justification. An exclusion without a justification cannot be defended to a reviewer and should be confirmed or reversed by the assessor.' : ' Each exclusion has a recorded justification.')),
            self::table(array(
                'ID',
                ucfirst($this->term('control')),
                ucfirst($this->term('family')),
                'Justification',
                'State'
            ), array(
                10,
                26,
                18,
                32,
                14
            ), $rows)
        );

        if ($unjustified > 0) {
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'title' => 'Action required',
                    'tone' => 'critical',
                    'body' => 'Record a justification for each exclusion marked "'.self::MISSING_JUSTIFICATION.'", stating why the '.$this->term('control').' does not apply (for example, the activity it governs is not performed or is fully outsourced) and who approved the decision.'
                )
            );
        }

        return $blocks;
    }

    private function reference_blocks(): array
    {
        return array(
            self::h1('References'),
            self::text(self::bullets(array(
                $this->a['standard'].': the framework defining the '.$this->term('controls').' and '.$this->term('families').' listed in this statement.',
                'Assessment Results report: the detailed result recorded for each '.$this->term('control').(count($this->objectives()) > 0 ? ' and '.$this->term('objective') : '').'.',
                'Evidence Register: the evidence linked to each '.$this->term('control').' and the evidence still outstanding.',
                'Gap Analysis and Remediation Plan: the deficiencies behind each partially or not implemented '.$this->term('control').' and the actions proposed to address them.'
            )))
        );
    }

    /* ------------------------------------------------------------- helpers */

    /** What an SoA means under this framework. */
    private function significance(): string
    {
        $key = strtolower((string) ($this->a['assessment']['framework_key'] ?? ''));
        $controls = $this->term('controls');

        if (strpos($key, 'iso') === 0 || strpos($key, '27001') !== false) {
            return 'Under '.$this->a['standard'].', the Statement of Applicability is a formal, mandatory document of the information security management system. It records each '.$this->term('control').' considered, whether it is included, the justification for inclusion or exclusion, and its implementation status. Certification auditors review it against the risk treatment plan, so exclusions must be justified and approved.'
                ."\n\n".'This statement is generated from the recorded assessment. It should be reviewed and approved by management before it is relied on as the organization\'s formal Statement of Applicability.';
        }

        if ($key === 'soc2') {
            return 'SOC 2 does not require a formal Statement of Applicability. This statement records which Trust Services '.$controls.' are in scope for the assessment and the applicability decision for each, with the implementation status recorded by the assessor.'
                ."\n\n".'It is not an attestation conclusion. Whether '.$controls.' are suitably designed or operating effectively is concluded only in a SOC 2 report issued by an independent service auditor. The selection of categories in scope, such as Availability or Confidentiality alongside Security, is a management decision that should be confirmed.';
        }

        return $this->a['standard'].' does not define a formal Statement of Applicability. This statement serves as a scoping record: it lists every '.$this->term('control').' in the framework, whether it is treated as applicable, the justification recorded for any exclusion, and the implementation status recorded in the assessment.'
            ."\n\n".'It documents the basis on which the assessment was performed and is not a certification or compliance opinion.';
    }

    private function families(): array
    {
        $out = array();

        foreach ($this->a['controls'] as $control) {
            $out[$control['family']][] = $control;
        }

        return $out;
    }

    private function objectives(): array
    {
        $out = array();

        foreach ($this->a['controls'] as $control) {
            foreach ($control['objectives'] as $objective) {
                $out[] = $objective;
            }
        }

        return $out;
    }

    private function excluded_controls(): array
    {
        return array_values(array_filter($this->a['controls'], function ($control) {
            return self::excluded($control);
        }));
    }

    private static function excluded(array $control): bool
    {
        return $control['applicability'] === 'Excluded' || $control['result'] === 'Not Applicable';
    }

    private function justification(array $control): string
    {
        if ($control['justification'] !== '') {
            return $control['justification'];
        }

        return self::excluded($control) ? self::MISSING_JUSTIFICATION : '';
    }

    private function evidence_cell(array $control): string
    {
        if ($control['evidence_count'] === 0) {
            return 'None linked';
        }

        if ($control['evidence_count'] <= 2) {
            return implode('; ', $control['evidence']);
        }

        return $control['evidence_count'].' items: '.implode('; ', array_slice($control['evidence'], 0, 2)).'; and '.($control['evidence_count'] - 2).' more';
    }
}
