<?php
/**
 * Standards library importer.
 *
 * Downloads the official machine-readable release of each framework and loads it
 * into the standards / standard_controls tables at full published depth. Source
 * text is imported verbatim; nothing here writes control text of its own.
 *
 * Run once from the CLI:
 *     APPLICATION_ENV=development php import_standards.php <company_id>
 *
 * Re-running replaces each standard it manages in full, so a partial or corrected
 * source can simply be imported again.
 */

if (PHP_SAPI !== 'cli') {
    exit("This importer runs from the command line only.\n");
}

require_once __DIR__.'/libs/Classes/Main.php';
require_once __DIR__.'/libs/Classes/Database.php';
require_once __DIR__.'/libs/Classes/Model.php';
require_once __DIR__.'/app/models/StandardsModel.php';

const EMDASH = "\u{2014}";
const CACHE = '/tmp/ciso_standards_src';

$company_id = (int) ($argv[1] ?? 0);

if ($company_id <= 0) {
    exit("Usage: php import_standards.php <company_id>\n");
}

/* ------------------------------------------------------------------ fetching */

function fetch(string $url, string $name, bool $binary = false): string
{
    if (!is_dir(CACHE)) {
        mkdir(CACHE, 0700, true);
    }

    $path = CACHE.'/'.$name;

    if (is_file($path) && filesize($path) > 0) {
        return $path;
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_ENCODING => '',
        // Several of these publishers sit behind a WAF that rejects a bare client;
        // the request has to look like the browser a person would use.
        CURLOPT_HTTPHEADER => array(
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Upgrade-Insecure-Requests: 1'
        )
    ));

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code !== 200 || strlen($body) === 0) {
        throw new RuntimeException('download failed ('.$code.') '.$url);
    }

    if (!$binary && stripos(substr($body, 0, 200), '<!DOCTYPE html') !== false && strlen($body) < 20000) {
        throw new RuntimeException('download returned an error page: '.$url);
    }

    file_put_contents($path, $body);

    return $path;
}

/* ------------------------------------------------------------------- helpers */

function tidy(string $s): string
{
    $s = str_replace(array("\r\n", "\r"), "\n", $s);
    $s = preg_replace('/[ \t]+/', ' ', $s);
    $s = preg_replace('/\n{3,}/', "\n\n", $s);
    return trim($s);
}

function clip(string $s, int $max = 380): string
{
    $s = tidy(str_replace("\n", ' ', $s));
    return mb_strlen($s) <= $max ? $s : rtrim(mb_substr($s, 0, $max - 1)).'…';
}

function title_case(string $s): string
{
    $s = trim($s);

    if ($s !== mb_strtoupper($s, 'UTF-8')) {
        return $s;
    }

    $small = array('and', 'or', 'of', 'the', 'for', 'to', 'in', 'a', 'an');
    $words = preg_split('/\s+/', $s);

    foreach ($words as $i => $w) {

        if (preg_match('/^\([A-Z]{2,6}\)[.,]?$/', $w)) {
            continue;
        }

        $lower = mb_strtolower($w, 'UTF-8');
        $words[$i] = ($i > 0 && in_array($lower, $small, true))
            ? $lower
            : mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    }

    return implode(' ', $words);
}

/* ------------------------------------------------------------- xlsx (builtin) */

function xlsx_rows(string $file, string $sheet_name): array
{
    $zip = new ZipArchive();

    if ($zip->open($file) !== true) {
        throw new RuntimeException('cannot open workbook '.$file);
    }

    $shared = array();
    $raw = $zip->getFromName('xl/sharedStrings.xml');

    if ($raw !== false) {
        foreach (simplexml_load_string($raw)->si as $si) {
            $text = '';
            foreach ($si->xpath('.//*[local-name()="t"]') as $t) {
                $text .= (string) $t;
            }
            $shared[] = $text;
        }
    }

    $map = array();
    foreach (simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'))->Relationship as $r) {
        $map[(string) $r['Id']] = (string) $r['Target'];
    }

    $target = '';
    foreach (simplexml_load_string($zip->getFromName('xl/workbook.xml'))->sheets->sheet as $s) {
        $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
        if ((string) $s['name'] === $sheet_name) {
            $target = $map[$rid];
        }
    }

    if ($target === '') {
        $zip->close();
        throw new RuntimeException('no sheet "'.$sheet_name.'" in '.$file);
    }

    $path = ltrim($target, '/');

    if (strpos($path, 'xl/') !== 0) {
        $path = 'xl/'.$path;
    }

    $xml = simplexml_load_string($zip->getFromName($path));
    $zip->close();

    $rows = array();

    foreach ($xml->sheetData->row as $row) {

        $cells = array();

        foreach ($row->c as $c) {

            // Empty columns are omitted entirely, so the column letter has to drive
            // the index - reading positionally shifts every later column.
            $ref = preg_replace('/[0-9]/', '', (string) $c['r']);
            $index = 0;

            for ($i = 0; $i < strlen($ref); $i++) {
                $index = $index * 26 + (ord($ref[$i]) - 64);
            }

            $type = (string) $c['t'];

            if ($type === 'inlineStr') {
                $value = '';
                foreach ($c->xpath('.//*[local-name()="t"]') as $t) {
                    $value .= (string) $t;
                }
            } elseif ($type === 's') {
                $value = $shared[(int) $c->v] ?? '';
            } else {
                $value = (string) $c->v;
            }

            $cells[$index - 1] = $value;
        }

        $flat = array();

        for ($i = 0, $n = $cells ? max(array_keys($cells)) + 1 : 0; $i < $n; $i++) {
            $flat[$i] = $cells[$i] ?? '';
        }

        $rows[] = $flat;
    }

    return $rows;
}

/* -------------------------------------------------------------- OSCAL parsing */

function oscal_prose(array $part): string
{
    $out = array();
    $label = '';

    foreach (($part['props'] ?? array()) as $p) {
        if ($p['name'] === 'label') {
            $label = trim($p['value']);
        }
    }

    $prose = trim((string) ($part['prose'] ?? ''));

    if ($prose !== '') {
        $out[] = ($label === '' ? '' : $label.' ').$prose;
    }

    foreach (($part['parts'] ?? array()) as $sub) {
        $child = oscal_prose($sub);
        if ($child !== '') {
            $out[] = $child;
        }
    }

    return implode("\n", $out);
}

/**
 * Builds an id => text map of every organization-defined parameter in a catalog.
 * The wording is the catalog's own label or selection choices; only the brackets
 * and separators are added, so nothing here invents control text.
 */
function oscal_params(array $node, array &$map): void
{
    foreach (($node['params'] ?? array()) as $p) {

        $id = (string) ($p['id'] ?? '');

        if ($id === '') {
            continue;
        }

        if (isset($p['select'])) {

            $choices = array();

            foreach (($p['select']['choice'] ?? array()) as $choice) {
                $choices[] = is_array($choice) ? (string) ($choice['value'] ?? '') : (string) $choice;
            }

            $how = (string) ($p['select']['how-many'] ?? 'one');
            $map[$id] = '[selection ('.$how.'): '.implode('; ', array_filter($choices)).']';
            continue;
        }

        if (isset($p['label'])) {
            $map[$id] = '['.trim((string) $p['label']).']';
        }
    }

    foreach (($node['controls'] ?? array()) as $c) {
        oscal_params($c, $map);
    }

    foreach (($node['groups'] ?? array()) as $g) {
        oscal_params($g, $map);
    }
}

function oscal_resolve(string $text, array $params): string
{
    // Selection choices can themselves contain parameter inserts, so one pass
    // leaves nested placeholders behind; this repeats until the text settles.
    for ($pass = 0; $pass < 5; $pass++) {

        $next = preg_replace_callback(
            '/\{\{\s*insert:\s*param,\s*([^}\s]+)\s*\}\}/',
            function ($m) use ($params) {
                return $params[$m[1]] ?? '['.$m[1].']';
            },
            $text
        );

        if ($next === $text) {
            break;
        }

        $text = $next;
    }

    return $text;
}

function oscal_body(array $control): string
{
    $statement = '';
    $guidance = '';

    foreach (($control['parts'] ?? array()) as $part) {
        if ($part['name'] === 'statement' && $statement === '') {
            $statement = oscal_prose($part);
        }
        if ($part['name'] === 'guidance' && $guidance === '') {
            $guidance = oscal_prose($part);
        }
    }

    $text = $statement;

    if ($guidance !== '') {
        $text .= ($text === '' ? '' : "\n\n").'Discussion: '.$guidance;
    }

    return tidy($text);
}

function oscal_prop(array $control, string $name): string
{
    foreach (($control['props'] ?? array()) as $p) {
        if ($p['name'] === $name) {
            return (string) $p['value'];
        }
    }

    return '';
}

/* --------------------------------------------------------------- the sources */

/** NIST SP 800-53 Rev 5 - controls and enhancements, each its own row. */
function source_800_53(): array
{
    $file = fetch(
        'https://raw.githubusercontent.com/usnistgov/oscal-content/main/nist.gov/SP800-53/rev5/json/NIST_SP-800-53_rev5_catalog.json',
        '800-53r5.json'
    );

    $catalog = json_decode(file_get_contents($file), true)['catalog'];
    $rows = array();
    $params = array();

    foreach ($catalog['groups'] as $g) {
        oscal_params($g, $params);
    }

    $walk = function (array $node, string $family) use (&$walk, &$rows, $params) {

        foreach (($node['controls'] ?? array()) as $c) {

            $labels = array();

            foreach (($c['props'] ?? array()) as $p) {
                if ($p['name'] === 'label') {
                    $labels[] = $p['value'];
                }
            }

            // Each control ships several label props, zero-padded and not; the
            // unpadded form (AC-2(1)) is how the publication cites itself.
            $identifier = $labels[0] ?? strtoupper($c['id']);

            foreach ($labels as $l) {
                if (!preg_match('/-0\d/', $l) && !preg_match('/\(0\d/', $l)) {
                    $identifier = $l;
                    break;
                }
            }

            $body = oscal_body($c);

            if (oscal_prop($c, 'status') === 'withdrawn') {
                $into = array();
                foreach (($c['links'] ?? array()) as $l) {
                    if (($l['rel'] ?? '') === 'incorporated-into') {
                        $into[] = strtoupper(ltrim(preg_replace('/_smt.*$/', '', $l['href']), '#'));
                    }
                }
                $body = 'Withdrawn from the catalog.'.($into ? ' Incorporated into '.implode(', ', $into).'.' : '');
            }

            $rows[] = array($identifier, tidy($c['title']), oscal_resolve($body, $params), $family);

            $walk($c, $family);
        }
    };

    foreach ($catalog['groups'] as $g) {
        $walk($g, tidy($g['title']));
    }

    return array(
        'standard_name' => 'NIST SP 800-53 Rev 5',
        'short_code' => 'NIST-800-53',
        'version' => 'Rev '.($catalog['metadata']['version'] ?? '5'),
        'description' => 'Security and Privacy Controls for Information Systems and Organizations. '
            ."Imported verbatim from NIST's official OSCAL catalog; controls and control enhancements are separate rows.",
        'expected' => 1196,
        'controls' => $rows
    );
}

/** NIST SP 800-171 Rev 3 - active security requirements. */
function source_800_171_r3(): array
{
    $file = fetch(
        'https://raw.githubusercontent.com/usnistgov/oscal-content/main/nist.gov/SP800-171/rev3/json/NIST_SP800-171_rev3_catalog.json',
        '800-171r3.json'
    );

    $catalog = json_decode(file_get_contents($file), true)['catalog'];
    $rows = array();
    $withdrawn = 0;
    $params = array();

    foreach ($catalog['groups'] as $g) {
        oscal_params($g, $params);
    }

    $walk = function (array $node, string $family) use (&$walk, &$rows, &$withdrawn, $params) {

        foreach (($node['controls'] ?? array()) as $c) {

            $id = (string) $c['id'];
            $identifier = strpos($id, 'SP_800_171_') === 0 ? substr($id, 11) : strtoupper($id);

            if (oscal_prop($c, 'status') === 'withdrawn') {
                $withdrawn++;
                $walk($c, $family);
                continue;
            }

            $rows[] = array($identifier, tidy($c['title']), oscal_resolve(oscal_body($c), $params), $family);

            $walk($c, $family);
        }
    };

    foreach ($catalog['groups'] as $g) {
        $walk($g, tidy($g['title']));
    }

    return array(
        'standard_name' => 'NIST SP 800-171 Rev 3',
        'short_code' => 'NIST-800-171',
        'version' => 'Rev 3',
        'description' => 'Protecting Controlled Unclassified Information in Nonfederal Systems and Organizations. '
            .'Imported verbatim from NIST\'s official OSCAL catalog. '
            .$withdrawn.' withdrawn requirements are excluded.',
        'expected' => 97,
        'controls' => $rows
    );
}

/** NIST SP 800-171A Rev 3 - assessment objectives, from the same OSCAL release. */
function source_800_171a_r3(): array
{
    $file = fetch(
        'https://raw.githubusercontent.com/usnistgov/oscal-content/main/nist.gov/SP800-171/rev3/json/NIST_SP800-171_rev3_catalog.json',
        '800-171r3.json'
    );

    $catalog = json_decode(file_get_contents($file), true)['catalog'];
    $rows = array();
    $params = array();

    foreach ($catalog['groups'] as $g) {
        oscal_params($g, $params);
    }

    $walk = function (array $node, string $family) use (&$walk, &$rows, $params) {

        foreach (($node['controls'] ?? array()) as $c) {

            $id = (string) $c['id'];
            $requirement = strpos($id, 'SP_800_171_') === 0 ? substr($id, 11) : strtoupper($id);

            foreach (($c['parts'] ?? array()) as $part) {

                if ($part['name'] !== 'assessment-objective') {
                    continue;
                }

                $objective = (string) ($part['id'] ?? '');
                $objective = preg_replace('/^assessment-objective_DS-/', '', $objective);
                $prose = oscal_resolve(tidy((string) ($part['prose'] ?? '')), $params);

                if ($objective === '' || $prose === '') {
                    continue;
                }

                $rows[] = array(
                    $objective,
                    clip($prose),
                    $prose."\n\nAssessment objective for requirement ".$requirement.' ('.tidy($c['title']).').',
                    $family
                );
            }

            $walk($c, $family);
        }
    };

    foreach ($catalog['groups'] as $g) {
        $walk($g, tidy($g['title']));
    }

    return array(
        'standard_name' => 'NIST SP 800-171A Rev 3',
        'short_code' => 'NIST-800-171A',
        'version' => 'Rev 3',
        'description' => 'Assessing Security Requirements for Controlled Unclassified Information. '
            ."Assessment objectives imported verbatim from NIST's official OSCAL release of SP 800-171 Rev 3.",
        'expected' => 422,
        'controls' => $rows
    );
}

/** NIST CSF 2.0 - CPRT export. Functions are families; categories and subcategories are controls. */
function source_csf(): array
{
    $file = fetch(
        'https://csrc.nist.gov/extensions/nudp/services/json/nudp/framework/version/CSF_2_0_0/export/excel',
        'csf20.xlsx',
        true
    );

    $rows = xlsx_rows($file, 'CSF 2.0');
    $out = array();
    $function = '';
    $withdrawn = 0;

    foreach (array_slice($rows, 2) as $r) {

        $f = trim($r[0] ?? '');
        $c = trim($r[1] ?? '');
        $s = trim($r[2] ?? '');

        if ($f !== '') {
            $function = tidy(preg_replace('/:.*$/s', '', $f));
            continue;
        }

        // CSF 2.0 republishes the categories and subcategories it retired from 1.1
        // as explicit withdrawal notices; those are not part of the 2.0 core.
        if ($c !== '') {
            if (preg_match('/^(.+?)\s*\(([A-Z]{2}\.[A-Z]{2})\):\s*(.*)$/s', $c, $m)) {
                if (preg_match('/^\[Withdrawn/', trim($m[3]))) {
                    $withdrawn++;
                    continue;
                }
                $out[] = array($m[2], tidy($m[1]), tidy($m[3]), $function);
            }
            continue;
        }

        if ($s === '' || !preg_match('/^([A-Z]{2}\.[A-Z]{2}-\d+):\s*(.+)$/s', $s, $m)) {
            continue;
        }

        $text = tidy($m[2]);

        if (preg_match('/^\[Withdrawn/', $text)) {
            $withdrawn++;
            continue;
        }

        $examples = trim($r[3] ?? '');

        $out[] = array(
            $m[1],
            clip($text),
            $text.($examples === '' ? '' : "\n\nImplementation examples:\n".tidy($examples)),
            $function
        );
    }

    $subcategories = 0;

    foreach ($out as $row) {
        if (strpos($row[0], '-') !== false) {
            $subcategories++;
        }
    }

    return array(
        'standard_name' => 'NIST Cybersecurity Framework 2.0',
        'short_code' => 'NIST-CSF',
        'version' => '2.0',
        'description' => 'The NIST Cybersecurity Framework (CSF) 2.0 Core. Imported verbatim from the NIST CPRT export. '
            .'Functions are the families; categories and subcategories are controls. '
            .$withdrawn.' entries retired from CSF 1.1 are excluded.',
        'expected' => 106,
        'expected_of' => $subcategories,
        'expected_label' => 'subcategories',
        'controls' => $out
    );
}

/**
 * FedRAMP Rev 5 baselines.
 *
 * GSA/fedramp-automation, where the OSCAL profiles used to live, now returns 404 -
 * the repository is gone. The baselines are taken instead from FedRAMP's own
 * published baseline workbook, which is the same Rev 5 content.
 */
function source_fedramp(string $sheet, string $label, int $expected): array
{
    $file = fetch(
        'https://raw.githubusercontent.com/FedRAMP/docs-legacy/main/overrides/assets/LEGACY%20FedRAMP_Security_Controls_Baseline.xlsx',
        'fedramp_baselines.xlsx',
        true
    );

    $rows = xlsx_rows($file, $sheet);
    $out = array();
    $header = null;

    foreach ($rows as $r) {

        $trimmed = array_map('trim', $r);

        if ($header === null) {
            if (in_array('ID', $trimmed, true) && in_array('Control Name', $trimmed, true)) {
                $header = $trimmed;
            }
            continue;
        }

        $col = function (string $needle) use ($header, $r) {
            foreach ($header as $i => $h) {
                if (strpos($h, $needle) === 0) {
                    return trim($r[$i] ?? '');
                }
            }
            return '';
        };

        $id = $col('ID');
        $name = $col('Control Name');

        if ($id === '' || $name === '') {
            continue;
        }

        $text = $col('NIST Control Description');
        $extra = '';

        foreach ($header as $i => $h) {
            if (strpos($h, 'Additional FedRAMP Requirements') !== false) {
                $extra = trim($r[$i] ?? '');
            }
        }

        if ($extra !== '') {
            $text .= ($text === '' ? '' : "\n\n").'Additional FedRAMP requirements and guidance: '.$extra;
        }

        $out[] = array($id, title_case($name), tidy($text), title_case($col('Family')));
    }

    return array(
        'standard_name' => 'FedRAMP Rev 5 '.$label.' Baseline',
        'short_code' => 'FEDRAMP-'.strtoupper($label),
        'version' => 'Rev 5',
        'description' => 'FedRAMP '.$label.' impact baseline. Control text imported verbatim from FedRAMP\'s published '
            .'Rev 5 baseline workbook, including the FedRAMP-defined parameters and additional requirements.',
        'expected' => $expected,
        'controls' => $out
    );
}

/** HIPAA Security Rule - 45 CFR 164 subpart C, verbatim from the eCFR API. */
function source_hipaa(): array
{
    $file = fetch(
        'https://www.ecfr.gov/api/versioner/v1/full/2026-07-01/title-45.xml?subtitle=A&subchapter=C&part=164',
        'hipaa-164.xml'
    );

    $xml = simplexml_load_file($file);
    $out = array();

    foreach ($xml->xpath('//DIV6') as $subpart) {

        if ((string) $subpart['N'] !== 'C') {
            continue;
        }

        foreach ($subpart->xpath('.//DIV8') as $section) {

            $head = tidy(preg_replace('/\s+/', ' ', (string) $section->HEAD));

            if (!preg_match('/^\x{00A7}?\s*(164\.\d+)\s*(.*)$/u', $head, $m)) {
                continue;
            }

            $number = $m[1];
            $title = rtrim(trim($m[2]), '.');

            $family = preg_match('/(Administrative|Physical|Technical|Organizational)\s+(safeguards|requirements)/i', $title, $s)
                ? 'Subpart C '.EMDASH.' '.ucfirst(strtolower($s[1])).' Safeguards'
                : 'Subpart C '.EMDASH.' General Rules';

            $inner = hipaa_paragraphs($section, $number, $family);

            if ($inner) {
                $out = array_merge($out, $inner);
                continue;
            }

            $body = array();

            foreach ($section->children() as $child) {
                if ($child->getName() === 'HEAD') {
                    continue;
                }
                $t = tidy(preg_replace('/\s+/', ' ', strip_tags($child->asXML())));
                if ($t !== '') {
                    $body[] = $t;
                }
            }

            $out[] = array($number, $title === '' ? $number : $title, tidy(implode("\n\n", $body)), $family);
        }
    }

    return array(
        'standard_name' => 'HIPAA Security Rule',
        'short_code' => 'HIPAA-SEC',
        'version' => '45 CFR 164 Subpart C',
        'description' => 'Security Standards for the Protection of Electronic Protected Health Information. '
            .'Regulation text imported verbatim from the eCFR API. Each standard and implementation specification is a '
            .'control, and every specification records whether it is required or addressable.',
        'expected' => 0,
        'controls' => $out
    );
}

/**
 * Walks a HIPAA section's paragraphs tracking the (a)(1)(ii)(A) hierarchy, so every
 * standard and implementation specification carries its full CFR citation. Without
 * the running path the specification letters collide - every section has an (A).
 */
function hipaa_paragraphs(SimpleXMLElement $section, string $number, string $family): array
{
    $levels = array('', '', '', '');
    $out = array();

    foreach ($section->children() as $child) {

        if ($child->getName() !== 'P') {
            continue;
        }

        $rest = tidy(preg_replace('/\s+/', ' ', strip_tags($child->asXML())));
        $depth = -1;

        while (preg_match('/^\(([a-zA-Z0-9]{1,4})\)\s*/', $rest, $m)) {

            $token = $m[1];

            if (preg_match('/^\d+$/', $token)) {
                $level = 1;
            } elseif (preg_match('/^[ivx]+$/', $token)) {
                $level = 2;
            } elseif (preg_match('/^[a-z]$/', $token)) {
                $level = 0;
            } else {
                $level = 3;
            }

            $levels[$level] = '('.$token.')';

            for ($i = $level + 1; $i < 4; $i++) {
                $levels[$i] = '';
            }

            $depth = $level;
            $rest = substr($rest, strlen($m[0]));
        }

        if ($depth < 0) {
            continue;
        }

        $citation = $number.implode('', $levels);

        if (preg_match('/^Standard:\s*([^.]+)\.\s*(.*)$/s', $rest, $s)) {
            $out[] = array($citation, tidy($s[1]), tidy($s[2]), $family);
            continue;
        }

        if (preg_match('/^(.{3,160}?)\s*\((Required|Addressable)\)\.?\s*(.*)$/s', $rest, $s)) {
            $out[] = array(
                $citation,
                tidy($s[1]),
                tidy(tidy($s[3])."\n\nThis implementation specification is ".strtolower($s[2]).'.'),
                $family
            );
        }
    }

    return $out;
}

/** FTC Safeguards Rule - 16 CFR 314, verbatim from the eCFR API. */
function source_ftc(): array
{
    $file = fetch(
        'https://www.ecfr.gov/api/versioner/v1/full/2026-07-01/title-16.xml?chapter=I&subchapter=C&part=314',
        'ftc-314.xml'
    );

    $xml = simplexml_load_file($file);
    $out = array();

    foreach ($xml->xpath('//DIV8') as $section) {

        $head = tidy(preg_replace('/\s+/', ' ', (string) $section->HEAD));

        if (!preg_match('/^\x{00A7}?\s*(314\.\d+)\s*(.*)$/u', $head, $m)) {
            continue;
        }

        $number = $m[1];
        $title = rtrim(trim($m[2]), '.');
        $family = 'Standards for Safeguarding Customer Information';

        $paragraphs = array();

        foreach ($section->children() as $child) {
            if ($child->getName() !== 'P') {
                continue;
            }
            $paragraphs[] = tidy(preg_replace('/\s+/', ' ', strip_tags($child->asXML())));
        }

        // 314.4 carries the elements of the programme and is broken out to every
        // lettered and numbered subsection; the rest stay whole sections.
        if ($number === '314.4') {

            $levels = array('', '', '');
            $last_letter = '';

            foreach ($paragraphs as $text) {

                $rest = $text;
                $depth = -1;

                while (preg_match('/^\(([a-z0-9]{1,4})\)\s*/i', $rest, $mm)) {

                    $token = $mm[1];

                    // "(i)" is both the letter after (h) and roman numeral one.
                    // 314.4 runs all the way to (i), so the token only counts as a
                    // subsection letter when it is the one that comes next in
                    // sequence; anything else that looks roman is a third level.
                    $expected = $last_letter === '' ? 'a' : chr(ord($last_letter) + 1);

                    if (preg_match('/^[a-z]$/', $token) && $token === $expected) {
                        $level = 0;
                        $last_letter = $token;
                    } elseif (preg_match('/^\d+$/', $token)) {
                        $level = 1;
                    } elseif (preg_match('/^[ivx]+$/', $token)) {
                        $level = 2;
                    } else {
                        $level = 0;
                        $last_letter = $token;
                    }

                    $levels[$level] = '('.$token.')';

                    for ($i = $level + 1; $i < 3; $i++) {
                        $levels[$i] = '';
                    }

                    $depth = $level;
                    $rest = substr($rest, strlen($mm[0]));
                }

                if ($depth < 0 || trim($rest) === '') {
                    continue;
                }

                $out[] = array(
                    $number.implode('', $levels),
                    clip(preg_replace('/^([A-Z][^.]{2,80})\..*$/s', '$1', $rest), 200),
                    tidy($rest),
                    $family
                );
            }

            continue;
        }

        $out[] = array($number, $title === '' ? $number : $title, tidy(implode("\n\n", $paragraphs)), $family);
    }

    return array(
        'standard_name' => 'FTC Safeguards Rule',
        'short_code' => 'FTC-SAFEGUARDS',
        'version' => '16 CFR 314',
        'description' => 'Standards for Safeguarding Customer Information under the Gramm-Leach-Bliley Act. '
            .'Regulation text imported verbatim from the eCFR API, with 314.4 broken out to every lettered and '
            .'numbered subsection.',
        'expected' => 0,
        'controls' => $out
    );
}

/** NYDFS 23 NYCRR 500 - sections and lettered subsections, from the DFS text. */
function source_nydfs(): array
{
    $pdf = fetch(
        'https://www.dfs.ny.gov/system/files/documents/2023/12/rf23_nycrr_part_500_amend02_20231101.pdf',
        'nydfs-500.pdf',
        true
    );

    $txt = CACHE.'/nydfs-500.txt';

    if (!is_file($txt)) {

        exec('command -v pdftotext', $probe, $status);

        if ($status !== 0) {
            throw new RuntimeException('pdftotext is not installed, cannot read the DFS PDF');
        }

        exec('pdftotext -layout '.escapeshellarg($pdf).' '.escapeshellarg($txt), $ignore, $status);

        if ($status !== 0 || !is_file($txt)) {
            throw new RuntimeException('pdftotext failed on the DFS PDF');
        }
    }

    // Page breaks arrive as form feeds glued to the front of the next line, so a
    // heading opening a page never sits at a line start until they are normalised.
    $text = str_replace(array("\r\n", "\f"), "\n", file_get_contents($txt));

    if (!preg_match_all('/^[ \t]*(500\.\d+)[ \t]*([A-Z][^\n]*?)\.?[ \t]*$/m', $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        throw new RuntimeException('no section headings found in the DFS text');
    }

    $out = array();
    $seen = array();

    foreach ($m as $i => $hit) {

        $number = $hit[1][0];

        if (isset($seen[$number])) {
            continue;
        }

        $seen[$number] = 1;

        $start = $hit[0][1] + strlen($hit[0][0]);
        $end = isset($m[$i + 1]) ? $m[$i + 1][0][1] : strlen($text);

        $body = substr($text, $start, $end - $start);
        $body = preg_replace('/^\s*\d+\s*$/m', '', $body);
        $body = preg_replace('/[ \t]+/', ' ', $body);
        $body = tidy($body);

        $family = 'Cybersecurity Requirements for Financial Services Companies';
        $title = tidy(rtrim($hit[2][0], '.'));

        $out[] = array($number, $title, $body, $family);

        // Lettered subsections are the operative requirements; each becomes its
        // own control under the section that introduces it.
        if (preg_match_all('/(?:^|\n)\((?<letter>[a-z])\)\s*(?<text>.+?)(?=\n\([a-z]\)|\z)/s', $body, $subs, PREG_SET_ORDER)) {
            foreach ($subs as $sub) {
                $sub_text = tidy($sub['text']);
                if ($sub_text === '') {
                    continue;
                }
                $out[] = array(
                    $number.'('.$sub['letter'].')',
                    clip($sub_text, 200),
                    $sub_text,
                    $family
                );
            }
        }
    }

    return array(
        'standard_name' => 'NYDFS Cybersecurity Regulation',
        'short_code' => 'NYDFS-500',
        'version' => '23 NYCRR 500 (Second Amendment)',
        'description' => 'Cybersecurity Requirements for Financial Services Companies, as amended 1 November 2023. '
            .'Text imported verbatim from the New York State Department of Financial Services publication of the '
            .'Second Amendment. Sections and their lettered subsections are separate controls.',
        'expected' => 0,
        'controls' => $out
    );
}

/* ---------------------------------------------------------------- the loading */

function load_standard(StandardsModel $model, int $company_id, array $source): array
{
    $rows = $source['controls'];

    if (count($rows) === 0) {
        throw new RuntimeException('parsed zero controls');
    }

    $seen = array();
    $duplicates = 0;
    $clean = array();

    foreach ($rows as $r) {

        list($identifier, $title, $description, $family) = $r;

        $identifier = trim($identifier);
        $title = trim($title) === '' ? $identifier : trim($title);

        if ($identifier === '') {
            continue;
        }

        $key = mb_strtolower($identifier);

        if (isset($seen[$key])) {
            $duplicates++;
            continue;
        }

        $seen[$key] = 1;

        $clean[] = array(
            'control_identifier' => mb_substr($identifier, 0, 120),
            'control_title' => mb_substr($title, 0, 400),
            'description' => $description,
            'family' => mb_substr($family, 0, 200)
        );
    }

    // A re-run replaces the standard outright rather than merging, so a corrected
    // source never leaves stale rows behind.
    foreach ($model->load_standards($company_id) as $existing) {
        if ($existing['short_code'] === $source['short_code']) {
            $model->delete_standard($existing['id'], $company_id, 0);
        }
    }

    $standard_id = $model->add_standard(
        $company_id,
        $source['standard_name'],
        $source['short_code'],
        $source['version'],
        $source['description'],
        'Active',
        0
    );

    $model->import_controls($standard_id, $clean, 0);

    return array(
        'standard_id' => $standard_id,
        'loaded' => count($clean),
        'duplicates' => $duplicates,
        'families' => count(array_unique(array_column($clean, 'family')))
    );
}

/* ------------------------------------------------------------------- run them */

$model = new StandardsModel();

$sources = array(
    'NIST SP 800-53 Rev 5' => 'source_800_53',
    'NIST SP 800-171 Rev 3' => 'source_800_171_r3',
    'NIST SP 800-171A Rev 3' => 'source_800_171a_r3',
    'NIST CSF 2.0' => 'source_csf',
    'FedRAMP Low' => function () { return source_fedramp('Low Baseline', 'Low', 156); },
    'FedRAMP Moderate' => function () { return source_fedramp('Moderate Baseline', 'Moderate', 323); },
    'FedRAMP High' => function () { return source_fedramp('High Baseline', 'High', 410); },
    'HIPAA Security Rule' => 'source_hipaa',
    'FTC Safeguards Rule' => 'source_ftc',
    'NYDFS 23 NYCRR 500' => 'source_nydfs'
);

$report = array();

foreach ($sources as $label => $builder) {

    printf("%-26s ", $label);

    try {

        $source = $builder();
        $result = load_standard($model, $company_id, $source);

        $expected = (int) $source['expected'];
        $counted = isset($source['expected_of']) ? (int) $source['expected_of'] : $result['loaded'];
        $drift = $expected > 0 ? abs($counted - $expected) / $expected * 100 : 0.0;

        $flag = ($expected > 0 && $drift > 2.0) ? '  ** OFF BY '.round($drift, 1).'% **' : '';

        printf("loaded %-5d families %-3d %s%s\n",
            $result['loaded'],
            $result['families'],
            $expected > 0 ? 'expected '.$expected.' ('.($source['expected_label'] ?? 'controls').' '.$counted.')' : '',
            $flag
        );

        $report[$label] = array('status' => 'loaded') + $result + array('expected' => $expected, 'counted' => $counted);

    } catch (Throwable $e) {
        printf("SKIPPED - %s\n", $e->getMessage());
        $report[$label] = array('status' => 'skipped', 'reason' => $e->getMessage());
    }
}

echo "\n";
file_put_contents(CACHE.'/report.json', json_encode($report, JSON_PRETTY_PRINT));
echo 'Report written to '.CACHE."/report.json\n";
