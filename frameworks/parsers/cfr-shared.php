<?php
/**
 * Reads one section of the Code of Federal Regulations from an eCFR XML snapshot as a
 * tree of its designated paragraphs.
 *
 * eCFR prints every paragraph as a flat <P>; the hierarchy is in the labels: (a), (1),
 * (i), (A). A paragraph may open with several labels and an italic heading
 * ("(c) Heading—(1) Sub-heading. Text"). Use only for sections whose lettered paragraphs
 * stop before (i), so that (i), (v) and (x) are always roman numerals; the reader
 * refuses a section where that does not hold.
 */
class Cfr {

    /**
     * ['name' => section heading, 'lead' => unlabelled opening text, 'nodes' => top-level
     * paragraphs]. A node is ['label', 'level', 'title', 'text', 'children'].
     */
    public static function section(string $file, string $number): array
    {
        $xml = simplexml_load_file(Source::path($file));
        $found = $xml->xpath('//DIV8[@N="'.$number.'"]');

        if (count($found) !== 1) {
            throw new RuntimeException('Section '.$number.' not found in '.$file);
        }

        $div = $found[0];
        $head = Source::normalise(html_entity_decode((string) $div->HEAD, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        $name = rtrim(trim(preg_replace('/^§\s*'.preg_quote($number, '/').'/u', '', $head)), '.');
        $nodes = array();
        $stack = array();
        $lead = '';

        foreach ($div->P as $p) {
            $inner = preg_replace('/^<P>|<\/P>$/', '', $p->asXML());
            $inner = str_replace(array('<I>', '</I>'), array('⟦', '⟧'), $inner);
            $rest = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_XML1, 'UTF-8')));

            if (!preg_match('/^\([a-zA-Z0-9]{1,4}\)/', $rest)) {
                $plain = str_replace(array('⟦', '⟧'), '', $rest);

                if (count($stack) > 0) {
                    $nodes[$stack[count($stack) - 1]]['text'] .= ' '.$plain;
                } else {
                    $lead = trim($lead.' '.$plain);
                }

                continue;
            }

            while (preg_match('/^\(([a-zA-Z0-9]{1,4})\)\s*/', $rest, $m)) {
                $rest = substr($rest, strlen($m[0]));
                $token = $m[1];

                if (ctype_digit($token)) {
                    $level = 1;
                } elseif (preg_match('/^[ivx]+$/', $token)) {
                    $level = 2;
                } elseif (preg_match('/^[a-h]$/', $token)) {
                    $level = 0;
                } elseif (ctype_upper($token)) {
                    $level = 3;
                } else {
                    throw new RuntimeException('Section '.$number.': ('.$token.') cannot be placed; its letters run past (h)');
                }

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
                    'children' => array()
                );

                if ($parent !== null) {
                    $nodes[$parent]['children'][] = $index;
                }

                $stack[] = $index;

                if (!preg_match('/^\([a-zA-Z0-9]{1,4}\)\s/', $rest)) {
                    $nodes[$index]['text'] = str_replace(array('⟦', '⟧'), '', $rest);
                    break;
                }
            }
        }

        $build = function (int $index) use (&$build, &$nodes): array {
            $node = $nodes[$index];
            $children = array();

            foreach ($node['children'] as $child) {
                $children[] = $build($child);
            }

            return array(
                'label' => $node['label'],
                'level' => $node['level'],
                'title' => $node['title'],
                'text' => trim($node['text']),
                'children' => $children
            );
        };

        $roots = array();

        foreach ($nodes as $index => $node) {
            if ($node['parent'] === null) {
                if ($node['level'] !== 0) {
                    throw new RuntimeException('Section '.$number.': ('.$node['label'].') has no lettered paragraph above it');
                }

                $roots[] = $build($index);
            }
        }

        return array(
            'name' => $name,
            'lead' => $lead,
            'nodes' => $roots
        );
    }

    /** A node's heading and text followed by everything beneath it, labels kept. */
    public static function render(array $node, bool $with_title = true): string
    {
        $text = trim(($with_title && $node['title'] !== '' ? $node['title'].' ' : '').$node['text']);

        foreach ($node['children'] as $child) {
            $text .= ' ('.$child['label'].') '.self::render($child);
        }

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

}
