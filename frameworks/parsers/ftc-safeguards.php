<?php
/**
 * FTC Safeguards Rule, 16 CFR Part 314, from the eCFR XML snapshot.
 *
 * - One group: § 314.4 Elements (the elements every information security program must include).
 * - Controls: the lettered elements 314.4(a) to (j). Text is the element's full text.
 * - Objectives: each numbered paragraph under an element (e.g. 314.4(c)(1) to (8)), with any
 *   roman-numeral items beneath it folded into its text.
 * - small_institution_exempt: elements § 314.6 lifts for institutions with customer
 *   information on fewer than 5,000 consumers (whole elements only; paragraphs (b)(1)
 *   and (d)(2) are listed in the applicability rule).
 */
return function (): array {
    $xml = simplexml_load_file(Source::path('ftc/16-cfr-314.xml'));
    $squash = function (string $text): string {
        return trim(preg_replace('/\s+/u', ' ', $text));
    };
    $section = $xml->xpath('//DIV8[@N="314.4"]')[0];
    $head = $squash(html_entity_decode((string) $section->HEAD, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    $exceptions = $xml->xpath('//DIV8[@N="314.6"]')[0];
    $exempt_text = $squash(strip_tags($exceptions->P->asXML()));

    // "Section 314.4(b)(1), (d)(2), (h), and (i) do not apply ..." - single-letter citations are whole elements.
    if (!preg_match('/^Section 314\.4(.+?) do not apply to financial institutions that maintain customer information concerning fewer than five thousand consumers\.$/', $exempt_text, $m)) {
        throw new RuntimeException('Unexpected wording of 314.6');
    }

    preg_match_all('/\(([a-z])\)(?!\()/', $m[1], $whole);
    $controls = array();
    $letter = null;
    $number = null;

    foreach ($section->P as $p) {
        $rest = $squash(html_entity_decode(strip_tags($p->asXML()), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        $tokens = array();

        while (preg_match('/^\(([a-z0-9]{1,4})\)\s*/', $rest, $t)) {
            $tokens[] = $t[1];
            $rest = substr($rest, strlen($t[0]));
        }

        if (count($tokens) === 0) {
            continue;
        }

        $marker = '';

        foreach ($tokens as $token) {
            // Letters run (a) to (j); "(i)" is the ninth element only when (h) came before it at the top level.
            $is_letter = preg_match('/^[a-j]$/', $token) && ($letter === null || $token === chr(ord($letter) + 1)) && ($token !== 'i' || $letter === 'h');

            if ($is_letter) {
                $letter = $token;
                $number = null;
                $controls[$letter] = array(
                    'id' => '314.4('.$letter.')',
                    'number' => '314.4('.$letter.')',
                    'text' => '',
                    'small_institution_exempt' => in_array($letter, $whole[1], true),
                    'weight' => null,
                    'partial_weight' => null,
                    'objectives' => array()
                );
            } elseif (preg_match('/^\d+$/', $token)) {
                $number = $token;
                $controls[$letter]['objectives'][$number] = array(
                    'id' => '314.4('.$letter.')('.$number.')',
                    'text' => ''
                );
            } else {
                $marker .= '('.$token.') ';
            }
        }

        $piece = trim($marker.$rest);

        if ($number === null) {
            $controls[$letter]['text'] = trim($controls[$letter]['text'].' '.$piece);
            continue;
        }

        $objective = &$controls[$letter]['objectives'][$number];
        $objective['text'] = trim($objective['text'].' '.$piece);
        unset($objective);
    }

    // Full element text: lead paragraph followed by each numbered paragraph with its marker.
    foreach ($controls as $letter => $control) {
        foreach ($control['objectives'] as $n => $objective) {
            $control['text'] = trim($control['text'].' ('.$n.') '.$objective['text']);
        }

        $control['objectives'] = array_values($control['objectives']);
        $controls[$letter] = $control;
    }

    return array(
        array(
            'number' => '314.4',
            'code' => '',
            'name' => rtrim(trim(preg_replace('/^§\s*314\.4/u', '', $head)), '.'),
            'controls' => array_values($controls)
        )
    );
};
