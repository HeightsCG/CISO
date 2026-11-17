<?php
/**
 * NIST SP 800-218A, Secure Software Development Practices for Generative AI and Dual-Use
 * Foundation Models: an SSDF Community Profile. Table 1 lists each SSDF practice and task with
 * an AI-specific priority (High, Medium, Low), recommendations (R), considerations (C), notes
 * (N) and informative references.
 *
 * Read from the committed pdftotext -layout extract by column position (each page's two-line
 * header gives the offsets). Families are the practices; controls are the tasks. Objectives
 * are the R items; C and N items and the informative references become guidance. All text is
 * NIST's.
 */
return function (): array {
    $lines = explode("\n", str_replace(array("\r", "\f"), '', file_get_contents(Source::path('nist800218a/NIST.SP.800-218A.layout.txt'))));
    $start = null;
    $end = null;

    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*Table 1\. SSDF Community Profile for AI Model Development\s*$/', $line)) {
            $start = $i;
        }

        if ($start !== null && trim($line) === 'References') {
            $end = $i;
            break;
        }
    }

    if ($start === null || $end === null) {
        throw new RuntimeException('800-218A: profile table not found');
    }

    $cols = null;
    $practice_text = '';
    $tasks = array();
    $task = null;

    for ($i = $start + 1; $i < $end; $i++) {
        $line = $lines[$i];

        if (preg_match('/^\s*Practice\s+Task\s+Priority\s*$/', $line)) {
            $above = $lines[$i - 1];
            $cols = array(
                strpos($line, 'Practice'),
                strpos($line, 'Task'),
                strpos($line, 'Priority'),
                strpos($above, 'Recommendations'),
                strpos($above, 'Informative')
            );
            continue;
        }

        if (preg_match('/^\s*Recommendations \[R\]|^\s*Notes \[N\] Specific|^\s*\d+\s*$|^(NIST SP 800-218A|July 2024)\s/', $line) || trim($line) === '' || $cols === null) {
            continue;
        }

        $cells = array();

        foreach ($cols as $c => $offset) {
            $from = $c === 0 ? 0 : max(0, $offset - 2);
            $to = isset($cols[$c + 1]) ? $cols[$c + 1] - 2 : strlen($line);
            $cells[$c] = trim((string) substr($line, $from, $to - $from));
        }

        if ($cells[0] !== '') {
            $practice_text .= ' '.$cells[0];
        }

        // Task and priority share one span: a long task line can run into the priority column.
        $span = trim((string) substr($line, $cols[1], $cols[3] - 2 - $cols[1]));

        if (preg_match('/^(.*?)\s{2,}(High|Medium|Low)$/', $span, $pm)) {
            $cells[1] = trim($pm[1]);
            $cells[2] = $pm[2];
        } else {
            $cells[1] = $span;
            $cells[2] = '';
        }

        if (preg_match('/^([A-Z]{2}\.\d+\.\d+): /', $cells[1], $m)) {
            if ($task !== null) {
                $tasks[] = $task;
            }

            $task = array(
                'id' => $m[1],
                'text' => substr($cells[1], strlen($m[0])),
                'priority' => $cells[2],
                'notes' => $cells[3],
                'refs' => $cells[4]
            );
            continue;
        }

        if ($task === null) {
            continue;
        }

        $join = function (string $left, string $right): string {
            return preg_match('/[a-z]-$/', $left) ? $left.$right : trim($left.' '.$right);
        };
        $task['text'] = $cells[1] === '' ? $task['text'] : $join($task['text'], $cells[1]);
        $task['priority'] = trim($task['priority'].' '.$cells[2]);
        $task['notes'] .= "\n".$cells[3];
        $task['refs'] = trim($task['refs'].' '.$cells[4]);
    }

    if ($task !== null) {
        $tasks[] = $task;
    }

    // Practice names and identifiers from the Practice column: "Define Security Requirements for Software Development (PO.1): ...".
    $practice_text = trim(preg_replace('/\s+/', ' ', $practice_text));
    preg_match_all('/(?:^|[.)]\s|\s)([A-Z][A-Za-z,\'’ -]+?) \(([A-Z]{2}\.\d+)\):/', $practice_text, $pm, PREG_SET_ORDER);
    $practices = array();

    foreach ($pm as $match) {
        $name = preg_replace('/^.*?(?:Prepare the Organization \(PO\)|Protect Software \(PS\)|Produce Well-Secured Software \(PW\)|Respond to Vulnerabilities \(RV\))\s*/', '', $match[1]);
        $practices[$match[2]] = trim($name);
    }

    $flat = function (string $text): string {
        $text = preg_replace('/(?<=[a-z])-\n(?=[a-z])/', '-', trim($text));

        return trim(preg_replace('/\s+/', ' ', $text));
    };

    $families = array();

    foreach ($tasks as $task) {
        $practice = preg_replace('/\.\d+$/', '', $task['id']);

        if (!isset($practices[$practice])) {
            throw new RuntimeException('800-218A: no practice name for '.$practice);
        }

        if (!in_array($task['priority'], array('High', 'Medium', 'Low'), true)) {
            throw new RuntimeException('800-218A: '.$task['id'].' priority "'.$task['priority'].'"');
        }

        $objectives = array();
        $guidance = array();

        foreach (preg_split('/\n(?=[RCN]\d+: )/', "\n".trim($task['notes'])) as $chunk) {
            if (!preg_match('/^([RCN]\d+): (.+)$/s', trim($chunk), $m)) {
                continue;
            }

            if ($m[1][0] === 'R') {
                $objectives[] = array(
                    'id' => $task['id'].'.'.$m[1],
                    'text' => $flat($m[2]),
                    'source' => 'sp800218a'
                );
            } else {
                $guidance[] = $m[1].': '.$flat($m[2]);
            }
        }

        if ($task['refs'] !== '') {
            $guidance[] = 'Informative references: '.$flat($task['refs']);
        }

        if (!isset($families[$practice])) {
            $families[$practice] = array(
                'number' => $practice,
                'code' => $practice,
                'name' => $practices[$practice],
                'controls' => array()
            );
        }

        $families[$practice]['controls'][] = array(
            'id' => $task['id'],
            'number' => $task['id'],
            'text' => $flat($task['text']),
            'description' => 'AI model development priority: '.$task['priority'].'.',
            'guidance' => implode("\n", $guidance),
            'type' => 'task',
            'priority' => $task['priority'],
            'weight' => null,
            'partial_weight' => null,
            'objectives' => $objectives,
            'source' => 'sp800218a'
        );
    }

    return array_values($families);
};
