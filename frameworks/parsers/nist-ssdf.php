<?php
/**
 * NIST Secure Software Development Framework 1.1 (SP 800-218), from the practices table
 * NIST publishes with it as a spreadsheet.
 *
 * - Groups are the 19 Practices (PO.1 ...), each carrying its practice group
 *   (Prepare the Organization, PO) from the "Groups" sheet.
 * - Controls are the 42 Tasks (PO.1.1 ...). A task's notional implementation examples
 *   are loaded as guidance, one per line.
 * - A task moved in version 1.1 ends "[Formerly PW.3.1]"; the note is taken out of the
 *   text and kept as `formerly`.
 */
return function (): array {
    $groups = array();

    foreach (Source::xlsx_sheet('ssdf/NIST.SP.800-218.SSDF-table.xlsx', 'Groups') as $row) {
        $a = Source::normalise($row['A'] ?? '');

        if ($a === '') {
            continue;
        }

        if (!preg_match('/^(.+?) \(([A-Z]{2})\): (.+)$/', $a, $m)) {
            throw new RuntimeException('Unrecognised group row: '.$a);
        }

        $groups[$m[2]] = array(
            'name' => $m[1],
            'description' => $m[3]
        );
    }

    $rows = Source::xlsx_sheet('ssdf/NIST.SP.800-218.SSDF-table.xlsx', 'SSDF');
    $families = array();
    $practice = null;

    foreach (array_slice($rows, 1) as $row) {
        $a = Source::normalise($row['A'] ?? '');
        $b = Source::normalise($row['B'] ?? '');

        if ($a !== '') {
            if (!preg_match('/^(.+?) \((([A-Z]{2})\.\d+)\): (.+)$/', $a, $m)) {
                throw new RuntimeException('Unrecognised practice row: '.$a);
            }

            if (!isset($groups[$m[3]])) {
                throw new RuntimeException('Practice '.$m[2].' has no group');
            }

            $practice = $m[2];
            $families[$practice] = array(
                'number' => $practice,
                'code' => $practice,
                'name' => $m[1],
                'description' => $m[4],
                'function' => $groups[$m[3]]['name'],
                'function_code' => $m[3],
                'function_description' => $groups[$m[3]]['description'],
                'controls' => array()
            );
        }

        if ($b === '') {
            continue;
        }

        if (!preg_match('/^(([A-Z]{2}\.\d+)\.\d+): (.+)$/', $b, $m)) {
            throw new RuntimeException('Unrecognised task row: '.$b);
        }

        if ($m[2] !== $practice) {
            throw new RuntimeException('Task '.$m[1].' is outside practice '.$practice);
        }

        $text = $m[3];
        $formerly = '';

        if (preg_match('/^(.*\S)\s*\[Formerly ([A-Z]{2}\.\d+\.\d+)\]$/', $text, $f)) {
            $text = $f[1];
            $formerly = $f[2];
        }

        $examples = array();

        foreach (preg_split('/\R/', $row['C'] ?? '') as $line) {
            $line = Source::normalise($line);

            if ($line !== '') {
                $examples[] = $line;
            }
        }

        $families[$practice]['controls'][] = array(
            'id' => $m[1],
            'number' => $m[1],
            'text' => $text,
            'guidance' => implode("\n", $examples),
            'type' => 'task',
            'formerly' => $formerly,
            'weight' => null,
            'partial_weight' => null,
            'objectives' => array()
        );
    }

    return array_values($families);
};
