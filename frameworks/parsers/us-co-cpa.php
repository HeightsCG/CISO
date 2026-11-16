<?php
/**
 * Colorado Privacy Act (Colorado Revised Statutes, Title 6, Article 1, Part 13), from the
 * Title 6 printout of the Colorado Revised Statutes 2024 published by the Office of
 * Legislative Legal Services.
 *
 * - Groups: the sections that place duties on controllers and processors: 6-1-1305 and
 *   1305.5 (responsibility by role, including minors' data), 1306 (consumer rights and
 *   how a controller must respond), 1307 (de-identified data), 1308 and 1308.5 (duties of
 *   controllers, including the duty of care toward minors), 1309 (data protection
 *   assessments) and 1314 (biometric identifiers).
 * - Controls: a numbered subsection that says "shall" or "must" of the business. Where a
 *   subsection is only a heading over lettered paragraphs ("(1) Duty of transparency.
 *   (a) A controller shall ..."), each paragraph that states a duty is a control instead.
 *   Left out: provisions where "shall" or "must" only construes, exempts, grants a
 *   right or binds the attorney general.
 * - Objectives: the items under a control (paragraphs, or roman items), only where they
 *   complete a sentence that states the duty and none of them is joined by "or".
 * - Left out: definitions and applicability (1301 to 1304), liability, enforcement,
 *   preemption and rulemaking (1310 to 1313).
 *
 * The printout is read from its layout-preserving text: each provision starts on a new
 * indented line with its labels ("(1)", "(a)", "(I)", "(A)"); text wraps to column 0.
 * Page footers, editor's notes and the closing "Source:" history are not law text.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $sections = array('6-1-1305', '6-1-1305.5', '6-1-1306', '6-1-1307', '6-1-1308', '6-1-1308.5', '6-1-1309', '6-1-1314');
    $lines = preg_split('/\R/', str_replace("\f", "\n", Source::pdf_text('us-co-cpa/crs2024-title-06.pdf')));
    $romans = array('I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'XIV', 'XV');
    $families = array();

    $is_duty = function (string $text): bool {
        $text = preg_replace('/\b(shall|must) (not )?(be construed|apply|be deemed|be considered|be void|have the right)\b|\bshall not be required\b/', '', $text);

        return preg_match('/\b(shall|must)\b/', $text) === 1 && preg_match('/^(The )?[Aa]ttorney general\b|^Nothing in /', $text) === 0;
    };

    foreach ($sections as $number) {
        $start = null;

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s+'.preg_quote($number, '/').'\. /', $line)) {
                $start = $i;
            }
        }

        if ($start === null) {
            throw new RuntimeException('Section '.$number.' not found');
        }

        // The heading runs from the section number to the first ". (" or ". [", across wrapped lines.
        $head = Source::normalise($lines[$start]);
        $i = $start + 1;

        while (!preg_match('/\.\s+[(\[]/', $head) && !preg_match('/^\s{6,}\(/', $lines[$i]) && trim($lines[$i]) !== '') {
            $head .= ' '.trim($lines[$i]);
            $i++;
        }

        if (!preg_match('/^'.preg_quote($number, '/').'\. (.+?)\.\s+([(\[].*)$/', $head, $m)) {
            throw new RuntimeException('Section '.$number.': heading not found');
        }

        $name = rtrim($m[1], '.');
        $buffer = array($m[2]);

        for (; $i < count($lines); $i++) {
            $line = $lines[$i];

            if (preg_match('/^\s+Source: /', $line) || preg_match('/^\s+6-1-\d+(\.\d+)?\. /', $line)) {
                break;
            }

            if (trim($line) === '' || preg_match('/Colorado Revised Statutes 2024\s+Page \d+ of \d+\s+Uncertified Printout/', $line)) {
                continue;
            }

            if (preg_match('/^\s{6,}\(/', $line)) {
                $buffer[] = trim($line);
            } else {
                $buffer[count($buffer) - 1] .= ' '.trim($line);
            }
        }

        // Build the tree from label chains.
        $nodes = array();
        $stack = array();
        $current = array(0, '', '', '');

        foreach ($buffer as $chunk) {
            $chunk = Source::normalise(preg_replace('/\[Editor\'s note:[^\]]*\]/', '', $chunk));

            if ($chunk === '') {
                continue;
            }

            while (true) {
                if (!preg_match('/^\(([0-9]+(?:\.[0-9]+)?|[a-z]|[IVX]+|[A-Z])\)\s*/', $chunk, $m)) {
                    // "(1) Duty of transparency. (a) ..." : a short heading, then more labels.
                    if (count($stack) > 0 && $nodes[$stack[count($stack) - 1]]['text'] === '' && preg_match('/^([A-Z][^.:]{2,90})\.\s+(\((?:[0-9]+|[a-z]|[IVX]+|[A-Z])\)\s.*)$/s', $chunk, $h) && !preg_match('/\b(shall|must|may|is|are|has|does)\b/', $h[1])) {
                        $nodes[$stack[count($stack) - 1]]['title'] = $h[1];
                        $chunk = $h[2];
                        continue;
                    }

                    break;
                }

                $token = $m[1];

                if (preg_match('/^\d/', $token)) {
                    $level = 0;
                } elseif (ctype_lower($token)) {
                    $level = 1;
                } elseif (in_array($token, $romans, true) && ($current[3] === '' || $token !== chr(ord($current[3]) + 1)) && ($current[2] === '' ? $token === 'I' : $token === $romans[array_search($current[2], $romans, true) + 1])) {
                    $level = 2;
                } else {
                    $level = 3;
                }

                $current[$level] = $token;

                for ($k = $level + 1; $k < 4; $k++) {
                    $current[$k] = '';
                }

                $chunk = substr($chunk, strlen($m[0]));

                while (count($stack) > 0 && $nodes[$stack[count($stack) - 1]]['level'] >= $level) {
                    array_pop($stack);
                }

                $parent = count($stack) === 0 ? null : $stack[count($stack) - 1];
                $index = count($nodes);
                $nodes[] = array(
                    'label' => '('.$token.')',
                    'level' => $level,
                    'title' => '',
                    'text' => '',
                    'parent' => $parent,
                    'children' => array()
                );

                if ($parent !== null) {
                    $nodes[$parent]['children'][] = $index;
                }

                $stack[] = $index;
            }

            if (count($stack) === 0) {
                // A section without numbered subsections.
                $nodes[] = array('label' => '', 'level' => 0, 'title' => '', 'text' => '', 'parent' => null, 'children' => array());
                $stack[] = count($nodes) - 1;
            }

            $top = $stack[count($stack) - 1];
            $nodes[$top]['text'] = trim($nodes[$top]['text'].' '.$chunk);
        }

        $render = function (int $index) use (&$render, &$nodes): string {
            $text = $nodes[$index]['text'];

            foreach ($nodes[$index]['children'] as $child) {
                $text .= ' '.$nodes[$child]['label'].' '.$render($child);
            }

            return Source::normalise($text);
        };

        $path = function (int $index) use (&$nodes): string {
            $labels = array();

            for ($n = $index; $n !== null; $n = $nodes[$n]['parent']) {
                array_unshift($labels, $nodes[$n]['label']);
            }

            return implode('', $labels);
        };

        $build = function (int $index, string $title) use (&$nodes, $render, $path, $number, $is_duty): ?array {
            $text = $render($index);

            if (!$is_duty($text)) {
                return null;
            }

            $id = $number.$path($index);
            $lead = $nodes[$index]['text'];
            $objectives = array();
            $alternatives = false;

            foreach ($nodes[$index]['children'] as $child) {
                $alternatives = $alternatives || preg_match('/[;,]\s*or$/', $nodes[$child]['text']) === 1;
            }

            if (count($nodes[$index]['children']) > 1 && !$alternatives && preg_match('/\b(shall|must)\b[^.]*:$/', $lead)) {
                foreach ($nodes[$index]['children'] as $child) {
                    $own = $render($child);
                    $objective = array(
                        'id' => $id.$nodes[$child]['label'],
                        'text' => $own
                    );

                    if (count($nodes[$child]['children']) === 0 && !preg_match('/\b(shall|must)\b/', $own)) {
                        $objective['description'] = ObjectiveText::sentence(ObjectiveText::stem($lead).' '.ObjectiveText::lower_first(ObjectiveText::item($own)));
                    }

                    $objectives[] = $objective;
                }
            }

            return array(
                'id' => $id,
                'number' => $id,
                'title' => $title,
                'text' => $text,
                'type' => 'provision',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives
            );
        };

        $controls = array();

        foreach ($nodes as $index => $node) {
            if ($node['parent'] !== null) {
                continue;
            }

            $title = $node['title'];

            // "(2) Duty of purpose specification. A controller shall ..." : a heading before the words.
            if ($title === '' && preg_match('/^([A-Z][^.:]{2,90})\.\s+(.*)$/s', $node['text'], $h) && !preg_match('/\b(shall|must|may|is|are|has|does)\b/', $h[1])) {
                $title = $h[1];
                $nodes[$index]['text'] = $h[2];
            }

            if (trim($nodes[$index]['text']) === '' && count($node['children']) > 0) {
                foreach ($node['children'] as $child) {
                    $built = $build($child, $title);

                    if ($built !== null) {
                        $controls[] = $built;
                    }
                }

                continue;
            }

            $built = $build($index, $title);

            if ($built !== null) {
                $controls[] = $built;
            }
        }

        unset($render, $build);
        $families[] = array(
            'number' => $number,
            'code' => '§ '.$number,
            'name' => $name,
            'controls' => $controls
        );
    }

    return $families;
};
