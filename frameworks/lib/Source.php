<?php
/**
 * Reads the official source snapshots in frameworks/sources. Parsers use these
 * helpers so every framework definition is derived from the same files the
 * reference tests check against.
 */
class Source {

    public static function path(string $file): string
    {
        $path = dirname(__DIR__).'/sources/'.$file;

        if (!is_file($path)) {
            throw new RuntimeException('Missing source snapshot: '.$file);
        }

        return $path;
    }

    /** Plain text of an XML document (eCFR exports), entities decoded. */
    public static function xml_text(string $file): string
    {
        $xml = file_get_contents(self::path($file));
        $xml = preg_replace('/<(P|HD|FP|ROW|TR|LI|SECTNO|SUBJECT)\b[^>]*>/', "\n", $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Text extract of a PDF. The extract is committed next to the PDF (same
     * name, .txt) so builds don't need pdftotext installed.
     */
    public static function pdf_text(string $file): string
    {
        $txt = preg_replace('/\.pdf$/', '.txt', $file);

        return file_get_contents(self::path($txt));
    }

    /** Rows of the first worksheet of an xlsx file, as arrays of cell strings keyed by column letter. */
    public static function xlsx_rows(string $file, int $sheet = 1): array
    {
        $zip = new ZipArchive();

        if ($zip->open(self::path($file)) !== true) {
            throw new RuntimeException('Cannot open '.$file);
        }

        $strings = array();
        $shared = $zip->getFromName('xl/sharedStrings.xml');

        if ($shared !== false) {
            $sst = simplexml_load_string($shared);

            foreach ($sst->si as $si) {
                $text = '';

                if (isset($si->t)) {
                    $text = (string) $si->t;
                }

                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }

                $strings[] = $text;
            }
        }

        $sheet_xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet'.$sheet.'.xml'));
        $zip->close();

        $rows = array();

        foreach ($sheet_xml->sheetData->row as $row) {
            $cells = array();

            foreach ($row->c as $c) {
                $col = preg_replace('/\d+/', '', (string) $c['r']);
                $type = (string) $c['t'];

                if ($type === 's') {
                    $value = $strings[(int) $c->v];
                } elseif ($type === 'inlineStr') {
                    $value = (string) $c->is->t;
                } else {
                    $value = (string) $c->v;
                }

                $cells[$col] = $value;
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    /** Rows of the worksheet with the given tab name (see xlsx_rows). */
    public static function xlsx_sheet(string $file, string $name): array
    {
        $zip = new ZipArchive();

        if ($zip->open(self::path($file)) !== true) {
            throw new RuntimeException('Cannot open '.$file);
        }

        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $zip->close();
        $target = null;

        foreach ($workbook->sheets->sheet as $sheet) {
            if ((string) $sheet['name'] !== $name) {
                continue;
            }

            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

            foreach ($rels->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = basename((string) $rel['Target']);
                }
            }
        }

        if ($target === null || !preg_match('/^sheet(\d+)\.xml$/', $target, $m)) {
            throw new RuntimeException('No sheet named '.$name.' in '.$file);
        }

        return self::xlsx_rows($file, (int) $m[1]);
    }

    /** Collapses whitespace and normalises typographic quotes and dashes so quotes can be matched. */
    public static function normalise(string $text): string
    {
        $map = array(
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{2013}" => '-',
            "\u{2014}" => '-',
            "\u{00A0}" => ' ',
            "\u{00AD}" => ''
        );
        $text = strtr($text, $map);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Text between two markers (first occurrence of $start, then first $end after it). */
    public static function between(string $text, string $start, string $end): string
    {
        $from = strpos($text, $start);

        if ($from === false) {
            throw new RuntimeException('Marker not found: '.$start);
        }

        $to = strpos($text, $end, $from + strlen($start));

        if ($to === false) {
            throw new RuntimeException('Marker not found: '.$end);
        }

        return substr($text, $from, $to - $from);
    }

    /** Title case for family names written in capitals in the source ("SYSTEM AND COMMUNICATIONS PROTECTION"). */
    public static function title_case(string $text): string
    {
        $small = array(
            'and',
            'of',
            'the',
            'for',
            'to',
            'in'
        );
        $words = explode(' ', strtolower(trim($text)));

        foreach ($words as $i => $word) {
            if ($i === 0 || !in_array($word, $small, true)) {
                $words[$i] = ucfirst($word);
            }
        }

        return implode(' ', $words);
    }

}
