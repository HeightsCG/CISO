<?php
/**
 * NYDFS 23 NYCRR Part 500 (Second Amendment), from the DFS publication of the
 * amended regulation (pdftotext -raw extract committed next to the PDF).
 *
 * - One group: Part 500, Cybersecurity Requirements for Financial Services Companies.
 * - Controls: sections 500.2 to 500.17 (definitions, confidentiality, exemptions,
 *   enforcement and transition provisions are carried by meta rules, not scored).
 * - Objectives: lettered subsections, with their numbered and roman items folded into the text.
 * - class_a_only: on objectives that begin "Each class A company"; the control lists them in
 *   class_a_objectives so the flag survives loading (objective rows carry no flags).
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $raw = Source::pdf_text('nydfs/23-nycrr-500.pdf');

    // Page breaks arrive as a form feed followed by the next page number.
    $raw = preg_replace('/\f\d*/', "\n", $raw);
    $lines = preg_split('/\R/', $raw);
    $title = null;
    $sections = array();
    $current = null;

    foreach ($lines as $line) {
        $line = trim($line);

        if ($title === null && preg_match('/^CYBERSECURITY REQUIREMENTS FOR FINANCIAL SERVICES COMPANIES$/', $line)) {
            $title = Source::title_case($line);
        }

        if (preg_match('/^(500\.\d+)\s*([A-Z][^\n]*?)\.$/', $line, $m)) {
            $current = $m[1];
            $sections[$current] = array(
                'title' => $m[2],
                'lines' => array()
            );
            continue;
        }

        if ($current !== null && $line !== '') {
            $sections[$current]['lines'][] = $line;
        }
    }

    // Joins wrapped lines; a line ending in a hyphen ("third-" / "party") was a hyphenated word.
    $join = function (array $lines): string {
        $text = '';

        foreach ($lines as $line) {
            if ($text !== '' && substr($text, -1) === '-' && preg_match('/^[a-z]/', $line)) {
                $text .= $line;
            } else {
                $text .= ($text === '' ? '' : ' ').$line;
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $text));
    };

    $controls = array();

    foreach ($sections as $number => $section) {
        $n = (int) substr($number, 4);

        if ($n < 2 || $n > 17) {
            continue;
        }

        $intro = array();
        $subs = array();
        $letter = null;
        $in_roman = false;
        $deep = null;

        foreach ($section['lines'] as $line) {
            $token = preg_match('/^\(([a-z]+|\d+)\)/', $line, $m) ? $m[1] : null;
            $expected = $letter === null ? 'a' : chr(ord($letter) + 1);
            $is_sub = false;

            // Under a roman item, (a), (b), (c) ... are nested clauses (500.17(b)(1)(i)(a)), not subsections.
            $nested = $in_roman && $token !== null && ($token === 'a' || ($deep !== null && $token === chr(ord($deep) + 1)));

            if ($nested) {
                $deep = $token;
            } elseif ($token !== null && $token === $expected) {
                $is_sub = true;
            } elseif ($token !== null && preg_match('/^\d+$/', $token)) {
                $in_roman = false;
                $deep = null;
            } elseif ($token !== null && preg_match('/^[ivx]+$/', $token)) {
                $in_roman = true;
                $deep = null;
            }

            if ($is_sub) {
                $letter = $token;
                $in_roman = false;
                $deep = null;
                $subs[$letter] = array();
            }

            if ($letter === null) {
                $intro[] = $line;
            } else {
                $subs[$letter][] = $line;
            }
        }

        $objectives = array();
        $class_a = array();
        $all_lines = $intro;

        foreach ($subs as $l => $sub_lines) {
            $all_lines = array_merge($all_lines, $sub_lines);
            $text = preg_replace('/^\('.$l.'\)\s*/', '', $join($sub_lines));
            $objective = array(
                'id' => $number.'('.$l.')',
                'text' => $text
            );

            // A subsection that continues the section's lead-in ("... address ... the following areas:").
            if (ObjectiveText::introduces_list($join($intro))) {
                $objective['description'] = ObjectiveText::complete($join($intro), $text);
            } elseif (ObjectiveText::trailing_connector($text)) {
                $objective['description'] = ObjectiveText::sentence($text);
            }

            if (preg_match('/^Each class A company\b/', $text)) {
                $objective['class_a_only'] = true;
                $class_a[] = $objective['id'];
            }

            $objectives[] = $objective;
        }

        $controls[] = array(
            'id' => $number,
            'number' => $number,
            'title' => $section['title'],
            'text' => $join($all_lines),
            'class_a_objectives' => $class_a,
            'weight' => null,
            'partial_weight' => null,
            'objectives' => $objectives
        );
    }

    if ($title === null || count($controls) !== 16) {
        throw new RuntimeException('Unexpected structure in the DFS text');
    }

    return array(
        array(
            'number' => '500',
            'code' => '',
            'name' => $title,
            'controls' => $controls
        )
    );
};
