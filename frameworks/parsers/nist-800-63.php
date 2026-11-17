<?php
/**
 * NIST SP 800-63-4 Digital Identity Guidelines (base volume and 63A, 63B, 63C).
 *
 * Every normative "SHALL" or "SHALL NOT" statement in the numbered sections of the four
 * volumes is an objective, taken word for word from the pdftotext -raw extracts committed
 * beside the PDFs. A numbered section that contains at least one such statement is a
 * control; each volume's top-level sections are the families. When a SHALL statement ends
 * with a colon, the list items that follow it are part of the same objective.
 */
/**
 * The SHALL statements in a section's lines. Lines are joined into sentences; numbered
 * and bulleted list items start new units; a SHALL sentence ending with a colon takes the
 * list items that follow it.
 */
if (!function_exists('nist_80063_shall_statements')) {
function nist_80063_shall_statements(array $lines): array
{
    $units = array();
    $current = '';
    $list = false;
    $previous = '';

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $item = preg_match('/^(?:•|\d{1,2}\.|[a-z]\.)\s+\S/u', $line) === 1;
        // A short line ends its paragraph: a capitalized line after it starts a new one.
        $break = $current !== '' && mb_strlen($previous) < 60 && preg_match('/^[A-Z“"]/u', $line) === 1 && preg_match('/[a-z,]$/', $previous) !== 1;
        $break = $break || ($current !== '' && $list && mb_strlen($previous) < 60 && preg_match('/^[A-Z“"]/u', $line) === 1);
        $previous = $line;

        if ($break && !$item) {
            $units[] = array($current, $list);
            $current = $line;
            $list = false;
            continue;
        }

        if ($item) {
            if ($current !== '') {
                $units[] = array($current, $list);
            }

            $current = $line;
            $list = true;
            continue;
        }

        if ($current === '') {
            $current = $line;
            $list = false;
        } elseif (substr($current, -1) === '-' && preg_match('/[a-z]-$/', $current)) {
            $current .= $line;
        } else {
            $current .= ' '.$line;
        }
    }

    if ($current !== '') {
        $units[] = array($current, $list);
    }

    // Split prose units into sentences; a list item stays whole, but prose that follows an item on the same unit splits off.
    $pieces = array();

    foreach ($units as $unit) {
        list($body, $is_item) = $unit;
        $protected = preg_replace_callback('/\b(e\.g|i\.e|Sec|Secs|Fig|Vol|No|vs|al|U\.S)\./', function ($m) {
            return str_replace('.', '§', $m[0]);
        }, $body);
        $sentences = preg_split('/(?<=[.:])\s+(?=[A-Z“"(\[])/u', $protected);

        foreach ($sentences as $n => $sentence) {
            $pieces[] = array(
                trim(str_replace('§', '.', $sentence)),
                $is_item && $n === 0
            );
        }
    }

    $statements = array();
    $open = null;

    foreach ($pieces as $piece) {
        list($sentence, $is_item) = $piece;

        if ($is_item && $open !== null) {
            $statements[$open] .= ($statements[$open][strlen($statements[$open]) - 1] === ':' ? ' ' : ' ').$sentence;
            continue;
        }

        $open = null;

        if (strpos($sentence, 'SHALL') === false) {
            continue;
        }

        $statements[] = $sentence;

        if (substr($sentence, -1) === ':') {
            $open = count($statements) - 1;
        }
    }

    return $statements;
}
}

return function (): array {
    $volumes = array(
        array('sp80063', 'nist80063/NIST.SP.800-63-4.pdf', '800-63-4', '63', 'SP 800-63', null),
        array('sp80063a', 'nist80063/NIST.SP.800-63A-4.pdf', '800-63A-4', '63A', 'SP 800-63A', 'Identity Proofing and Enrollment'),
        array('sp80063b', 'nist80063/NIST.SP.800-63B-4.pdf', '800-63B-4', '63B', 'SP 800-63B', 'Authentication and Authenticator Management'),
        array('sp80063c', 'nist80063/NIST.SP.800-63C-4.pdf', '800-63C-4', '63C', 'SP 800-63C', 'Federation and Assertions')
    );

    $families = array();

    foreach ($volumes as $volume) {
        list($source, $file, $docno, $prefix, $label, $subtitle) = $volume;

        $text = str_replace("\r", '', Source::pdf_text($file));
        $footer = '/^(?:[ivx]+|\d+)\f?NIST SP '.preg_quote($docno, '/').'\nJuly 2025\nDigital Identity Guidelines\n'.($subtitle === null ? '' : preg_quote($subtitle, '/').'\n').'/m';
        $text = preg_replace($footer, '', $text);
        $text = preg_replace('/(?<=\S)(?:[ivx]+|\d+)\f?NIST SP '.preg_quote($docno, '/').'\nJuly 2025\nDigital Identity Guidelines\n'.($subtitle === null ? '' : preg_quote($subtitle, '/').'\n').'/', "\n", $text);
        $lines = explode("\n", $text);

        // Headings in order, from the table of contents (an entry can wrap onto a second line).
        $toc_start = array_search('Table of Contents', $lines, true);
        $headings = array();
        $pending = '';

        for ($i = $toc_start + 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);

            if (strpos($line, 'References') === 0) {
                break;
            }

            if ($line === '') {
                continue;
            }

            $line = $pending === '' ? $line : $pending.' '.$line;

            if (!preg_match('/(?:(?:\s\.)+\s*|\s)\d+$/', $line)) {
                $pending = $line;
                continue;
            }

            $pending = '';
            $entry = trim(preg_replace('/(?:(?:\s\.)+\s*|\s)\d+$/', '', $line));

            if (preg_match('/^(\d+(?:\.\d+)*)\. (.+)$/', $entry, $m)) {
                $headings[] = array(
                    $m[1],
                    $m[2]
                );
            }
        }

        if (count($headings) < 10) {
            throw new RuntimeException($label.': table of contents not found');
        }

        // Locate each heading in the body, in order, after the table of contents.
        $at = $toc_start + 1;

        while (!preg_match('/^1\. /', trim($lines[$at])) || preg_match('/(?:\s\.)+\s*\d+$/', $lines[$at])) {
            $at++;
        }

        $positions = array();

        foreach ($headings as $h => $heading) {
            $want = $heading[0].'. '.$heading[1];

            for ($j = $at; $j < count($lines); $j++) {
                if (trim($lines[$j]) === $want) {
                    break;
                }

                // A long heading can wrap in the body as well.
                if (isset($lines[$j + 1]) && trim($lines[$j]).' '.trim($lines[$j + 1]) === $want) {
                    $lines[$j] = $want;
                    $lines[$j + 1] = '';
                    break;
                }
            }

            if ($j === count($lines)) {
                throw new RuntimeException($label.': heading not found in body: '.$want);
            }

            $positions[$h] = $j;
            $at = $j + 1;
        }

        $end = $at;

        while ($end < count($lines) && trim($lines[$end]) !== 'References') {
            $end++;
        }

        $groups = array();

        foreach ($headings as $h => $heading) {
            list($number, $title) = $heading;
            $from = $positions[$h] + 1;
            $to = isset($positions[$h + 1]) ? $positions[$h + 1] : $end;
            $top = explode('.', $number)[0];

            // Sub-headings deeper than the table of contents shows ("3.1.1.1. Password Authenticators") split the section further.
            $segments = array(array($number, $title, array()));

            foreach (array_slice($lines, $from, $to - $from) as $line) {
                if (preg_match('/^('.preg_quote($number, '/').'(?:\.\d+)+)\. ([A-Z][^.]{2,100})$/', trim($line), $sub)) {
                    $segments[] = array($sub[1], $sub[2], array());
                    continue;
                }

                $segments[count($segments) - 1][2][] = $line;
            }

            if (!isset($groups[$top])) {
                $top_title = $title;

                foreach ($headings as $candidate) {
                    if ($candidate[0] === $top) {
                        $top_title = $candidate[1];
                    }
                }

                $groups[$top] = array(
                    'number' => $prefix.'-'.$top,
                    'code' => $prefix.'-'.$top,
                    'name' => $top_title.' ('.$label.')',
                    'controls' => array()
                );
            }

            foreach ($segments as $segment) {
                list($seg_number, $seg_title, $seg_lines) = $segment;

                // Notations defines what SHALL means; it is not a requirement.
                if ($seg_title === 'Notations') {
                    continue;
                }

                $statements = nist_80063_shall_statements($seg_lines);

                if (count($statements) === 0) {
                    continue;
                }

                $id = $prefix.'-'.$seg_number;
                $objectives = array();

                foreach ($statements as $s => $statement) {
                    $objectives[] = array(
                        'id' => $id.'.'.($s + 1),
                        'text' => $statement,
                        'source' => $source
                    );
                }

                $groups[$top]['controls'][] = array(
                    'id' => $id,
                    'number' => $id,
                    'title' => $seg_title,
                    'text' => '',
                    'description' => 'The '.count($statements).' SHALL '.(count($statements) === 1 ? 'requirement' : 'requirements').' of '.$label.'-4 section '.$seg_number.', '.$seg_title.'.',
                    'type' => 'section',
                    'weight' => null,
                    'partial_weight' => null,
                    'objectives' => $objectives,
                    'source' => $source
                );
            }
        }

        foreach ($groups as $group) {
            if (count($group['controls']) > 0) {
                $families[] = $group;
            }
        }
    }

    return $families;
};
