<?php
/**
 * HIPAA Privacy Rule, 45 CFR 164 Subpart E, from the eCFR XML snapshot.
 *
 * - Groups: the fourteen sections that set standards, 164.502 to 164.530. Left out:
 *   164.500 (applicability), 164.501 (definitions), 164.532 (transition provisions for
 *   arrangements made before the compliance dates), 164.534 (compliance dates, all past)
 *   and 164.535 (severability).
 * - Controls: every paragraph the rule heads "Standard" or "Implementation
 *   specification(s)", identified by the citation of that paragraph. Specifications
 *   carry `parent`, the standard before them in the same section.
 * - A heading printed on the first sub-paragraph of an otherwise empty paragraph
 *   ("(3)(i) Implementation specification: Unemancipated minors.") governs the whole
 *   paragraph, so the control is (3) and (i), (ii), (iii) are its objectives.
 * - Objectives: the paragraphs directly beneath a control; deeper paragraphs stay in
 *   the objective's text with their own labels.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $xml = simplexml_load_file(Source::path('hipaa/45-cfr-164-subpart-e.xml'));
    $sections = array(
        '164.502',
        '164.504',
        '164.506',
        '164.508',
        '164.509',
        '164.510',
        '164.512',
        '164.514',
        '164.520',
        '164.522',
        '164.524',
        '164.526',
        '164.528',
        '164.530'
    );
    $romans = array(
        'i',
        'ii',
        'iii',
        'iv',
        'v',
        'vi',
        'vii',
        'viii',
        'ix',
        'x',
        'xi',
        'xii',
        'xiii',
        'xiv',
        'xv',
        'xvi',
        'xvii',
        'xviii',
        'xix',
        'xx'
    );
    $label = '/^\((⟦?)([a-zA-Z0-9]{1,5})⟧?\)\s*/u';
    $families = array();

    $squash = function (string $text): string {
        return trim(preg_replace('/\s+/u', ' ', $text));
    };

    // Marks italic runs with ⟦ ⟧ so headings and italic paragraph labels survive tag stripping.
    $plain = function (SimpleXMLElement $p) use ($squash): string {
        $inner = preg_replace('/^<P>|<\/P>$/', '', $p->asXML());
        $inner = str_replace(array('<I>', '</I>'), array('⟦', '⟧'), $inner);

        return $squash(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    };

    $unmark = function (string $text): string {
        return str_replace(array('⟦', '⟧'), '', $text);
    };

    // The label that may follow $previous at a level; '' asks for the first one.
    $next = function (int $level, string $previous) use ($romans): string {
        if ($level === 0 || $level === 3) {
            $first = $level === 0 ? 'a' : 'A';

            return $previous === '' ? $first : chr(ord($previous) + 1);
        }

        if ($level === 1 || $level === 4) {
            return (string) ((int) $previous + 1);
        }

        return $previous === '' ? 'i' : $romans[array_search($previous, $romans, true) + 1];
    };

    foreach ($xml->xpath('//DIV8') as $div) {
        $number = (string) $div['N'];

        if (!in_array($number, $sections, true)) {
            continue;
        }

        $head = Source::normalise(html_entity_decode((string) $div->HEAD, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        $name = rtrim(trim(preg_replace('/^§\s*'.preg_quote($number, '/').'/u', '', $head)), '.');
        $nodes = array();
        $stack = array();
        $current = array(
            '',
            '',
            '',
            '',
            '',
            ''
        );

        $paragraphs = array();

        foreach ($div->P as $p) {
            $paragraphs[] = $plain($p);
        }

        foreach ($paragraphs as $position => $rest) {

            // eCFR prints 164.502(b)(1) without its "(1)"; the paragraph after it is "(2)".
            if ($number === '164.502' && strpos($rest, '(b) ⟦Standard: Minimum necessary⟧— ⟦Minimum necessary applies.⟧') === 0) {
                $rest = str_replace('— ⟦Minimum necessary applies.⟧', '—(1) ⟦Minimum necessary applies.⟧', $rest);
            }

            // "(b)-(d) [Reserved]": nothing to carry; the next letter follows the range.
            if (preg_match('/^\([a-z]\)-\(([a-z])\) \[Reserved\]$/', $rest, $m)) {
                $current = array(
                    $m[1],
                    '',
                    '',
                    '',
                    '',
                    ''
                );
                $stack = array();
                continue;
            }

            if (!preg_match($label, $rest)) {
                // Flush text and definitions continue the paragraph above; a section's opening sentence is not a duty.
                if (count($stack) > 0) {
                    $nodes[$stack[count($stack) - 1]]['text'] .= ' '.$unmark($rest);
                }

                continue;
            }

            $leading = true;

            while (preg_match($label, $rest, $m)) {
                $token = $m[2];
                $italic = $m[1] !== '';

                if ($italic) {
                    $level = ctype_digit($token) ? 4 : 5;
                } elseif (ctype_digit($token) && $current[3] !== '' && $token === $next(4, $current[4])) {
                    // eCFR does not always italicise the numerals beneath (A): "(A) ... (1) ... (2) ...".
                    if ($token === $next(1, $current[1])) {
                        throw new RuntimeException($number.': ('.$token.') after ('.$current[3].') could sit at either numbered level');
                    }

                    $level = 4;
                } elseif (ctype_digit($token)) {
                    $level = 1;
                } elseif (ctype_upper($token)) {
                    $level = 3;
                } elseif (strlen($token) > 1) {
                    $level = 2;
                } elseif ($token === 'i' && !$leading) {
                    // A second label in one paragraph ("(2) Heading—(i) ...") is always the deeper level.
                    $level = 2;
                } elseif ($token === 'i' && $current[0] === 'h' && $current[1] !== '' && $current[2] === '') {
                    // (i) after (h)(1) is a numeral when (ii) follows it and a letter when (1) or (j) does.
                    $following = '';

                    foreach (array_slice($paragraphs, $position + 1) as $later) {
                        if (preg_match($label, $later, $f)) {
                            $following = $f[2];
                            break;
                        }
                    }

                    if ($following === 'ii') {
                        $level = 2;
                    } elseif ($following === '1' || $following === 'j') {
                        $level = 0;
                    } else {
                        throw new RuntimeException($number.': (i) after (h)('.$current[1].') could be a letter or a numeral');
                    }
                } elseif (($token === 'i' && $current[1] !== '' && $current[2] === '') || ($token === 'v' && $current[2] === 'iv') || ($token === 'x' && $current[2] === 'ix')) {
                    $level = 2;
                } else {
                    $level = 0;
                }

                if ($token !== $next($level, $current[$level])) {
                    throw new RuntimeException($number.': ('.$token.') does not follow ('.$current[$level].') at level '.$level.' in '.implode('/', $current).': '.substr($rest, 0, 80));
                }

                $current[$level] = $token;
                $leading = false;

                for ($i = $level + 1; $i < 6; $i++) {
                    $current[$i] = '';
                }

                $rest = substr($rest, strlen($m[0]));
                $title = '';

                if (preg_match('/^⟦([^⟧]*)⟧\s*(?:—\s*)?/u', $rest, $t)) {
                    $title = trim($t[1]);
                    $rest = substr($rest, strlen($t[0]));
                }

                while (count($stack) > 0 && $nodes[$stack[count($stack) - 1]]['level'] >= $level) {
                    array_pop($stack);
                }

                $parent = count($stack) === 0 ? null : $stack[count($stack) - 1];
                $index = count($nodes);
                $nodes[] = array(
                    'label' => $token,
                    'level' => $level,
                    'title' => $title,
                    'text' => '',
                    'parent' => $parent,
                    'children' => array(),
                    'path' => ($parent === null ? '' : $nodes[$parent]['path']).'('.$token.')'
                );

                if ($parent !== null) {
                    $nodes[$parent]['children'][] = $index;
                }

                $stack[] = $index;

                if (!preg_match($label, $rest)) {
                    $nodes[$index]['text'] = $unmark($rest);
                    break;
                }
            }
        }

        $is_heading = function (string $title): bool {
            return preg_match('/^(Standard|Implementation specifications?)\b/', $title) === 1;
        };

        // A node's own heading and text followed by everything beneath it, labels kept.
        $render = function (int $index, bool $with_title = true) use (&$render, &$nodes, $squash): string {
            $node = $nodes[$index];
            $text = trim(($with_title && $node['title'] !== '' ? $node['title'].' ' : '').$node['text']);

            foreach ($node['children'] as $child) {
                $text .= ' ('.$nodes[$child]['label'].') '.$render($child);
            }

            return $squash($text);
        };

        $controls = array();
        $standard = '';

        foreach ($nodes as $index => $node) {
            if (!$is_heading($node['title'])) {
                continue;
            }

            $owner = $index;
            $untitled = null;
            $parent = $node['parent'];

            // The heading sits on the first sub-paragraph of an empty paragraph whose other sub-paragraphs have none.
            if ($parent !== null && $nodes[$parent]['title'] === '' && trim($nodes[$parent]['text']) === '' && $nodes[$parent]['children'][0] === $index && count($nodes[$parent]['children']) > 1) {
                $others = 0;

                foreach (array_slice($nodes[$parent]['children'], 1) as $sibling) {
                    $first = $nodes[$sibling]['children'][0] ?? null;
                    $inherits = $nodes[$sibling]['title'] === '' && trim($nodes[$sibling]['text']) === '' && $first !== null && $is_heading($nodes[$first]['title']);
                    $others += $is_heading($nodes[$sibling]['title']) || $inherits ? 1 : 0;
                }

                if ($others === 0) {
                    $owner = $parent;
                    $untitled = $index;
                }
            }

            $id = $number.$nodes[$owner]['path'];
            $heading = rtrim($node['title'], '.:');

            if (preg_match('/^Standard(?::\s*(.+))?$/', $heading, $s)) {
                $type = 'standard';
                $title = ucfirst($s[1] ?? $name);
                $standard = $id;
            } elseif (preg_match('/^Implementation specifications?:\s*(.+)$/', $heading, $s)) {
                $type = 'implementation_specification';
                $title = ucfirst($s[1]);
            } else {
                throw new RuntimeException('Unrecognised heading at '.$id.': '.$node['title']);
            }

            $objectives = array();
            $text = $nodes[$owner]['text'];

            foreach ($nodes[$owner]['children'] as $child) {
                $own = $render($child, $child !== $untitled);
                $objective = array(
                    'id' => $number.$nodes[$child]['path'],
                    'text' => $own
                );

                // A list item under a lead-in ("... excludes the following direct identifiers: (i) Names;").
                if (ObjectiveText::introduces_list($nodes[$owner]['text']) && count($nodes[$child]['children']) === 0 && $nodes[$child]['title'] === '') {
                    $lead = trim($nodes[$owner]['text']);
                    $item = ObjectiveText::item($own);

                    // After a dash the item continues the sentence ("A covered entity— (1) May not ...").
                    if (mb_substr($lead, -1) === '—') {
                        $item = ObjectiveText::lower_first($item);
                    }

                    $objective['description'] = ObjectiveText::sentence(rtrim($lead, ' —').' '.$item);
                }

                $objectives[] = $objective;
                $text .= ' ('.$nodes[$child]['label'].') '.$own;
            }

            $controls[] = array(
                'id' => $id,
                'number' => $id,
                'title' => $title,
                'text' => $squash($text),
                'type' => $type,
                'requirement_type' => $type,
                'parent' => $type === 'implementation_specification' ? $standard : '',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives
            );
        }

        unset($render);
        $families[] = array(
            'number' => $number,
            'code' => '',
            'name' => $name,
            'controls' => $controls
        );
    }

    return $families;
};
