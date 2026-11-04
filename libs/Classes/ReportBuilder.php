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
    protected $a;
    protected $company;
    protected $company_id;
    protected $options;

    /**
     * @param array $options detail ('executive'|'standard'|'comprehensive'),
     *                       appendices (bool)
     */
    public function __construct(int $assessment_id, int $company_id, array $company, array $options = array())
    {
        $this->company_id = $company_id;
        $this->company = $company;
        $this->options = array_merge(array(
            'detail' => 'standard',
            'appendices' => true
        ), $options);

        $analysis = (new ReportAnalysis($assessment_id, $company_id))->build();

        if ($analysis !== null && class_exists('ReportNarratives')) {
            $analysis = ReportNarratives::apply($analysis, $company_id);
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
                'file_base' => ($this->a['client'] !== '' ? $this->a['client'].' - ' : '').$this->title().' - '.$this->a['name']
            ), $this->doc_options()),
            'sheets' => $this->sheets()
        );
    }

    /** The cover's detail rows; the same set every report has always shown. */
    protected function cover_rows(): array
    {
        $rows = array(
            'Report date' => date('F j, Y'),
            'Assessment' => $this->a['name'],
            'Client' => $this->a['client'],
            'Standard' => $this->a['standard'],
            'Status' => ReportAnalysis::plain($this->a['assessment']['assessment_status']),
            'Prepared by' => ReportAnalysis::plain($this->company['company_name'] ?? '')
        );

        if (strcasecmp($this->a['name'], $this->a['standard']) === 0) {
            unset($rows['Assessment']);
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
