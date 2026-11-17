<?php
/**
 * NIST SP 800-61 Rev. 3, Incident Response Recommendations and Considerations for Cyber Risk
 * Management. Section 3 is a CSF 2.0 Community Profile: every CSF Function, Category and
 * Subcategory has a row with an incident response priority (High, Medium, Low) and may carry
 * recommendations (R), considerations (C) and notes (N).
 *
 * The two profile tables are read from the committed pdftotext -layout extract, where the four
 * columns keep their horizontal positions; each page's header row gives the column offsets.
 * Families are the CSF Categories; controls are the Subcategories, plus any Function or
 * Category row that carries its own recommendations (they apply to the elements beneath it).
 * Objectives are the R items; C and N items become guidance. All text is NIST's.
 */
return function (): array {
    $lines = explode("\n", str_replace(array("\r", "\f"), '', file_get_contents(Source::path('nist80061/NIST.SP.800-61r3.layout.txt'))));
    $start = null;
    $end = null;

    foreach ($lines as $i => $line) {
        if ($start === null && preg_match('/^\s*Table 2\. CSF 2\.0 Community Profile part 1/', $line) && strpos($line, '....') === false) {
            $start = $i;
        }

        if ($start !== null && trim($line) === 'References') {
            $end = $i;
            break;
        }
    }

    if ($start === null || $end === null) {
        throw new RuntimeException('800-61r3: profile tables not found');
    }

    $rows = array();
    $cols = null;
    $row = null;

    for ($i = $start + 1; $i < $end; $i++) {
        $line = $lines[$i];

        // The prose of section 3.2 sits between the two tables: close the last row and wait for Table 3's header.
        if (preg_match('/^3\.2\. Incident Response$/', $line)) {
            if ($row !== null) {
                $rows[] = $row;
                $row = null;
            }

            $cols = null;
            continue;
        }

        if (preg_match('/^(\s*)CSF Element(\s+)CSF Element Description(\s+)Priority(\s+)Recommendations/', $line)) {
            $cols = array(
                strpos($line, 'CSF Element'),
                strpos($line, 'CSF Element Description'),
                strpos($line, 'Priority'),
                strpos($line, 'Recommendations')
            );
            continue;
        }

        if ($cols === null || trim($line) === '' || preg_match('/^\s*\d+\s*$/', $line) || preg_match('/^(NIST SP 800-61r3|April 2025)\s/', $line) || preg_match('/^\s*Table 3\. CSF 2\.0 Community Profile/', $line)) {
            continue;
        }

        // Split the line into its four columns by the header offsets (a cell may start a little left of its header).
        $cells = array();

        foreach ($cols as $c => $offset) {
            $from = $c === 0 ? 0 : max(0, $offset - 2);
            $to = isset($cols[$c + 1]) ? $cols[$c + 1] - 2 : strlen($line);
            $cells[$c] = trim((string) substr($line, $from, $to - $from));
        }

        if (preg_match('/^([A-Z]{2}(?:\.[A-Z]{2}(?:-\d{2})?)?)(?:\s|$)/', $cells[0], $m)) {
            if ($row !== null) {
                $rows[] = $row;
            }

            $row = array(
                'id' => $m[1],
                'name' => trim(substr($cells[0], strlen($m[1]))),
                'description' => $cells[1],
                'priority' => $cells[2],
                'notes' => $cells[3]
            );
            continue;
        }

        if ($row === null) {
            continue;
        }

        $join = function (string $left, string $right): string {
            return preg_match('/[a-z]-$/', $left) ? $left.$right : trim($left.' '.$right);
        };
        $row['name'] = $join($row['name'], $cells[0]);
        $row['description'] = $join($row['description'], $cells[1]);
        $row['priority'] = trim($row['priority'].' '.$cells[2]);
        $row['notes'] .= "\n".$cells[3];
    }

    if ($row !== null) {
        $rows[] = $row;
    }

    $flat = function (string $text): string {
        $text = preg_replace('/(?<=[a-z])-\n(?=[a-z])/', '-', trim($text));

        return trim(preg_replace('/\s+/', ' ', $text));
    };

    $families = array();
    $function_names = array();

    foreach ($rows as $row) {
        if (!in_array($row['priority'], array('High', 'Medium', 'Low'), true)) {
            throw new RuntimeException('800-61r3: '.$row['id'].' has no priority ('.$row['priority'].')');
        }

        // R1:, C2:, N1: items, each running to the next item.
        $items = array();

        foreach (preg_split('/\n(?=[RCN]\d+: )/', "\n".trim($row['notes'])) as $chunk) {
            if (preg_match('/^([RCN]\d+): (.+)$/s', trim($chunk), $m)) {
                $items[] = array(
                    $m[1],
                    $flat($m[2])
                );
            }
        }

        $name = trim($row['name'], '() ');
        $level = preg_match('/-\d{2}$/', $row['id']) ? 'subcategory' : (strpos($row['id'], '.') !== false ? 'category' : 'function');

        if ($level === 'function') {
            $function_names[$row['id']] = $name;
        }

        $category = $level === 'subcategory' ? substr($row['id'], 0, 5) : ($level === 'category' ? $row['id'] : null);

        if ($category !== null && !isset($families[$category])) {
            $families[$category] = array(
                'number' => $category,
                'code' => $category,
                'name' => '',
                'controls' => array()
            );
        }

        if ($level === 'category') {
            $families[$category]['name'] = $name;
        }

        $recommendations = array();
        $guidance = array();

        foreach ($items as $item) {
            if ($item[0][0] === 'R') {
                $recommendations[] = $item;
            } else {
                $guidance[] = $item[0].': '.$item[1];
            }
        }

        // Function and Category rows become controls only when they carry recommendations.
        if ($level !== 'subcategory' && count($recommendations) === 0) {
            continue;
        }

        $objectives = array();

        foreach ($recommendations as $item) {
            $objectives[] = array(
                'id' => $row['id'].'.'.$item[0],
                'text' => $item[1],
                'source' => 'sp80061'
            );
        }

        $control = array(
            'id' => $row['id'],
            'number' => $row['id'],
            'text' => $flat($row['description']),
            'description' => 'Incident response priority: '.$row['priority'].'.',
            'guidance' => implode("\n", $guidance),
            'type' => $level,
            'priority' => $row['priority'],
            'weight' => null,
            'partial_weight' => null,
            'objectives' => $objectives,
            'source' => 'sp80061'
        );

        if ($level !== 'subcategory') {
            $control['title'] = $name.' (applies to the whole '.$level.')';
        }

        if ($level === 'function') {
            // A Function-level row is filed under the Function's first Category, which follows it.
            $families['_'.$row['id']] = array(
                'number' => $row['id'],
                'code' => $row['id'],
                'name' => $name.' (Function)',
                'controls' => array($control)
            );
            continue;
        }

        $families[$category]['controls'][] = $control;
    }

    return array_values(array_filter($families, function ($family) {
        return count($family['controls']) > 0;
    }));
};
