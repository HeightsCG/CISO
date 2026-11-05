<?php
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;

/**
 * Draws a risk assessment report (see ReadyReports) as a PDF or a Word
 * document. Both follow the same structure as the consultancy's sample risk
 * assessment: cover, document control, contents, executive summary,
 * background and scope, results by domain with findings, ongoing review,
 * management acknowledgment and appendices. Sections with
 * nothing in them are left out rather than printed empty.
 *
 * The consultancy's own logo and brand colour are used, so each tenant's
 * reports carry their own identity.
 *
 * Document array (see document()): title, client, prepared_for, company,
 * cover_rows, confidential, chapter_breaks, toc (bool: contents page built
 * from h1/h2 with page numbers), numbered (bool: h1 "1.", h2 "1.1"),
 * blocks, file_base.
 *
 * Every block is array('kind' => ..., 'value' => ...). Headings (h1, h2,
 * h3, domain_head) may also carry 'anchor' (link target id) and
 * 'numbered' => false (skip numbering, e.g. appendices); h1/h2 become PDF
 * bookmarks and Word navigation-pane titles. Value shapes:
 *   h1 | h2 | h3 | note | text   string; text: blank line = paragraph, "- " = bullet
 *   lead       array(label, text)
 *   stats      list of array(label, value)
 *   metrics    array(items => list of array(value, label, sub?, tone?)), max 4 per row
 *   bars       list of array(label, value, max, text, color?, tone?)
 *              or array(items => that list, max_label => scale caption)
 *   stackbar   array(segments => list of array(label, value, color?, tone?))
 *   table      array(head, widths (%), rows, small?, total?, anchors?, link_col?)
 *   register   same as table; anchors[i] links cell link_col (default 0) of row i
 *   kv         array(rows => list of array(label, value))
 *   panel      array(title?, tone, body) tone: neutral|brand|positive|warning|critical
 *   finding    array(ref, heading, severity High|Medium|Low, rating (pill text),
 *              rows? => list of array(label, text), meta? => list of array(label, value),
 *              sections? => list of array(label, text or list of bullet strings),
 *              anchor?)
 *   callout    array(title, items)
 *   posture | dashboard | heatmap | domain_head | roadmap | signature | contents
 *              as built by risk_document(); toc: no value, placed automatically
 */
class ReportRenderer {

    const SEVERITY_COLORS = array(
        'High' => '#B42318',
        'Medium' => '#B54708',
        'Low' => '#067647'
    );

    const POSTURE_COLORS = array(
        'Low' => '#067647',
        'Moderate' => '#A15C07',
        'Elevated' => '#B54708',
        'High' => '#B42318',
        'Critical' => '#7A271A'
    );

    const RATING_COLORS = array(
        'Satisfactory' => '#067647',
        'Satisfactory with exceptions' => '#4D7C0F',
        'Needs improvement' => '#B54708',
        'Unsatisfactory' => '#B42318'
    );

    const TONES = array(
        'neutral' => array(
            '#475467',
            '#F4F6F8'
        ),
        'positive' => array(
            '#067647',
            '#EDF8F1'
        ),
        'warning' => array(
            '#B54708',
            '#FEF6E7'
        ),
        'critical' => array(
            '#B42318',
            '#FDF0EF'
        )
    );

    /** Dompdf multiplies line-height by the font's own height (ascent - descent, x1.1). */
    const LINE_SERIF = 1.508;

    const LINE_SANS = 1.566;

    /** Blocks short enough to keep on the same page as the heading above them. */
    const KEEP_WITH_HEADING = array(
        'text',
        'lead',
        'note',
        'finding',
        'panel',
        'kv',
        'metrics',
        'stats',
        'stackbar',
        'bars',
        'heatmap',
        'dashboard',
        'posture',
        'callout'
    );

    /** A finding card with less text than this never splits across pages. */
    const FINDING_WHOLE_CHARS = 700;

    /** Share of the page below which a chapter's last page counts as nearly empty. */
    const TAIL_FILL = 0.3;

    /** Tables this short never split across pages. */
    const KEEP_WHOLE_ROWS = 6;

    /** Rows of a longer table kept on the page with its header and heading. */
    const KEEP_LEAD_ROWS = 3;

    const CSF_PREFIXES = array(
        'GV' => 'Govern',
        'ID' => 'Identify',
        'PR' => 'Protect',
        'DE' => 'Detect',
        'RS' => 'Respond',
        'RC' => 'Recover'
    );

    /** The risk assessment report. Returns array('bytes', 'file_name', 'extension', 'content_type'). */
    public static function render(array $r, string $format): array
    {
        return self::document(self::risk_document($r), $format);
    }

    /**
     * Any document: a cover described by $doc and the blocks that follow it.
     * $doc keys: title, client, prepared_for, company (row), cover_rows
     * (label => value), confidential (bool), chapter_breaks (bool: h1s
     * start a new page, see chapter_starts), blocks, file_base.
     */
    public static function document(array $doc, string $format): array
    {
        $base = preg_replace('/[^A-Za-z0-9 ,.&()\'-]/', '', $doc['file_base']);

        if ($format === 'docx') {
            return array(
                'bytes' => self::docx($doc),
                'file_name' => $base.'.docx',
                'extension' => 'docx',
                'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );
        }

        return array(
            'bytes' => self::pdf($doc),
            'file_name' => $base.'.pdf',
            'extension' => 'pdf',
            'content_type' => 'application/pdf'
        );
    }

    private static function risk_document(array $r): array
    {
        $narrative = ReportNarrative::for_risk_report($r);

        if (count($narrative['finding_text']) > 0) {
            $r = self::apply_finding_text($r, $narrative['finding_text']);
        }

        $outline = self::outline($r, $narrative);
        $client = $outline['client'];
        $title = self::plain($r['report']['report_title']);

        $blocks = array(
            array(
                'kind' => 'h1',
                'value' => 'Contents'
            ),
            array(
                'kind' => 'contents',
                'value' => $outline['contents']
            )
        );

        return array(
            'title' => $title,
            'client' => $client,
            'prepared_for' => $client,
            'company' => $r['company'],
            'cover_rows' => self::cover_rows($r),
            'confidential' => true,
            'chapter_breaks' => true,
            'blocks' => array_merge($blocks, $outline['blocks']),
            'file_base' => $client.' - '.$title.' v'.$r['report']['report_version']
        );
    }

    /** Swap the plain-English write-ups into automatically generated findings. */
    private static function apply_finding_text(array $r, array $texts): array
    {
        $by_ref = array();

        foreach ($texts as $text) {
            $by_ref[$text['ref']] = $text;
        }

        $rewrite = function (array $finding) use ($by_ref) {
            $text = $by_ref[$finding['finding_ref']] ?? null;
            if ($text === null) {
                return $finding;
            }
            $finding['finding_title'] = $text['title'];
            $finding['observation'] = $text['observation'];
            $finding['risk_text'] = $text['risk'];
            $finding['recommendation'] = $text['recommendation'];
            return $finding;
        };

        $r['findings'] = array_map($rewrite, $r['findings']);
        $r['findings_ranked'] = array_map($rewrite, $r['findings_ranked']);

        foreach ($r['domains'] as $index => $domain) {
            $r['domains'][$index]['findings'] = array_map($rewrite, $domain['findings']);
        }

        return $r;
    }

    /**
     * Indexes of the h1 blocks that start a new page when the document asks
     * for chapter breaks. A short section of plain text (a paragraph or a
     * signature) flows on after the previous one instead of taking a page.
     */
    private static function chapter_starts(array $doc): array
    {
        $starts = array();

        if (empty($doc['chapter_breaks'])) {
            return $starts;
        }

        $blocks = array_values($doc['blocks']);
        $short = array(
            'text',
            'note',
            'lead',
            'signature'
        );

        foreach ($blocks as $index => $block) {
            if ($block['kind'] !== 'h1' || !empty($block['closing'])) {
                continue;
            }
            $body = 0;
            $plain = true;
            for ($next = $index + 1; $next < count($blocks) && $blocks[$next]['kind'] !== 'h1'; $next++) {
                $body++;
                if (!in_array($blocks[$next]['kind'], $short, true)) {
                    $plain = false;
                }
            }
            if ($index === 0 || !$plain || $body > 3) {
                $starts[] = $index;
            }
        }

        return $starts;
    }

    /**
     * The blocks as both formats draw them: a contents page added when the
     * document asks for one, heading numbers and anchors filled in, and the
     * h1/h2 outline that feeds the contents page and the PDF bookmarks.
     */
    private static function prepare(array $doc): array
    {
        $blocks = array_values($doc['blocks']);

        if (!empty($doc['document_control'])) {
            $blocks = array_merge($blocks, self::document_control_blocks($doc['document_control']));
        }
        $numbered = !empty($doc['numbered']);
        $has_toc = false;

        foreach ($blocks as $block) {
            if ($block['kind'] === 'toc') {
                $has_toc = true;
            }
        }

        if (!empty($doc['toc']) && !$has_toc) {
            array_unshift($blocks, array(
                'kind' => 'h1',
                'value' => 'Contents',
                'numbered' => false,
                'outline' => false
            ), array(
                'kind' => 'toc',
                'value' => null
            ));
        }

        $h1 = 0;
        $h2 = 0;
        $outline = array();
        $headings = array(
            'h1',
            'h2',
            'h3',
            'domain_head'
        );

        foreach ($blocks as $index => $block) {
            if ($block['kind'] === 'finding' && !empty($block['value']['anchor'])) {
                $blocks[$index]['anchor'] = self::anchor($block['value']['anchor']);
                continue;
            }
            if (!in_array($block['kind'], $headings, true)) {
                continue;
            }
            $text = $block['kind'] === 'domain_head' ? $block['value']['title'] : (string) $block['value'];
            $number = '';
            if ($numbered && ($block['numbered'] ?? true) !== false) {
                if ($block['kind'] === 'h1') {
                    $h1++;
                    $h2 = 0;
                    $number = $h1.'.';
                } elseif ($block['kind'] !== 'h3' && $h1 > 0) {
                    $h2++;
                    $number = $h1.'.'.$h2;
                }
            }
            $anchor = self::anchor($block['anchor'] ?? 'sec-'.($index + 1));
            $blocks[$index]['anchor'] = $anchor;
            $blocks[$index]['number'] = $number;
            $blocks[$index]['text'] = $text;
            if ($block['kind'] !== 'h3' && ($block['outline'] ?? true) !== false) {
                $outline[] = array(
                    'level' => $block['kind'] === 'h1' ? 1 : 2,
                    'number' => $number,
                    'text' => $text,
                    'anchor' => $anchor
                );
            }
        }

        return array(
            'blocks' => $blocks,
            'outline' => $outline
        );
    }

    /**
     * The footer's state line on interior pages, e.g. "Draft v0.3 · Generated
     * 4 Nov 2026 15:42 UTC" or "Final v1.0 · Generated ...". A Final whose
     * assessment changed since approval reads as Draft.
     */
    public static function status_line(array $control): string
    {
        $generated = 'Generated '.self::stamp($control['generated'] ?? '');

        if (!empty($control['changed_since_final'])) {
            return 'Draft · changed since Final v'.$control['report_version'].' · '.$generated;
        }

        return $control['report_status'].' v'.$control['report_version'].' · '.$generated;
    }

    /** The closing "Document control" section every report ends with. */
    private static function document_control_blocks(array $control): array
    {
        $status = $control['report_status'];
        if (!empty($control['changed_since_final'])) {
            $status = 'Draft (changed since Final v'.$control['report_version'].' was approved)';
        }

        $rows = array(
            array(
                'Assessment reference',
                '#'.$control['assessment_id'].' · '.$control['assessment_name']
            )
        );

        if ((string) ($control['assessment_status'] ?? '') !== '') {
            $rows[] = array(
                'Assessment status',
                (string) $control['assessment_status']
            );
        }

        foreach ($control['periods'] ?? array() as $period) {
            if ((string) $period[1] !== '') {
                $rows[] = array(
                    (string) $period[0],
                    (string) $period[1]
                );
            }
        }

        $rows[] = array(
            'Report version',
            (string) $control['report_version']
        );
        $rows[] = array(
            'Report status',
            $status
        );
        $rows[] = array(
            'Generated',
            self::stamp($control['generated'] ?? '')
        );

        if ($control['report_status'] === 'Final' && empty($control['changed_since_final'])) {
            if ((string) ($control['approved_by'] ?? '') !== '') {
                $rows[] = array(
                    'Approved by',
                    (string) $control['approved_by']
                );
            }
            if (!empty($control['approved_date'])) {
                $rows[] = array(
                    'Approved',
                    self::stamp($control['approved_date'])
                );
            }
        }

        return array(
            array(
                'kind' => 'h1',
                'value' => 'Document control',
                'numbered' => false,
                'outline' => false,
                'anchor' => 'document-control',
                'closing' => true
            ),
            array(
                'kind' => 'kv',
                'closing' => true,
                'value' => array(
                    'rows' => $rows
                )
            )
        );
    }

    private static function stamp($value): string
    {
        $time = strtotime((string) $value);

        return date('j M Y H:i T', $time !== false ? $time : time());
    }

    private static function anchor($value): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $value);
    }

    /** array(text colour, background) for a tone; 'brand' uses the company colour. */
    private static function tone(?string $tone, string $brand): array
    {
        if ($tone === 'brand' || $tone === null || $tone === '' || !isset(self::TONES[$tone])) {
            return array(
                $brand,
                self::tint($brand, 0.08)
            );
        }

        return self::TONES[$tone];
    }

    private static function tint(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');
        $out = '#';

        foreach (str_split($hex, 2) as $pair) {
            $out .= sprintf('%02X', (int) round(255 - (255 - hexdec($pair)) * $amount));
        }

        return $out;
    }

    /** Text into paragraphs, each a list of lines or array('bullets' => list). Arrays of strings are bullets. */
    private static function chunks($text): array
    {
        if (is_array($text)) {
            return array(
                array(
                    'bullets' => array_values(array_map('strval', $text))
                )
            );
        }

        $out = array();

        foreach (preg_split("/\n\s*\n/", trim((string) $text)) as $chunk) {
            $plain = array();
            $bullets = array();
            foreach (preg_split("/\n/", trim($chunk)) as $line) {
                if (preg_match('/^\s*[-•]\s+/', $line) === 1) {
                    if (count($plain) > 0) {
                        $out[] = array(
                            'lines' => $plain
                        );
                        $plain = array();
                    }
                    $bullets[] = preg_replace('/^\s*[-•]\s+/', '', $line);
                    continue;
                }
                if (count($bullets) > 0) {
                    $out[] = array(
                        'bullets' => $bullets
                    );
                    $bullets = array();
                }
                if (trim($line) !== '') {
                    $plain[] = $line;
                }
            }
            if (count($plain) > 0) {
                $out[] = array(
                    'lines' => $plain
                );
            }
            if (count($bullets) > 0) {
                $out[] = array(
                    'bullets' => $bullets
                );
            }
        }

        return $out;
    }

    private static function text_length($text): int
    {
        return is_array($text) ? strlen(implode(' ', $text)) : strlen((string) $text);
    }

    /** Columns whose filled cells are all numbers, percentages or "n of m". */
    private static function numeric_columns(array $rows, int $columns): array
    {
        $numeric = array();

        for ($col = 0; $col < $columns; $col++) {
            $filled = 0;
            $numbers = 0;
            foreach ($rows as $row) {
                $cell = trim((string) ($row[$col] ?? ''));
                if ($cell === '') {
                    continue;
                }
                $filled++;
                if (preg_match('/^[-+]?\d[\d,.]*\s*%?$|^\d+\s*(of|\/)\s*\d+$/', $cell) === 1) {
                    $numbers++;
                }
            }
            $numeric[$col] = $filled > 0 && $numbers === $filled;
        }

        return $numeric;
    }

    public static function table_block(array $head, array $widths, array $rows, bool $small = false): array
    {
        return array(
            'kind' => 'table',
            'value' => array(
                'head' => $head,
                'widths' => $widths,
                'rows' => $rows,
                'small' => $small
            )
        );
    }

    /* ------------------------------------------------------------- content */

    /**
     * The report as an ordered outline both formats walk through: headings,
     * paragraphs, key-value lists, tables and finding blocks. Building it once
     * keeps the PDF and the Word document identical in content.
     */
    private static function outline(array $r, array $narrative): array
    {
        $report = $r['report'];
        $client = self::plain($r['client']['company_name'] ?? '');
        $out = array();
        $number = 0;
        $contents = array();

        $add = function ($kind, $value) use (&$out) {
            $out[] = array(
                'kind' => $kind,
                'value' => $value
            );
        };

        $section = function ($title) use (&$number, &$contents, $add) {
            $number++;
            $contents[] = $number.'  '.$title;
            $add('h1', $number.'. '.$title);
            return $number;
        };

        $text = function ($value) use ($add) {
            if (self::plain($value) !== '') {
                $add('text', self::plain($value));
            }
        };

        $s = $section('Executive summary');
        $sub = 0;

        $add('posture', array(
            'rating' => $narrative['posture_rating'],
            'headline' => $narrative['posture_headline']
        ));

        $add('callout', array(
            'title' => 'Key messages',
            'items' => $narrative['key_messages']
        ));

        $add('dashboard', array(
            'totals' => $r['totals'],
            'total' => count($r['findings']),
            'areas' => count($r['domains'])
        ));

        if (self::plain($report['purpose_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Purpose');
            $text($report['purpose_text']);
        }

        $picture = self::plain($report['conclusion_text']) !== '' ? $report['conclusion_text'] : $narrative['executive_summary'];

        if (self::plain($picture) !== '') {
            $add('h2', $s.'.'.(++$sub).' Overall picture');
            $text($picture);
        }

        if (count($r['findings']) > 0) {
            $matrix = array();
            foreach ($r['findings'] as $finding) {
                $key = (int) $finding['likelihood'].'-'.(int) $finding['impact'];
                $matrix[$key] = ($matrix[$key] ?? 0) + 1;
            }
            $add('h2', $s.'.'.(++$sub).' Risk heat map');
            $add('heatmap', $matrix);
            $add('note', 'Each cell shows how many findings sit at that likelihood and impact. Scores of 15 and above are high, 8 to 14 medium, and 7 or below low.');
        }

        if (count($r['domains']) > 0) {
            $add('h2', $s.'.'.(++$sub).' Results by domain');
            $rows = array();
            foreach ($r['domains'] as $domain) {
                $rows[] = array(
                    self::plain($domain['domain_name']),
                    (string) $domain['counts']['High'],
                    (string) $domain['counts']['Medium'],
                    (string) $domain['counts']['Low'],
                    (string) ($domain['domain_rating'] ?? '')
                );
            }
            $rows[] = array(
                'Total',
                (string) $r['totals']['High'],
                (string) $r['totals']['Medium'],
                (string) $r['totals']['Low'],
                ''
            );
            $add('table', array(
                'head' => array(
                    'Domain',
                    'High',
                    'Medium',
                    'Low',
                    'Overall assessment'
                ),
                'widths' => array(
                    40,
                    9,
                    11,
                    9,
                    31
                ),
                'rows' => $rows,
                'total' => true
            ));
        }

        if (count($r['findings_ranked']) > 0) {
            $add('h2', $s.'.'.(++$sub).' Summary of findings');
            $rows = array();
            foreach ($r['findings_ranked'] as $finding) {
                $rows[] = array(
                    $finding['finding_ref'],
                    self::plain($finding['finding_title']),
                    $finding['rating'],
                    $finding['response_status'] === 'Pending' ? '' : $finding['response_status'],
                    self::date($finding['target_date'])
                );
            }
            $add('table', array(
                'head' => array(
                    'Ref.',
                    'Finding',
                    'Rating',
                    'Response',
                    'Target date'
                ),
                'widths' => array(
                    8,
                    50,
                    11,
                    17,
                    14
                ),
                'rows' => $rows
            ));
        }

        $s = $section('Background, scope and approach');
        $sub = 0;

        if (self::plain($report['background_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Background');
            $text($report['background_text']);
        }

        $add('h2', $s.'.'.(++$sub).' Scope');
        $text($report['scope_text']);

        if (count($r['domains']) > 0) {
            $mapped = false;
            foreach ($r['domains'] as $domain) {
                if (self::plain($domain['csf_refs'] ?? '') !== '') {
                    $mapped = true;
                }
            }
            $rows = array();
            foreach ($r['domains'] as $domain) {
                $row = array(
                    self::plain($domain['domain_name']),
                    self::plain($domain['areas_reviewed'])
                );
                if ($mapped) {
                    $row[] = self::plain($domain['csf_refs']);
                }
                $rows[] = $row;
            }
            $add('table', array(
                'head' => $mapped
                    ? array(
                        'Domain',
                        'Areas reviewed',
                        'NIST CSF 2.0'
                    )
                    : array(
                        'Domain',
                        'Areas reviewed'
                    ),
                'widths' => $mapped
                    ? array(
                        27,
                        55,
                        18
                    )
                    : array(
                        30,
                        70
                    ),
                'rows' => $rows
            ));
        }

        $text($report['out_of_scope_text']);

        if (self::plain($report['approach_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Approach');
            $text($report['approach_text']);
        }

        if (self::plain($report['methodology_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Rating methodology');
            $text($report['methodology_text']);
        }

        if (self::plain($report['limitations_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Limitations and responsibilities');
            $text($report['limitations_text']);
        }

        $s = $section('Assessment results by domain');
        $add('text', 'For each domain we summarize what was reviewed and what is working, followed by any findings. Each finding describes what we observed, the risk it creates, our recommendation and management\'s response.');
        $sub = 0;

        $domains = $r['domains'];

        $commentary = array();

        foreach ($narrative['domain_commentary'] as $item) {
            $commentary[(int) $item['domain_id']] = $item['commentary'];
        }

        foreach ($domains as $domain) {
            $sub++;
            $contents[] = '     '.$s.'.'.$sub.'  '.self::plain($domain['domain_name']);

            $counts = array(
                'High' => 0,
                'Medium' => 0,
                'Low' => 0
            );
            foreach ($domain['findings'] as $finding) {
                $counts[$finding['rating']]++;
            }

            $add('domain_head', array(
                'title' => $s.'.'.$sub.' '.self::plain($domain['domain_name']),
                'rating' => $domain['domain_rating'] ?? null,
                'counts' => $counts
            ));

            $written = self::plain($domain['domain_summary'] ?? '');
            $ai = $commentary[(int) ($domain['id'] ?? 0)] ?? '';
            $summary = $ai !== '' || $written === '' ? $ai : $written;

            if ($summary !== '') {
                $add('text', $summary);
            } elseif (count($domain['findings']) === 0) {
                $add('text', 'No findings were identified in this area.');
            }

            foreach ($domain['findings'] as $finding) {
                $add('finding', self::finding_block($finding));
            }
        }

        if (count($narrative['roadmap']) > 0) {
            $section('Remediation roadmap');
            $add('text', 'The actions below address the findings in order of priority. Each cites the findings it resolves.');
            $phases = array();
            foreach (ReportNarrative::PHASES as $phase) {
                $phases[$phase] = array();
            }
            foreach ($narrative['roadmap'] as $item) {
                $phases[$item['phase']][] = $item;
            }
            $add('roadmap', $phases);
        }

        if (self::plain($narrative['conclusion']) !== '') {
            $section('Conclusion');
            $text($narrative['conclusion']);
        }

        if (self::plain($report['review_text']) !== '') {
            $section('Ongoing review');
            $text($report['review_text']);
        }

        $section('Management acknowledgment');
        $add('text', 'I have reviewed this risk assessment, including the findings, the treatment decisions and the remediation dates set out above.');
        $add('signature', array(
            'name' => self::plain($report['signoff_name']),
            'title' => self::plain($report['signoff_title'])
        ));

        $appendix = 0;
        $letter = function () use (&$appendix) {
            return chr(65 + $appendix++);
        };

        if (count($r['threats']) > 0) {
            $l = $letter();
            $contents[] = $l.'  Appendix '.$l.'. Threat analysis';
            $add('h1', 'Appendix '.$l.'. Threat analysis');
            $rows = array();
            foreach ($r['threats'] as $threat) {
                $rows[] = array(
                    $threat['threat_ref'],
                    self::plain($threat['threat_name']),
                    self::plain($threat['threat_description']),
                    self::plain($threat['parties_affected'])
                );
            }
            $add('table', array(
                'head' => array(
                    'Ref.',
                    'Threat',
                    'Description',
                    'Parties affected'
                ),
                'widths' => array(
                    7,
                    27,
                    44,
                    22
                ),
                'rows' => $rows
            ));
        }

        $rows = array();

        foreach ($r['findings'] as $finding) {
            if (self::plain($finding['csf_refs'] ?? '') === '') {
                continue;
            }
            $rows[] = array(
                $finding['finding_ref'],
                self::plain($finding['finding_title']),
                self::csf_functions($finding['csf_refs']),
                self::plain($finding['csf_refs'])
            );
        }

        if (count($rows) > 0) {
            $l = $letter();
            $contents[] = $l.'  Appendix '.$l.'. NIST CSF 2.0 mapping of findings';
            $add('h1', 'Appendix '.$l.'. NIST CSF 2.0 mapping of findings');
            $add('table', array(
                'head' => array(
                    'Ref.',
                    'Finding',
                    'CSF function',
                    'CSF subcategory'
                ),
                'widths' => array(
                    8,
                    46,
                    20,
                    26
                ),
                'rows' => $rows
            ));
        }

        return array(
            'contents' => $contents,
            'blocks' => $out,
            'client' => $client
        );
    }

    private static function finding_block(array $finding): array
    {
        $rows = array();

        $pairs = array(
            'Observation' => $finding['observation'],
            'Risk' => $finding['risk_text'],
            'Recommendation' => $finding['recommendation'],
            'Management response' => ($finding['response_status'] === 'Pending' ? '' : $finding['response_status'].'. ').self::plain($finding['management_response']),
            'Owner / target' => trim(self::plain($finding['response_owner']).($finding['target_date'] ? ' / '.self::date($finding['target_date']) : ''), ' /'),
            'NIST CSF 2.0' => $finding['csf_refs'] ?? ''
        );

        foreach ($pairs as $label => $value) {
            if (self::plain($value) !== '') {
                $rows[] = array(
                    $label,
                    self::plain($value)
                );
            }
        }

        return array(
            'ref' => $finding['finding_ref'],
            'title' => trim($finding['finding_ref'].' '.self::plain($finding['finding_title'])),
            'heading' => self::plain($finding['finding_title']),
            'severity' => $finding['rating'],
            'rating' => $finding['rating'].' ('.$finding['score'].')',
            'rows' => $rows
        );
    }

    private static function csf_functions(string $refs): string
    {
        $found = array();

        foreach (self::CSF_PREFIXES as $prefix => $name) {
            if (preg_match('/\b'.$prefix.'\b|\b'.$prefix.'\./', strtoupper($refs)) && !in_array($name, $found, true)) {
                $found[] = $name;
            }
        }

        return implode(', ', $found);
    }

    public static function plain($value): string
    {
        return trim(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
    }

    private static function date($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $time = strtotime((string) $value);

        return $time ? date('F j, Y', $time) : '';
    }

    private static function cover_rows(array $r): array
    {
        $report = $r['report'];
        $rows = array();

        if ($report['report_date']) {
            $rows['Report date'] = self::date($report['report_date']);
        }

        if ($report['period_start'] && $report['period_end']) {
            $rows['Assessment period'] = date('F j', strtotime($report['period_start'])).' to '.self::date($report['period_end']);
        }

        $rows['Version'] = $report['report_version'];
        $rows['Prepared by'] = self::plain($r['company']['company_name'] ?? '');

        return $rows;
    }

    public static function brand(array $company): string
    {
        $color = (string) ($company['brand_color'] ?? '');

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#1F3A5F';
    }

    public static function accent(array $company): string
    {
        $color = (string) ($company['brand_color_accent'] ?? '');

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#0EA5E9';
    }

    /** The company logo as raw bytes and type, or null. Fetched once per render. */
    public static function logo(array $company)
    {
        $url = (string) ($company['logo_path'] ?? '');

        if ($url === '' || !preg_match('#^https://#', $url)) {
            return null;
        }

        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 5
            )
        ));

        $bytes = @file_get_contents($url, false, $context);

        if ($bytes === false || @getimagesizefromstring($bytes) === false) {
            return null;
        }

        return array(
            'bytes' => $bytes,
            'type' => getimagesizefromstring($bytes)['mime']
        );
    }

    /* ------------------------------------------------------------------ PDF */

    const PAGE_TOP = 78;

    const PAGE_BODY = 644;

    /**
     * Renders once; renders again a little tighter when a chapter ends on a
     * nearly empty page, and once more to put page numbers in the contents.
     * Headings become PDF bookmarks.
     */
    private static function pdf(array $doc): string
    {
        $prepared = self::prepare($doc);
        $toc = false;

        foreach ($prepared['blocks'] as $block) {
            if ($block['kind'] === 'toc') {
                $toc = true;
            }
        }

        $tight = false;
        $run = self::pdf_run($doc, $prepared, array(), $tight);

        if (self::pdf_short_tail($run['seen'], $run['marks'])) {
            $tight = true;
            $run = self::pdf_run($doc, $prepared, array(), $tight);
        }

        $blank = self::pdf_blank_breaks($run['seen'], $run['marks']);

        if (count($blank) > 0) {
            $doc['flow_marks'] = $blank;
            $run = self::pdf_run($doc, $prepared, array(), $tight);
        }

        if ($toc) {
            $pages = array();
            foreach ($prepared['outline'] as $entry) {
                if (isset($run['seen'][$entry['anchor']])) {
                    $pages[$entry['anchor']] = $run['seen'][$entry['anchor']]['page'] - 1;
                }
            }
            $run = self::pdf_run($doc, $prepared, $pages, $tight);
        }

        $dompdf = $run['dompdf'];
        $title = $doc['title'];
        $header = ($doc['client'] !== '' ? $doc['client'].' · ' : '').$title;
        $footer = 'Confidential · '.self::plain($doc['company']['company_name'] ?? '');
        $status = !empty($doc['document_control']) ? self::status_line($doc['document_control']) : '';

        $dompdf->getCanvas()->page_script(function ($page, $total, $canvas, $metrics) use ($header, $footer, $status) {
            if ($page === 1) {
                return;
            }
            $font = $metrics->getFont('Sans');
            $grey = array(
                0.33,
                0.39,
                0.44
            );
            $width = $canvas->get_width();
            $label = 'Page '.($page - 1).' of '.($total - 1);
            $canvas->text($width - 64 - $metrics->getTextWidth($header, $font, 7.5), 40, $header, $font, 7.5, $grey);
            $canvas->line(64, 54, $width - 64, 54, $grey, 0.4);
            $canvas->text(64, $canvas->get_height() - 42, $footer, $font, 7.5, $grey);
            $canvas->text($width - 64 - $metrics->getTextWidth($label, $font, 7.5), $canvas->get_height() - 42, $label, $font, 7.5, $grey);
            if ($status !== '') {
                $canvas->text(64, $canvas->get_height() - 31, $status, $font, 7, $grey);
            }
        });

        $bytes = $dompdf->output();

        return self::pdf_outline($bytes, $prepared['outline'], $run['seen'], $dompdf->getCanvas()->get_cpdf());
    }

    /** One Dompdf render, recording the page and position of every element with an id. */
    private static function pdf_run(array $doc, array $prepared, array $pages, bool $tight): array
    {
        $fonts = Main::lib_path().'/Fonts';
        $built = self::pdf_html($doc, $prepared, $pages, $tight);

        $options = new Options();
        $options->setChroot(array(
            $fonts
        ));
        $options->setFontDir(sys_get_temp_dir());
        $options->setFontCache(sys_get_temp_dir());
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Serif');

        $seen = array();
        $dompdf = new Dompdf($options);
        $dompdf->setCallbacks(array(
            array(
                'event' => 'begin_frame',
                'f' => function ($frame, $canvas) use (&$seen) {
                    $node = $frame->get_node();
                    if (!($node instanceof DOMElement)) {
                        return;
                    }
                    $id = $node->getAttribute('id');
                    if ($id === '' || isset($seen[$id])) {
                        return;
                    }
                    $seen[$id] = array(
                        'page' => $canvas->get_page_number(),
                        'y' => $frame->get_position('y')
                    );
                }
            )
        ));
        $dompdf->loadHtml($built['html']);
        $dompdf->setPaper('letter');
        $dompdf->render();

        return array(
            'dompdf' => $dompdf,
            'seen' => $seen,
            'marks' => $built['marks']
        );
    }

    /** True when a chapter longer than a page ends with only a few lines on its last page. */
    /**
     * Chapters whose forced page break produced an empty page: the previous
     * chapter filled its last page exactly, so its end marker spilled alone
     * onto a new page and the chapter break added another. Those chapters
     * flow instead and start on the page the marker spilled onto.
     */
    private static function pdf_blank_breaks(array $seen, int $marks): array
    {
        $blank = array();

        for ($index = 1; $index < $marks; $index++) {
            $start = $seen['m-start-'.$index] ?? null;
            $end = $seen['m-end-'.($index - 1)] ?? null;
            if ($start !== null && $end !== null && $start['page'] === $end['page'] + 1 && $end['y'] <= self::PAGE_TOP + 2) {
                $blank[] = $index;
            }
        }

        return $blank;
    }

    private static function pdf_short_tail(array $seen, int $marks): bool
    {
        for ($index = 0; $index < $marks; $index++) {
            $start = $seen['m-start-'.$index] ?? null;
            $end = $seen['m-end-'.$index] ?? null;
            if ($start === null || $end === null || $end['page'] <= $start['page']) {
                continue;
            }
            if (($end['y'] - self::PAGE_TOP) / self::PAGE_BODY < self::TAIL_FILL) {
                return true;
            }
        }

        return false;
    }

    private static function pdf_css(string $brand, string $accent, string $fonts, bool $tight): string
    {
        $serif = function (float $want) {
            return round($want / self::LINE_SERIF, 3);
        };
        $sans = function (float $want) {
            return round($want / self::LINE_SANS, 3);
        };
        $gap = $tight ? 5 : 7;
        $cell = $tight ? '3.5pt 6pt' : '4.5pt 6pt';

        return '@font-face{font-family:"Sans";font-weight:normal;src:url("file://'.$fonts.'/source-sans-3-400-normal.ttf")}'
            .'@font-face{font-family:"Sans";font-weight:bold;src:url("file://'.$fonts.'/source-sans-3-600-normal.ttf")}'
            .'@font-face{font-family:"Serif";font-weight:normal;src:url("file://'.$fonts.'/source-serif-4-400-normal.ttf")}'
            .'@font-face{font-family:"Serif";font-weight:bold;src:url("file://'.$fonts.'/source-serif-4-600-normal.ttf")}'
            .'@font-face{font-family:"Serif";font-style:italic;src:url("file://'.$fonts.'/source-serif-4-400-italic.ttf")}'
            .'@page{margin:78pt 64pt 70pt 64pt}'
            .'body{font-family:"Serif";font-size:10pt;line-height:'.$serif($tight ? 1.45 : 1.55).';color:#1d2733}'
            .'h1{font-family:"Sans";font-weight:bold;font-size:19pt;line-height:'.$sans(1.2).';color:'.$brand.';border-bottom:1pt solid '.$brand.';padding-bottom:5pt;margin:'.($tight ? 18 : 24).'pt 0 12pt 0;page-break-after:avoid}'
            .'h2{font-family:"Sans";font-weight:bold;font-size:12.5pt;line-height:'.$sans(1.25).';color:#1d2733;margin:'.($tight ? 13 : 17).'pt 0 6pt 0;page-break-after:avoid}'
            .'h3{font-family:"Sans";font-weight:bold;font-size:10.5pt;line-height:'.$sans(1.3).';color:#344054;margin:'.($tight ? 9 : 12).'pt 0 4pt 0;page-break-after:avoid}'
            .'.hn{color:'.$brand.';padding-right:7pt}'
            .'h1 .hn{color:'.$brand.'}'
            .'p{margin:0 0 '.$gap.'pt 0}'
            .'ul{margin:0 0 '.$gap.'pt 0;padding-left:14pt}li{margin-bottom:2.5pt}'
            .'a{color:inherit;text-decoration:none}'
            .'table{width:100%;border-collapse:collapse;margin:4pt 0 12pt 0;font-family:"Sans";font-size:8.8pt;line-height:'.$sans(1.32).'}'
            .'thead{display:table-header-group}tr{page-break-inside:avoid}tr.keep{page-break-after:avoid}tr.widow{page-break-before:avoid}table.whole{page-break-inside:avoid}'
            .'th{background:'.$brand.';color:#fff;text-align:left;font-weight:bold;padding:5pt 6pt;vertical-align:bottom}'
            .'td{border-bottom:0.5pt solid #d6dce3;padding:'.$cell.';vertical-align:top;word-wrap:break-word}'
            .'th.num,td.num{text-align:right}'
            .'td a.ref{color:'.$brand.';font-weight:bold}'
            .'tr.total td{font-weight:bold;border-top:0.8pt solid #1d2733}'
            .'table.small{font-size:8.2pt}'
            .'.note{font-family:"Sans";font-size:8.5pt;line-height:'.$sans(1.4).';color:#55636f;margin:0 0 10pt 0}'
            .'.lead b{font-family:"Serif";font-weight:bold}'
            .'.cover{position:relative;height:600pt;page-break-after:always}'
            .'.cover .band{position:absolute;top:-78pt;left:-64pt;width:612pt;height:340pt;background:'.$brand.'}'
            .'.cover .accent{position:absolute;top:262pt;left:-64pt;width:612pt;height:5pt;background:'.$accent.'}'
            .'.cover .kicker{position:absolute;top:36pt;left:0;font-family:"Sans";font-size:8.5pt;letter-spacing:2pt;color:#ffffff;text-transform:uppercase}'
            .'.cover .title{position:absolute;top:92pt;left:0;width:470pt;font-family:"Sans";font-weight:bold;font-size:32pt;line-height:1.08;color:#ffffff}'
            .'.cover .for{position:absolute;top:214pt;left:0;width:470pt;font-family:"Sans";font-size:13pt;color:#ffffff}'
            .'.cover .logo{position:absolute;top:296pt;left:0;height:40pt}'
            .'.cover .firm{position:absolute;top:300pt;left:0;font-family:"Sans";font-weight:bold;font-size:16pt;color:'.$brand.'}'
            .'.cover table.meta{position:absolute;top:372pt;left:0;width:484pt;font-family:"Sans";font-size:9.5pt;margin:0}'
            .'.cover table.meta td{border:none;padding:4pt 0}'
            .'.cover table.meta td.label{color:#55636f;width:30%}'
            .'.cover .confidential{position:absolute;top:556pt;left:0;width:484pt;border-top:0.5pt solid #b8c2cd;padding-top:7pt;font-size:8pt;line-height:1.4;color:#55636f}'
            .'.cover{font-size:10.5pt;line-height:1.45}.cover table.meta{line-height:1.45}'
            .'tr.alt td{background:#F5F7F9}'
            .'.posture{margin:0 0 12pt 0;padding:12pt 16pt;color:#ffffff}'
            .'.posture .label{font-family:"Sans";font-size:8pt;letter-spacing:1.5pt;text-transform:uppercase}'
            .'.posture .rating{font-family:"Sans";font-weight:bold;font-size:22pt;line-height:1.1}'
            .'.posture .headline{font-size:11pt;margin-top:4pt}'
            .'.callout{background:#F3F6F9;border-left:3pt solid '.$brand.';padding:10pt 16pt 6pt 16pt;margin:0 0 14pt 0}'
            .'.callout .ctitle{font-family:"Sans";font-weight:bold;font-size:11pt;color:'.$brand.';margin-bottom:6pt}'
            .'.callout ol{margin:0 0 4pt 0;padding-left:16pt}.callout li{margin-bottom:5pt}'
            .'.dash{width:100%;margin:0 0 6pt 0}.dash td{border:0.5pt solid #d6dce3;padding:8pt 10pt;width:25%;vertical-align:top}'
            .'.dash .num{font-family:"Sans";font-weight:bold;font-size:22pt;line-height:'.$sans(1.15).'}.dash .cap{font-family:"Sans";font-size:8.5pt;color:#55636f}'
            .'.sevbar{width:100%;margin:0 0 14pt 0}.sevbar td{height:8pt;padding:0;border:none}'
            .'.heat{width:auto;margin:6pt 0 4pt 0;font-family:"Sans";page-break-inside:avoid}.heat td{width:44pt;height:30pt;text-align:center;vertical-align:middle;border:1.5pt solid #ffffff;font-weight:bold;font-size:11pt;padding:0}'
            .'.heat td.axis{background:none;color:#55636f;font-weight:normal;font-size:8pt;width:44pt}'
            .'.dhead{margin:20pt 0 8pt 0;border-bottom:0.8pt solid #1d2733;padding-bottom:5pt;page-break-after:avoid}'
            .'.dhead table{margin:0;font-family:"Sans"}.dhead td{border:none;padding:0;vertical-align:bottom}'
            .'.dhead .dt{font-weight:bold;font-size:13pt;color:#1d2733}.dhead .dm{text-align:right;font-size:8.5pt;color:#55636f}'
            .'.pill{padding:1.5pt 6pt;font-family:"Sans";font-size:8pt;font-weight:bold;color:#ffffff}'
            .'.fcard{border:0.5pt solid #d6dce3;padding:9pt 13pt 5pt 13pt;margin:'.($tight ? 8 : 12).'pt 0 '.($tight ? 10 : 14).'pt 0}'
            .'.fcard.top{border-bottom:none;padding-bottom:1pt;margin-bottom:0}.fcard.rest{border-top:none;padding-top:1pt;margin-top:0}'
            .'.fcard .fh{margin:0 0 4pt 0;font-family:"Sans"}.fcard .fh td{border:none;padding:0 0 3pt 0;vertical-align:top}'
            .'.fcard .fref{font-size:8.5pt;color:#55636f;letter-spacing:0.5pt}.fcard .ftitle{font-weight:bold;font-size:11.5pt;line-height:'.$sans(1.25).';color:#1d2733}'
            .'.fcard .fmeta{font-family:"Sans";font-size:8.5pt;line-height:'.$sans(1.4).';color:#1d2733;border-top:0.5pt solid #EEF1F4;border-bottom:0.5pt solid #EEF1F4;padding:4pt 0;margin:0 0 6pt 0}'
            .'.fcard .fmeta .ml{color:#55636f}.fcard .fmeta .sep{color:#b8c2cd;padding:0 5pt}'
            .'.fcard .fsec{padding:3pt 0 2pt 0}'
            .'.fcard .fl{font-family:"Sans";font-weight:bold;font-size:7.8pt;letter-spacing:0.8pt;text-transform:uppercase;color:#55636f;margin:0 0 2pt 0;page-break-after:avoid}'
            .'.fcard .fsec p,.fcard .fsec ul{margin-bottom:5pt}'
            .'.fcard table.rows{font-family:"Serif";font-size:10pt;line-height:'.$serif(1.45).';margin:0 0 4pt 0}.fcard table.rows td{border:none;border-top:0.5pt solid #EEF1F4;padding:4pt 6pt 4pt 0}'
            .'.fcard td.label{width:26%;font-family:"Sans";font-size:8.5pt;color:#55636f}'
            .'.phase{margin:0 0 12pt 0;border:0.5pt solid #d6dce3}.phase .ph{page-break-after:avoid}'
            .'.phase .ph{color:#ffffff;font-family:"Sans";font-weight:bold;font-size:10.5pt;padding:5pt 12pt}'
            .'.phase table{margin:0}.phase td{border:none;border-top:0.5pt solid #EEF1F4;padding:6pt 12pt}'
            .'.phase .at{font-family:"Sans";font-weight:bold;font-size:10pt}.phase .ad{font-size:9.5pt;margin-top:2pt}.phase .ar{font-family:"Sans";font-size:8pt;color:#55636f;width:18%;text-align:right;vertical-align:top}'
            .'.contents p{margin:0 0 3pt 0;font-family:"Sans";white-space:pre}.contents p.widow{page-break-before:avoid}'
            .'.toc table{font-size:10pt;margin:2pt 0 0 0;line-height:'.$sans(1.3).'}.toc td{border:none;padding:3pt 0}'
            .'.toc tr.t1 td{font-weight:bold;padding-top:9pt;padding-bottom:4pt;border-bottom:0.5pt solid #e4e8ed}'
            .'.toc tr.t2 td{font-size:9.5pt;color:#344054}'
            .'.toc td.n1{width:6%;color:'.$brand.'}.toc td.n2{width:7%;color:#55636f}.toc td.pg{width:8%;text-align:right;color:#55636f;font-weight:normal}'
            .'.stats{width:100%;margin:6pt 0 14pt 0}.stats td{border:0.5pt solid #d6dce3;padding:8pt 10pt;width:25%;vertical-align:top}'
            .'.stats .value{font-family:"Sans";font-weight:bold;font-size:20pt;line-height:'.$sans(1.15).';color:'.$brand.'}.stats .label{font-family:"Sans";font-size:8.5pt;color:#55636f}'
            .'.metrics{margin:4pt 0 14pt 0;page-break-inside:avoid}.metrics td{border:none;padding:0;vertical-align:top}'
            .'.metrics td.card{background:#F7F9FA;padding:8pt 10pt 8pt 10pt}.metrics td.gap{width:2%}'
            .'.metrics .mv{font-weight:bold;font-size:20pt;line-height:'.$sans(1.1).'}.metrics .ml{font-size:8.5pt;color:#344054;margin-top:2pt}.metrics .ms{font-size:7.8pt;color:#55636f;margin-top:1pt}'
            .'.metrics tr.space td{height:6pt}'
            .'.bars{page-break-inside:avoid}.bars td{border:none;padding:3.5pt 5pt;vertical-align:middle}.bars .track{background:#eef1f4;height:9pt;width:100%}.bars .fill{height:9pt}'
            .'.bars td.scale{font-size:7.5pt;color:#55636f;text-align:right;padding-top:0}'
            .'.stack{margin:4pt 0 4pt 0;page-break-inside:avoid}.stack td{height:11pt;padding:0;border:none}'
            .'.legend{margin:0 0 14pt 0;font-size:8.5pt;page-break-before:avoid}.legend td{border:none;padding:2pt 10pt 2pt 0;vertical-align:middle}'
            .'.legend .sw{width:8pt}.legend .sw div{width:7pt;height:7pt}.legend .lv{color:#55636f}'
            .'.kv{margin:4pt 0 14pt 0}.kv td{padding:5pt 8pt 5pt 0;border-bottom:0.5pt solid #e4e8ed}.kv tr:first-child td{border-top:0.5pt solid #e4e8ed}'
            .'.kv td.k{width:28%;font-weight:bold;color:#344054}.kv td.v{font-family:"Serif";font-size:9.5pt;line-height:'.$serif(1.45).'}.kv td.v p,.kv td.v ul{margin-bottom:3pt}'
            .'.panel{padding:9pt 14pt 4pt 14pt;margin:4pt 0 14pt 0}.panel.whole{page-break-inside:avoid}'
            .'.panel .pt{font-family:"Sans";font-weight:bold;font-size:10.5pt;line-height:'.$sans(1.3).';margin:0 0 4pt 0;page-break-after:avoid}'
            .'.panel p,.panel ul{margin-bottom:5pt}'
            .'.sign{margin-top:40pt;width:100%}.sign td{border:none;padding:26pt 12pt 2pt 0;width:50%}'
            .'.sign .line{border-top:0.5pt solid #1d2733;padding-top:3pt;font-family:"Sans";font-size:8.5pt;color:#55636f}'
            .'.mark{height:0.1pt;margin:0;padding:0;line-height:0.1pt;font-size:0.1pt}'
            .'.first{margin-top:0;page-break-before:avoid}'
            .'h1.chapter{page-break-before:always}';
    }

    /** The HTML Dompdf draws, and how many chapter marks it carries (see pdf_short_tail). */
    private static function pdf_html(array $doc, array $prepared, array $pages, bool $tight): array
    {
        $brand = self::brand($doc['company']);
        $accent = self::accent($doc['company']);
        $logo = self::logo($doc['company']);
        $firm = self::plain($doc['company']['company_name'] ?? '');
        $title = $doc['title'];
        $e = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };
        $ctx = array(
            'brand' => $brand,
            'outline' => $prepared['outline'],
            'pages' => $pages,
            'numbered' => !empty($doc['numbered'])
        );

        $logo_html = $logo === null
            ? '<div class="firm">'.$e($firm).'</div>'
            : '<img class="logo" src="data:'.$logo['type'].';base64,'.base64_encode($logo['bytes']).'">';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .self::pdf_css($brand, $accent, Main::lib_path().'/Fonts', $tight)
            .'</style></head><body>';

        $html .= '<div class="cover"><div class="band"></div><div class="accent"></div>'
            .'<div class="kicker">Confidential</div>'
            .'<div class="title">'.$e($title).'</div>'
            .($doc['prepared_for'] !== '' ? '<div class="for">Prepared for '.$e($doc['prepared_for']).'</div>' : '')
            .$logo_html
            .'<table class="meta">';

        foreach ($doc['cover_rows'] as $label => $value) {
            $html .= '<tr><td class="label">'.$e($label).'</td><td>'.$e($value).'</td></tr>';
        }

        $html .= '</table>';

        if (!empty($doc['confidential']) && $doc['client'] !== '') {
            $html .= '<div class="confidential">Confidential. This report contains sensitive information about the security of '.$e($doc['client']).'\'s information systems. It is intended solely for the use of '.$e($doc['client']).' management and its board, and should not be distributed to others without the written consent of '.$e($doc['client']).'.</div>';
        }

        $html .= '</div>';

        $held = '';
        $first = true;
        $marks = 0;
        $chapters = self::chapter_starts(array(
            'chapter_breaks' => !empty($doc['chapter_breaks']),
            'blocks' => $prepared['blocks']
        ));
        $intro = array(
            'text',
            'lead',
            'note'
        );
        $headings = array(
            'h1',
            'h2',
            'h3',
            'domain_head'
        );

        $closed = false;

        foreach ($prepared['blocks'] as $position => $block) {
            $kind = $block['kind'];

            if (!empty($block['closing']) && !$closed && $marks > 0) {
                $html .= $held.'<div class="mark" id="m-end-'.($marks - 1).'"></div>';
                $held = '';
                $closed = true;
            }

            if ($kind === 'h1' && !$closed && ($first || in_array($position, $chapters, true))) {
                if (!$first) {
                    $html .= $held.'<div class="mark" id="m-end-'.($marks - 1).'"></div>';
                }
                $html .= self::pdf_heading($block, $e, $first || in_array($marks, $doc['flow_marks'] ?? array(), true) ? 'first' : 'chapter').'<div class="mark" id="m-start-'.$marks.'"></div>';
                $marks++;
                $first = false;
                $held = '';
                continue;
            }

            if (in_array($kind, $headings, true)) {
                $held .= self::pdf_block($block, $e, $ctx);
                continue;
            }

            if ($kind === 'finding') {
                $html .= self::pdf_finding($block, $e, $held);
                $held = '';
                continue;
            }

            $small_table = in_array($kind, array(
                'table',
                'register'
            ), true) && count($block['value']['rows']) <= self::KEEP_WHOLE_ROWS;

            if ($held !== '' && $kind === 'panel' && self::text_length($block['value']['body'] ?? '') >= 700) {
                $html .= '<div style="page-break-after:avoid">'.$held.'</div>'.self::pdf_block($block, $e, $ctx);
                $held = '';
                continue;
            }

            if ($held !== '' && ($small_table || in_array($kind, self::KEEP_WITH_HEADING, true))) {
                $next = $prepared['blocks'][$position + 1]['kind'] ?? 'h1';
                $lead_in = in_array($kind, $intro, true) && $next !== 'finding' && !in_array($next, $headings, true);
                $html .= '<div style="page-break-inside:avoid'.($lead_in ? ';page-break-after:avoid' : '').'">'.$held.self::pdf_block($block, $e, $ctx).'</div>';
                $held = '';
                continue;
            }

            $html .= $held.self::pdf_block($block, $e, $ctx);
            $held = '';
        }

        if ($marks > 0 && !$closed) {
            $html .= $held.'<div class="mark" id="m-end-'.($marks - 1).'"></div>';
            $held = '';
        }

        return array(
            'html' => $html.$held.'</body></html>',
            'marks' => $marks
        );
    }

    private static function pdf_heading(array $block, callable $e, string $class = ''): string
    {
        $tag = $block['kind'];
        $number = ($block['number'] ?? '') !== '' ? '<span class="hn">'.$e(rtrim($block['number'], '.')).'</span>' : '';

        return '<'.$tag.' id="'.$e($block['anchor'] ?? '').'"'.($class !== '' ? ' class="'.$class.'"' : '').'>'.$number.$e($block['text'] ?? $block['value']).'</'.$tag.'>';
    }

    /**
     * A finding card. A short card never splits; a long one keeps its header,
     * the details line and its first section together and lets the rest flow.
     */
    private static function pdf_finding(array $block, callable $e, string $held): string
    {
        $value = $block['value'];
        $color = self::SEVERITY_COLORS[$value['severity'] ?? ''] ?? '#55636f';
        $sections = $value['sections'] ?? array();
        $rows = $value['rows'] ?? array();
        $length = 0;

        foreach ($rows as $row) {
            $length += self::text_length($row[1]);
        }

        foreach ($sections as $section) {
            $length += self::text_length($section[1]) + 40;
        }

        $head = '<table class="fh"><tr><td>'.(($value['ref'] ?? '') !== '' ? '<div class="fref">'.$e($value['ref']).'</div>' : '').'<div class="ftitle">'.$e($value['heading']).'</div></td>'
            .'<td style="width:24%;text-align:right">'.(($value['rating'] ?? '') !== '' ? '<span class="pill" style="background:'.$color.'">'.$e($value['rating']).'</span>' : '').'</td></tr></table>';

        if (count($value['meta'] ?? array()) > 0) {
            $parts = array();
            foreach ($value['meta'] as $pair) {
                $parts[] = '<span class="ml">'.$e($pair[0]).'</span> '.$e($pair[1]);
            }
            $head .= '<div class="fmeta">'.implode('<span class="sep">|</span>', $parts).'</div>';
        }

        $body = array();

        if (count($rows) > 0) {
            $table = '<table class="rows">';
            foreach ($rows as $row) {
                $table .= '<tr><td class="label">'.$e($row[0]).'</td><td>'.nl2br($e($row[1])).'</td></tr>';
            }
            $body[] = $table.'</table>';
        }

        foreach ($sections as $section) {
            $body[] = '<div class="fsec"><div class="fl">'.$e($section[0]).'</div>'.self::pdf_paragraphs($section[1], $e).'</div>';
        }

        $id = isset($block['anchor']) ? ' id="'.$e($block['anchor']).'"' : '';
        $style = ' style="border-left:4pt solid '.$color.'"';

        if ($length <= self::FINDING_WHOLE_CHARS || count($body) === 0) {
            return '<div style="page-break-inside:avoid">'.$held.'<div class="fcard"'.$id.$style.'>'.$head.implode('', $body).'</div></div>';
        }

        $lead = array_shift($body);

        if (count($rows) === 0 && self::text_length($sections[0][1]) > 900) {
            $chunks = self::chunks($sections[0][1]);
            $lead = '<div class="fsec"><div class="fl">'.$e($sections[0][0]).'</div>'.self::pdf_chunks(array_slice($chunks, 0, 1), $e).'</div>';
            if (count($chunks) > 1) {
                array_unshift($body, '<div class="fsec">'.self::pdf_chunks(array_slice($chunks, 1), $e).'</div>');
            }
        }

        if (count($body) === 0) {
            return '<div style="page-break-inside:avoid">'.$held.'<div class="fcard top"'.$id.$style.'>'.$head.$lead.'</div></div>';
        }

        return '<div style="page-break-inside:avoid">'.$held.'<div class="fcard top"'.$id.$style.'>'.$head.$lead.'</div></div>'
            .'<div class="fcard rest"'.$style.'>'.implode('', $body).'</div>';
    }

    /**
     * Adds the h1/h2 outline as PDF bookmarks, as an incremental update
     * appended to the file Dompdf wrote (Dompdf has no outline support).
     */
    private static function pdf_outline(string $bytes, array $outline, array $seen, $cpdf): string
    {
        $pages = $cpdf->objects[$cpdf->objects[$cpdf->catalogId]['info']['pages']]['info']['pages'] ?? array();
        $items = array();

        foreach ($outline as $entry) {
            $at = $seen[$entry['anchor']] ?? null;
            if ($at === null || !isset($pages[$at['page'] - 1])) {
                continue;
            }
            if ($entry['level'] === 2 && count($items) === 0) {
                continue;
            }
            $item = array(
                'title' => trim($entry['number'].' '.$entry['text']),
                'page' => $pages[$at['page'] - 1],
                'top' => round(792 - $at['y'] + 6, 1),
                'children' => array()
            );
            if ($entry['level'] === 1) {
                $items[] = $item;
            } else {
                $items[count($items) - 1]['children'][] = $item;
            }
        }

        if (count($items) === 0) {
            return $bytes;
        }

        $trailer = (string) substr($bytes, (int) strrpos($bytes, "\ntrailer"));

        if (preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $trailer, $start) !== 1
            || preg_match('/\/Size (\d+)/', $trailer, $size) !== 1
            || preg_match('/\/Info (\d+) 0 R/', $trailer, $info) !== 1
            || preg_match('/\/ID\s*\[[^\]]*\]/', $trailer, $file_id) !== 1
            || preg_match('/\n'.$cpdf->catalogId.' 0 obj\n(<<.*?>>)\nendobj/s', $bytes, $catalog) !== 1) {
            return $bytes;
        }

        $next = (int) $size[1];
        $root = $next++;
        $objects = array();
        $string = function ($text) {
            return '<FEFF'.strtoupper(bin2hex(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8'))).'>';
        };

        $place = function (array $list, int $parent) use (&$place, &$next, &$objects, $string) {
            $ids = array();
            foreach ($list as $item) {
                $ids[] = $next++;
            }
            foreach ($list as $index => $item) {
                $id = $ids[$index];
                $dict = '<< /Title '.$string($item['title']).' /Parent '.$parent.' 0 R /Dest ['.$item['page'].' 0 R /XYZ null '.$item['top'].' null]';
                if ($index > 0) {
                    $dict .= ' /Prev '.$ids[$index - 1].' 0 R';
                }
                if ($index < count($list) - 1) {
                    $dict .= ' /Next '.$ids[$index + 1].' 0 R';
                }
                if (count($item['children']) > 0) {
                    $children = $place($item['children'], $id);
                    $dict .= ' /First '.$children[0].' 0 R /Last '.end($children).' 0 R /Count -'.count($children);
                }
                $objects[$id] = $dict.' >>';
            }
            return $ids;
        };

        $top = $place($items, $root);
        $objects[$root] = '<< /Type /Outlines /First '.$top[0].' 0 R /Last '.end($top).' 0 R /Count '.count($top).' >>';

        $dict = preg_replace('/\s*\/(Outlines \d+ 0 R|PageMode \/\w+)/', '', $catalog[1]);
        $objects[$cpdf->catalogId] = substr($dict, 0, -2).' /Outlines '.$root.' 0 R /PageMode /UseOutlines >>';

        ksort($objects);
        $out = $bytes;
        $offsets = array();

        foreach ($objects as $id => $object) {
            $out .= "\n";
            $offsets[$id] = strlen($out);
            $out .= $id." 0 obj\n".$object."\nendobj";
        }

        $out .= "\n";
        $xref = strlen($out);
        $out .= "xref\n".$cpdf->catalogId." 1\n".sprintf('%010d', $offsets[$cpdf->catalogId])." 00000 n \n";
        unset($offsets[$cpdf->catalogId]);
        $out .= $root.' '.count($offsets)."\n";

        foreach ($offsets as $offset) {
            $out .= sprintf('%010d', $offset)." 00000 n \n";
        }

        return $out."trailer\n<< /Size ".$next.' /Root '.$cpdf->catalogId.' 0 R /Info '.$info[1].' 0 R '.$file_id[0].' /Prev '.$start[1]." >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private static function pdf_block(array $block, callable $e, array $ctx): string
    {
        $value = $block['value'];

        if (in_array($block['kind'], array(
            'h1',
            'h2',
            'h3'
        ), true)) {
            return self::pdf_heading($block, $e);
        }

        if ($block['kind'] === 'toc') {
            return self::pdf_toc($ctx, $e);
        }

        if ($block['kind'] === 'finding') {
            return self::pdf_finding($block, $e, '');
        }

        if ($block['kind'] === 'metrics') {
            return self::pdf_metrics($value['items'] ?? $value, $e, $ctx['brand']);
        }

        if ($block['kind'] === 'stackbar') {
            return self::pdf_stackbar($value['segments'] ?? $value, $e, $ctx['brand']);
        }

        if ($block['kind'] === 'kv') {
            $html = '<table class="kv">';
            foreach ($value['rows'] ?? $value as $row) {
                $html .= '<tr><td class="k">'.$e($row[0]).'</td><td class="v">'.self::pdf_paragraphs($row[1], $e).'</td></tr>';
            }
            return $html.'</table>';
        }

        if ($block['kind'] === 'panel') {
            $tone = self::tone($value['tone'] ?? 'neutral', $ctx['brand']);
            $body = $value['body'] ?? '';
            $whole = self::text_length($body) < 700 ? ' whole' : '';
            return '<div class="panel'.$whole.'" style="background:'.$tone[1].';border-left:3pt solid '.$tone[0].'">'
                .(($value['title'] ?? '') !== '' ? '<div class="pt" style="color:'.$tone[0].'">'.$e($value['title']).'</div>' : '')
                .self::pdf_paragraphs($body, $e).'</div>';
        }

        if ($block['kind'] === 'text') {
            return self::pdf_paragraphs($value, $e);
        }

        if ($block['kind'] === 'note') {
            return '<p class="note">'.$e($value).'</p>';
        }

        if ($block['kind'] === 'contents') {
            $html = '<div class="contents">';
            $last = count($value) - 1;
            foreach ($value as $index => $line) {
                $html .= '<p'.($index === $last && $index > 0 ? ' class="widow"' : '').'>'.$e($line).'</p>';
            }
            return $html.'</div>';
        }

        if ($block['kind'] === 'stats') {
            $html = '<table class="stats"><tr>';
            foreach ($value as $index => $stat) {
                if ($index > 0 && $index % 4 === 0) {
                    $html .= '</tr><tr>';
                }
                $html .= '<td><div class="value">'.$e($stat['value']).'</div><div class="label">'.$e($stat['label']).'</div></td>';
            }
            return $html.'</tr></table>';
        }

        if ($block['kind'] === 'bars') {
            $bars = $value['items'] ?? $value;
            $html = '<table class="bars">';
            foreach ($bars as $bar) {
                $width = ($bar['max'] ?? 0) > 0 ? min(100, round(100 * $bar['value'] / $bar['max'])) : 0;
                $color = isset($bar['tone']) ? self::tone($bar['tone'], $ctx['brand'])[0] : ($bar['color'] ?? $ctx['brand']);
                $html .= '<tr><td style="width:34%">'.$e($bar['label']).'</td><td><div class="track"><div class="fill" style="width:'.$width.'%;background:'.$e($color).'"></div></div></td><td style="width:16%;text-align:right">'.$e($bar['text'] ?? '').'</td></tr>';
            }
            if (($value['max_label'] ?? '') !== '') {
                $html .= '<tr><td></td><td class="scale">'.$e($value['max_label']).'</td><td></td></tr>';
            }
            return $html.'</table>';
        }

        if ($block['kind'] === 'lead') {
            return '<p class="lead">'.($value['label'] !== '' ? '<b>'.$e($value['label']).'</b> ' : '').$e($value['text']).'</p>';
        }

        if ($block['kind'] === 'table' || $block['kind'] === 'register') {
            return self::pdf_table($value, $e);
        }

        if ($block['kind'] === 'posture') {
            $color = self::POSTURE_COLORS[$value['rating']] ?? '#55636f';
            return '<div class="posture" style="background:'.$color.'"><div class="label">Overall risk posture</div><div class="rating">'.$e($value['rating']).'</div><div class="headline">'.$e($value['headline']).'</div></div>';
        }

        if ($block['kind'] === 'callout') {
            $html = '<div class="callout"><div class="ctitle">'.$e($value['title']).'</div><ol>';
            foreach ($value['items'] as $item) {
                $html .= '<li>'.$e($item).'</li>';
            }
            return $html.'</ol></div>';
        }

        if ($block['kind'] === 'dashboard') {
            $t = $value['totals'];
            $sum = max(1, $t['High'] + $t['Medium'] + $t['Low']);
            $html = '<table class="dash"><tr>'
                .'<td><div class="num">'.(int) $value['total'].'</div><div class="cap">Findings across '.(int) $value['areas'].' areas</div></td>'
                .'<td><div class="num" style="color:'.self::SEVERITY_COLORS['High'].'">'.(int) $t['High'].'</div><div class="cap">High</div></td>'
                .'<td><div class="num" style="color:'.self::SEVERITY_COLORS['Medium'].'">'.(int) $t['Medium'].'</div><div class="cap">Medium</div></td>'
                .'<td><div class="num" style="color:'.self::SEVERITY_COLORS['Low'].'">'.(int) $t['Low'].'</div><div class="cap">Low</div></td>'
                .'</tr></table><table class="sevbar"><tr>';
            foreach (self::SEVERITY_COLORS as $rating => $color) {
                if ($t[$rating] > 0) {
                    $html .= '<td style="width:'.round(100 * $t[$rating] / $sum).'%;background:'.$color.'"></td>';
                }
            }
            if ($t['High'] + $t['Medium'] + $t['Low'] === 0) {
                $html .= '<td style="background:#E3E8EE"></td>';
            }
            return $html.'</tr></table>';
        }

        if ($block['kind'] === 'heatmap') {
            $html = '<table class="heat">';
            for ($impact = 5; $impact >= 1; $impact--) {
                $html .= '<tr><td class="axis">'.($impact === 3 ? 'Impact ' : '').$impact.'</td>';
                for ($likelihood = 1; $likelihood <= 5; $likelihood++) {
                    $score = $impact * $likelihood;
                    $background = $score >= 15 ? '#F6C9C4' : ($score >= 8 ? '#FBE2B6' : '#D3EEDD');
                    $count = $value[$likelihood.'-'.$impact] ?? 0;
                    $html .= '<td style="background:'.$background.'">'.($count > 0 ? (int) $count : '').'</td>';
                }
                $html .= '</tr>';
            }
            $html .= '<tr><td class="axis"></td>';
            for ($likelihood = 1; $likelihood <= 5; $likelihood++) {
                $html .= '<td class="axis">'.$likelihood.'</td>';
            }
            return $html.'</tr><tr><td class="axis"></td><td class="axis" colspan="5">Likelihood</td></tr></table>';
        }

        if ($block['kind'] === 'domain_head') {
            $meta = array();
            foreach ($value['counts'] as $rating => $count) {
                if ($count > 0) {
                    $meta[] = $count.' '.strtolower($rating);
                }
            }
            $pill = $value['rating'] !== null ? '<span class="pill" style="background:'.(self::RATING_COLORS[$value['rating']] ?? '#55636f').'">'.$e($value['rating']).'</span>' : '';
            $number = ($block['number'] ?? '') !== '' ? '<span class="hn">'.$e($block['number']).'</span>' : '';
            return '<div class="dhead" id="'.$e($block['anchor'] ?? '').'"><table><tr><td class="dt">'.$number.$e($value['title']).'</td><td class="dm">'.$pill.(count($meta) > 0 ? '<br>'.$e(implode(' · ', $meta)) : '').'</td></tr></table></div>';
        }

        if ($block['kind'] === 'roadmap') {
            $colors = array(
                '#B42318',
                '#B54708',
                '#075985',
                '#3E5C76'
            );
            $html = '';
            $index = 0;
            foreach ($value as $phase => $items) {
                $color = $colors[$index++] ?? '#075985';
                if (count($items) === 0) {
                    continue;
                }
                $html .= '<div class="phase"><div class="ph" style="background:'.$color.'">'.$e($phase).'</div><table>';
                foreach ($items as $item) {
                    $html .= '<tr><td><div class="at">'.$e($item['title']).'</div><div class="ad">'.$e($item['detail']).'</div></td><td class="ar">'.$e(implode(', ', $item['refs'])).'</td></tr>';
                }
                $html .= '</table></div>';
            }
            return $html;
        }

        if ($block['kind'] === 'signature') {
            return '<table class="sign"><tr><td><div style="font-family:Sans">'.$e($value['name']).'</div><div class="line">Name</div></td><td><div style="font-family:Sans">'.$e($value['title']).'</div><div class="line">Title</div></td></tr>'
                .'<tr><td><div>&nbsp;</div><div class="line">Signature</div></td><td><div>&nbsp;</div><div class="line">Date</div></td></tr></table>';
        }

        return '';
    }

    private static function pdf_table(array $value, callable $e): string
    {
        $count = count($value['rows']);
        $anchors = $value['anchors'] ?? array();
        $link_col = (int) ($value['link_col'] ?? 0);
        $numeric = self::numeric_columns($value['rows'], count($value['head']));
        $classes = array();

        if (!empty($value['small'])) {
            $classes[] = 'small';
        }

        if ($count <= self::KEEP_WHOLE_ROWS) {
            $classes[] = 'whole';
        }

        $html = '<table class="'.implode(' ', $classes).'"><thead><tr class="keep">';

        foreach ($value['head'] as $index => $head) {
            $html .= '<th style="width:'.(int) $value['widths'][$index].'%"'.($numeric[$index] ? ' class="num"' : '').'>'.$e($head).'</th>';
        }

        $html .= '</tr></thead><tbody>';
        $last = $count - 1;

        foreach (array_values($value['rows']) as $index => $row) {
            $row_classes = array();
            if (!empty($value['total']) && $index === $last) {
                $row_classes[] = 'total';
            } elseif ($index % 2 === 1) {
                $row_classes[] = 'alt';
            }
            if ($index < self::KEEP_LEAD_ROWS - 1) {
                $row_classes[] = 'keep';
            }
            if ($index >= $last - 1 && $index >= self::KEEP_LEAD_ROWS + 1) {
                $row_classes[] = 'widow';
            }
            $html .= '<tr'.(count($row_classes) > 0 ? ' class="'.implode(' ', $row_classes).'"' : '').'>';
            foreach (array_values($row) as $col => $cell) {
                $text = $e($cell);
                if ($col === $link_col && ($anchors[$index] ?? '') !== '' && $text !== '') {
                    $text = '<a class="ref" href="#'.$e(self::anchor($anchors[$index])).'">'.$text.'</a>';
                }
                $html .= '<td'.(!empty($numeric[$col]) ? ' class="num"' : '').'>'.$text.'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    /** The contents page: h1 and h2 entries, linked, with page numbers once known. */
    private static function pdf_toc(array $ctx, callable $e): string
    {
        $numbered = $ctx['numbered'];
        $html = '<div class="toc"><table>';

        foreach ($ctx['outline'] as $entry) {
            $page = $ctx['pages'][$entry['anchor']] ?? '';
            $link = '<a href="#'.$e($entry['anchor']).'">'.$e($entry['text']).'</a>';
            if ($entry['level'] === 1) {
                $html .= '<tr class="t1">'.($numbered ? '<td class="n1">'.$e(rtrim($entry['number'], '.')).'</td><td colspan="2">' : '<td colspan="3">').$link.'</td><td class="pg">'.$e($page).'</td></tr>';
                continue;
            }
            $html .= '<tr class="t2"><td class="n1"></td>'.($numbered ? '<td class="n2">'.$e($entry['number']).'</td><td>' : '<td colspan="2">').$link.'</td><td class="pg">'.$e($page).'</td></tr>';
        }

        return $html.'</table></div>';
    }

    private static function pdf_metrics(array $items, callable $e, string $brand): string
    {
        $items = array_values($items);
        $count = count($items);

        if ($count === 0) {
            return '';
        }

        $per_row = (int) ceil($count / ceil($count / 4));
        $width = round((100 - 2 * ($per_row - 1)) / $per_row, 2);
        $html = '<table class="metrics">';

        foreach (array_chunk($items, $per_row) as $row_index => $row) {
            if ($row_index > 0) {
                $html .= '<tr class="space"><td colspan="'.(2 * $per_row - 1).'"></td></tr>';
            }
            $html .= '<tr>';
            for ($i = 0; $i < $per_row; $i++) {
                if ($i > 0) {
                    $html .= '<td class="gap"></td>';
                }
                $item = $row[$i] ?? null;
                if ($item === null) {
                    $html .= '<td style="width:'.$width.'%"></td>';
                    continue;
                }
                $color = self::tone($item['tone'] ?? 'brand', $brand)[0];
                $html .= '<td class="card" style="width:'.$width.'%;border-top:2pt solid '.$color.'"><div class="mv" style="color:'.$color.'">'.$e($item['value']).'</div>'
                    .'<div class="ml">'.$e($item['label']).'</div>'
                    .(($item['sub'] ?? '') !== '' ? '<div class="ms">'.$e($item['sub']).'</div>' : '').'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</table>';
    }

    private static function pdf_stackbar(array $segments, callable $e, string $brand): string
    {
        $sum = 0;

        foreach ($segments as $segment) {
            $sum += max(0, (float) $segment['value']);
        }

        $bar = '<table class="stack"><tr>';
        $legend = '<table class="legend"><tr>';

        foreach ($segments as $segment) {
            $color = isset($segment['tone']) ? self::tone($segment['tone'], $brand)[0] : ($segment['color'] ?? $brand);
            $share = $sum > 0 ? 100 * max(0, (float) $segment['value']) / $sum : 0;
            if ($share > 0) {
                $bar .= '<td style="width:'.round($share, 2).'%;background:'.$e($color).'"></td>';
            }
            $legend .= '<td class="sw"><div style="background:'.$e($color).'"></div></td><td>'.$e($segment['label']).' <span class="lv">'.$e($segment['value']).' ('.round($share).'%)</span></td>';
        }

        if ($sum <= 0) {
            $bar .= '<td style="background:#E3E8EE"></td>';
        }

        return $bar.'</tr></table>'.$legend.'</tr></table>';
    }

    /** Blank lines separate paragraphs; runs of lines starting with "- " (or a list of strings) become bullet lists. */
    private static function pdf_paragraphs($text, callable $e): string
    {
        return self::pdf_chunks(self::chunks($text), $e);
    }

    private static function pdf_chunks(array $chunks, callable $e): string
    {
        $html = '';

        foreach ($chunks as $chunk) {
            if (isset($chunk['bullets'])) {
                $html .= '<ul><li>'.implode('</li><li>', array_map($e, $chunk['bullets'])).'</li></ul>';
                continue;
            }
            $html .= '<p>'.implode('<br>', array_map($e, $chunk['lines'])).'</p>';
        }

        return $html;
    }

    /* ----------------------------------------------------------------- Word */

    private static function docx(array $doc): string
    {
        Settings::setOutputEscapingEnabled(true);

        $brand = ltrim(self::brand($doc['company']), '#');
        $firm = self::plain($doc['company']['company_name'] ?? '');
        $title = $doc['title'];
        $prepared = self::prepare($doc);
        $chapters = self::chapter_starts(array(
            'chapter_breaks' => !empty($doc['chapter_breaks']),
            'blocks' => $prepared['blocks']
        ));

        $word = new PhpWord();
        $word->getCompatibility()->setOoxmlVersion(15);
        $word->setDefaultFontName('Georgia');
        $word->setDefaultFontSize(10.5);
        $word->addTitleStyle(1, array(
            'name' => 'Calibri',
            'size' => 18,
            'bold' => true,
            'color' => $brand
        ), array(
            'spaceBefore' => 480,
            'spaceAfter' => 200,
            'keepNext' => true,
            'borderBottomSize' => 8,
            'borderBottomColor' => $brand
        ));
        $word->addTitleStyle(2, array(
            'name' => 'Calibri',
            'size' => 13,
            'bold' => true,
            'color' => '1D2733'
        ), array(
            'spaceBefore' => 240,
            'spaceAfter' => 100,
            'keepNext' => true
        ));
        $word->addTitleStyle(3, array(
            'name' => 'Calibri',
            'size' => 11,
            'bold' => true,
            'color' => '344054'
        ), array(
            'spaceBefore' => 180,
            'spaceAfter' => 60,
            'keepNext' => true
        ));
        $word->addParagraphStyle('Body', array(
            'spaceAfter' => 120,
            'lineHeight' => 1.2
        ));

        $section = $word->addSection(array(
            'marginTop' => 1440,
            'marginBottom' => 1300,
            'marginLeft' => 1300,
            'marginRight' => 1300
        ));

        $grey = array(
            'name' => 'Calibri',
            'size' => 8,
            'color' => '55636F'
        );

        $section->addHeader(Header::FIRST);
        $section->addFooter(Footer::FIRST);
        $header = $section->addHeader();
        $header->addText(($doc['client'] !== '' ? $doc['client'].' · ' : '').$title, $grey, array(
            'alignment' => 'right'
        ));
        $footer = $section->addFooter();
        $footer->addPreserveText('Confidential · '.$firm.'          Page {PAGE} of {NUMPAGES}', $grey, array(
            'alignment' => 'right'
        ));
        if (!empty($doc['document_control'])) {
            $footer->addText(self::status_line($doc['document_control']), $grey, array(
                'alignment' => 'right'
            ));
        }

        $logo = self::logo($doc['company']);

        if ($logo !== null) {
            $logo_file = tempnam(sys_get_temp_dir(), 'logo');
            file_put_contents($logo_file, $logo['bytes']);
            $section->addImage($logo_file, array(
                'height' => 40
            ));
        } else {
            $section->addText($firm, array(
                'name' => 'Calibri',
                'size' => 16,
                'bold' => true,
                'color' => $brand
            ));
        }

        $section->addTextBreak(6);
        $section->addText('CONFIDENTIAL', array(
            'name' => 'Calibri',
            'size' => 8,
            'color' => '55636F',
            'spacing' => 40
        ));
        $section->addText($title, array(
            'name' => 'Calibri',
            'size' => 28,
            'bold' => true,
            'color' => $brand
        ), array(
            'borderBottomSize' => 6,
            'borderBottomColor' => '1D2733',
            'spaceAfter' => 200
        ));
        $section->addText($doc['prepared_for'] !== '' ? 'Prepared for '.$doc['prepared_for'] : '', array(
            'name' => 'Calibri',
            'size' => 13
        ));
        $section->addTextBreak(6);

        $cover = $section->addTable(array(
            'cellMargin' => 40,
            'layout' => 'fixed'
        ));

        foreach ($doc['cover_rows'] as $label => $value) {
            $cover->addRow();
            $cover->addCell(2600)->addText($label, array(
                'name' => 'Calibri',
                'size' => 9.5,
                'color' => '55636F'
            ));
            $cover->addCell(5200)->addText($value, array(
                'name' => 'Calibri',
                'size' => 9.5
            ));
        }

        if (!empty($doc['confidential']) && $doc['client'] !== '') {
            $section->addTextBreak(2);
            $section->addText('Confidential. This report contains sensitive information about the security of '.$doc['client'].'\'s information systems. It is intended solely for the use of '.$doc['client'].' management and its board, and should not be distributed to others without the written consent of '.$doc['client'].'.', array(
                'size' => 8,
                'color' => '55636F'
            ), array(
                'borderTopSize' => 4,
                'borderTopColor' => 'B8C2CD'
            ));
        }

        $section->addPageBreak();

        foreach ($prepared['blocks'] as $position => $block) {
            if ($position > 0 && in_array($position, $chapters, true)) {
                $section->addPageBreak();
            }
            self::docx_block($section, $block, $brand);
        }

        $file = tempnam(sys_get_temp_dir(), 'docx');
        IOFactory::createWriter($word, 'Word2007')->save($file);
        self::docx_finish($file);
        $bytes = file_get_contents($file);
        @unlink($file);

        if (isset($logo_file)) {
            @unlink($logo_file);
        }

        return $bytes;
    }

    /**
     * Two fixes to the saved file. The settings carry no updateFields flag,
     * so Word opens the report without asking to update fields. PhpWord
     * writes the contents entries (so the list shows without an update) but
     * points their page fields at bookmark "<n>" while the headings carry
     * "_Toc<n>"; they are pointed at the real bookmarks, so Update Field
     * fills in page numbers instead of "bookmark not defined".
     */
    private static function docx_finish(string $file): void
    {
        $zip = new ZipArchive();

        if ($zip->open($file) !== true) {
            return;
        }

        $fixes = array(
            'word/document.xml' => array(
                '/PAGEREF (\d+) /',
                'PAGEREF _Toc$1 '
            ),
            'word/settings.xml' => array(
                '/<w:updateFields[^>]*\/>/',
                ''
            )
        );

        foreach ($fixes as $part => $fix) {
            $xml = $zip->getFromName($part);
            if (!is_string($xml)) {
                continue;
            }
            $fixed = preg_replace($fix[0], $fix[1], $xml);
            if (is_string($fixed) && $fixed !== $xml) {
                $zip->addFromString($part, $fixed);
            }
        }

        $zip->close();
    }

    private static function docx_block($section, array $block, string $brand): void
    {
        $value = $block['value'];
        $kind = $block['kind'];

        if (in_array($kind, array(
            'h1',
            'h2',
            'h3'
        ), true)) {
            $text = ($block['number'] ?? '') !== '' ? $block['number'].' '.$block['text'] : ($block['text'] ?? $value);
            if (($block['outline'] ?? true) === false) {
                $section->addText($text, array(
                    'name' => 'Calibri',
                    'size' => 18,
                    'bold' => true,
                    'color' => $brand
                ), array(
                    'spaceAfter' => 200,
                    'keepNext' => true,
                    'borderBottomSize' => 8,
                    'borderBottomColor' => $brand
                ));
                return;
            }
            $section->addTitle($text, (int) substr($kind, 1));
            return;
        }

        if ($kind === 'toc') {
            $section->addTOC(array(
                'name' => 'Calibri',
                'size' => 10
            ), array(
                'tabLeader' => 'dot'
            ), 1, 2);
            return;
        }

        if ($kind === 'text') {
            self::docx_paragraphs($section, $value, null, 'Body');
            return;
        }

        if ($kind === 'panel') {
            $tone = self::tone($value['tone'] ?? 'neutral', '#'.$brand);
            $table = $section->addTable(array(
                'cellMargin' => 140,
                'width' => 5000,
                'unit' => 'pct'
            ));
            $table->addRow(null, array(
                'cantSplit' => self::text_length($value['body'] ?? '') < 1400
            ));
            $cell = $table->addCell(9600, array(
                'bgColor' => ltrim($tone[1], '#'),
                'borderLeftSize' => 24,
                'borderLeftColor' => ltrim($tone[0], '#')
            ));
            if (($value['title'] ?? '') !== '') {
                $cell->addText($value['title'], array(
                    'name' => 'Calibri',
                    'size' => 10.5,
                    'bold' => true,
                    'color' => ltrim($tone[0], '#')
                ), array(
                    'spaceAfter' => 60,
                    'keepNext' => true
                ));
            }
            self::docx_paragraphs($cell, $value['body'] ?? '', array(
                'size' => 10
            ), array(
                'spaceAfter' => 80,
                'lineHeight' => 1.15
            ));
            $section->addTextBreak();
            return;
        }

        if ($kind === 'kv') {
            $table = $section->addTable(array(
                'borderTopSize' => 4,
                'borderTopColor' => 'E4E8ED',
                'borderBottomSize' => 4,
                'borderBottomColor' => 'E4E8ED',
                'borderInsideHSize' => 4,
                'borderInsideHColor' => 'E4E8ED',
                'cellMargin' => 70,
                'width' => 5000,
                'unit' => 'pct',
                'layout' => 'fixed'
            ));
            foreach ($value['rows'] ?? $value as $row) {
                $table->addRow(null, array(
                    'cantSplit' => true
                ));
                $table->addCell(2700)->addText((string) $row[0], array(
                    'name' => 'Calibri',
                    'size' => 9,
                    'bold' => true,
                    'color' => '344054'
                ));
                self::docx_paragraphs($table->addCell(6900), $row[1], array(
                    'size' => 9.5
                ), array(
                    'spaceAfter' => 40
                ));
            }
            $section->addTextBreak();
            return;
        }

        if ($kind === 'metrics') {
            $items = array_values($value['items'] ?? $value);
            $count = count($items);
            if ($count === 0) {
                return;
            }
            $per_row = (int) ceil($count / ceil($count / 4));
            $table = $section->addTable(array(
                'cellMargin' => 120,
                'width' => 5000,
                'unit' => 'pct',
                'layout' => 'fixed',
                'borderInsideVSize' => 60,
                'borderInsideVColor' => 'FFFFFF',
                'borderInsideHSize' => 60,
                'borderInsideHColor' => 'FFFFFF'
            ));
            foreach (array_chunk($items, $per_row) as $row) {
                $table->addRow(null, array(
                    'cantSplit' => true
                ));
                for ($i = 0; $i < $per_row; $i++) {
                    $item = $row[$i] ?? null;
                    $width = (int) floor(9600 / $per_row);
                    if ($item === null) {
                        $table->addCell($width);
                        continue;
                    }
                    $color = ltrim(self::tone($item['tone'] ?? 'brand', '#'.$brand)[0], '#');
                    $cell = $table->addCell($width, array(
                        'bgColor' => 'F7F9FA',
                        'borderTopSize' => 16,
                        'borderTopColor' => $color
                    ));
                    $cell->addText((string) $item['value'], array(
                        'name' => 'Calibri',
                        'size' => 20,
                        'bold' => true,
                        'color' => $color
                    ), array(
                        'spaceAfter' => 0,
                        'keepNext' => true
                    ));
                    $cell->addText((string) $item['label'], array(
                        'name' => 'Calibri',
                        'size' => 8.5,
                        'color' => '344054'
                    ), array(
                        'spaceAfter' => 0
                    ));
                    if (($item['sub'] ?? '') !== '') {
                        $cell->addText((string) $item['sub'], array(
                            'name' => 'Calibri',
                            'size' => 8,
                            'color' => '55636F'
                        ), array(
                            'spaceAfter' => 0
                        ));
                    }
                }
            }
            $section->addTextBreak();
            return;
        }

        if ($kind === 'stackbar') {
            $segments = $value['segments'] ?? $value;
            $sum = 0;
            foreach ($segments as $segment) {
                $sum += max(0, (float) $segment['value']);
            }
            $bar = $section->addTable(array(
                'cellMargin' => 0,
                'width' => 5000,
                'unit' => 'pct',
                'layout' => 'fixed'
            ));
            $bar->addRow(200, array(
                'exactHeight' => true,
                'cantSplit' => true
            ));
            $legend = $section->addTextRun(array(
                'spaceBefore' => 80,
                'spaceAfter' => 200
            ));
            foreach ($segments as $index => $segment) {
                $color = ltrim(isset($segment['tone']) ? self::tone($segment['tone'], '#'.$brand)[0] : ($segment['color'] ?? '#'.$brand), '#');
                $share = $sum > 0 ? max(0, (float) $segment['value']) / $sum : 0;
                if ($share > 0) {
                    $bar->addCell(max(1, (int) round(9600 * $share)), array(
                        'bgColor' => $color
                    ));
                }
                $legend->addText(($index > 0 ? '      ' : '')."\u{25A0} ", array(
                    'name' => 'Calibri',
                    'size' => 10,
                    'color' => $color
                ));
                $legend->addText($segment['label'].' '.$segment['value'].' ('.round(100 * $share).'%)', array(
                    'name' => 'Calibri',
                    'size' => 8.5,
                    'color' => '344054'
                ));
            }
            if ($sum <= 0) {
                $bar->addCell(9600, array(
                    'bgColor' => 'E3E8EE'
                ));
            }
            return;
        }

        if ($block['kind'] === 'note') {
            $section->addText($value, array(
                'name' => 'Calibri',
                'size' => 8.5,
                'color' => '55636F'
            ));
            return;
        }

        if ($block['kind'] === 'contents') {
            foreach ($value as $line) {
                $section->addText(str_replace(' ', "\u{00A0}", $line), array(
                    'name' => 'Calibri'
                ), array(
                    'spaceAfter' => 60
                ));
            }
            return;
        }

        if ($block['kind'] === 'stats') {
            $rows = array();
            foreach ($value as $stat) {
                $rows[] = array(
                    $stat['label'],
                    (string) $stat['value']
                );
            }
            self::docx_table($section, array(
                'Measure',
                'Value'
            ), array(
                70,
                30
            ), $rows, $brand, false, false);
            return;
        }

        if ($block['kind'] === 'bars') {
            $table = $section->addTable(array(
                'cellMargin' => 50,
                'width' => 5000,
                'unit' => 'pct',
                'layout' => 'fixed'
            ));
            $font = array(
                'name' => 'Calibri',
                'size' => 9
            );
            foreach ($value['items'] ?? $value as $bar) {
                $filled = ($bar['max'] ?? 0) > 0 ? (int) round(24 * min(1, $bar['value'] / $bar['max'])) : 0;
                $color = ltrim(isset($bar['tone']) ? self::tone($bar['tone'], '#'.$brand)[0] : ($bar['color'] ?? '#'.$brand), '#');
                $table->addRow(null, array(
                    'cantSplit' => true
                ));
                $table->addCell(3300)->addText((string) $bar['label'], $font, array(
                    'keepNext' => true,
                    'spaceAfter' => 0
                ));
                $run = $table->addCell(4700)->addTextRun(array(
                    'keepNext' => true,
                    'spaceAfter' => 0
                ));
                $run->addText(str_repeat("\u{2588}", $filled), array(
                    'name' => 'Calibri',
                    'size' => 9,
                    'color' => $color
                ));
                $run->addText(str_repeat("\u{2588}", 24 - $filled), array(
                    'name' => 'Calibri',
                    'size' => 9,
                    'color' => 'E6EAEE'
                ));
                $table->addCell(1600)->addText((string) ($bar['text'] ?? ''), $font, array(
                    'alignment' => 'right',
                    'keepNext' => true,
                    'spaceAfter' => 0
                ));
            }
            if (($value['max_label'] ?? '') !== '') {
                $table->addRow();
                $table->addCell(3300);
                $table->addCell(4700)->addText($value['max_label'], array(
                    'name' => 'Calibri',
                    'size' => 7.5,
                    'color' => '55636F'
                ), array(
                    'alignment' => 'right',
                    'spaceAfter' => 0
                ));
                $table->addCell(1600);
            }
            $section->addTextBreak();
            return;
        }

        if ($block['kind'] === 'lead') {
            $run = $section->addTextRun(array(
                'spaceAfter' => 120
            ));
            if ($value['label'] !== '') {
                $run->addText($value['label'].' ', array(
                    'bold' => true
                ));
            }
            $run->addText($value['text']);
            return;
        }

        if ($block['kind'] === 'table' || $block['kind'] === 'register') {
            self::docx_table($section, $value['head'], $value['widths'], $value['rows'], $brand, !empty($value['total']), !empty($value['small']), false, $value['anchors'] ?? array(), (int) ($value['link_col'] ?? 0));
            return;
        }

        if ($block['kind'] === 'finding') {
            self::docx_finding($section, $block);
            return;
        }

        if ($block['kind'] === 'domain_head') {
            $meta = array();
            foreach ($value['counts'] as $rating => $count) {
                if ($count > 0) {
                    $meta[] = $count.' '.strtolower($rating);
                }
            }
            $section->addTitle(($block['number'] ?? '') !== '' ? $block['number'].' '.$value['title'] : $value['title'], 2);
            if ($value['rating'] !== null || count($meta) > 0) {
                $run = $section->addTextRun(array(
                    'spaceAfter' => 100,
                    'keepNext' => true
                ));
                if ($value['rating'] !== null) {
                    $run->addText($value['rating'], array(
                        'name' => 'Calibri',
                        'size' => 8.5,
                        'bold' => true,
                        'color' => ltrim(self::RATING_COLORS[$value['rating']] ?? '#55636F', '#')
                    ));
                }
                if (count($meta) > 0) {
                    $run->addText(($value['rating'] !== null ? '  ·  ' : '').implode(' · ', $meta), array(
                        'name' => 'Calibri',
                        'size' => 8.5,
                        'color' => '55636F'
                    ));
                }
            }
            return;
        }

        if ($block['kind'] === 'posture') {
            $color = ltrim(self::POSTURE_COLORS[$value['rating']] ?? '#55636F', '#');
            $white = array(
                'name' => 'Calibri',
                'color' => 'FFFFFF'
            );
            $table = $section->addTable(array(
                'cellMargin' => 160,
                'width' => 5000,
                'unit' => 'pct'
            ));
            $table->addRow(null, array(
                'cantSplit' => true
            ));
            $cell = $table->addCell(9600, array(
                'bgColor' => $color
            ));
            $cell->addText('OVERALL RISK POSTURE', array_merge($white, array(
                'size' => 8,
                'spacing' => 30
            )), array(
                'keepNext' => true
            ));
            $cell->addText($value['rating'], array_merge($white, array(
                'size' => 20,
                'bold' => true
            )), array(
                'keepNext' => true
            ));
            $cell->addText($value['headline'], array_merge($white, array(
                'size' => 11
            )));
            $section->addTextBreak();
            return;
        }

        if ($block['kind'] === 'callout') {
            $table = $section->addTable(array(
                'cellMargin' => 140,
                'width' => 5000,
                'unit' => 'pct'
            ));
            $table->addRow(null, array(
                'cantSplit' => true
            ));
            $cell = $table->addCell(9600, array(
                'bgColor' => 'F3F6F9',
                'borderLeftSize' => 24,
                'borderLeftColor' => $brand
            ));
            $cell->addText($value['title'], array(
                'name' => 'Calibri',
                'size' => 11,
                'bold' => true,
                'color' => $brand
            ), array(
                'spaceAfter' => 80,
                'keepNext' => true
            ));
            foreach (array_values($value['items']) as $index => $item) {
                $cell->addText(($index + 1).'. '.$item, null, array(
                    'spaceAfter' => 60,
                    'indentation' => array(
                        'left' => 280,
                        'hanging' => 280
                    )
                ));
            }
            $section->addTextBreak();
            return;
        }

        if ($block['kind'] === 'dashboard') {
            $t = $value['totals'];
            $cards = array(
                array(
                    (string) (int) $value['total'],
                    'Findings across '.(int) $value['areas'].' areas',
                    '1D2733'
                )
            );
            foreach (self::SEVERITY_COLORS as $rating => $color) {
                $cards[] = array(
                    (string) (int) $t[$rating],
                    $rating,
                    ltrim($color, '#')
                );
            }
            $table = $section->addTable(array(
                'borderSize' => 4,
                'borderColor' => 'D6DCE3',
                'cellMargin' => 120,
                'width' => 5000,
                'unit' => 'pct',
                'layout' => 'fixed'
            ));
            $table->addRow(null, array(
                'cantSplit' => true
            ));
            foreach ($cards as $card) {
                $cell = $table->addCell(2400);
                $cell->addText($card[0], array(
                    'name' => 'Calibri',
                    'size' => 20,
                    'bold' => true,
                    'color' => $card[2]
                ), array(
                    'keepNext' => true
                ));
                $cell->addText($card[1], array(
                    'name' => 'Calibri',
                    'size' => 8.5,
                    'color' => '55636F'
                ), array(
                    'keepNext' => true
                ));
            }
            $sum = $t['High'] + $t['Medium'] + $t['Low'];
            $bar = $section->addTable(array(
                'cellMargin' => 0,
                'width' => 5000,
                'unit' => 'pct',
                'layout' => 'fixed'
            ));
            $bar->addRow(140, array(
                'exactHeight' => true
            ));
            foreach (self::SEVERITY_COLORS as $rating => $color) {
                if ($t[$rating] > 0) {
                    $bar->addCell((int) round(9600 * $t[$rating] / $sum), array(
                        'bgColor' => ltrim($color, '#')
                    ));
                }
            }
            if ($sum === 0) {
                $bar->addCell(9600, array(
                    'bgColor' => 'E3E8EE'
                ));
            }
            $section->addTextBreak();
            return;
        }

        if ($block['kind'] === 'heatmap') {
            $axis = array(
                'name' => 'Calibri',
                'size' => 8,
                'color' => '55636F'
            );
            $center = array(
                'alignment' => 'center',
                'spaceAfter' => 0,
                'keepNext' => true
            );
            $table = $section->addTable(array(
                'borderSize' => 12,
                'borderColor' => 'FFFFFF',
                'cellMargin' => 40,
                'layout' => 'fixed'
            ));
            for ($impact = 5; $impact >= 1; $impact--) {
                $table->addRow(560, array(
                    'cantSplit' => true
                ));
                $table->addCell(900, array(
                    'valign' => 'center'
                ))->addText(($impact === 3 ? 'Impact ' : '').$impact, $axis, $center);
                for ($likelihood = 1; $likelihood <= 5; $likelihood++) {
                    $score = $impact * $likelihood;
                    $count = $value[$likelihood.'-'.$impact] ?? 0;
                    $table->addCell(880, array(
                        'bgColor' => $score >= 15 ? 'F6C9C4' : ($score >= 8 ? 'FBE2B6' : 'D3EEDD'),
                        'valign' => 'center'
                    ))->addText($count > 0 ? (string) (int) $count : '', array(
                        'name' => 'Calibri',
                        'size' => 11,
                        'bold' => true
                    ), $center);
                }
            }
            $table->addRow(null, array(
                'cantSplit' => true
            ));
            $table->addCell(900);
            for ($likelihood = 1; $likelihood <= 5; $likelihood++) {
                $table->addCell(880)->addText((string) $likelihood, $axis, $center);
            }
            $table->addRow(null, array(
                'cantSplit' => true
            ));
            $table->addCell(900);
            $table->addCell(4400, array(
                'gridSpan' => 5
            ))->addText('Likelihood', $axis, array(
                'alignment' => 'center',
                'spaceAfter' => 0
            ));
            $section->addTextBreak();
            return;
        }

        if ($block['kind'] === 'roadmap') {
            $colors = array(
                '#B42318',
                '#B54708',
                '#075985',
                '#3E5C76'
            );
            $index = 0;
            foreach ($value as $phase => $items) {
                $color = ltrim($colors[$index++] ?? '#075985', '#');
                if (count($items) === 0) {
                    continue;
                }
                $table = $section->addTable(array(
                    'borderSize' => 4,
                    'borderColor' => 'D6DCE3',
                    'cellMargin' => 100,
                    'width' => 5000,
                    'unit' => 'pct',
                    'layout' => 'fixed'
                ));
                $table->addRow(null, array(
                    'tblHeader' => true,
                    'cantSplit' => true
                ));
                $table->addCell(9600, array(
                    'gridSpan' => 2,
                    'bgColor' => $color
                ))->addText($phase, array(
                    'name' => 'Calibri',
                    'size' => 10.5,
                    'bold' => true,
                    'color' => 'FFFFFF'
                ), array(
                    'keepNext' => true
                ));
                foreach ($items as $item) {
                    $table->addRow(null, array(
                        'cantSplit' => true
                    ));
                    $cell = $table->addCell(7900);
                    $cell->addText($item['title'], array(
                        'name' => 'Calibri',
                        'size' => 10,
                        'bold' => true
                    ));
                    $cell->addText($item['detail'], array(
                        'size' => 9.5
                    ));
                    $table->addCell(1700)->addText(implode(', ', $item['refs']), array(
                        'name' => 'Calibri',
                        'size' => 8,
                        'color' => '55636F'
                    ), array(
                        'alignment' => 'right'
                    ));
                }
                $section->addTextBreak();
            }
            return;
        }

        if ($block['kind'] === 'signature') {
            $table = $section->addTable(array(
                'cellMargin' => 80
            ));
            $pairs = array(
                array(
                    $value['name'],
                    'Name',
                    $value['title'],
                    'Title'
                ),
                array(
                    '',
                    'Signature',
                    '',
                    'Date'
                )
            );
            foreach ($pairs as $pair) {
                $table->addRow(700);
                for ($i = 0; $i <= 2; $i += 2) {
                    $cell = $table->addCell(4800, array(
                        'valign' => 'bottom'
                    ));
                    $cell->addText($pair[$i], array(
                        'name' => 'Calibri'
                    ));
                    $cell->addText($pair[$i + 1], array(
                        'name' => 'Calibri',
                        'size' => 8.5,
                        'color' => '55636F'
                    ), array(
                        'borderTopSize' => 4,
                        'borderTopColor' => '1D2733'
                    ));
                }
            }
        }
    }

    private static function docx_table($section, array $head, array $widths, array $rows, string $brand, bool $total, bool $small, bool $whole = false, array $anchors = array(), int $link_col = 0): void
    {
        $rows = array_values($rows);
        $last = count($rows) - 1;
        $whole = $whole || count($rows) <= self::KEEP_WHOLE_ROWS;
        $numeric = self::numeric_columns($rows, count($head));

        $size = $small ? 8 : 9;
        $table = $section->addTable(array(
            'borderBottomSize' => 4,
            'borderBottomColor' => 'D6DCE3',
            'borderInsideHSize' => 4,
            'borderInsideHColor' => 'D6DCE3',
            'cellMargin' => 80,
            'width' => 5000,
            'unit' => 'pct',
            'layout' => 'fixed'
        ));

        $table->addRow(null, array(
            'tblHeader' => true,
            'cantSplit' => true
        ));

        foreach ($head as $index => $label) {
            $table->addCell((int) round(9600 * $widths[$index] / 100), array(
                'bgColor' => $brand,
                'valign' => 'bottom'
            ))->addText($label, array(
                'name' => 'Calibri',
                'size' => $size,
                'bold' => true,
                'color' => 'FFFFFF'
            ), array(
                'keepNext' => true,
                'spaceAfter' => 0,
                'alignment' => !empty($numeric[$index]) ? 'right' : 'left'
            ));
        }

        foreach ($rows as $index => $row) {
            $table->addRow(null, array(
                'cantSplit' => true
            ));
            $keep = $index < $last && ($whole || $index < self::KEEP_LEAD_ROWS - 1 || $index >= $last - 2);
            foreach (array_values($row) as $col => $cell) {
                $font = array(
                    'name' => 'Calibri',
                    'size' => $size,
                    'bold' => $total && $index === $last
                );
                $paragraph = array(
                    'keepNext' => $keep,
                    'spaceAfter' => 0,
                    'alignment' => !empty($numeric[$col]) ? 'right' : 'left'
                );
                $container = $table->addCell((int) round(9600 * $widths[$col] / 100), $index % 2 === 1 && !($total && $index === $last)
                    ? array(
                        'bgColor' => 'F5F7F9'
                    )
                    : null);
                if ($col === $link_col && ($anchors[$index] ?? '') !== '' && (string) $cell !== '') {
                    $container->addLink(self::word_anchor($anchors[$index]), (string) $cell, array_merge($font, array(
                        'bold' => true,
                        'color' => $brand
                    )), $paragraph, true);
                    continue;
                }
                $container->addText((string) $cell, $font, $paragraph);
            }
        }

        $section->addTextBreak();
    }

    /** Word bookmark names: a letter first, then letters, digits and underscores. */
    private static function word_anchor($anchor): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $anchor);

        return substr(preg_match('/^[A-Za-z]/', $name) === 1 ? $name : 'a_'.$name, 0, 40);
    }

    /** Text into a Word container: paragraphs, with "- " lines (or a list of strings) as bullets. */
    private static function docx_paragraphs($container, $text, ?array $font, $paragraph): void
    {
        foreach (self::chunks($text) as $chunk) {
            if (isset($chunk['bullets'])) {
                foreach ($chunk['bullets'] as $bullet) {
                    $container->addListItem($bullet, 0, $font, null, $paragraph);
                }
                continue;
            }
            foreach ($chunk['lines'] as $line) {
                $container->addText($line, $font, $paragraph);
            }
        }
    }

    /** A finding as a bordered table: header, details line, then the labelled rows and sections. */
    private static function docx_finding($section, array $block): void
    {
        $value = $block['value'];
        $color = ltrim(self::SEVERITY_COLORS[$value['severity'] ?? ''] ?? '#55636F', '#');
        $grey = array(
            'name' => 'Calibri',
            'size' => 8.5,
            'color' => '55636F'
        );

        if (isset($block['anchor'])) {
            $section->addBookmark(self::word_anchor($block['anchor']));
        }

        $table = $section->addTable(array(
            'cellMargin' => 90,
            'width' => 5000,
            'unit' => 'pct',
            'layout' => 'fixed',
            'borderTopSize' => 4,
            'borderTopColor' => 'D6DCE3',
            'borderRightSize' => 4,
            'borderRightColor' => 'D6DCE3',
            'borderBottomSize' => 4,
            'borderBottomColor' => 'D6DCE3',
            'borderLeftSize' => 24,
            'borderLeftColor' => $color
        ));

        $table->addRow(null, array(
            'cantSplit' => true
        ));
        $head = $table->addCell(9600, array(
            'gridSpan' => 2
        ));

        if (($value['ref'] ?? '') !== '') {
            $head->addText($value['ref'], $grey, array(
                'keepNext' => true,
                'spaceAfter' => 0
            ));
        }

        $run = $head->addTextRun(array(
            'keepNext' => true,
            'spaceAfter' => 60
        ));
        $run->addText(($value['heading'] ?? $value['title'] ?? '').'    ', array(
            'name' => 'Calibri',
            'size' => 11.5,
            'bold' => true
        ));

        if (($value['rating'] ?? '') !== '') {
            $run->addText($value['rating'], array(
                'name' => 'Calibri',
                'size' => 9,
                'bold' => true,
                'color' => $color
            ));
        }

        if (count($value['meta'] ?? array()) > 0) {
            $meta = $head->addTextRun(array(
                'keepNext' => true,
                'spaceAfter' => 40,
                'borderTopSize' => 4,
                'borderTopColor' => 'EEF1F4'
            ));
            foreach (array_values($value['meta']) as $index => $pair) {
                $meta->addText(($index > 0 ? '   |   ' : '').$pair[0].' ', $grey);
                $meta->addText((string) $pair[1], array(
                    'name' => 'Calibri',
                    'size' => 8.5,
                    'color' => '1D2733'
                ));
            }
        }

        foreach ($value['rows'] ?? array() as $row) {
            $table->addRow(null, array(
                'cantSplit' => true
            ));
            $table->addCell(2700)->addText($row[0], array(
                'name' => 'Calibri',
                'size' => 9,
                'color' => '55636F'
            ));
            $cell = $table->addCell(6900);
            foreach (preg_split("/\n/", (string) $row[1]) as $line) {
                $cell->addText($line, array(
                    'size' => 10
                ));
            }
        }

        foreach ($value['sections'] ?? array() as $part) {
            $table->addRow(null, array(
                'cantSplit' => self::text_length($part[1]) < 700
            ));
            $cell = $table->addCell(9600, array(
                'gridSpan' => 2
            ));
            $cell->addText($part[0], array(
                'name' => 'Calibri',
                'size' => 8,
                'bold' => true,
                'allCaps' => true,
                'color' => '55636F'
            ), array(
                'keepNext' => true,
                'spaceAfter' => 30
            ));
            self::docx_paragraphs($cell, $part[1], array(
                'size' => 10
            ), array(
                'spaceAfter' => 60,
                'lineHeight' => 1.15
            ));
        }

        $section->addTextBreak();
    }

}
