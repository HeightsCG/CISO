<?php
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;

/**
 * Draws an assembled report (see ReportBuilder::assemble) as a PDF or a Word
 * document. Both follow the same structure as the consultancy's sample risk
 * assessment: cover, document control, contents, executive summary,
 * background and scope, results by domain with findings, risk register,
 * ongoing review, management acknowledgment and appendices. Sections with
 * nothing in them are left out rather than printed empty.
 *
 * The consultancy's own logo and brand colour are used, so each tenant's
 * reports carry their own identity.
 */
class ReportRenderer {

    /** Blocks short enough to keep on the same page as the heading above them. */
    const KEEP_WITH_HEADING = array(
        'text',
        'lead',
        'note',
        'finding'
    );

    const CSF_PREFIXES = array(
        'GV' => 'Govern',
        'ID' => 'Identify',
        'PR' => 'Protect',
        'DE' => 'Detect',
        'RS' => 'Respond',
        'RC' => 'Recover'
    );

    /** Returns array('bytes', 'file_name', 'extension', 'content_type'). */
    public static function render(array $r, string $format): array
    {
        $client = ReportBuilder::plain($r['client']['company_name'] ?? 'Client');
        $title = ReportBuilder::plain($r['report']['report_title']);
        $base = preg_replace('/[^A-Za-z0-9 ,.&()\'-]/', '', $client.' - '.$title.' v'.$r['report']['report_version']);

        if ($format === 'docx') {
            return array(
                'bytes' => self::docx($r),
                'file_name' => $base.'.docx',
                'extension' => 'docx',
                'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );
        }

        return array(
            'bytes' => self::pdf($r),
            'file_name' => $base.'.pdf',
            'extension' => 'pdf',
            'content_type' => 'application/pdf'
        );
    }

    /* ------------------------------------------------------------- content */

    /**
     * The report as an ordered outline both formats walk through: headings,
     * paragraphs, key-value lists, tables and finding blocks. Building it once
     * keeps the PDF and the Word document identical in content.
     */
    private static function outline(array $r): array
    {
        $report = $r['report'];
        $client = ReportBuilder::plain($r['client']['company_name'] ?? '');
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
            if (ReportBuilder::plain($value) !== '') {
                $add('text', ReportBuilder::plain($value));
            }
        };

        $s = $section('Executive summary');
        $sub = 0;

        if (ReportBuilder::plain($report['purpose_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Purpose');
            $text($report['purpose_text']);
        }

        if (ReportBuilder::plain($report['conclusion_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Overall conclusion');
            $text($report['conclusion_text']);
        }

        if (count($r['domains']) > 0) {
            $add('h2', $s.'.'.(++$sub).' Results by domain');
            $rows = array();
            foreach ($r['domains'] as $domain) {
                $rows[] = array(
                    ReportBuilder::plain($domain['domain_name']),
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

        $csf_rows = array();
        foreach ($r['csf'] as $row) {
            if ($row['current_tier'] !== null || $row['target_tier'] !== null || ReportBuilder::plain($row['main_gaps']) !== '') {
                $csf_rows[] = array(
                    $row['csf_function'],
                    self::tier($row['current_tier']),
                    self::tier($row['target_tier']),
                    ReportBuilder::plain($row['main_gaps'])
                );
            }
        }

        if (count($csf_rows) > 0) {
            $add('h2', $s.'.'.(++$sub).' NIST CSF 2.0 profile');
            $add('table', array(
                'head' => array(
                    'Function',
                    'Current tier',
                    'Target tier',
                    'Main gaps'
                ),
                'widths' => array(
                    15,
                    19,
                    19,
                    47
                ),
                'rows' => $csf_rows
            ));
            $add('note', 'Tiers: 1 Partial, 2 Risk Informed, 3 Repeatable, 4 Adaptive. Target tiers were agreed with management and reflect the organization\'s size and risk.');
        }

        if (count($r['findings_ranked']) > 0) {
            $add('h2', $s.'.'.(++$sub).' Summary of findings');
            $rows = array();
            foreach ($r['findings_ranked'] as $finding) {
                $rows[] = array(
                    $finding['finding_ref'],
                    ReportBuilder::plain($finding['finding_title']),
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

        if (ReportBuilder::plain($report['background_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Background');
            $text($report['background_text']);
        }

        $add('h2', $s.'.'.(++$sub).' Scope');
        $text($report['scope_text']);

        if (count($r['domains']) > 0) {
            $rows = array();
            foreach ($r['domains'] as $domain) {
                $rows[] = array(
                    ReportBuilder::plain($domain['domain_name']),
                    ReportBuilder::plain($domain['areas_reviewed']),
                    ReportBuilder::plain($domain['csf_refs'])
                );
            }
            $add('table', array(
                'head' => array(
                    'Domain',
                    'Areas reviewed',
                    'NIST CSF 2.0'
                ),
                'widths' => array(
                    27,
                    55,
                    18
                ),
                'rows' => $rows
            ));
        }

        $text($report['out_of_scope_text']);

        if (ReportBuilder::plain($report['approach_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Approach');
            $text($report['approach_text']);
        }

        if (ReportBuilder::plain($report['methodology_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Rating methodology');
            $text($report['methodology_text']);
        }

        if (ReportBuilder::plain($report['limitations_text']) !== '') {
            $add('h2', $s.'.'.(++$sub).' Limitations and responsibilities');
            $text($report['limitations_text']);
        }

        $s = $section('Assessment results by domain');
        $add('text', 'For each domain we summarize what was reviewed and what is working, followed by any findings. Each finding describes what we observed, the risk it creates, our recommendation and management\'s response.');
        $sub = 0;

        $domains = $r['domains'];

        if (count($r['unassigned_findings']) > 0) {
            $domains[] = array(
                'domain_name' => 'Other findings',
                'domain_rating' => null,
                'domain_summary' => '',
                'findings' => $r['unassigned_findings']
            );
        }

        foreach ($domains as $domain) {
            $sub++;
            $contents[] = '     '.$s.'.'.$sub.'  '.ReportBuilder::plain($domain['domain_name']);
            $add('h2', $s.'.'.$sub.' '.ReportBuilder::plain($domain['domain_name']));

            $summary = ReportBuilder::plain($domain['domain_summary'] ?? '');
            if (($domain['domain_rating'] ?? null) !== null || $summary !== '') {
                $add('lead', array(
                    'label' => ($domain['domain_rating'] ?? null) !== null ? 'Overall: '.$domain['domain_rating'].'.' : '',
                    'text' => $summary
                ));
            }

            if (count($domain['findings']) === 0 && $summary === '') {
                $add('text', 'No findings were identified in this domain.');
            }

            foreach ($domain['findings'] as $finding) {
                $add('finding', self::finding_block($finding));
            }
        }

        if (count($r['risks']) > 0) {
            $s = $section('Risk register');
            $add('text', 'The register records each identified risk, its domain, the threats that give rise to it, its rating and the treatment decision.'.(count($r['threats']) > 0 ? ' Threat references are listed in the threat analysis appendix.' : ''));
            $names = array();
            foreach ($r['domains'] as $domain) {
                $names[(int) $domain['id']] = ReportBuilder::plain($domain['domain_name']);
            }
            $rows = array();
            foreach ($r['risks_ranked'] as $risk) {
                $rows[] = array(
                    $risk['risk_ref'],
                    ReportBuilder::plain($risk['risk_text']),
                    $names[(int) $risk['domain_id']] ?? '',
                    ReportBuilder::plain($risk['threat_refs']),
                    (string) $risk['likelihood'],
                    (string) $risk['impact'],
                    (string) $risk['score'],
                    ReportBuilder::plain($risk['treatment'])
                );
            }
            $add('table', array(
                'head' => array(
                    'Ref.',
                    'Risk',
                    'Domain',
                    'Threats',
                    'L',
                    'I',
                    'Score',
                    'Treatment'
                ),
                'widths' => array(
                    7,
                    29,
                    14,
                    10,
                    5,
                    5,
                    8,
                    22
                ),
                'rows' => $rows,
                'small' => true
            ));
            $add('note', 'L = likelihood, I = impact, each rated 1 to 5. Ratings: High 15 to 25, Medium 8 to 14, Low 1 to 7.');
        }

        if (ReportBuilder::plain($report['review_text']) !== '') {
            $section('Ongoing review');
            $text($report['review_text']);
        }

        $section('Management acknowledgment');
        $add('text', 'I have reviewed this risk assessment, including the findings, the treatment decisions and the remediation dates set out above.');
        $add('signature', array(
            'name' => ReportBuilder::plain($report['signoff_name']),
            'title' => ReportBuilder::plain($report['signoff_title'])
        ));

        $appendix = 0;
        $letter = function () use (&$appendix) {
            return chr(65 + $appendix++);
        };

        if (count($r['xrefs']) > 0) {
            $l = $letter();
            $contents[] = $l.'  Appendix '.$l.'. Regulatory cross-reference';
            $add('h1', 'Appendix '.$l.'. Regulatory cross-reference');
            $rows = array();
            foreach ($r['xrefs'] as $xref) {
                $rows[] = array(
                    ReportBuilder::plain($xref['xref_section']),
                    ReportBuilder::plain($xref['requirement']),
                    ReportBuilder::plain($xref['where_addressed'])
                );
            }
            $add('table', array(
                'head' => array(
                    'Section',
                    'Requirement',
                    'Where addressed'
                ),
                'widths' => array(
                    18,
                    55,
                    27
                ),
                'rows' => $rows
            ));
        }

        if (count($r['assets']) > 0) {
            $l = $letter();
            $contents[] = $l.'  Appendix '.$l.'. Systems and data inventory';
            $add('h1', 'Appendix '.$l.'. Systems and data inventory');
            $rows = array();
            foreach ($r['assets'] as $asset) {
                $rows[] = array(
                    ReportBuilder::plain($asset['asset_name']),
                    ReportBuilder::plain($asset['asset_kind']),
                    ReportBuilder::plain($asset['asset_owner']),
                    ReportBuilder::plain($asset['data_held']),
                    $asset['sensitivity']
                );
            }
            $add('table', array(
                'head' => array(
                    'System or service',
                    'Type',
                    'Owner',
                    'Sensitive information held',
                    'Sensitivity'
                ),
                'widths' => array(
                    24,
                    14,
                    17,
                    32,
                    13
                ),
                'rows' => $rows
            ));
        }

        if (count($r['threats']) > 0) {
            $l = $letter();
            $contents[] = $l.'  Appendix '.$l.'. Threat analysis';
            $add('h1', 'Appendix '.$l.'. Threat analysis');
            $rows = array();
            foreach ($r['threats'] as $threat) {
                $rows[] = array(
                    $threat['threat_ref'],
                    ReportBuilder::plain($threat['threat_name']),
                    ReportBuilder::plain($threat['threat_description']),
                    ReportBuilder::plain($threat['parties_affected'])
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

        if (count($r['scan_rows']) > 0 || ReportBuilder::plain($report['scan_text']) !== '') {
            $l = $letter();
            $contents[] = $l.'  Appendix '.$l.'. External vulnerability scan summary';
            $add('h1', 'Appendix '.$l.'. External vulnerability scan summary');
            $text($report['scan_text']);
            if (count($r['scan_rows']) > 0) {
                $rows = array();
                foreach ($r['scan_rows'] as $scan) {
                    $rows[] = array(
                        $scan['scan_rating'],
                        (string) $scan['issue_count'],
                        ReportBuilder::plain($scan['scan_summary']),
                        ReportBuilder::plain($scan['disposition'])
                    );
                }
                $add('table', array(
                    'head' => array(
                        'Rating',
                        'Issues',
                        'Summary',
                        'Disposition'
                    ),
                    'widths' => array(
                        12,
                        10,
                        50,
                        28
                    ),
                    'rows' => $rows
                ));
            }
        }

        if (count($r['findings']) > 0) {
            $l = $letter();
            $contents[] = $l.'  Appendix '.$l.'. NIST CSF 2.0 mapping of findings';
            $add('h1', 'Appendix '.$l.'. NIST CSF 2.0 mapping of findings');
            $rows = array();
            foreach ($r['findings'] as $finding) {
                $rows[] = array(
                    $finding['finding_ref'],
                    ReportBuilder::plain($finding['finding_title']),
                    self::csf_functions($finding['csf_refs']),
                    ReportBuilder::plain($finding['csf_refs'])
                );
            }
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
            'Management response' => ($finding['response_status'] === 'Pending' ? '' : $finding['response_status'].'. ').ReportBuilder::plain($finding['management_response']),
            'Owner / target' => trim(ReportBuilder::plain($finding['response_owner']).($finding['target_date'] ? ' / '.self::date($finding['target_date']) : ''), ' /'),
            'NIST CSF 2.0' => $finding['csf_refs']
        );

        foreach ($pairs as $label => $value) {
            if (ReportBuilder::plain($value) !== '') {
                $rows[] = array(
                    $label,
                    ReportBuilder::plain($value)
                );
            }
        }

        return array(
            'title' => trim($finding['finding_ref'].' '.ReportBuilder::plain($finding['finding_title'])),
            'rating' => $finding['rating'].' ('.$finding['score'].')',
            'rows' => $rows
        );
    }

    private static function tier($tier): string
    {
        return $tier === null ? '' : (int) $tier.' '.(ReportBuilder::TIERS[(int) $tier] ?? '');
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

        if (ReportBuilder::plain($report['regulatory_reference']) !== '') {
            $rows['Regulatory reference'] = ReportBuilder::plain($report['regulatory_reference']);
        }

        $rows['Version'] = $report['report_version'];
        $rows['Prepared by'] = ReportBuilder::plain($r['company']['company_name'] ?? '');

        return $rows;
    }

    private static function brand(array $r): string
    {
        $color = (string) ($r['company']['brand_color'] ?? '');

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#1F3A5F';
    }

    /** The company logo as raw bytes and type, or null. Fetched once per render. */
    private static function logo(array $r)
    {
        $url = (string) ($r['company']['logo_path'] ?? '');

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

    private static function pdf(array $r): string
    {
        $outline = self::outline($r);
        $brand = self::brand($r);
        $logo = self::logo($r);
        $firm = ReportBuilder::plain($r['company']['company_name'] ?? '');
        $title = ReportBuilder::plain($r['report']['report_title']);
        $e = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };

        $fonts = Main::lib_path().'/Fonts';
        $logo_html = $logo === null
            ? '<div class="firm">'.$e($firm).'</div>'
            : '<img class="logo" src="data:'.$logo['type'].';base64,'.base64_encode($logo['bytes']).'">';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'@font-face{font-family:"Sans";font-weight:normal;src:url("file://'.$fonts.'/source-sans-3-400-normal.ttf")}'
            .'@font-face{font-family:"Sans";font-weight:bold;src:url("file://'.$fonts.'/source-sans-3-600-normal.ttf")}'
            .'@font-face{font-family:"Serif";font-weight:normal;src:url("file://'.$fonts.'/source-serif-4-400-normal.ttf")}'
            .'@font-face{font-family:"Serif";font-weight:bold;src:url("file://'.$fonts.'/source-serif-4-600-normal.ttf")}'
            .'@font-face{font-family:"Serif";font-style:italic;src:url("file://'.$fonts.'/source-serif-4-400-italic.ttf")}'
            .'@page{margin:78pt 64pt 70pt 64pt}'
            .'body{font-family:"Serif";font-size:10.5pt;line-height:1.45;color:#1d2733}'
            .'h1{font-family:"Sans";font-weight:bold;font-size:20pt;color:'.$brand.';border-bottom:1.2pt solid '.$brand.';padding-bottom:6pt;margin:0 0 14pt 0;page-break-before:always}'
            .'h2{font-family:"Sans";font-weight:bold;font-size:13pt;color:#1d2733;margin:16pt 0 6pt 0}'
            .'p{margin:0 0 8pt 0}'
            .'ul{margin:0 0 8pt 0;padding-left:16pt}li{margin-bottom:3pt}'
            .'table{width:100%;border-collapse:collapse;margin:4pt 0 10pt 0;font-family:"Sans";font-size:9pt}'
            .'th{background:'.$brand.';color:#fff;text-align:left;font-weight:bold;padding:4pt 5pt}'
            .'td{border-bottom:0.5pt solid #d6dce3;padding:4pt 5pt;vertical-align:top}'
            .'tr.total td{font-weight:bold;border-top:0.8pt solid #1d2733}'
            .'table.small{font-size:8.2pt}'
            .'.note{font-family:"Sans";font-size:8.5pt;color:#55636f}'
            .'.lead b{font-family:"Serif";font-weight:bold}'
            .'.finding{margin:12pt 0 14pt 0;page-break-inside:avoid}'
            .'.finding .head{border-top:0.8pt solid #1d2733;padding:5pt 0 6pt 0;font-family:"Sans";font-weight:bold;font-size:10.5pt}'
            .'.finding .head span{float:right;font-weight:normal}'
            .'.finding table{font-family:"Serif";font-size:10pt;margin:0}'
            .'.finding td{border:none;padding:3pt 4pt 3pt 0}'
            .'.finding td.label{width:30%;font-family:"Sans";font-size:9pt;color:#55636f}'
            .'.cover{page-break-after:always}'
            .'.cover .logo{height:42pt;margin-top:8pt}'
            .'.cover .firm{font-family:"Sans";font-weight:bold;font-size:16pt;color:'.$brand.';margin-top:8pt}'
            .'.cover .kicker{margin-top:110pt;font-family:"Sans";font-size:8.5pt;letter-spacing:2pt;color:#55636f;text-transform:uppercase}'
            .'.cover .title{font-family:"Sans";font-weight:bold;font-size:28pt;color:'.$brand.';margin:6pt 0 10pt 0;padding-bottom:14pt;border-bottom:0.8pt solid #1d2733}'
            .'.cover .for{font-family:"Sans";font-size:13pt;margin-bottom:70pt}'
            .'.cover table{font-family:"Sans";font-size:9.5pt;width:85%}'
            .'.cover td{border:none;padding:3pt 0}'
            .'.cover td.label{color:#55636f;width:30%}'
            .'.cover .confidential{margin-top:28pt;border-top:0.5pt solid #b8c2cd;padding-top:8pt;font-size:8.5pt;color:#55636f}'
            .'.contents p{margin:0 0 5pt 0;font-family:"Sans";white-space:pre}'
            .'.sign{margin-top:40pt;width:100%}.sign td{border:none;padding:26pt 12pt 2pt 0;width:50%}'
            .'.sign .line{border-top:0.5pt solid #1d2733;padding-top:3pt;font-family:"Sans";font-size:8.5pt;color:#55636f}'
            .'.first{page-break-before:avoid}'
            .'</style></head><body>';

        $html .= '<div class="cover">'.$logo_html
            .'<div class="kicker">Confidential</div>'
            .'<div class="title">'.$e($title).'</div>'
            .'<div class="for">Prepared for '.$e($outline['client']).'</div><table>';

        foreach (self::cover_rows($r) as $label => $value) {
            $html .= '<tr><td class="label">'.$e($label).'</td><td>'.$e($value).'</td></tr>';
        }

        $html .= '</table><div class="confidential">Confidential. This report contains sensitive information about the security of '.$e($outline['client']).'\'s information systems. It is intended solely for the use of '.$e($outline['client']).' management and its board, and should not be distributed to others without the written consent of '.$e($outline['client']).'.</div></div>';

        $html .= self::pdf_front_matter($r, $e);

        $html .= '<h1>Contents</h1><div class="contents">';
        foreach ($outline['contents'] as $line) {
            $html .= '<p>'.$e($line).'</p>';
        }
        $html .= '</div>';

        $held = '';

        foreach ($outline['blocks'] as $block) {

            if ($block['kind'] === 'h2') {
                $held .= self::pdf_block($block, $e);
                continue;
            }

            if ($held !== '' && in_array($block['kind'], self::KEEP_WITH_HEADING, true)) {
                $html .= '<div style="page-break-inside:avoid">'.$held.self::pdf_block($block, $e).'</div>';
                $held = '';
                continue;
            }

            $html .= $held.self::pdf_block($block, $e);
            $held = '';
        }

        $html .= $held.'</body></html>';

        $options = new Options();
        $options->setChroot(array(
            $fonts
        ));
        $options->setFontDir(sys_get_temp_dir());
        $options->setFontCache(sys_get_temp_dir());
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Serif');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter');
        $dompdf->render();

        $header = ($outline['client'] !== '' ? $outline['client'].' · ' : '').$title;
        $footer = 'Confidential · '.$firm;

        $dompdf->getCanvas()->page_script(function ($page, $total, $canvas, $metrics) use ($header, $footer) {
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
        });

        return $dompdf->output();
    }

    private static function pdf_front_matter(array $r, callable $e): string
    {
        $html = '<h1 class="first">Document control</h1>';

        if (count($r['versions']) > 0) {
            $html .= '<table><tr><th style="width:12%">Version</th><th style="width:18%">Date</th><th>Description</th><th style="width:24%">Author</th></tr>';
            foreach ($r['versions'] as $version) {
                $html .= '<tr><td>'.$e($version['version_label']).'</td><td>'.$e(self::date($version['version_date'])).'</td><td>'.$e(ReportBuilder::plain($version['version_note'])).'</td><td>'.$e(ReportBuilder::plain($version['version_author'])).'</td></tr>';
            }
            $html .= '</table>';
        } else {
            $html .= '<p>Version '.$e($r['report']['report_version']).', '.$e($r['report']['report_stage']).'.</p>';
        }

        if (count($r['distribution']) > 0) {
            $html .= '<h2>Distribution</h2><table><tr><th style="width:32%">Name</th><th style="width:34%">Title</th><th>Organization</th></tr>';
            foreach ($r['distribution'] as $person) {
                $html .= '<tr><td>'.$e(ReportBuilder::plain($person['person_name'])).'</td><td>'.$e(ReportBuilder::plain($person['person_title'])).'</td><td>'.$e(ReportBuilder::plain($person['organisation'])).'</td></tr>';
            }
            $html .= '</table>';
        }

        if (count($r['team']) > 0) {
            $html .= '<h2>Assessment team</h2><table><tr><th style="width:26%">Role</th><th style="width:22%">Name</th><th style="width:22%">Organization</th><th>Responsibility</th></tr>';
            foreach ($r['team'] as $person) {
                $html .= '<tr><td>'.$e(ReportBuilder::plain($person['person_title'])).'</td><td>'.$e(ReportBuilder::plain($person['person_name'])).'</td><td>'.$e(ReportBuilder::plain($person['organisation'])).'</td><td>'.$e(ReportBuilder::plain($person['responsibility'])).'</td></tr>';
            }
            $html .= '</table>';
        }

        return $html;
    }

    private static function pdf_block(array $block, callable $e): string
    {
        $value = $block['value'];

        if ($block['kind'] === 'h1') {
            return '<h1>'.$e($value).'</h1>';
        }

        if ($block['kind'] === 'h2') {
            return '<h2>'.$e($value).'</h2>';
        }

        if ($block['kind'] === 'text') {
            return self::pdf_paragraphs($value, $e);
        }

        if ($block['kind'] === 'note') {
            return '<p class="note">'.$e($value).'</p>';
        }

        if ($block['kind'] === 'lead') {
            return '<p class="lead">'.($value['label'] !== '' ? '<b>'.$e($value['label']).'</b> ' : '').$e($value['text']).'</p>';
        }

        if ($block['kind'] === 'table') {
            $html = '<table class="'.(!empty($value['small']) ? 'small' : '').'"><tr>';
            foreach ($value['head'] as $index => $head) {
                $html .= '<th style="width:'.(int) $value['widths'][$index].'%">'.$e($head).'</th>';
            }
            $html .= '</tr>';
            $last = count($value['rows']) - 1;
            foreach ($value['rows'] as $index => $row) {
                $html .= '<tr'.(!empty($value['total']) && $index === $last ? ' class="total"' : '').'>';
                foreach ($row as $cell) {
                    $html .= '<td>'.$e($cell).'</td>';
                }
                $html .= '</tr>';
            }
            return $html.'</table>';
        }

        if ($block['kind'] === 'finding') {
            $html = '<div class="finding"><div class="head"><span>'.$e($value['rating']).'</span>'.$e($value['title']).'</div><table>';
            foreach ($value['rows'] as $row) {
                $html .= '<tr><td class="label">'.$e($row[0]).'</td><td>'.nl2br($e($row[1])).'</td></tr>';
            }
            return $html.'</table></div>';
        }

        if ($block['kind'] === 'signature') {
            return '<table class="sign"><tr><td><div style="font-family:Sans">'.$e($value['name']).'</div><div class="line">Name</div></td><td><div style="font-family:Sans">'.$e($value['title']).'</div><div class="line">Title</div></td></tr>'
                .'<tr><td><div>&nbsp;</div><div class="line">Signature</div></td><td><div>&nbsp;</div><div class="line">Date</div></td></tr></table>';
        }

        return '';
    }

    /** Blank lines separate paragraphs; runs of lines starting with "- " become bullet lists. */
    private static function pdf_paragraphs(string $text, callable $e): string
    {
        $html = '';

        foreach (preg_split("/\n\s*\n/", trim($text)) as $chunk) {

            $plain = array();
            $bullets = array();

            foreach (preg_split("/\n/", trim($chunk)) as $line) {
                if (preg_match('/^\s*[-•]\s+/', $line) === 1) {
                    if (count($plain) > 0) {
                        $html .= '<p>'.implode('<br>', array_map($e, $plain)).'</p>';
                        $plain = array();
                    }
                    $bullets[] = preg_replace('/^\s*[-•]\s+/', '', $line);
                    continue;
                }
                if (count($bullets) > 0) {
                    $html .= '<ul><li>'.implode('</li><li>', array_map($e, $bullets)).'</li></ul>';
                    $bullets = array();
                }
                $plain[] = $line;
            }

            if (count($plain) > 0) {
                $html .= '<p>'.implode('<br>', array_map($e, $plain)).'</p>';
            }

            if (count($bullets) > 0) {
                $html .= '<ul><li>'.implode('</li><li>', array_map($e, $bullets)).'</li></ul>';
            }
        }

        return $html;
    }

    /* ----------------------------------------------------------------- Word */

    private static function docx(array $r): string
    {
        Settings::setOutputEscapingEnabled(true);

        $outline = self::outline($r);
        $brand = ltrim(self::brand($r), '#');
        $firm = ReportBuilder::plain($r['company']['company_name'] ?? '');
        $title = ReportBuilder::plain($r['report']['report_title']);

        $word = new PhpWord();
        $word->setDefaultFontName('Georgia');
        $word->setDefaultFontSize(10.5);
        $word->addTitleStyle(1, array(
            'name' => 'Calibri',
            'size' => 18,
            'bold' => true,
            'color' => $brand
        ), array(
            'spaceBefore' => 0,
            'spaceAfter' => 200,
            'pageBreakBefore' => true,
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
        $header->addText(($outline['client'] !== '' ? $outline['client'].' · ' : '').$title, $grey, array(
            'alignment' => 'right'
        ));
        $footer = $section->addFooter();
        $footer->addPreserveText('Confidential · '.$firm.'          Page {PAGE} of {NUMPAGES}', $grey, array(
            'alignment' => 'right'
        ));

        $logo = self::logo($r);

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
        $section->addText('Prepared for '.$outline['client'], array(
            'name' => 'Calibri',
            'size' => 13
        ));
        $section->addTextBreak(6);

        $cover = $section->addTable(array(
            'cellMargin' => 40,
            'layout' => 'fixed'
        ));

        foreach (self::cover_rows($r) as $label => $value) {
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

        $section->addTextBreak(2);
        $section->addText('Confidential. This report contains sensitive information about the security of '.$outline['client'].'\'s information systems. It is intended solely for the use of '.$outline['client'].' management and its board, and should not be distributed to others without the written consent of '.$outline['client'].'.', array(
            'size' => 8,
            'color' => '55636F'
        ), array(
            'borderTopSize' => 4,
            'borderTopColor' => 'B8C2CD'
        ));

        $section->addTitle('Document control', 1);

        if (count($r['versions']) > 0) {
            $rows = array();
            foreach ($r['versions'] as $version) {
                $rows[] = array(
                    $version['version_label'],
                    self::date($version['version_date']),
                    ReportBuilder::plain($version['version_note']),
                    ReportBuilder::plain($version['version_author'])
                );
            }
            self::docx_table($section, array(
                'Version',
                'Date',
                'Description',
                'Author'
            ), array(
                12,
                18,
                46,
                24
            ), $rows, $brand, false, false);
        }

        if (count($r['distribution']) > 0) {
            $section->addTitle('Distribution', 2);
            $rows = array();
            foreach ($r['distribution'] as $person) {
                $rows[] = array(
                    ReportBuilder::plain($person['person_name']),
                    ReportBuilder::plain($person['person_title']),
                    ReportBuilder::plain($person['organisation'])
                );
            }
            self::docx_table($section, array(
                'Name',
                'Title',
                'Organization'
            ), array(
                32,
                34,
                34
            ), $rows, $brand, false, false);
        }

        if (count($r['team']) > 0) {
            $section->addTitle('Assessment team', 2);
            $rows = array();
            foreach ($r['team'] as $person) {
                $rows[] = array(
                    ReportBuilder::plain($person['person_title']),
                    ReportBuilder::plain($person['person_name']),
                    ReportBuilder::plain($person['organisation']),
                    ReportBuilder::plain($person['responsibility'])
                );
            }
            self::docx_table($section, array(
                'Role',
                'Name',
                'Organization',
                'Responsibility'
            ), array(
                26,
                22,
                22,
                30
            ), $rows, $brand, false, false);
        }

        $section->addTitle('Contents', 1);

        foreach ($outline['contents'] as $line) {
            $section->addText(str_replace(' ', "\u{00A0}", $line), array(
                'name' => 'Calibri'
            ), array(
                'spaceAfter' => 60
            ));
        }

        foreach ($outline['blocks'] as $block) {
            self::docx_block($section, $block, $brand);
        }

        $file = tempnam(sys_get_temp_dir(), 'docx');
        IOFactory::createWriter($word, 'Word2007')->save($file);
        $bytes = file_get_contents($file);
        @unlink($file);

        if (isset($logo_file)) {
            @unlink($logo_file);
        }

        return $bytes;
    }

    private static function docx_block($section, array $block, string $brand): void
    {
        $value = $block['value'];

        if ($block['kind'] === 'h1') {
            $section->addTitle($value, 1);
            return;
        }

        if ($block['kind'] === 'h2') {
            $section->addTitle($value, 2);
            return;
        }

        if ($block['kind'] === 'text') {
            foreach (preg_split("/\n\s*\n/", trim($value)) as $chunk) {
                foreach (preg_split("/\n/", trim($chunk)) as $line) {
                    if (preg_match('/^\s*[-•]\s+/', $line)) {
                        $section->addListItem(preg_replace('/^\s*[-•]\s+/', '', $line), 0);
                    } else {
                        $section->addText($line, null, array(
                            'spaceAfter' => 80
                        ));
                    }
                }
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

        if ($block['kind'] === 'table') {
            self::docx_table($section, $value['head'], $value['widths'], $value['rows'], $brand, !empty($value['total']), !empty($value['small']));
            return;
        }

        if ($block['kind'] === 'finding') {
            $table = $section->addTable(array(
                'cellMargin' => 60,
                'width' => 5000,
                'unit' => 'pct',
                'layout' => 'fixed'
            ));
            $table->addRow(null, array(
                'cantSplit' => true
            ));
            $head = $table->addCell(9600, array(
                'gridSpan' => 2,
                'borderTopSize' => 8,
                'borderTopColor' => '1D2733'
            ));
            $run = $head->addTextRun();
            $run->addText($value['title'].'    ', array(
                'name' => 'Calibri',
                'bold' => true
            ));
            $run->addText($value['rating'], array(
                'name' => 'Calibri'
            ));
            foreach ($value['rows'] as $row) {
                $table->addRow(null, array(
                    'cantSplit' => true
                ));
                $table->addCell(2700)->addText($row[0], array(
                    'name' => 'Calibri',
                    'size' => 9,
                    'color' => '55636F'
                ));
                $cell = $table->addCell(6900);
                foreach (preg_split("/\n/", $row[1]) as $line) {
                    $cell->addText($line, array(
                        'size' => 10
                    ));
                }
            }
            $section->addTextBreak();
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

    private static function docx_table($section, array $head, array $widths, array $rows, string $brand, bool $total, bool $small): void
    {
        $size = $small ? 8 : 9;
        $table = $section->addTable(array(
            'borderBottomSize' => 4,
            'borderBottomColor' => 'D6DCE3',
            'borderInsideHSize' => 4,
            'borderInsideHColor' => 'D6DCE3',
            'cellMargin' => 70,
            'width' => 5000,
            'unit' => 'pct',
            'layout' => 'fixed'
        ));

        $table->addRow(null, array(
            'tblHeader' => true
        ));

        foreach ($head as $index => $label) {
            $table->addCell((int) round(9600 * $widths[$index] / 100), array(
                'bgColor' => $brand
            ))->addText($label, array(
                'name' => 'Calibri',
                'size' => $size,
                'bold' => true,
                'color' => 'FFFFFF'
            ));
        }

        $last = count($rows) - 1;

        foreach ($rows as $index => $row) {
            $table->addRow(null, array(
                'cantSplit' => true
            ));
            foreach ($row as $col => $cell) {
                $table->addCell((int) round(9600 * $widths[$col] / 100))->addText((string) $cell, array(
                    'name' => 'Calibri',
                    'size' => $size,
                    'bold' => $total && $index === $last
                ));
            }
        }

        $section->addTextBreak();
    }

}
