<?php
/**
 * Plain text out of the office and text formats the assistant cannot read
 * natively. PDFs and images are not handled here; the model reads those itself.
 * Returns null when the file cannot be read as the format its extension claims.
 */
class DocumentText {

    const TEXT_EXTENSIONS = array(
        'txt',
        'csv',
        'md',
        'json',
        'xml',
        'log'
    );

    public static function extract(string $path, string $extension): ?string
    {
        if ($extension === 'docx') {
            return self::docx($path);
        }

        if ($extension === 'xlsx') {
            return self::xlsx($path);
        }

        if (in_array($extension, self::TEXT_EXTENSIONS, true)) {
            $text = file_get_contents($path);
            if ($text === false) {
                return null;
            }
            return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return null;
    }

    private static function docx(string $path): ?string
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return null;
        }

        $xml = preg_replace('#</w:p>#', "\n", $xml);
        $xml = preg_replace('#<w:tab/>#', "\t", $xml);
        $xml = preg_replace('#<w:br[^>]*/>#', "\n", $xml);

        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** Every sheet, one row per line, cells separated by tabs. */
    private static function xlsx(string $path): ?string
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return null;
        }

        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $rels === false) {
            $zip->close();
            return null;
        }

        $shared = array();
        $raw = $zip->getFromName('xl/sharedStrings.xml');

        if ($raw !== false) {
            foreach (simplexml_load_string($raw)->si as $si) {
                $value = '';
                foreach ($si->xpath('.//*[local-name()="t"]') as $t) {
                    $value .= (string) $t;
                }
                $shared[] = $value;
            }
        }

        $targets = array();
        foreach (simplexml_load_string($rels)->Relationship as $r) {
            $targets[(string) $r['Id']] = (string) $r['Target'];
        }

        $out = array();

        foreach (simplexml_load_string($workbook)->sheets->sheet as $sheet) {

            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            $sheet_path = ltrim($targets[$rid] ?? '', '/');

            if ($sheet_path === '') {
                continue;
            }

            if (strpos($sheet_path, 'xl/') !== 0) {
                $sheet_path = 'xl/'.$sheet_path;
            }

            $sheet_xml = $zip->getFromName($sheet_path);

            if ($sheet_xml === false) {
                continue;
            }

            $out[] = '## Sheet: '.(string) $sheet['name'];

            foreach (simplexml_load_string($sheet_xml)->sheetData->row as $row) {
                $out[] = implode("\t", self::row_cells($row, $shared));
            }

            $out[] = '';
        }

        $zip->close();

        return trim(implode("\n", $out));
    }

    private static function row_cells(SimpleXMLElement $row, array $shared): array
    {
        $cells = array();

        foreach ($row->c as $c) {

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

            $cells[max(0, $index - 1)] = $value;
        }

        $flat = array();
        $count = count($cells) > 0 ? max(array_keys($cells)) + 1 : 0;

        for ($i = 0; $i < $count; $i++) {
            $flat[$i] = $cells[$i] ?? '';
        }

        return $flat;
    }

}
