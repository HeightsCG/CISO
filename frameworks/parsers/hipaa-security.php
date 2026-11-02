<?php
/**
 * HIPAA Security Rule, 45 CFR 164 Subpart C, from the eCFR XML snapshot.
 *
 * - Groups: the five sections that adopt standards (164.306(c)): 164.308, .310, .312, .314, .316.
 * - Controls: every standard and every implementation specification, identified by the
 *   paragraph citation where its italic title appears. Specifications carry `parent`
 *   (their standard) and `implementation` (required/addressable) exactly as the title says.
 * - Objectives: the unlabelled sub-paragraphs a standard or specification lists
 *   (e.g. the three things a business associate contract must provide).
 */
return function (): array {
    $xml = simplexml_load_file(Source::path('hipaa/45-cfr-164-subpart-c.xml'));
    $sections = array(
        '164.308',
        '164.310',
        '164.312',
        '164.314',
        '164.316'
    );
    $families = array();

    // Collapses whitespace but keeps the rule's own punctuation (em dashes, apostrophes).
    $squash = function (string $text): string {
        return trim(preg_replace('/\s+/u', ' ', $text));
    };

    // Marks italic runs with ⟦ ⟧ so titles survive tag stripping.
    $plain = function (SimpleXMLElement $p) use ($squash): string {
        $inner = preg_replace('/^<P>|<\/P>$/', '', $p->asXML());
        $inner = str_replace(array('<I>', '</I>'), array('⟦', '⟧'), $inner);

        return $squash(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    };

    // Paragraph level of a token: (a) 0, (1) 1, (i) 2, (A) 3. Letters stop at (e) here, so i/v/x are roman.
    $level_of = function (string $token): int {
        if (preg_match('/^\d+$/', $token)) {
            return 1;
        }

        if (preg_match('/^[ivx]+$/', $token)) {
            return 2;
        }

        if (preg_match('/^[a-h]$/', $token)) {
            return 0;
        }

        return 3;
    };

    $strip = function (string $text): string {
        return trim(str_replace(array('⟦', '⟧'), '', $text));
    };

    foreach ($xml->xpath('//DIV8') as $div) {
        $number = (string) $div['N'];

        if (!in_array($number, $sections, true)) {
            continue;
        }

        $head = Source::normalise(html_entity_decode((string) $div->HEAD, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        $name = rtrim(trim(preg_replace('/^§\s*'.preg_quote($number, '/').'/u', '', $head)), '.');
        $controls = array();
        $levels = array(
            '',
            '',
            '',
            ''
        );
        $group = null;
        $standard = null;
        $current = null;
        $queue = array();

        foreach ($div->P as $p) {
            $queue[] = $plain($p);
        }

        while (count($queue) > 0) {
            $rest = array_shift($queue);
            $depth = -1;

            while (preg_match('/^\(([a-zA-Z0-9]{1,4})\)\s*/', $rest, $m)) {
                $depth = $level_of($m[1]);
                $levels[$depth] = '('.$m[1].')';

                for ($i = $depth + 1; $i < 4; $i++) {
                    $levels[$i] = '';
                }

                $rest = substr($rest, strlen($m[0]));
            }

            if ($depth < 0) {
                continue;
            }

            $path = implode('', $levels);
            $id = $number.$path;

            // A status heading ("Implementation specifications (Required)") governs the paragraphs beneath it.
            if ($group !== null && strpos($path, $group['path']) !== 0) {
                $group = null;
            }

            if (!preg_match('/^⟦([^⟧]*)⟧\s*(.*)$/su', $rest, $t)) {
                if ($current === null) {
                    continue;
                }

                $c = &$controls[$current];

                // A sibling paragraph (164.308(b)(2)) makes the control's own paragraph an element too.
                if (count($c['objectives']) === 0 && strlen($path) <= strlen($c['path'])) {
                    $c['objectives'][] = array(
                        'id' => $c['id'],
                        'text' => $c['body']
                    );
                }

                $c['objectives'][] = array(
                    'id' => $id,
                    'text' => $strip($rest)
                );
                $c['text'] .= ' '.$levels[$depth].' '.$strip($rest);
                unset($c);
                continue;
            }

            $title = trim($t[1]);
            $body = trim($t[2]);
            $status = null;
            $type = null;

            if (preg_match('/^Standard:\s*(.+?)\.?$/', $title, $s)) {
                $type = 'standard';
                $title = $s[1];
            } elseif (preg_match('/^Implementation specifications?(?:\s*\((Required|Addressable)\))?[:.]?\s*(.*?)$/', $title, $s)) {
                $named = trim($s[2]);

                if (preg_match('/^(.+?)\s*\((Required|Addressable)\)\.?$/', $named, $n)) {
                    $type = 'implementation_specification';
                    $title = $n[1];
                    $status = strtolower($n[2]);
                } elseif ($named === '') {
                    $group = array(
                        'path' => $path,
                        'status' => $s[1] === '' ? null : strtolower($s[1])
                    );
                    $body = ltrim($body, "—-. \t");

                    // The heading may run straight into its first specification: "(Required)—(i) Business ...".
                    if (preg_match('/^\(/', $body)) {
                        array_unshift($queue, $body);
                        continue;
                    }

                    // Otherwise text after a status heading is itself the specification (164.314(b)(2)).
                    if ($body === '' || $group['status'] === null) {
                        continue;
                    }

                    $type = 'implementation_specification';
                    $title = 'Implementation specifications';
                    $status = $group['status'];
                    $group = array(
                        'path' => $path.'(',
                        'status' => null
                    );
                } else {
                    throw new RuntimeException('Unrecognised specification heading at '.$id);
                }
            } elseif (preg_match('/^(.+?)\s*\((Required|Addressable)\)\.?$/', $title, $s)) {
                $type = 'implementation_specification';
                $title = $s[1];
                $status = strtolower($s[2]);
            } elseif ($group !== null && $group['status'] !== null) {
                $type = 'implementation_specification';
                $title = rtrim($title, '.');
                $status = $group['status'];
            } else {
                // An italic heading outside any specification list names a standard (164.308(b)(1); Appendix A agrees).
                $type = 'standard';
                $title = rtrim($title, '.');
            }

            // "Standard: Documentation. (i) Maintain ..." - the first element shares the heading's paragraph.
            $tail = '';

            if (preg_match('/^\(([a-zA-Z0-9]{1,4})\)\s/', $body)) {
                $tail = $body;
                $body = '';
            }

            if ($type === 'standard') {
                $standard = $id;
            }

            $controls[$id] = array(
                'id' => $id,
                'number' => $id,
                'title' => $title,
                'text' => $body,
                'type' => $type,
                'requirement_type' => $type,
                'implementation' => $status,
                'parent' => $type === 'standard' ? '' : $standard,
                'weight' => null,
                'partial_weight' => null,
                'objectives' => array(),
                'path' => $path,
                'body' => $body
            );
            $current = $id;

            if ($tail !== '') {
                array_unshift($queue, $tail);
            }
        }

        foreach ($controls as $id => $control) {
            $control['text'] = $squash($control['text']);
            unset($control['path'], $control['body']);
            $controls[$id] = $control;
        }

        $families[] = array(
            'number' => $number,
            'code' => '',
            'name' => $name,
            'controls' => array_values($controls)
        );
    }

    return $families;
};
