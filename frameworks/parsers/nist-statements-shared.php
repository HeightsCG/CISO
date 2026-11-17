<?php
/**
 * Shared by the NIST guideline parsers that take a publication's normative sentences as objectives.
 */
/**
 * The normative statements (sentences matching $keyword) in a section's lines. Lines are joined into sentences; numbered
 * and bulleted list items start new units; a SHALL sentence ending with a colon takes the
 * list items that follow it.
 */
if (!function_exists('nist_statements')) {
function nist_statements(array $lines, string $keyword = '/SHALL/'): array
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

        if (!preg_match($keyword, $sentence)) {
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
