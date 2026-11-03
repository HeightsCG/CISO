<?php
/**
 * Ready-made reports, generated straight from the data in one click: the risk
 * assessment report, assessment results, gap analysis, executive summary,
 * statement of applicability, remediation plan and evidence register. Each
 * one becomes a document for ReportRenderer (PDF and Word) and, for the
 * list-style reports, sheets for XlsxWriter (Excel).
 *
 * The compliance score counts only controls that were assessed and apply:
 * Implemented scores 1, Partially Implemented 0.5, Not Implemented 0.
 */
class ReadyReports {

    /** key => name|scope|formats|description. Scope is assessment or project. */
    const TYPES = array(
        'assessment_results' => 'Assessment Results Report|assessment|pdf,docx,xlsx|Scorecard, results by family, and every control with its result and evidence.',
        'gap_analysis' => 'Gap Analysis|assessment|pdf,docx,xlsx|Only the controls not implemented or partially implemented, by family, with the gap and a priority.',
        'executive_summary' => 'Executive Summary|assessment|pdf,docx|A short summary for leadership: score, progress, results charts, strongest and weakest areas, and top gaps.',
        'statement_of_applicability' => 'Statement of Applicability|assessment|pdf,docx,xlsx|Each control marked applicable or not, with implementation status, justification and supporting evidence.',
        'remediation_plan' => 'Remediation Plan|assessment|pdf,docx,xlsx|Gaps turned into a prioritized action list, including report findings with their owners and target dates.',
        'risk_assessment' => 'Risk Assessment Report|project|pdf,docx|The client deliverable: executive summary, results by area, rated findings for every gap, and sign-off. Uses your edited report when one exists.',
        'evidence_register' => 'Evidence Register|project|pdf,docx,xlsx|Every evidence file in the project and the controls it supports, plus controls still without evidence.'
    );

    const RESULTS = array(
        'Implemented',
        'Partially Implemented',
        'Not Implemented',
        'Not Applicable',
        'Not Assessed'
    );

    const COLORS = array(
        'Implemented' => '#067647',
        'Partially Implemented' => '#B54708',
        'Not Implemented' => '#B42318',
        'Not Applicable' => '#7A8794',
        'Not Assessed' => '#B8C2CD'
    );

    private $company_id;
    private $company;
    private $assessments_model;
    private $projects_model;
    private $evidence_model;
    private $reports_model;
    private $ready_model;

    public function __construct()
    {
        $this->company_id = Session::get('company_id');
        $this->company = (new CompaniesModel())->get_company($this->company_id)[0] ?? array();
        $this->assessments_model = new AssessmentsModel();
        $this->projects_model = new ProjectsModel();
        $this->evidence_model = new EvidenceModel();
        $this->reports_model = new ReportsModel();
        $this->ready_model = new ReadyReportsModel();
    }

    /** The report types for the screens: key, name, scope, formats, description. */
    public static function catalog(): array
    {
        $list = array();

        $format_names = array(
            'pdf' => 'PDF',
            'docx' => 'Word',
            'xlsx' => 'Excel'
        );

        $scope_names = array(
            'assessment' => 'One assessment',
            'project' => 'One project'
        );

        foreach (self::TYPES as $key => $spec) {
            $parts = explode('|', $spec);
            $formats = explode(',', $parts[2]);
            $labels = array();
            foreach ($formats as $format) {
                $labels[] = $format_names[$format];
            }
            $list[] = array(
                'key' => $key,
                'name' => $parts[0],
                'scope' => $parts[1],
                'scope_label' => $scope_names[$parts[1]],
                'formats' => $formats,
                'format_label' => implode(', ', $labels),
                'description' => $parts[3]
            );
        }

        return $list;
    }

    /** The report types that cover one assessment, or one project. */
    public static function catalog_for(string $scope): array
    {
        $list = array();

        foreach (self::catalog() as $item) {
            if ($item['scope'] === $scope) {
                $list[] = $item;
            }
        }

        return $list;
    }

    public static function spec(string $type)
    {
        foreach (self::catalog() as $item) {
            if ($item['key'] === $type) {
                return $item;
            }
        }

        return null;
    }

    /**
     * The file for one report, or array('error' => ...). $target_id is an
     * assessment or project id depending on the report.
     */
    public function render(string $type, int $target_id, string $format): array
    {
        $spec = self::spec($type);

        if ($spec === null) {
            return self::fail('That report could not be found');
        }

        if (!in_array($format, $spec['formats'], true)) {
            return self::fail('That report is not available in that format');
        }

        if ($type === 'risk_assessment') {
            $assembled = $this->risk_assessment($target_id);
            if (isset($assembled['error'])) {
                return $assembled;
            }
            return ReportRenderer::render($assembled, $format);
        }

        $built = $this->{'build_'.$type}($target_id);

        if (isset($built['error'])) {
            return $built;
        }

        if ($format === 'xlsx') {
            return array(
                'bytes' => XlsxWriter::build($built['sheets'], ltrim($this->brand(), '#')),
                'file_name' => preg_replace('/[^A-Za-z0-9 ,.&()\'-]/', '', $built['doc']['file_base']).'.xlsx',
                'extension' => 'xlsx',
                'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
        }

        return ReportRenderer::document($built['doc'], $format);
    }

    /* ---------------------------------------------------------- assessments */

    /** Everything the assessment reports share: the record, its controls and the counts. */
    private function assessment(int $assessment_id)
    {
        $rows = $this->assessments_model->get_assessment($assessment_id, $this->company_id);

        if (count($rows) !== 1) {
            return null;
        }

        $assessment = $rows[0];
        $items = $this->assessments_model->load_controls($assessment_id, $this->company_id);
        $counts = array_fill_keys(self::RESULTS, 0);
        $families = array();

        foreach ($items as $item) {
            $result = in_array($item['item_result'], self::RESULTS, true) ? $item['item_result'] : 'Not Assessed';
            $counts[$result]++;
            $family = self::plain($item['family']) !== '' ? self::plain($item['family']) : 'General';
            if (!isset($families[$family])) {
                $families[$family] = array(
                    'items' => array(),
                    'counts' => array_fill_keys(self::RESULTS, 0)
                );
            }
            $families[$family]['items'][] = $item;
            $families[$family]['counts'][$result]++;
        }

        $evidence = array();

        foreach ($this->ready_model->item_evidence($assessment_id, $this->company_id) as $link) {
            $evidence[(int) $link['assessment_item_id']][] = self::plain($link['evidence_title']);
        }

        return array(
            'assessment' => $assessment,
            'name' => self::plain($assessment['assessment_name']),
            'client' => self::plain($assessment['client_name'] ?? ''),
            'project' => self::plain($assessment['project_name']),
            'standard' => self::plain($assessment['standard_name']),
            'items' => $items,
            'counts' => $counts,
            'families' => $families,
            'evidence' => $evidence,
            'total' => count($items),
            'assessed' => count($items) - $counts['Not Assessed'],
            'score' => self::score($counts),
            'with_evidence' => count($evidence)
        );
    }

    private static function score(array $counts)
    {
        $applicable = $counts['Implemented'] + $counts['Partially Implemented'] + $counts['Not Implemented'];

        if ($applicable === 0) {
            return null;
        }

        return (int) round(100 * ($counts['Implemented'] + 0.5 * $counts['Partially Implemented']) / $applicable);
    }

    private function cover(array $a, string $title): array
    {
        return array(
            'Report date' => date('F j, Y'),
            'Assessment' => $a['name'],
            'Project' => $a['project'],
            'Standard' => $a['standard'],
            'Status' => self::plain($a['assessment']['assessment_status']),
            'Prepared by' => self::plain($this->company['company_name'] ?? '')
        );
    }

    private function doc(array $a, string $title, array $blocks): array
    {
        return array(
            'title' => $title,
            'client' => $a['client'],
            'prepared_for' => $a['client'],
            'company' => $this->company,
            'cover_rows' => $this->cover($a, $title),
            'confidential' => true,
            'blocks' => $blocks,
            'file_base' => ($a['client'] !== '' ? $a['client'].' - ' : '').$title.' - '.$a['name']
        );
    }

    private function overview_stats(array $a): array
    {
        return self::stats(array(
            'Controls' => (string) $a['total'],
            'Assessed' => $a['assessed'].' ('.self::pct($a['assessed'], $a['total']).')',
            'Compliance score' => $a['score'] === null ? 'Not yet scored' : $a['score'].'%',
            'Controls with evidence' => (string) $a['with_evidence']
        ));
    }

    private static function score_note(): array
    {
        return self::note('Compliance score: Implemented counts in full and Partially Implemented counts half, out of the controls that were assessed and apply. Controls not yet assessed or not applicable are excluded.');
    }

    private function family_table(array $a): array
    {
        $rows = array();

        foreach ($a['families'] as $family => $data) {
            $score = self::score($data['counts']);
            $rows[] = array(
                $family,
                (string) count($data['items']),
                (string) $data['counts']['Implemented'],
                (string) $data['counts']['Partially Implemented'],
                (string) $data['counts']['Not Implemented'],
                (string) $data['counts']['Not Applicable'],
                (string) $data['counts']['Not Assessed'],
                $score === null ? '' : $score.'%'
            );
        }

        return ReportRenderer::table_block(array(
            'Family',
            'Controls',
            'Impl.',
            'Partial',
            'Not impl.',
            'N/A',
            'Not assessed',
            'Score'
        ), array(
            30,
            10,
            9,
            9,
            10,
            8,
            13,
            11
        ), $rows, true);
    }

    private function status_table(array $a): array
    {
        $rows = array();

        foreach (self::RESULTS as $result) {
            $rows[] = array(
                $result,
                (string) $a['counts'][$result],
                self::pct($a['counts'][$result], $a['total'])
            );
        }

        return ReportRenderer::table_block(array(
            'Result',
            'Controls',
            'Share'
        ), array(
            50,
            25,
            25
        ), $rows);
    }

    private function build_assessment_results(int $id): array
    {
        $a = $this->assessment($id);

        if ($a === null) {
            return self::fail('That assessment could not be found');
        }

        $blocks = array(
            self::h1('Summary'),
            self::text('This report records the results of the '.$a['name'].' assessment against '.$a['standard'].($a['client'] !== '' ? ' for '.$a['client'] : '').'. '
                .$a['assessed'].' of '.$a['total'].' controls have been assessed.'),
            $this->overview_stats($a),
            self::score_note(),
            self::h2('Results by status'),
            $this->status_table($a),
            self::h2('Results by family'),
            $this->family_table($a),
            self::h1('Control results')
        );

        $sheet_rows = array();

        foreach ($a['families'] as $family => $data) {
            $rows = array();
            foreach ($data['items'] as $item) {
                $titles = $a['evidence'][(int) $item['id']] ?? array();
                $rows[] = array(
                    self::plain($item['control_identifier']),
                    self::plain($item['control_title']),
                    $item['item_result'],
                    (string) count($titles)
                );
                $sheet_rows[] = array(
                    $family,
                    self::plain($item['control_identifier']),
                    self::plain($item['control_title']),
                    $item['item_result'],
                    count($titles),
                    implode('; ', $titles)
                );
            }
            $blocks[] = self::h2($family);
            $blocks[] = ReportRenderer::table_block(array(
                'ID',
                'Control',
                'Result',
                'Evidence'
            ), array(
                12,
                58,
                18,
                12
            ), $rows, true);
        }

        return array(
            'doc' => $this->doc($a, 'Assessment Results Report', $blocks),
            'sheets' => array(
                array(
                    'name' => 'Controls',
                    'head' => array(
                        'Family',
                        'Identifier',
                        'Control',
                        'Result',
                        'Evidence count',
                        'Evidence'
                    ),
                    'widths' => array(
                        24,
                        14,
                        50,
                        20,
                        14,
                        50
                    ),
                    'rows' => $sheet_rows
                ),
                $this->family_sheet($a)
            )
        );
    }

    private function family_sheet(array $a): array
    {
        $rows = array();

        foreach ($a['families'] as $family => $data) {
            $score = self::score($data['counts']);
            $rows[] = array(
                $family,
                count($data['items']),
                $data['counts']['Implemented'],
                $data['counts']['Partially Implemented'],
                $data['counts']['Not Implemented'],
                $data['counts']['Not Applicable'],
                $data['counts']['Not Assessed'],
                $score === null ? '' : $score
            );
        }

        return array(
            'name' => 'By family',
            'head' => array(
                'Family',
                'Controls',
                'Implemented',
                'Partially implemented',
                'Not implemented',
                'Not applicable',
                'Not assessed',
                'Score %'
            ),
            'widths' => array(
                30,
                12,
                14,
                20,
                16,
                15,
                14,
                10
            ),
            'rows' => $rows
        );
    }

    /** Not Implemented and Partially Implemented controls, worst first within each family. */
    private static function gaps(array $a): array
    {
        $gaps = array();

        foreach ($a['families'] as $family => $data) {
            foreach ($data['items'] as $item) {
                if ($item['item_result'] === 'Not Implemented' || $item['item_result'] === 'Partially Implemented') {
                    $gaps[$family][] = $item;
                }
            }
            if (isset($gaps[$family])) {
                usort($gaps[$family], function ($x, $y) {
                    return ($x['item_result'] === 'Not Implemented' ? 0 : 1) <=> ($y['item_result'] === 'Not Implemented' ? 0 : 1);
                });
            }
        }

        return $gaps;
    }

    private static function priority(string $result): string
    {
        return $result === 'Not Implemented' ? 'High' : 'Medium';
    }

    private function build_gap_analysis(int $id): array
    {
        $a = $this->assessment($id);

        if ($a === null) {
            return self::fail('That assessment could not be found');
        }

        $gaps = self::gaps($a);
        $gap_total = $a['counts']['Not Implemented'] + $a['counts']['Partially Implemented'];

        $blocks = array(
            self::h1('Summary'),
            self::text($gap_total === 0
                ? 'No gaps are recorded: every assessed control is implemented or not applicable.'.($a['counts']['Not Assessed'] > 0 ? ' '.$a['counts']['Not Assessed'].' controls have not been assessed yet and are not included.' : '')
                : $gap_total.' controls in the '.$a['name'].' assessment are not fully implemented: '.$a['counts']['Not Implemented'].' not implemented (high priority) and '.$a['counts']['Partially Implemented'].' partially implemented (medium priority), across '.count($gaps).' famil'.(count($gaps) === 1 ? 'y' : 'ies').'.'
                    .($a['counts']['Not Assessed'] > 0 ? ' '.$a['counts']['Not Assessed'].' controls have not been assessed yet and may contain further gaps.' : '')),
            self::stats(array(
                'Gaps' => (string) $gap_total,
                'Not implemented' => (string) $a['counts']['Not Implemented'],
                'Partially implemented' => (string) $a['counts']['Partially Implemented'],
                'Families affected' => (string) count($gaps)
            ))
        );

        $sheet_rows = array();

        if ($gap_total > 0) {
            $rows = array();
            foreach ($gaps as $family => $items) {
                $counts = array_count_values(array_column($items, 'item_result'));
                $rows[] = array(
                    $family,
                    (string) ($counts['Not Implemented'] ?? 0),
                    (string) ($counts['Partially Implemented'] ?? 0),
                    (string) count($items)
                );
            }
            $blocks[] = self::h2('Gaps by family');
            $blocks[] = ReportRenderer::table_block(array(
                'Family',
                'Not implemented',
                'Partially implemented',
                'Total gaps'
            ), array(
                43,
                19,
                22,
                16
            ), $rows);
            $blocks[] = self::h1('Gaps');

            foreach ($gaps as $family => $items) {
                $rows = array();
                foreach ($items as $item) {
                    $rows[] = array(
                        self::priority($item['item_result']),
                        self::plain($item['control_identifier']),
                        self::plain($item['control_title']),
                        $item['item_result'],
                        self::plain($item['gap_description'])
                    );
                    $sheet_rows[] = array(
                        self::priority($item['item_result']),
                        $family,
                        self::plain($item['control_identifier']),
                        self::plain($item['control_title']),
                        $item['item_result'],
                        self::plain($item['gap_description'])
                    );
                }
                $blocks[] = self::h2($family);
                $blocks[] = ReportRenderer::table_block(array(
                    'Priority',
                    'ID',
                    'Control',
                    'Result',
                    'Gap'
                ), array(
                    10,
                    12,
                    30,
                    15,
                    33
                ), $rows, true);
            }
        }

        return array(
            'doc' => $this->doc($a, 'Gap Analysis', $blocks),
            'sheets' => array(
                array(
                    'name' => 'Gaps',
                    'head' => array(
                        'Priority',
                        'Family',
                        'Identifier',
                        'Control',
                        'Result',
                        'Gap'
                    ),
                    'widths' => array(
                        10,
                        24,
                        14,
                        50,
                        22,
                        70
                    ),
                    'rows' => $sheet_rows
                )
            )
        );
    }

    private function build_executive_summary(int $id): array
    {
        $a = $this->assessment($id);

        if ($a === null) {
            return self::fail('That assessment could not be found');
        }

        $scored = array();

        foreach ($a['families'] as $family => $data) {
            $score = self::score($data['counts']);
            if ($score !== null) {
                $scored[$family] = $score;
            }
        }

        arsort($scored);

        $narrative = $a['assessed'].' of '.$a['total'].' controls ('.self::pct($a['assessed'], $a['total']).') have been assessed against '.$a['standard'].'.';

        if ($a['score'] !== null) {
            $narrative .= ' Of the assessed controls that apply, '.$a['counts']['Implemented'].' are implemented, '.$a['counts']['Partially Implemented'].' partially implemented and '.$a['counts']['Not Implemented'].' not implemented, giving a compliance score of '.$a['score'].'%.';
        } else {
            $narrative .= ' No applicable controls have been assessed yet, so there is no compliance score.';
        }

        if (count($scored) >= 2) {
            $narrative .= "\n\nThe strongest area is ".array_key_first($scored).' ('.reset($scored).'%) and the weakest is '.array_key_last($scored).' ('.end($scored).'%).';
        }

        $blocks = array(
            self::h1('At a glance'),
            self::text($narrative),
            $this->overview_stats($a),
            self::score_note(),
            self::h2('Results')
        );

        $bars = array();
        foreach (self::RESULTS as $result) {
            $bars[] = array(
                'label' => $result,
                'value' => $a['counts'][$result],
                'max' => max(1, $a['total']),
                'text' => $a['counts'][$result].' ('.self::pct($a['counts'][$result], $a['total']).')',
                'color' => self::COLORS[$result]
            );
        }
        $blocks[] = array(
            'kind' => 'bars',
            'value' => $bars
        );

        if (count($scored) > 0) {
            $bars = array();
            foreach ($scored as $family => $score) {
                $bars[] = array(
                    'label' => $family,
                    'value' => $score,
                    'max' => 100,
                    'text' => $score.'%',
                    'color' => $this->brand()
                );
            }
            $blocks[] = self::h2('Compliance score by family');
            $blocks[] = array(
                'kind' => 'bars',
                'value' => $bars
            );
        }

        $priority = array();
        foreach ($a['items'] as $item) {
            if ($item['item_result'] === 'Not Implemented' && count($priority) < 10) {
                $priority[] = array(
                    self::plain($item['control_identifier']),
                    self::plain($item['control_title']),
                    mb_strimwidth(self::plain($item['gap_description']), 0, 220, '...')
                );
            }
        }

        if (count($priority) > 0) {
            $blocks[] = self::h2('Priority gaps');
            $blocks[] = ReportRenderer::table_block(array(
                'ID',
                'Control',
                'Gap'
            ), array(
                14,
                40,
                46
            ), $priority, true);
        }

        $findings = $this->report_findings((int) $a['assessment']['project_id']);

        if (count($findings) > 0) {
            $rows = array();
            foreach (array_slice($findings, 0, 8) as $finding) {
                $rows[] = array(
                    $finding['finding_ref'],
                    self::plain($finding['finding_title']),
                    $finding['rating'].' ('.$finding['score'].')',
                    $finding['response_status'] === 'Pending' ? '' : $finding['response_status']
                );
            }
            $blocks[] = self::h2('Key findings');
            $blocks[] = ReportRenderer::table_block(array(
                'Ref.',
                'Finding',
                'Rating',
                'Response'
            ), array(
                9,
                57,
                16,
                18
            ), $rows);
        }

        return array(
            'doc' => $this->doc($a, 'Executive Summary', $blocks),
            'sheets' => array()
        );
    }

    /** Findings from the project's report builder, highest score first, when a report exists. */
    private function report_findings(int $project_id): array
    {
        $report = $this->reports_model->get_report_by_project($project_id, $this->company_id);

        if (count($report) !== 1) {
            return array();
        }

        return (new ReportBuilder())->assemble($report[0])['findings_ranked'];
    }

    private function build_statement_of_applicability(int $id): array
    {
        $a = $this->assessment($id);

        if ($a === null) {
            return self::fail('That assessment could not be found');
        }

        $applicable = $a['total'] - $a['counts']['Not Applicable'];

        $blocks = array(
            self::h1('Statement of Applicability'),
            self::text('This statement lists every control in '.$a['standard'].', whether it applies'.($a['client'] !== '' ? ' to '.$a['client'] : '').', its implementation status, the justification recorded by the assessor, and the evidence that supports it. '
                .$applicable.' of '.$a['total'].' controls are applicable; '.$a['counts']['Not Applicable'].' have been excluded.'),
            self::stats(array(
                'Controls' => (string) $a['total'],
                'Applicable' => (string) $applicable,
                'Excluded' => (string) $a['counts']['Not Applicable'],
                'Implemented' => (string) $a['counts']['Implemented']
            ))
        );

        $sheet_rows = array();

        foreach ($a['families'] as $family => $data) {
            $rows = array();
            foreach ($data['items'] as $item) {
                $excluded = $item['item_result'] === 'Not Applicable';
                $titles = implode('; ', $a['evidence'][(int) $item['id']] ?? array());
                $status = $excluded ? '' : $item['item_result'];
                $rows[] = array(
                    self::plain($item['control_identifier']),
                    self::plain($item['control_title']),
                    $excluded ? 'No' : 'Yes',
                    $status,
                    self::plain($item['applicability_justification']),
                    $titles
                );
                $sheet_rows[] = array(
                    $family,
                    self::plain($item['control_identifier']),
                    self::plain($item['control_title']),
                    $excluded ? 'No' : 'Yes',
                    $status,
                    self::plain($item['applicability_justification']),
                    $titles
                );
            }
            $blocks[] = self::h2($family);
            $blocks[] = ReportRenderer::table_block(array(
                'ID',
                'Control',
                'Applies',
                'Implementation',
                'Justification',
                'Evidence'
            ), array(
                11,
                27,
                8,
                14,
                23,
                17
            ), $rows, true);
        }

        return array(
            'doc' => $this->doc($a, 'Statement of Applicability', $blocks),
            'sheets' => array(
                array(
                    'name' => 'Statement of Applicability',
                    'head' => array(
                        'Family',
                        'Identifier',
                        'Control',
                        'Applicable',
                        'Implementation',
                        'Justification',
                        'Evidence'
                    ),
                    'widths' => array(
                        24,
                        14,
                        50,
                        11,
                        22,
                        60,
                        50
                    ),
                    'rows' => $sheet_rows
                )
            )
        );
    }

    private function build_remediation_plan(int $id): array
    {
        $a = $this->assessment($id);

        if ($a === null) {
            return self::fail('That assessment could not be found');
        }

        $gaps = self::gaps($a);
        $findings = array_values(array_filter($this->report_findings((int) $a['assessment']['project_id']), function ($finding) {
            return $finding['rating'] !== 'Low' || $finding['response_status'] !== 'Not agreed';
        }));

        $actions = 0;
        foreach ($gaps as $items) {
            $actions += count($items);
        }

        $blocks = array(
            self::h1('Summary'),
            self::text('This plan lists the actions needed to close the gaps found in the '.$a['name'].' assessment, highest priority first. '
                .(count($findings) > 0 ? 'It starts with the findings from the risk assessment report, which carry agreed owners and target dates, followed by' : 'It lists').' the control-level actions from the assessment.'),
            self::stats(array(
                'Report findings' => (string) count($findings),
                'Control actions' => (string) $actions,
                'High priority' => (string) $a['counts']['Not Implemented'],
                'Medium priority' => (string) $a['counts']['Partially Implemented']
            ))
        );

        $finding_rows = array();

        if (count($findings) > 0) {
            $rows = array();
            foreach ($findings as $finding) {
                $row = array(
                    $finding['finding_ref'],
                    self::plain($finding['finding_title']),
                    $finding['rating'],
                    self::plain($finding['recommendation']),
                    self::plain($finding['response_owner']),
                    self::date($finding['target_date']),
                    $finding['response_status'] === 'Pending' ? '' : $finding['response_status']
                );
                $rows[] = $row;
                $finding_rows[] = $row;
            }
            $blocks[] = self::h1('Actions from report findings');
            $blocks[] = ReportRenderer::table_block(array(
                'Ref.',
                'Finding',
                'Rating',
                'Recommended action',
                'Owner',
                'Target',
                'Response'
            ), array(
                7,
                22,
                8,
                29,
                12,
                11,
                11
            ), $rows, true);
        }

        $sheet_rows = array();

        if ($actions > 0) {
            $rows = array();
            foreach ($gaps as $family => $items) {
                foreach ($items as $item) {
                    $action = self::plain($item['recommendation']) !== '' ? self::plain($item['recommendation']) : 'Implement the control as described in '.$a['standard'].'.';
                    $rows[] = array(
                        self::priority($item['item_result']),
                        self::plain($item['control_identifier']),
                        self::plain($item['control_title']),
                        $item['item_result'],
                        $action
                    );
                    $sheet_rows[] = array(
                        self::priority($item['item_result']),
                        $family,
                        self::plain($item['control_identifier']),
                        self::plain($item['control_title']),
                        $item['item_result'],
                        $action,
                        '',
                        '',
                        ''
                    );
                }
            }
            usort($rows, function ($x, $y) {
                return ($x[0] === 'High' ? 0 : 1) <=> ($y[0] === 'High' ? 0 : 1);
            });
            usort($sheet_rows, function ($x, $y) {
                return ($x[0] === 'High' ? 0 : 1) <=> ($y[0] === 'High' ? 0 : 1);
            });
            $blocks[] = self::h1('Control remediation actions');
            $blocks[] = ReportRenderer::table_block(array(
                'Priority',
                'ID',
                'Control',
                'Current result',
                'Action'
            ), array(
                10,
                12,
                29,
                15,
                34
            ), $rows, true);
        } elseif (count($findings) === 0) {
            $blocks[] = self::text('No remediation actions are needed yet: no controls are recorded as not implemented or partially implemented.');
        }

        $sheets = array(
            array(
                'name' => 'Control actions',
                'head' => array(
                    'Priority',
                    'Family',
                    'Identifier',
                    'Control',
                    'Current result',
                    'Action',
                    'Owner',
                    'Target date',
                    'Status'
                ),
                'widths' => array(
                    10,
                    24,
                    14,
                    45,
                    20,
                    60,
                    18,
                    14,
                    14
                ),
                'rows' => $sheet_rows
            )
        );

        if (count($finding_rows) > 0) {
            array_unshift($sheets, array(
                'name' => 'Report findings',
                'head' => array(
                    'Ref.',
                    'Finding',
                    'Rating',
                    'Recommended action',
                    'Owner',
                    'Target date',
                    'Response'
                ),
                'widths' => array(
                    8,
                    45,
                    10,
                    60,
                    20,
                    16,
                    16
                ),
                'rows' => $finding_rows
            ));
        }

        return array(
            'doc' => $this->doc($a, 'Remediation Plan', $blocks),
            'sheets' => $sheets
        );
    }

    /* -------------------------------------------------------------- project */

    /**
     * The risk assessment for a project. When the report builder has a report
     * for it, that report is used as edited. Otherwise one is put together
     * from the project's assessments: each control not implemented or only
     * partially implemented becomes a finding, rated by a fixed rule that the
     * report states (not implemented: likelihood 4, impact 4, High; partially
     * implemented: likelihood 3, impact 3, Medium), and each control family
     * becomes a domain rated from its compliance score.
     */
    private function risk_assessment(int $project_id): array
    {
        $rows = $this->projects_model->get_project($project_id, $this->company_id);

        if (count($rows) !== 1) {
            return self::fail('That project could not be found');
        }

        $saved = $this->reports_model->get_report_by_project($project_id, $this->company_id);

        if (count($saved) === 1) {
            return (new ReportBuilder())->assemble($saved[0]);
        }

        $project = $rows[0];
        $client = (new ClientsModel())->get_client($project['client_id'], $this->company_id)[0] ?? array();
        $client_name = self::plain($client['company_name'] ?? '');
        $firm = self::plain($this->company['company_name'] ?? '');

        $domains = array();
        $findings = array();
        $standards = array();
        $counts = array_fill_keys(self::RESULTS, 0);
        $number = 0;

        $assessment_rows = $this->assessments_model->load_assessments($project_id, $this->company_id);
        $several = count($assessment_rows) > 1;

        foreach ($assessment_rows as $row) {

            $a = $this->assessment((int) $row['id']);

            if ($a === null) {
                continue;
            }

            $standards[] = $a['standard'];
            $csf = stripos($a['standard'], 'Cybersecurity Framework') !== false;

            foreach (self::RESULTS as $result) {
                $counts[$result] += $a['counts'][$result];
            }

            foreach ($a['families'] as $family => $data) {

                $domain_id = count($domains) + 1;
                $score = self::score($data['counts']);
                $domain_findings = array();

                foreach ($data['items'] as $item) {

                    if ($item['item_result'] !== 'Not Implemented' && $item['item_result'] !== 'Partially Implemented') {
                        continue;
                    }

                    $number++;
                    $high = $item['item_result'] === 'Not Implemented';
                    $gap = self::plain($item['gap_description']);
                    $recommendation = self::plain($item['recommendation']);
                    $finding = array(
                        'id' => $number,
                        'domain_id' => $domain_id,
                        'finding_ref' => 'F-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                        'finding_title' => self::plain($item['control_identifier']).' '.rtrim(self::plain($item['control_title']), '. ').' ('.strtolower($item['item_result']).')',
                        'observation' => $gap !== '' ? $gap : 'Assessed as '.strtolower($item['item_result']).' during the '.$a['name'].' assessment ('.self::plain($item['control_identifier']).').',
                        'risk_text' => $high
                            ? 'Without this control, the organization is exposed to the threats it is designed to address.'
                            : 'The control is only partly in place, so the protection it is meant to give is incomplete.',
                        'recommendation' => $recommendation !== '' ? $recommendation : ($high ? 'Put this control in place in full.' : 'Complete the implementation of this control.'),
                        'likelihood' => $high ? 4 : 3,
                        'impact' => $high ? 4 : 3,
                        'score' => $high ? 16 : 9,
                        'rating' => $high ? 'High' : 'Medium',
                        'csf_refs' => $csf ? self::plain($item['control_identifier']) : '',
                        'response_status' => 'Pending',
                        'management_response' => '',
                        'response_owner' => '',
                        'target_date' => null,
                        'sort_order' => $number
                    );
                    $domain_findings[] = $finding;
                    $findings[] = $finding;
                }

                $domains[] = array(
                    'id' => $domain_id,
                    'domain_name' => $several ? $family.' ('.$a['name'].')' : $family,
                    'areas_reviewed' => count($data['items']).' controls',
                    'csf_refs' => '',
                    'domain_rating' => self::domain_rating($score),
                    'domain_summary' => $data['counts']['Implemented'].' of '.count($data['items']).' controls are implemented'
                        .($data['counts']['Not Assessed'] > 0 ? ', and '.$data['counts']['Not Assessed'].' have not been assessed yet' : '').'.',
                    'counts' => array(
                        'High' => count(array_filter($domain_findings, function ($f) {
                            return $f['rating'] === 'High';
                        })),
                        'Medium' => count(array_filter($domain_findings, function ($f) {
                            return $f['rating'] === 'Medium';
                        })),
                        'Low' => 0
                    ),
                    'findings' => $domain_findings
                );
            }
        }

        if (count($standards) === 0) {
            return self::fail('This project has no assessments to report on yet');
        }

        $ranked = $findings;
        usort($ranked, function ($x, $y) {
            return $y['score'] <=> $x['score'];
        });

        $total = array_sum($counts);
        $assessed = $total - $counts['Not Assessed'];
        $score = self::score($counts);
        $high = $counts['Not Implemented'];
        $medium = $counts['Partially Implemented'];

        $threats = array();
        foreach (ReportBuilder::default_threats() as $index => $threat) {
            $threats[] = array(
                'threat_ref' => 'T'.($index + 1),
                'threat_name' => $threat[0],
                'threat_description' => $threat[1],
                'parties_affected' => $threat[2]
            );
        }

        $report = array(
            'id' => 0,
            'company_id' => $this->company_id,
            'project_id' => $project_id,
            'report_title' => 'Cybersecurity Risk Assessment',
            'report_date' => date('Y-m-d'),
            'period_start' => $project['start_date'],
            'period_end' => $project['end_date'],
            'regulatory_reference' => '',
            'report_version' => '1.0',
            'report_stage' => 'Final',
            'purpose_text' => ($client_name !== '' ? $client_name : 'The client').' engaged '.$firm.' to assess its cybersecurity controls against '.implode(' and ', array_unique($standards)).'. This report identifies and prioritizes the gaps found so they can be addressed.',
            'conclusion_text' => $assessed.' of '.$total.' controls ('.self::pct($assessed, $total).') have been assessed'
                .($score !== null ? ', giving a compliance score of '.$score.'%' : '').'. '
                .($high + $medium === 0
                    ? 'No gaps have been recorded among the assessed controls.'
                    : 'We identified '.($high + $medium).' gap'.($high + $medium === 1 ? '' : 's').': '.$high.' rated high (controls not implemented) and '.$medium.' rated medium (controls partially implemented). '.($high + $medium === 1 ? 'It is' : 'Each is').' described in Section 3 with a recommendation.')
                .($counts['Not Assessed'] > 0 ? ' '.$counts['Not Assessed'].' controls have not been assessed yet and are not reflected in the findings.' : ''),
            'background_text' => self::plain($project['description']),
            'scope_text' => 'The assessment covered '.$total.' controls in '.implode(' and ', array_unique($standards)).', grouped into the areas below.',
            'out_of_scope_text' => '',
            'approach_text' => 'Each control was reviewed and assigned a result: implemented, partially implemented, not implemented or not applicable, supported by evidence held in the project\'s evidence vault and the assessor\'s findings.',
            'methodology_text' => 'Each control not implemented or only partially implemented is reported as a finding. Findings for controls not implemented are rated likelihood 4 and impact 4, a score of 16 (High). Findings for controls partially implemented are rated likelihood 3 and impact 3, a score of 9 (Medium). '
                ."\n\n".'Each area is rated from its compliance score, in which implemented controls count in full and partially implemented controls count half: 85% or more is Satisfactory, 60% or more Satisfactory with exceptions, 30% or more Needs improvement, and below 30% Unsatisfactory.',
            'limitations_text' => ReportBuilder::default_limitations($firm),
            'review_text' => 'This assessment should be reviewed and updated at least annually, and sooner after any significant change to the business or its technology.',
            'scan_text' => '',
            'signoff_name' => self::plain($client['contact_name'] ?? ''),
            'signoff_title' => self::plain($client['contact_title'] ?? '')
        );

        return array(
            'report' => $report,
            'project' => $project,
            'client' => $client,
            'company' => $this->company,
            'versions' => array(),
            'distribution' => array(),
            'team' => array(),
            'domains' => $domains,
            'findings' => $findings,
            'findings_ranked' => $ranked,
            'unassigned_findings' => array(),
            'totals' => array(
                'High' => $high,
                'Medium' => $medium,
                'Low' => 0
            ),
            'risks' => array(),
            'risks_ranked' => array(),
            'threats' => $threats,
            'assets' => array(),
            'csf' => array(),
            'scan_rows' => array(),
            'xrefs' => array()
        );
    }

    private static function domain_rating($score)
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

    private function build_evidence_register(int $project_id): array
    {
        $project = $this->projects_model->get_project($project_id, $this->company_id);

        if (count($project) !== 1) {
            return self::fail('That project could not be found');
        }

        $project = $project[0];
        $client = self::plain($project['client_name'] ?? '');
        $name = self::plain($project['project_name']);
        $files = $this->evidence_model->load_evidence($project_id, $this->company_id);
        $controls = array();

        foreach ($this->ready_model->evidence_controls($project_id, $this->company_id) as $link) {
            $controls[(int) $link['evidence_id']][] = self::plain($link['control_identifier']);
        }

        $rows = array();
        $sheet_rows = array();
        $linked = 0;

        foreach ($files as $file) {
            $supports = array_values(array_unique($controls[(int) $file['id']] ?? array()));
            if (count($supports) > 0) {
                $linked++;
            }
            $rows[] = array(
                self::plain($file['evidence_title']),
                self::plain($file['file_name']),
                self::plain($file['uploaded_by_name'] ?? ''),
                self::date($file['date_created']),
                implode(', ', $supports),
                (int) $file['evidence_private'] === 1 ? 'Private' : 'Shared'
            );
            $sheet_rows[] = array(
                self::plain($file['evidence_title']),
                self::plain($file['description']),
                self::plain($file['file_name']),
                self::plain($file['uploaded_by_name'] ?? ''),
                self::date($file['date_created']),
                count($supports),
                implode(', ', $supports),
                (int) $file['evidence_private'] === 1 ? 'Private' : 'Shared'
            );
        }

        $missing_blocks = array();
        $missing_rows = array();

        foreach ($this->assessments_model->load_assessments($project_id, $this->company_id) as $assessment) {
            $rows_missing = array();
            foreach ($this->assessments_model->load_controls((int) $assessment['id'], $this->company_id) as $item) {
                if ((int) $item['evidence_count'] === 0 && $item['item_result'] !== 'Not Applicable') {
                    $rows_missing[] = array(
                        self::plain($item['control_identifier']),
                        self::plain($item['control_title']),
                        $item['item_result']
                    );
                    $missing_rows[] = array(
                        self::plain($assessment['assessment_name']),
                        self::plain($item['control_identifier']),
                        self::plain($item['control_title']),
                        $item['item_result']
                    );
                }
            }
            if (count($rows_missing) > 0) {
                $missing_blocks[] = self::h2(self::plain($assessment['assessment_name']));
                $missing_blocks[] = ReportRenderer::table_block(array(
                    'ID',
                    'Control',
                    'Result'
                ), array(
                    16,
                    62,
                    22
                ), $rows_missing, true);
            }
        }

        $blocks = array(
            self::h1('Summary'),
            self::text('This register lists the '.count($files).' evidence files held for '.$name.($client !== '' ? ' ('.$client.')' : '').', the controls each one supports, and the applicable controls that do not yet have evidence.'),
            self::stats(array(
                'Evidence files' => (string) count($files),
                'Linked to controls' => (string) $linked,
                'Not linked' => (string) (count($files) - $linked),
                'Controls without evidence' => (string) count($missing_rows)
            )),
            self::h1('Evidence files')
        );

        if (count($rows) > 0) {
            $blocks[] = ReportRenderer::table_block(array(
                'Title',
                'File',
                'Uploaded by',
                'Date',
                'Controls supported',
                'Visibility'
            ), array(
                24,
                20,
                14,
                13,
                19,
                10
            ), $rows, true);
        } else {
            $blocks[] = self::text('No evidence files have been added to this project yet.');
        }

        if (count($missing_blocks) > 0) {
            $blocks[] = self::h1('Controls without evidence');
            $blocks = array_merge($blocks, $missing_blocks);
        }

        return array(
            'doc' => array(
                'title' => 'Evidence Register',
                'client' => $client,
                'prepared_for' => $client,
                'company' => $this->company,
                'cover_rows' => array(
                    'Report date' => date('F j, Y'),
                    'Project' => $name,
                    'Prepared by' => self::plain($this->company['company_name'] ?? '')
                ),
                'confidential' => true,
                'blocks' => $blocks,
                'file_base' => ($client !== '' ? $client.' - ' : '').'Evidence Register - '.$name
            ),
            'sheets' => array(
                array(
                    'name' => 'Evidence',
                    'head' => array(
                        'Title',
                        'Description',
                        'File',
                        'Uploaded by',
                        'Date',
                        'Controls supported',
                        'Control identifiers',
                        'Visibility'
                    ),
                    'widths' => array(
                        40,
                        50,
                        34,
                        20,
                        16,
                        12,
                        40,
                        12
                    ),
                    'rows' => $sheet_rows
                ),
                array(
                    'name' => 'Controls without evidence',
                    'head' => array(
                        'Assessment',
                        'Identifier',
                        'Control',
                        'Result'
                    ),
                    'widths' => array(
                        30,
                        14,
                        60,
                        20
                    ),
                    'rows' => $missing_rows
                )
            )
        );
    }

    /* -------------------------------------------------------------- helpers */

    private function brand(): string
    {
        $color = (string) ($this->company['brand_color'] ?? '');

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#1F3A5F';
    }

    private static function stats(array $pairs): array
    {
        $tiles = array();

        foreach ($pairs as $label => $value) {
            $tiles[] = array(
                'label' => $label,
                'value' => $value
            );
        }

        return array(
            'kind' => 'stats',
            'value' => $tiles
        );
    }

    private static function h1(string $text): array
    {
        return array(
            'kind' => 'h1',
            'value' => $text
        );
    }

    private static function h2(string $text): array
    {
        return array(
            'kind' => 'h2',
            'value' => $text
        );
    }

    private static function text(string $text): array
    {
        return array(
            'kind' => 'text',
            'value' => $text
        );
    }

    private static function note(string $text): array
    {
        return array(
            'kind' => 'note',
            'value' => $text
        );
    }

    private static function pct(int $part, int $whole): string
    {
        return $whole > 0 ? (int) round(100 * $part / $whole).'%' : '0%';
    }

    private static function date($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $time = strtotime((string) $value);

        return $time ? date('F j, Y', $time) : '';
    }

    private static function plain($value): string
    {
        return trim(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
    }

    private static function fail(string $message): array
    {
        return array(
            'error' => $message
        );
    }

}
