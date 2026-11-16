<?php
/**
 * NIST AI Risk Management Framework 1.0 Core.
 *
 * - Groups are the 19 Categories (GOVERN 1 ...), each carrying its Function. The AI RMF
 *   gives Categories no short title, only an outcome statement, so the statement is the
 *   group's name. It is read from Tables 1 to 4 of NIST AI 100-1.
 * - Controls are the 72 Subcategories (GOVERN 1.1 ...). Their text is the outcome statement
 *   printed in Tables 1 to 4. NIST's AI RMF Playbook data words 21 of the 72 statements
 *   differently from the publication, so it supplies only the guidance (its suggested actions).
 * - The tables are two columns in the layout-preserving text extract: Category statements
 *   on the left, Subcategories on the right. Each column is read as its own stream, so a
 *   Category statement that runs over a page break joins up again.
 */
return function (): array {
    $playbook = json_decode(file_get_contents(Source::path('ai-rmf/playbook.json')), true);
    $text = Source::pdf_text('ai-rmf/NIST.AI.100-1.pdf');
    $functions = array(
        'GOVERN',
        'MAP',
        'MEASURE',
        'MANAGE'
    );
    $label = '(?:'.implode('|', $functions).') \d(?:\.\d+)?';
    $actions = array();

    foreach ($playbook as $entry) {
        $list = array();

        foreach (preg_split('/\R/', $entry['section_actions']) as $line) {
            $line = Source::normalise(preg_replace('/^\s*[*-]\s*/', '', $line));

            if ($line !== '') {
                $list[] = $line;
            }
        }

        $actions[$entry['title']] = array(
            'category' => str_replace('-', ' ', $entry['category']),
            'guidance' => implode("\n", $list)
        );
    }

    $columns = array(
        'left' => array(),
        'right' => array()
    );

    // Pages are separated by form feeds. A table runs from its "Table n:" heading to the
    // next numbered section heading. The Subcategory column starts where the nearest
    // Subcategory label above on the page starts (below, for lines ahead of the first
    // label), give or take the one column a continuation line may sit to its left.
    foreach (explode("\f", $text) as $page) {
        $lines = preg_split('/\R/', $page);
        $starts = array();

        foreach ($lines as $n => $line) {
            if (preg_match('/^(.*?)(?:'.implode('|', $functions).') \d\.\d+:/', $line, $m)) {
                $starts[$n] = strlen($m[1]);
            }
        }

        if (count($starts) === 0) {
            continue;
        }

        $inside = false;

        foreach ($lines as $n => $line) {
            $line = rtrim($line);

            if (preg_match('/^\s*Table [1-4]: Categories and subcategories for the /', $line)) {
                $inside = true;
                continue;
            }

            if (preg_match('/^\s*(5\.[2-4]|6\.)\s+[A-Z]/', $line)) {
                $inside = false;
            }

            if (!$inside || trim($line) === '' || preg_match('/^\s*(Continued on next page|Page \d+|NIST AI 100-1\s+AI RMF 1\.0|Categories\s+Subcategories)$/', $line)) {
                continue;
            }

            $start = null;

            foreach ($starts as $at => $column) {
                if ($at <= $n || $start === null) {
                    $start = $column;
                }
            }

            $cut = isset($starts[$n]) ? $start : $start - 1;
            $left = trim(substr($line, 0, $cut));
            $right = trim((string) substr($line, $cut));

            if ($left !== '') {
                $columns['left'][] = $left;
            }

            if ($right !== '') {
                $columns['right'][] = $right;
            }
        }
    }

    // A word broken at a line end keeps its hyphen only when the publication also prints it hyphenated on one line.
    $join = function (array $lines) use ($text): string {
        $joined = '';

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));

            if (preg_match('/(\w+)-$/', $joined, $a) && preg_match('/^([a-z]+)/', $line, $b)) {
                $joined = stripos($text, $a[1].'-'.$b[1]) !== false ? $joined.$line : substr($joined, 0, -1).$line;
                continue;
            }

            $joined .= ($joined === '' ? '' : ' ').$line;
        }

        return Source::normalise($joined);
    };

    $statements = function (array $lines) use ($label, $join): array {
        $found = array();
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^('.$label.'):\s*(.*)$/', $line, $m)) {
                $current = $m[1];
                $found[$current] = $m[2] === '' ? array() : array($m[2]);
            } elseif ($current !== null) {
                $found[$current][] = $line;
            }
        }

        foreach ($found as $id => $parts) {
            $found[$id] = $join($parts);
        }

        return $found;
    };

    $families = array();

    foreach ($statements($columns['left']) as $id => $statement) {
        if (strpos($id, '.') !== false) {
            throw new RuntimeException('Subcategory '.$id.' was read from the Category column');
        }

        $function = explode(' ', $id)[0];
        $families[$id] = array(
            'number' => $id,
            'code' => $id,
            'name' => $statement,
            'function' => $function,
            'function_code' => $function,
            'controls' => array()
        );
    }

    foreach ($statements($columns['right']) as $id => $statement) {
        $category = substr($id, 0, strpos($id, '.'));

        if (!isset($families[$category])) {
            throw new RuntimeException('Subcategory '.$id.' has no category');
        }

        if (!isset($actions[$id]) || $actions[$id]['category'] !== $category) {
            throw new RuntimeException('Subcategory '.$id.' is not in the Playbook data under '.$category);
        }

        $families[$category]['controls'][] = array(
            'id' => $id,
            'number' => $id,
            'text' => $statement,
            'guidance' => $actions[$id]['guidance'],
            'type' => 'subcategory',
            'weight' => null,
            'partial_weight' => null,
            'objectives' => array()
        );
        unset($actions[$id]);
    }

    if (count($actions) > 0) {
        throw new RuntimeException('In the Playbook data but not in AI 100-1: '.implode(', ', array_keys($actions)));
    }

    return array_values($families);
};
