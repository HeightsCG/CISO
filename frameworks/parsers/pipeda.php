<?php
/**
 * Personal Information Protection and Electronic Documents Act (S.C. 2000, c. 5), Part 1,
 * from the consolidated XML Justice Canada publishes.
 *
 * - Groups: the ten principles of Schedule 1 (4.1 Accountability to 4.10 Challenging
 *   Compliance), which section 5(1) makes binding, followed by the three groups of Part 1
 *   sections that add duties: consent and purposes, access requests, and breaches of
 *   security safeguards.
 * - Schedule 1 controls: each principle statement (4.1) and each numbered clause (4.1.1).
 *   Section 5(2) says "should" in Schedule 1 is a recommendation, not an obligation, so
 *   every control carries `force`: obligation (the principle statements, and clauses that
 *   say shall or must) or recommendation (clauses that say should and neither of the
 *   others). Clauses that say none of the three explain or illustrate and are left out,
 *   as are the notes to clauses 4.3 and 4.9, whose exceptions sections 7 and 9 now state.
 * - Part 1 controls: the subsections named in $sections; the others permit, define or
 *   bind the Commissioner or a government institution.
 * - Objectives: the lettered items of a clause or the paragraphs of a subsection.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $dom = new DOMDocument();
    $dom->load(Source::path('pipeda/P-8.6.xml'));
    $xpath = new DOMXPath($dom);

    $clean = function (string $text): string {
        return Source::normalise(str_replace("\u{a0}", ' ', $text));
    };

    $force = function (string $text): string {
        if (preg_match('/\b(shall|must)\b/i', $text)) {
            return 'obligation';
        }

        return preg_match('/\bshould\b/i', $text) ? 'recommendation' : '';
    };

    $objectives_of = function (array $items, string $lead, string $id): array {
        $objectives = array();

        foreach ($items as $item) {
            $objective = array(
                'id' => $id.$item['label'],
                'text' => $item['text']
            );

            if (ObjectiveText::introduces_list($lead)) {
                $objective['description'] = ObjectiveText::sentence(ObjectiveText::stem($lead).' '.ObjectiveText::item($item['text']));
            }

            $objectives[] = $objective;
        }

        return $objectives;
    };

    $families = array();
    $left_out = array();

    foreach ($xpath->query('//Schedule//GroupHeading[@format-ref="group1-part"]') as $heading) {
        $title = $clean($heading->textContent);

        if (!preg_match('/^(4\.\d+) Principle \d+ ?[-—] ?(.+)$/u', $title, $m)) {
            throw new RuntimeException('Unrecognised principle heading: '.$title);
        }

        $principle = $m[1];
        $controls = array();
        $node = $heading->nextSibling;

        while ($node !== null && !($node->nodeType === XML_ELEMENT_NODE && $node->nodeName === 'GroupHeading')) {
            if ($node->nodeType !== XML_ELEMENT_NODE || $node->nodeName !== 'Provision') {
                $node = $node->nextSibling;
                continue;
            }

            // The notes to clauses 4.3 and 4.9 describe exceptions that sections 7 and 9 now set out; they state no duty.
            if ($xpath->query('./Note', $node)->length > 0 && $xpath->query('./Text', $node)->length === 0) {
                $node = $node->nextSibling;
                continue;
            }

            $bold = $xpath->query('./Text/Emphasis[@style="bold"]', $node)->item(0);
            $id = $bold === null ? $principle : $clean($bold->textContent);
            $own = array();
            $items = array();

            foreach ($xpath->query('./Text | ./Provision', $node) as $part) {
                if ($part->nodeName === 'Text') {
                    if ($bold === null) {
                        $own[] = $clean($part->textContent);
                    }

                    continue;
                }

                foreach ($part->childNodes as $piece) {
                    if ($piece->nodeName === 'Text') {
                        $own[] = $clean($piece->textContent);
                    } elseif ($piece->nodeName === 'Provision') {
                        $label = $xpath->query('./Label', $piece)->item(0);

                        if ($label === null) {
                            $own[] = $clean($piece->textContent);
                        } else {
                            $items[] = array(
                                'label' => $clean($label->textContent),
                                'text' => $clean($xpath->query('./Text', $piece)->item(0)->textContent)
                            );
                        }
                    }
                }
            }

            $lead = $clean(implode(' ', $own));
            $text = $lead;

            foreach ($items as $item) {
                $text .= ' '.$item['label'].' '.$item['text'];
            }

            $kind = $id === $principle ? 'obligation' : $force($text);

            if ($kind === '') {
                $left_out[] = $id;
            } else {
                $controls[] = array(
                    'id' => $id,
                    'number' => $id,
                    'text' => $clean($text),
                    'type' => $id === $principle ? 'principle' : 'clause',
                    'force' => $kind,
                    'weight' => null,
                    'partial_weight' => null,
                    'objectives' => $objectives_of($items, $lead, $id)
                );
            }

            $node = $node->nextSibling;
        }

        $families[] = array(
            'number' => $principle,
            'code' => $principle,
            'name' => 'Principle '.substr($principle, 2).': '.trim($m[2]),
            'controls' => $controls
        );
    }

    // Part 1: group => section => subsections kept ('' for a section without subsections).
    $sections = array(
        'Consent and purposes' => array(
            '5' => array('(3)'),
            '6.1' => array('')
        ),
        'Access requests' => array(
            '8' => array('(2)', '(3)', '(7)', '(8)'),
            '9' => array('(1)', '(5)'),
            '10' => array('')
        ),
        'Breaches of security safeguards' => array(
            '10.1' => array('(1)', '(2)', '(3)', '(4)', '(5)', '(6)'),
            '10.2' => array('(1)', '(2)'),
            '10.3' => array('(1)', '(2)')
        )
    );

    foreach ($sections as $group => $wanted) {
        $controls = array();

        foreach ($wanted as $number => $subsections) {
            $number = (string) $number;
            $section = null;

            foreach ($xpath->query('//Body//Section') as $candidate) {
                if ($clean($xpath->query('./Label', $candidate)->item(0)->textContent) === $number) {
                    $section = $candidate;
                    break;
                }
            }

            if ($section === null) {
                throw new RuntimeException('Section '.$number.' not found');
            }

            foreach ($subsections as $label) {
                $node = $section;

                if ($label !== '') {
                    $node = null;

                    foreach ($xpath->query('./Subsection', $section) as $candidate) {
                        if ($clean($xpath->query('./Label', $candidate)->item(0)->textContent) === $label) {
                            $node = $candidate;
                        }
                    }

                    if ($node === null) {
                        throw new RuntimeException('Subsection '.$number.$label.' not found');
                    }
                }

                $id = 's. '.$number.$label;
                $lead = $clean($xpath->query('./Text', $node)->item(0)->textContent);
                $items = array();

                foreach ($xpath->query('./Paragraph', $node) as $paragraph) {
                    $items[] = array(
                        'label' => $clean($xpath->query('./Label', $paragraph)->item(0)->textContent),
                        'text' => $clean(implode(' ', array_map(function ($t) {
                            return $t->textContent;
                        }, iterator_to_array($xpath->query('./Text | ./Subparagraph/Text', $paragraph)))))
                    );
                }

                $text = $lead;

                foreach ($items as $item) {
                    $text .= ' '.$item['label'].' '.$item['text'];
                }

                $note = $xpath->query('./MarginalNote', $node)->item(0) ?? $xpath->query('./MarginalNote', $section)->item(0);
                $controls[] = array(
                    'id' => $id,
                    'number' => $id,
                    'title' => $note === null ? '' : $clean($note->textContent),
                    'text' => $clean($text),
                    'type' => 'subsection',
                    'force' => 'obligation',
                    'weight' => null,
                    'partial_weight' => null,
                    'objectives' => $objectives_of($items, $lead, $id)
                );
            }
        }

        $families[] = array(
            'number' => $group,
            'code' => 'Part 1',
            'name' => $group,
            'controls' => $controls
        );
    }

    return $families;
};
