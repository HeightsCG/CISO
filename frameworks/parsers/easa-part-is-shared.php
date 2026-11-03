<?php
/**
 * EASA Part-IS organisation requirements, shared by both variants:
 * Part-IS.I.OR (Annex II to Implementing Regulation (EU) 2023/203) and
 * Part-IS.D.OR (Annex to Delegated Regulation (EU) 2022/1645). The two annexes
 * have the same fourteen points and the same layout.
 *
 * - Requirements, headings, text and lettered/numbered points: the consolidated
 *   act as published by the EU Publications Office (XHTML). Every point at every
 *   level becomes an objective with its own words; a point's lead-in and any
 *   unnumbered paragraphs that follow its sub-points stay with that point.
 * - Guidance: every AMC and GM item for the requirement from the EASA Easy Access
 *   Rules for Information Security (PDF, committed text extract), with its heading
 *   and ED Decision, in source order.
 * - Groups: the regulation has none; requirements are grouped by theme here.
 *
 * Returns a function so it can be required by both parsers in one build.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (string $letter, string $regulation_file, string $guidance_file, string $guidance_start, string $guidance_end): array {
    $prefix = 'IS.'.$letter.'.OR.';
    $quoted = preg_quote($prefix, '/');

    $groups = array(
        array(
            'number' => '1',
            'code' => 'MS',
            'name' => 'Management system',
            'points' => array(
                '100',
                '200',
                '250',
                '255',
                '260'
            )
        ),
        array(
            'number' => '2',
            'code' => 'RM',
            'name' => 'Risk assessment and treatment',
            'points' => array(
                '205',
                '210'
            )
        ),
        array(
            'number' => '3',
            'code' => 'IR',
            'name' => 'Incidents, reporting and findings',
            'points' => array(
                '215',
                '220',
                '225',
                '230'
            )
        ),
        array(
            'number' => '4',
            'code' => 'OR',
            'name' => 'Contracting, personnel and records',
            'points' => array(
                '235',
                '240',
                '245'
            )
        )
    );

    $dom = new DOMDocument();

    if (!$dom->loadXML(file_get_contents(Source::path($regulation_file)), LIBXML_NONET)) {
        throw new RuntimeException('Cannot parse '.$regulation_file);
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');

    // Footnote markers such as "(2)" are not part of the requirement text.
    foreach (iterator_to_array($xpath->query('//h:a[starts-with(@href, "#E")]')) as $a) {
        $a->parentNode->removeChild($a);
    }

    $clean = function (string $text): string {
        $text = Source::normalise($text);
        $text = preg_replace('/\s*\(\s*\)/', '', $text);

        return trim($text);
    };

    $has_class = function (DOMNode $node, string $class): bool {
        if (!$node instanceof DOMElement) {
            return false;
        }

        return in_array($class, preg_split('/\s+/', $node->getAttribute('class')), true);
    };

    $child_by_class = function (DOMElement $node, string $class) use ($has_class): ?DOMElement {
        foreach ($node->childNodes as $child) {
            if ($has_class($child, $class)) {
                return $child;
            }
        }

        return null;
    };

    // One lettered or numbered point: its label, own paragraphs and sub-points.
    $point = function (DOMElement $grid) use (&$point, $child_by_class, $has_class, $clean): array {
        $label = $clean($child_by_class($grid, 'grid-list-column-1')->textContent);
        $body = $child_by_class($grid, 'grid-list-column-2');
        $out = array(
            'label' => str_replace(' ', '', $label),
            'lead' => array(),
            'tail' => array(),
            'points' => array()
        );

        foreach ($body->childNodes as $child) {
            if (!$child instanceof DOMElement || $has_class($child, 'modref')) {
                continue;
            }

            if ($has_class($child, 'grid-container')) {
                $out['points'][] = $point($child);
            } elseif ($child->nodeName === 'p') {
                $text = $clean($child->textContent);

                if ($text === '') {
                    continue;
                }

                if (count($out['points']) === 0) {
                    $out['lead'][] = $text;
                } else {
                    $out['tail'][] = $text;
                }
            } else {
                throw new RuntimeException('Unexpected element in point '.$label);
            }
        }

        return $out;
    };

    $requirements = array();
    $headings = $xpath->query('//h:p[starts-with(@class, "title-gr-seq-level")]');

    foreach ($headings as $heading) {
        if (!preg_match('/^'.$quoted.'(\d{3})\s+(.+)$/', $clean($heading->textContent), $m)) {
            continue;
        }

        $number = $m[1];
        $requirement = array(
            'number' => $number,
            'title' => $m[2],
            'paragraphs' => array(),
            'points' => array()
        );

        for ($node = $heading->nextSibling; $node !== null; $node = $node->nextSibling) {
            if (!$node instanceof DOMElement || $has_class($node, 'modref')) {
                continue;
            }

            if ($node->nodeName === 'p' && strpos($node->getAttribute('class'), 'title-gr-seq-level') === 0) {
                break;
            }

            if ($has_class($node, 'grid-container')) {
                $requirement['points'][] = $point($node);
            } elseif ($node->nodeName === 'p' && $has_class($node, 'norm')) {
                $requirement['paragraphs'][] = $clean($node->textContent);
            } elseif ($node->nodeName === 'p' && $clean($node->textContent) === '') {
                continue;
            } else {
                break;
            }
        }

        if (isset($requirements[$number])) {
            throw new RuntimeException('Two headings for '.$prefix.$number);
        }

        $requirements[$number] = $requirement;
    }

    // A point with its sub-points run together as one list item ("... in particular: a; b; c").
    $full = function (array $p) use (&$full): string {
        $items = array();

        foreach ($p['points'] as $sub) {
            $items[] = ObjectiveText::item($full($sub));
        }

        $text = implode(' ', $p['lead']);

        if (count($items) > 0) {
            $text = trim($text.' '.implode('; ', $items));
        }

        return trim($text.' '.implode(' ', $p['tail']));
    };

    // Full text (labelled lines) and one objective per point, depth first. $lead is the
    // parent's lead-in: a point that finishes it ("the organisation shall:" / "define a
    // corrective action plan;") gets the joined sentence as its description, and a point
    // that only introduces its sub-points gets them listed in its description.
    $render = function (array $p, string $parent_id, string $depth, string $lead) use (&$render, $full): array {
        $id = $parent_id.$p['label'];
        $own_lead = implode(' ', $p['lead']);
        $own = trim(implode(' ', array_merge($p['lead'], $p['tail'])));
        $fragment = ObjectiveText::introduces_list($lead);
        $lines = array();
        $objectives = array();

        if (count($p['lead']) > 0) {
            $lines[] = $depth.$p['label'].' '.$own_lead;
        } else {
            $lines[] = $depth.$p['label'];
        }

        if ($own === '') {
            throw new RuntimeException('Point '.$id.' has no text');
        }

        $objective = array(
            'id' => $id,
            'text' => $own
        );

        if ($fragment) {
            $objective['description'] = ObjectiveText::complete($lead, $full($p));
        } elseif (count($p['points']) > 0 && ObjectiveText::introduces_list($own_lead)) {
            $items = array();

            foreach ($p['points'] as $sub) {
                $items[] = ObjectiveText::item($full($sub));
            }

            $objective['description'] = trim(ObjectiveText::sentence($own_lead.' '.implode('; ', $items)).' '.implode(' ', $p['tail']));
        } elseif (ObjectiveText::trailing_connector($own)) {
            $objective['description'] = ObjectiveText::sentence($own);
        }

        $objectives[] = $objective;
        $child_lead = $fragment ? ObjectiveText::join($lead, $own_lead) : $own_lead;

        foreach ($p['points'] as $sub) {
            $child = $render($sub, $id, $depth.'  ', $child_lead);
            $lines = array_merge($lines, $child['lines']);
            $objectives = array_merge($objectives, $child['objectives']);
        }

        foreach ($p['tail'] as $tail) {
            $lines[] = $depth.'  '.$tail;
        }

        return array(
            'lines' => $lines,
            'objectives' => $objectives
        );
    };

    $guidance = array();
    $lines = preg_split('/\R/', Source::pdf_text($guidance_file));
    $start = array_search($guidance_start, $lines, true);

    if ($start === false) {
        throw new RuntimeException('Guidance section not found: '.$guidance_start);
    }

    $end = count($lines);

    if ($guidance_end !== '') {
        for ($i = $start + 1; $i < count($lines); $i++) {
            if ($lines[$i] === $guidance_end) {
                $end = $i;
                break;
            }
        }
    }

    // Drop the running page footer and header lines, which can land inside tables.
    $furniture = array(
        'Powered by EASA eRules',
        'Easy Access Rules for Information Security',
        'Implementing Regulation (EU)',
        'Delegated Regulation (EU)',
        '2023/203',
        '2022/1645',
        'ANNEX II — ORGANISATION',
        'ANNEX — ORGANISATION',
        'REQUIREMENTS (PART-IS.'.$letter.'.OR)'
    );
    $body = array();

    for ($i = $start; $i < $end; $i++) {
        $line = trim(str_replace("\f", '', $lines[$i]));

        if (in_array($line, $furniture, true) || preg_match('/^Page \d+ of \d+\| \w+ \d{4}$/', $line)) {
            continue;
        }

        $body[] = $line;
    }

    $paragraphs = array();
    $current = array();

    $source_line = '/^(ED Decision \d{4}\/\d{3}\/R|Regulation \(EU\) (No )?\d{4}\/\d+)$/';

    foreach ($body as $line) {
        // A decision line under a heading is its own paragraph, even without a blank line around it.
        $split = preg_match($source_line, $line) === 1;

        if ($line === '' || $split) {
            if (count($current) > 0) {
                $paragraphs[] = Source::normalise(implode(' ', $current));
                $current = array();
            }

            if ($split) {
                $paragraphs[] = $line;
            }

            continue;
        }

        $current[] = $line;
    }

    if (count($current) > 0) {
        $paragraphs[] = Source::normalise(implode(' ', $current));
    }

    // A topic heading is the paragraph just before its "ED Decision" or "Regulation (EU)" line.
    $topics = array();
    $topic = null;
    $count = count($paragraphs);

    for ($i = 0; $i < $count; $i++) {
        $next = $paragraphs[$i + 1] ?? '';
        $is_source_line = preg_match('/^(ED Decision \d{4}\/\d{3}\/R|Regulation \(EU\) (No )?\d{4}\/\d+)/', $next) === 1;
        $is_heading = $is_source_line && preg_match('/^((AMC|GM)\d+ '.$quoted.'\s?\d{3}|'.$quoted.'\d{3} |Appendix [IVX]+ )/', $paragraphs[$i]) === 1;

        if ($is_heading) {
            if ($topic !== null) {
                $topics[] = $topic;
            }

            $topic = array(
                'heading' => $paragraphs[$i],
                'decision' => array(),
                'body' => array()
            );
            $i++;

            // The decision line(s) may sit in one paragraph or several.
            for (; $i < $count && preg_match('/^((ED Decision \d{4}\/\d{3}\/R|Regulation \(EU\) (No )?\d{4}\/\d+)\s*)+$/', $paragraphs[$i]); $i++) {
                $topic['decision'][] = $paragraphs[$i];
            }

            $i--;
            continue;
        }

        if ($topic !== null) {
            $topic['body'][] = $paragraphs[$i];
        }
    }

    if ($topic !== null) {
        $topics[] = $topic;
    }

    foreach ($topics as $t) {
        if (!preg_match('/^(AMC|GM)\d+ '.$quoted.'\s?(\d{3})/', $t['heading'], $m)) {
            continue;
        }

        // A list label on its own line belongs with the text after it.
        $body_paragraphs = array();
        $carry = '';

        foreach ($t['body'] as $p) {
            $last = count($body_paragraphs) - 1;

            if ($carry !== '') {
                $p = $carry.' '.$p;
                $carry = '';
            }

            // A label left at the end of the previous paragraph, e.g. "are: (a)".
            if (preg_match('/^(.+:)\s(\([a-z0-9]+\))$/', $p, $label)) {
                $p = $label[1];
                $carry = $label[2];
            }

            if ($last >= 0 && preg_match('/^(\([a-z0-9]+\)|[a-z0-9]+\.|\x{2014}|\x{2013}|-|\x{2022})$/u', $body_paragraphs[$last])) {
                $body_paragraphs[$last] .= ' '.$p;
            } else {
                $body_paragraphs[] = $p;
            }
        }

        if ($carry !== '') {
            $body_paragraphs[] = $carry;
        }

        $guidance[$m[2]][] = $t['heading'].' ('.implode('; ', $t['decision']).")\n".implode("\n", $body_paragraphs);
    }

    $out = array();

    foreach ($groups as $group) {
        $family = array(
            'number' => $group['number'],
            'code' => $group['code'],
            'name' => $group['name'],
            'controls' => array()
        );

        foreach ($group['points'] as $number) {
            if (!isset($requirements[$number])) {
                throw new RuntimeException('Requirement not found in source: '.$prefix.$number);
            }

            $r = $requirements[$number];
            $id = $prefix.$number;
            $text_lines = $r['paragraphs'];
            $objectives = array();

            foreach ($r['points'] as $p) {
                $rendered = $render($p, $id, '', '');
                $text_lines = array_merge($text_lines, $rendered['lines']);
                $objectives = array_merge($objectives, $rendered['objectives']);
            }

            if (count($r['points']) === 0) {
                $objectives[] = array(
                    'id' => $id,
                    'text' => implode(' ', $r['paragraphs'])
                );
            } elseif (count($r['paragraphs']) > 0) {
                throw new RuntimeException('Unnumbered text before the points of '.$id);
            }

            $family['controls'][] = array(
                'id' => $id,
                'number' => $number,
                'title' => $r['title'],
                'text' => implode("\n", $text_lines),
                'description' => '',
                'guidance' => implode("\n\n", $guidance[$number] ?? array()),
                'type' => 'requirement',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives
            );
            unset($requirements[$number]);
        }

        $out[] = $family;
    }

    if (count($requirements) > 0) {
        throw new RuntimeException('Requirements not assigned to a group: '.implode(', ', array_keys($requirements)));
    }

    return $out;
};
