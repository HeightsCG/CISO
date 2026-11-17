<?php
/**
 * NIST SP 800-88 Rev. 2 Guidelines for Media Sanitization. The publication states no SHALL
 * requirements; its guidance is expressed as what organizations should (and in places must)
 * do. The assessable items are the numbered sections of Section 3 (sanitization methods and
 * cryptographic erase) and Section 4 (the media sanitization program) that contain such
 * sentences; each "should", "must" or "shall" sentence is an objective, word for word from
 * the committed pdftotext -raw extract. Families are the second-level sections.
 */
require_once __DIR__.'/nist-statements-shared.php';

return function (): array {
    $text = str_replace("\r", '', Source::pdf_text('nist80088/NIST.SP.800-88r2.pdf'));
    $text = preg_replace('/\f?NIST SP 800-88r2 Guidelines for Media Sanitization\nSeptember 2025\n(?:[ivx]+|\d+)\n/', "\n", $text);
    $text = str_replace("\f", '', $text);
    // A footnote set mid-sentence: "modules7" + "7" + the note's line.
    $text = preg_replace('/([a-z])(\d{1,2})\n\2\n[^\n]+\n/', "$1\n", $text);

    // A footnote whose text follows its number on the same line ("owner17" + "17 The role of ...").
    // Its lines run wider than body text; a short closing line that does not continue the sentence belongs to it too.
    $out = array();
    $raw = explode("\n", $text);

    for ($i = 0; $i < count($raw); $i++) {
        if (isset($raw[$i + 1]) && preg_match('/[A-Za-z)\].](\d{1,2})$/', $raw[$i], $marker) && strpos($raw[$i + 1], $marker[1].' ') === 0) {
            $out[] = substr($raw[$i], 0, -strlen($marker[1]));
            $i++;

            while (isset($raw[$i + 1]) && mb_strlen($raw[$i + 1]) > 110) {
                $i++;
            }

            if (isset($raw[$i + 1]) && mb_strlen($raw[$i + 1]) < 60 && preg_match('/[.”]$/u', $raw[$i + 1])) {
                $i++;
            }

            continue;
        }

        $out[] = $raw[$i];
    }

    $text = implode("\n", $out);

    $start = strpos($text, "\n3. Summary of Sanitization Methods\n");
    $end = strpos($text, "\nReferences\n", $start);

    if ($start === false || $end === false) {
        throw new RuntimeException('800-88r2: body of sections 3 and 4 not found');
    }

    $lines = explode("\n", substr($text, $start + 1, $end - $start - 1));
    $sections = array();
    $current = null;

    foreach ($lines as $line) {
        if (preg_match('/^([34](?:\.\d+)*)\. ([A-Z][^.]{2,90})$/', trim($line), $m)) {
            $current = $m[1];
            $sections[$current] = array(
                'title' => $m[2],
                'lines' => array()
            );
            continue;
        }

        if ($current !== null) {
            $sections[$current]['lines'][] = $line;
        }
    }

    $families = array();

    foreach ($sections as $number => $section) {
        $parts = explode('.', $number);

        if (count($parts) < 2) {
            continue;
        }

        $family = $parts[0].'.'.$parts[1];

        if (!isset($families[$family])) {
            if (!isset($sections[$family])) {
                throw new RuntimeException('800-88r2: no heading for section '.$family);
            }

            $families[$family] = array(
                'number' => $family,
                'code' => $family,
                'name' => $sections[$family]['title'],
                'controls' => array()
            );
        }

        // Roles and responsibilities are stated as what each role is responsible for.
        $keyword = strpos($number, '4.7') === 0 ? '/\b(?:should|must|shall|responsible for|responsibility)\b/i' : '/\b(?:should|must|shall)\b/i';
        $statements = nist_statements($section['lines'], $keyword);

        if (count($statements) === 0) {
            continue;
        }

        $objectives = array();

        foreach ($statements as $s => $statement) {
            $objectives[] = array(
                'id' => $number.'.'.($s + 1),
                'text' => $statement,
                'source' => 'sp80088'
            );
        }

        $families[$family]['controls'][] = array(
            'id' => $number,
            'number' => $number,
            'title' => $section['title'],
            'text' => '',
            'description' => 'The '.count($statements).' '.(count($statements) === 1 ? 'statement' : 'statements').' of SP 800-88r2 section '.$number.', '.$section['title'].', on what organizations should do.',
            'type' => 'section',
            'weight' => null,
            'partial_weight' => null,
            'objectives' => $objectives,
            'source' => 'sp80088'
        );
    }

    return array_values(array_filter($families, function ($family) {
        return count($family['controls']) > 0;
    }));
};
