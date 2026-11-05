<?php
/**
 * Base for the per-report builders (ReportExecutiveSummary, ReportRiskAssessment,
 * ReportGapAnalysis, ReportRemediationPlan, ReportEvidenceRegister,
 * ReportStatementOfApplicability, ReportAssessmentResults). It loads the
 * assessment analysis once, overlays stored AI narratives when they exist,
 * and offers the block helpers and the document shell (cover rows, file
 * name) that every report shares. A builder returns
 * array('doc' => ..., 'sheets' => ...) or array('error' => ...).
 */
abstract class ReportBuilder
{
    /** Rows the cover's detail table holds above the confidentiality notice. */
    const COVER_ROWS = 6;

    protected $a;
    protected $company;
    protected $company_id;
    protected $options;

    /**
     * @param array $options detail ('executive'|'standard'|'comprehensive'),
     *                       appendices (bool), report_status ('Draft'|'In Review'|'Final'),
     *                       report_version, changed_since_final (bool), generated
     *                       (Y-m-d H:i:s), approved_by, approved_date, narrative_mode
     *                       ('draft'|'final'); see ReportStates::document_options()
     */
    public function __construct(int $assessment_id, int $company_id, array $company, array $options = array())
    {
        $this->company_id = $company_id;
        $this->company = $company;
        $this->options = array_merge(array(
            'detail' => 'standard',
            'appendices' => true,
            'report_status' => 'Draft',
            'report_version' => ReportStates::INITIAL_VERSION,
            'changed_since_final' => false,
            'generated' => date('Y-m-d H:i:s'),
            'approved_by' => '',
            'approved_date' => null,
            'narrative_mode' => 'draft'
        ), $options);

        $analysis = (new ReportAnalysis($assessment_id, $company_id))->build();

        if ($analysis !== null && class_exists('ReportNarratives')) {
            $analysis = ReportNarratives::apply($analysis, $company_id, $this->options['narrative_mode'] === 'final' ? 'final' : 'draft');
        }

        $this->a = $analysis;
    }

    abstract protected function title(): string;

    abstract protected function blocks(): array;

    /** Excel sheets, when the report offers Excel. */
    protected function sheets(): array
    {
        return array();
    }

    /** Extra document options (toc, numbered). */
    protected function doc_options(): array
    {
        return array(
            'toc' => false,
            'numbered' => false
        );
    }

    public function build(): array
    {
        if ($this->a === null) {
            return array(
                'error' => 'That assessment could not be found'
            );
        }

        return array(
            'doc' => array_merge(array(
                'title' => $this->title(),
                'client' => $this->a['client'],
                'prepared_for' => $this->a['client'],
                'company' => $this->company,
                'cover_rows' => $this->cover_rows(),
                'confidential' => true,
                'blocks' => $this->blocks(),
                'file_base' => ($this->a['client'] !== '' ? $this->a['client'].' - ' : '').$this->title().' - '.$this->a['name'],
                'document_control' => $this->document_control()
            ), $this->doc_options()),
            'sheets' => $this->sheets()
        );
    }

    /**
     * The document's state for the interior footer and the closing
     * "Document control" block: assessment reference, version, status,
     * generated timestamp and, for Final, who approved it and when.
     */
    protected function document_control(): array
    {
        return array(
            'assessment_id' => (int) $this->a['assessment']['id'],
            'assessment_name' => $this->a['name'],
            'report_status' => $this->options['report_status'],
            'report_version' => (string) $this->options['report_version'],
            'changed_since_final' => (bool) $this->options['changed_since_final'],
            'generated' => (string) $this->options['generated'],
            'approved_by' => (string) $this->options['approved_by'],
            'approved_date' => $this->options['approved_date'],
            'assessment_status' => ReportAnalysis::plain($this->a['assessment']['assessment_status'] ?? ''),
            'periods' => $this->period_rows()
        );
    }

    /**
     * The three periods every report distinguishes: the planned assessment
     * period, the window in which responses were recorded (fieldwork) and
     * the reporting cutoff, as label/value rows.
     */
    protected function period_rows(): array
    {
        $periods = $this->a['periods'] ?? array();

        return array(
            array(
                'Planned assessment period',
                (string) ($periods['planned'] ?? $this->a['period'])
            ),
            array(
                'Responses recorded',
                (string) ($periods['responses'] ?? 'Not recorded')
            ),
            array(
                'Reporting cutoff',
                (string) ($periods['cutoff'] ?? date('F j, Y H:i', strtotime((string) $this->options['generated'])).' UTC')
            )
        );
    }

    /** The report's approval state as the cover shows it, e.g. "Draft v1.1". */
    protected function report_status_text(): string
    {
        $version = (string) $this->options['report_version'];

        if (!empty($this->options['changed_since_final'])) {
            return 'Draft (changed since Final v'.$version.')';
        }

        return $this->options['report_status'].' v'.$version;
    }

    /**
     * The cover's detail rows: the set every report has always shown, with
     * assessment completion and report approval as separate rows. When the
     * table would outgrow its space, the Client row gives way: the cover
     * already names the client under the title ("Prepared for ...").
     */
    protected function cover_rows(): array
    {
        $rows = array(
            'Report date' => date('F j, Y'),
            'Assessment' => $this->a['name'],
            'Client' => $this->a['client'],
            'Standard' => $this->a['standard'],
            'Assessment status' => ReportAnalysis::plain($this->a['assessment']['assessment_status']),
            'Report status' => $this->report_status_text(),
            'Prepared by' => ReportAnalysis::plain($this->company['company_name'] ?? '')
        );

        if (strcasecmp($this->a['name'], $this->a['standard']) === 0) {
            unset($rows['Assessment']);
        }

        if (count($rows) > self::COVER_ROWS && $this->a['client'] !== '') {
            unset($rows['Client']);
        }

        return $rows;
    }

    protected function detail(): string
    {
        return $this->options['detail'];
    }

    protected function term(string $key): string
    {
        return $this->a['terms'][$key] ?? $key;
    }

    /* ------------------------------------------------------------ blocks */

    protected static function h1(string $text): array
    {
        return array(
            'kind' => 'h1',
            'value' => $text
        );
    }

    protected static function h2(string $text): array
    {
        return array(
            'kind' => 'h2',
            'value' => $text
        );
    }

    protected static function h3(string $text): array
    {
        return array(
            'kind' => 'h3',
            'value' => $text
        );
    }

    /** Paragraphs separated by blank lines; lines starting "- " become bullets. */
    protected static function text(string $text): array
    {
        return array(
            'kind' => 'text',
            'value' => $text
        );
    }

    protected static function note(string $text): array
    {
        return array(
            'kind' => 'note',
            'value' => $text
        );
    }

    protected static function table(array $head, array $widths, array $rows, bool $small = true): array
    {
        return ReportRenderer::table_block($head, $widths, $rows, $small);
    }

    protected static function bullets(array $lines): string
    {
        return implode("\n", array_map(function ($line) {
            return '- '.$line;
        }, $lines));
    }

    protected static function pct(int $part, int $whole): string
    {
        return ReportAnalysis::pct($part, $whole);
    }

    protected static function join_list(array $items): string
    {
        return ReportAnalysis::join_list($items);
    }
}
