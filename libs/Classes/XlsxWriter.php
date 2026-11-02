<?php
/**
 * A minimal Excel (.xlsx) writer for list-style reports: one or more sheets,
 * each a header row and plain rows of text or numbers. The header is bold on
 * the brand colour, frozen, and has filter buttons; columns get sensible
 * widths. Text is written inline, so no shared-strings table is needed.
 */
class XlsxWriter {

    /**
     * $sheets: array of array('name' => ..., 'head' => [...], 'rows' => [[...]], 'widths' => [...]).
     * $brand: header colour as RRGGBB. Returns the file contents.
     */
    public static function build(array $sheets, string $brand): string
    {
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::OVERWRITE);

        $names = array();
        $overrides = '';
        $relations = '';
        $entries = '';

        foreach (array_values($sheets) as $index => $sheet) {
            $number = $index + 1;
            $name = self::sheet_name($sheet['name'], $names);
            $names[] = $name;
            $zip->addFromString('xl/worksheets/sheet'.$number.'.xml', self::sheet_xml($sheet));
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $relations .= '<Relationship Id="rId'.$number.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$number.'.xml"/>';
            $entries .= '<sheet name="'.self::xml($name).'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';
        }

        $styles_id = count($sheets) + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$entries.'</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relations
            .'<Relationship Id="rId'.$styles_id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF'.strtoupper($brand).'"/></patternFill></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');

        $zip->close();
        $bytes = file_get_contents($file);
        @unlink($file);

        return $bytes;
    }

    private static function sheet_xml(array $sheet): string
    {
        $head = $sheet['head'];
        $columns = count($head);
        $last = self::column($columns - 1);
        $count = count($sheet['rows']) + 1;

        $cols = '<cols>';
        foreach ($head as $index => $label) {
            $width = $sheet['widths'][$index] ?? 18;
            $cols .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.(int) $width.'" customWidth="1"/>';
        }
        $cols .= '</cols>';

        $data = '<row r="1">';
        foreach ($head as $index => $label) {
            $data .= self::cell(self::column($index).'1', $label, 1);
        }
        $data .= '</row>';

        foreach (array_values($sheet['rows']) as $r => $row) {
            $number = $r + 2;
            $data .= '<row r="'.$number.'">';
            foreach (array_values($row) as $index => $value) {
                $data .= self::cell(self::column($index).$number, $value, 2);
            }
            $data .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .$cols
            .'<sheetData>'.$data.'</sheetData>'
            .'<autoFilter ref="A1:'.$last.$count.'"/>'
            .'</worksheet>';
    }

    private static function cell(string $ref, $value, int $style): string
    {
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
        }

        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.self::xml((string) $value).'</t></is></c>';
    }

    private static function column(int $index): string
    {
        $name = '';
        $index++;

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = (int) (($index - $mod) / 26);
        }

        return $name;
    }

    /** Excel sheet names: 31 characters, no []:*?/\, unique in the workbook. */
    private static function sheet_name(string $name, array $taken): string
    {
        $clean = mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name), 0, 31);
        $candidate = $clean === '' ? 'Sheet' : $clean;
        $suffix = 2;

        while (in_array($candidate, $taken, true)) {
            $candidate = mb_substr($clean, 0, 28).' '.$suffix++;
        }

        return $candidate;
    }

    private static function xml(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

}
