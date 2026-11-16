<?php
/**
 * HIPAA Breach Notification Rule, 45 CFR 164 Subpart D, from the eCFR XML snapshot.
 *
 * - Groups: the six sections that impose duties: 164.404, .406, .408, .410, .412, .414.
 *   164.400 (applicability) and 164.402 (definitions) impose none.
 * - Controls: each lettered paragraph, typed from its italic heading: `standard`,
 *   `implementation_specification`, or `requirement` (164.414, which the rule does not label).
 *   Specifications carry `parent`, the standard of their section.
 * - 164.412 has no lettered headings: the section is one control and its two
 *   alternatives, (a) and (b), are its objectives.
 * - Objectives: the numbered paragraphs directly beneath a control; deeper paragraphs
 *   stay in the objective's text with their own labels.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $xml = simplexml_load_file(Source::path('hipaa/45-cfr-164-subpart-d.xml'));
    $sections = array(
        '164.404',
        '164.406',
        '164.408',
        '164.410',
        '164.412',
        '164.414'
    );
    $families = array();

    $squash = function (string $text): string {
        return trim(preg_replace('/\s+/u', ' ', $text));
    };

    // Marks italic runs with ⟦ ⟧ so headings survive tag stripping.
    $plain = function (SimpleXMLElement $p) use ($squash): string {
        $inner = preg_replace('/^<P>|<\/P>$/', '', $p->asXML());
        $inner = str_replace(array('<I>', '</I>'), array('⟦', '⟧'), $inner);

        return $squash(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    };

    // Paragraph level of a label: (a) 0, (1) 1, (i) 2, (A) 3. Letters stop at (d) here, so i/v/x are roman.
    $level_of = function (string $label): int {
        if (preg_match('/^\d+$/', $label)) {
            return 1;
        }

        if (preg_match('/^[ivx]+$/', $label)) {
            return 2;
        }

        if (preg_match('/^[a-h]$/', $label)) {
            return 0;
        }

        return 3;
    };

    // A node's own heading and text followed by everything beneath it, labels kept.
    $render = function (array $node) use (&$render, $squash): string {
        $text = trim(($node['title'] === '' ? '' : $node['title'].' ').$node['text']);

        foreach ($node['children'] as $child) {
            $text .= ' ('.$child['label'].') '.$render($child);
        }

        return $squash($text);
    };

    foreach ($xml->xpath('//DIV8') as $div) {
        $number = (string) $div['N'];

        if (!in_array($number, $sections, true)) {
            continue;
        }

        $head = Source::normalise(html_entity_decode((string) $div->HEAD, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        $name = rtrim(trim(preg_replace('/^§\s*'.preg_quote($number, '/').'/u', '', $head)), '.');
        $lead = '';
        $roots = array();
        $stack = array();

        foreach ($div->P as $p) {
            $rest = $plain($p);

            if (!preg_match('/^\([a-zA-Z0-9]{1,4}\)/', $rest)) {
                $lead = str_replace(array('⟦', '⟧'), '', $rest);
                continue;
            }

            while (preg_match('/^\(([a-zA-Z0-9]{1,4})\)\s*/', $rest, $m)) {
                $rest = substr($rest, strlen($m[0]));
                $level = $level_of($m[1]);
                $title = '';

                if (preg_match('/^⟦([^⟧]*)⟧\s*(?:—\s*)?/u', $rest, $t)) {
                    $title = trim($t[1]);
                    $rest = substr($rest, strlen($t[0]));
                }

                $node = array(
                    'label' => $m[1],
                    'level' => $level,
                    'title' => $title,
                    'text' => '',
                    'children' => array()
                );

                while (count($stack) > 0 && $stack[count($stack) - 1]['level'] >= $level) {
                    array_pop($stack);
                }

                if (count($stack) === 0) {
                    $roots[] = $node;
                    $stack[] = array(
                        'level' => $level,
                        'ref' => &$roots[count($roots) - 1]
                    );
                } else {
                    $parent = &$stack[count($stack) - 1]['ref'];
                    $parent['children'][] = $node;
                    $stack[] = array(
                        'level' => $level,
                        'ref' => &$parent['children'][count($parent['children']) - 1]
                    );
                    unset($parent);
                }

                if (!preg_match('/^\([a-zA-Z0-9]{1,4}\)\s/', $rest)) {
                    $stack[count($stack) - 1]['ref']['text'] = str_replace(array('⟦', '⟧'), '', $rest);
                    break;
                }
            }
        }

        unset($stack);
        $controls = array();
        $standard = '';

        // 164.412: an unlabelled lead-in whose lettered paragraphs are alternatives, not separate duties.
        if ($lead !== '') {
            $objectives = array();
            $text = $lead;

            foreach ($roots as $root) {
                $item = $render($root);
                $objectives[] = array(
                    'id' => $number.'('.$root['label'].')',
                    'text' => $item,
                    'description' => ObjectiveText::complete($lead, $item)
                );
                $text .= ' ('.$root['label'].') '.$item;
            }

            $controls[] = array(
                'id' => $number,
                'number' => $number,
                'title' => $name,
                'text' => $squash($text),
                'type' => 'requirement',
                'requirement_type' => 'requirement',
                'parent' => '',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives
            );
            $roots = array();
        }

        foreach ($roots as $root) {
            $id = $number.'('.$root['label'].')';
            $heading = rtrim($root['title'], '.');

            if ($heading === 'Standard') {
                $type = 'standard';
                $title = $name;
                $standard = $id;
            } elseif (preg_match('/^Implementation specifications?:\s*(.+)$/', $heading, $s)) {
                $type = 'implementation_specification';
                $title = $s[1];
            } elseif ($heading !== '') {
                $type = 'requirement';
                $title = $heading;
            } else {
                throw new RuntimeException('No heading at '.$id);
            }

            $objectives = array();

            foreach ($root['children'] as $child) {
                $objectives[] = array(
                    'id' => $id.'('.$child['label'].')',
                    'text' => $render($child)
                );
            }

            $body = $root;
            $body['title'] = '';
            $controls[] = array(
                'id' => $id,
                'number' => $id,
                'title' => $title,
                'text' => $render($body),
                'type' => $type,
                'requirement_type' => $type,
                'parent' => $type === 'implementation_specification' ? $standard : '',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives
            );
        }

        $families[] = array(
            'number' => $number,
            'code' => '',
            'name' => $name,
            'controls' => $controls
        );
    }

    return $families;
};
