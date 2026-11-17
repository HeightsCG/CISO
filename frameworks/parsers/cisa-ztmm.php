<?php
/**
 * CISA Zero Trust Maturity Model, Version 2.0 (April 2023). Tables 2 to 7 describe, for each
 * pillar (Identity, Devices, Networks, Applications and Workloads, Data) and for the
 * cross-cutting capabilities, each function at four maturity stages: Traditional, Initial,
 * Advanced and Optimal.
 *
 * Each function is a control whose text is its Traditional description (the starting point);
 * its objectives are the Initial, Advanced and Optimal descriptions, so the stage reached is
 * shown by which objectives are met. Tables are read from the committed pdftotext -layout
 * extract by column position (each page's header row gives the offsets). Footnote markers
 * inside cells are removed; all other text is CISA's.
 */
return function (): array {
    $lines = explode("\n", str_replace(array("\r", "\f"), '', file_get_contents(Source::path('cisa-ztmm/zero_trust_maturity_model_v2_508.layout.txt'))));
    $tables = array(
        'ID' => array('Table 2: Identity Pillar', 'Identity'),
        'DV' => array('Table 3: Devices Pillar', 'Devices'),
        'NW' => array('Table 4: Networks Pillar', 'Networks'),
        'AW' => array('Table 5: Applications and Workloads', 'Applications and Workloads'),
        'DA' => array('Table 6: Data', 'Data'),
        'CC' => array('Table 7: Cross-Cutting Capabilities', 'Cross-Cutting Capabilities')
    );

    // Body positions of each table heading (the table of contents entries have dot leaders).
    $starts = array();

    foreach ($tables as $code => $table) {
        foreach ($lines as $i => $line) {
            if (trim($line) === $table[0]) {
                $starts[$code] = $i;
            }
        }

        if (!isset($starts[$code])) {
            throw new RuntimeException('ZTMM: '.$table[0].' not found');
        }
    }

    $codes = array_keys($tables);
    $families = array();

    foreach ($codes as $t => $code) {
        $end = isset($codes[$t + 1]) ? $starts[$codes[$t + 1]] : count($lines);

        if ($code === 'CC') {
            for ($end = $starts[$code] + 1; $end < count($lines); $end++) {
                if (preg_match('/^\s*(?:\d+\s+)?(?:Conclusion|Appendix)/', $lines[$end])) {
                    break;
                }
            }
        }

        $rows = array();
        $row = null;
        $cols = null;

        for ($i = $starts[$code] + 1; $i < $end; $i++) {
            $line = $lines[$i];

            if (preg_match('/^\s*Function\s+Traditional\s+Initial\s+Advanced\s+Optimal\s*$/', $line)) {
                $cols = array(
                    strpos($line, 'Function'),
                    strpos($line, 'Traditional'),
                    strpos($line, 'Initial'),
                    strpos($line, 'Advanced'),
                    strpos($line, 'Optimal')
                );
                $blank = 0;
                continue;
            }

            // A footnote number, the page footer or a figure ends the table rows on this page.
            if (preg_match('/^\s{0,4}\d{1,2}(?:\s*$|\s+\S)/', $line) || preg_match('/^Zero Trust Maturity Model\s/', $line)) {
                $cols = null;
                continue;
            }

            // Two blank lines end the rows on a page: what follows is footnotes.
            if (trim($line) === '') {
                $blank = ($blank ?? 0) + 1;

                if ($blank >= 2) {
                    $cols = null;
                }

                continue;
            }

            $blank = 0;

            if ($cols === null) {
                continue;
            }

            $cells = array();

            foreach ($cols as $c => $offset) {
                $from = $c === 0 ? 0 : max(0, $offset - 2);
                $to = isset($cols[$c + 1]) ? $cols[$c + 1] - 2 : strlen($line);
                $cells[$c] = trim((string) substr($line, $from, $to - $from));
            }

            if ($cells[0] !== '' && preg_match('/^Agency\b/', $cells[1])) {
                if ($row !== null) {
                    $rows[] = $row;
                }

                $row = $cells;
                continue;
            }

            if ($row === null) {
                continue;
            }

            foreach ($cells as $c => $cell) {
                if ($cell === '') {
                    continue;
                }

                $row[$c] = preg_match('/[a-z]-$/', $row[$c]) && !preg_match('/^(?:and|or)\b/', $cell) ? $row[$c].$cell : trim($row[$c].' '.$cell);
            }
        }

        if ($row !== null) {
            $rows[] = $row;
        }

        $controls = array();

        foreach ($rows as $n => $cells) {
            $clean = function (string $text): string {
                $text = preg_replace('/(?<=\S)\s\d{1,2}(?=[\s.,;]|$)/', '', $text);

                return trim(preg_replace('/\s+/', ' ', $text));
            };
            $name = $clean(preg_replace('/\s*\(New\s+Function\)/', '', $cells[0]));
            $id = $code.'-'.sprintf('%02d', $n + 1);
            $objectives = array();

            foreach (array(2 => 'Initial', 3 => 'Advanced', 4 => 'Optimal') as $c => $stage) {
                $objectives[] = array(
                    'id' => $id.'.'.strtolower($stage),
                    'text' => $clean($cells[$c]),
                    'stage' => $stage,
                    'source' => 'ztmm'
                );
            }

            $controls[] = array(
                'id' => $id,
                'number' => $id,
                'title' => $name,
                'text' => $clean($cells[1]),
                'description' => 'Traditional stage shown as the text; the Initial, Advanced and Optimal stages are the test points.',
                'type' => strpos($name, 'Capability') !== false || $code === 'CC' ? 'cross_cutting' : 'function',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives,
                'source' => 'ztmm'
            );
        }

        $families[] = array(
            'number' => $code,
            'code' => $code,
            'name' => $tables[$code][1],
            'controls' => $controls
        );
    }

    return $families;
};
