<?php
/**
 * NIST Cybersecurity Framework 2.0 Core, from NIST's CSF 2.0 Reference Tool (CPRT) export.
 *
 * - Groups are the 22 Categories (GV.OC ...), each carrying its Function (GOVERN, GV).
 *   Categories are the level at which CSF outcomes are grouped and profiled; Functions
 *   alone would put up to 30+ subcategories in one group.
 * - Controls are the 106 Subcategories (GV.OC-01 ...); their implementation examples
 *   are loaded as guidance.
 * - Entries the export lists as "[Withdrawn: ...]" (CSF 1.1 items folded into 2.0) are excluded.
 */
return function (): array {
    $rows = Source::xlsx_rows('csf/cprt-csf-2.0.xlsx', 2);
    $families = array();
    $function = null;
    $category = null;

    foreach (array_slice($rows, 2) as $row) {
        $a = Source::normalise($row['A'] ?? '');
        $b = trim($row['B'] ?? '');
        $c = trim($row['C'] ?? '');

        // Function rows open with "GOVERN (GV): ..."; the closing "GOVERN (GV)" row has no colon.
        if (preg_match('/^([A-Z]+) \(([A-Z]{2})\): (.+)$/', $a, $m)) {
            $function = array(
                'name' => $m[1],
                'code' => $m[2],
                'description' => $m[3]
            );
            continue;
        }

        if ($b !== '' && preg_match('/^(.+?) \(([A-Z]{2}\.[A-Z]{2})\): (.+)$/s', Source::normalise($b), $m)) {
            $category = null;

            if (strpos($m[3], '[Withdrawn') === 0) {
                continue;
            }

            if (strpos($m[2], $function['code'].'.') !== 0) {
                throw new RuntimeException('Category '.$m[2].' is outside function '.$function['code']);
            }

            $category = $m[2];
            $families[$category] = array(
                'number' => $category,
                'code' => $category,
                'name' => $m[1],
                'description' => $m[3],
                'function' => $function['name'],
                'function_code' => $function['code'],
                'function_description' => $function['description'],
                'controls' => array()
            );
            continue;
        }

        if ($c === '' || !preg_match('/^(([A-Z]{2}\.[A-Z]{2})-\d{2}): (.+)$/s', Source::normalise($c), $m)) {
            continue;
        }

        $id = $m[1];

        if (strpos($m[3], '[Withdrawn') === 0) {
            continue;
        }

        if ($m[2] !== $category) {
            throw new RuntimeException('Subcategory '.$id.' is outside category '.$category);
        }

        $examples = array();

        foreach (preg_split('/\R/', $row['D'] ?? '') as $line) {
            $line = Source::normalise($line);

            if ($line !== '') {
                $examples[] = $line;
            }
        }

        $families[$category]['controls'][] = array(
            'id' => $id,
            'number' => $id,
            'text' => $m[3],
            'guidance' => implode("\n", $examples),
            'type' => 'subcategory',
            'weight' => null,
            'partial_weight' => null,
            'objectives' => array()
        );
    }

    return array_values($families);
};
