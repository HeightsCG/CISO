<?php
/**
 * Privacy Act 1988 (Cth), from the Word file of compilation No. 104 (C2026C00227) on the
 * Federal Register of Legislation.
 *
 * - Groups: the thirteen Australian Privacy Principles of Schedule 1 (APP 1 to APP 13),
 *   each carrying its Part of the Schedule, and the notifiable data breach duties of
 *   Part IIIC (sections 26WH, 26WK and 26WL).
 * - Controls: every subclause (1.2) or subsection (26WK(2)) that says "must"; the others
 *   state the object of a principle, an exception, a permission or a definition.
 * - Objectives: the lettered paragraphs of a control, but only where they are elements
 *   that must all be met (a list introduced by a sentence that says must, with no
 *   paragraph ending in "or" and no closing words after the list). Where the paragraphs
 *   are alternatives or conditions ("If: (a) ... (b) ...; the entity must ..."), the
 *   control is assessed whole.
 * - The Word file marks each kind of provision with a paragraph style: ActHead5 for a
 *   clause or section heading, subsection for a subclause, paragraph and paragraphsub for
 *   (a) and (i), subsection2 for the words that close a list, notetext for notes.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $zip = new ZipArchive();

    if ($zip->open(Source::path('au-privacy/C2026C00227.docx')) !== true) {
        throw new RuntimeException('Cannot open the Privacy Act Word file');
    }

    $dom = new DOMDocument();
    $dom->loadXML($zip->getFromName('word/document.xml'));
    $zip->close();
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $rows = array();

    foreach ($xpath->query('//w:body//w:p') as $p) {
        $text = '';

        foreach ($xpath->query('.//w:t | .//w:tab | .//w:noBreakHyphen', $p) as $piece) {
            $text .= $piece->localName === 'tab' ? ' ' : ($piece->localName === 'noBreakHyphen' ? '-' : $piece->textContent);
        }

        $text = Source::normalise(str_replace("\u{a0}", ' ', $text));
        $style = $xpath->query('./w:pPr/w:pStyle', $p)->item(0);

        if ($text !== '') {
            $rows[] = array(
                $style === null ? '' : $style->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val'),
                $text
            );
        }
    }

    // Provisions under one ActHead5 heading, until the next heading of any level.
    $provisions = function (string $heading) use ($rows): array {
        $start = null;

        foreach ($rows as $i => $row) {
            if ($row[0] === 'ActHead5' && strpos($row[1], $heading) === 0) {
                $start = $i;
            }
        }

        if ($start === null) {
            throw new RuntimeException('Heading not found: '.$heading);
        }

        $found = array();
        $title = '';

        for ($i = $start + 1; $i < count($rows) && strpos($rows[$i][0], 'ActHead') !== 0; $i++) {
            list($style, $text) = $rows[$i];

            if ($style === 'SubsectionHead') {
                $title = $text;
            } elseif ($style === 'subsection') {
                if (!preg_match('/^(\d+\.\d+|\(\d+\))\s*(.*)$/s', $text, $m)) {
                    throw new RuntimeException($heading.': unnumbered subsection: '.$text);
                }

                $found[] = array(
                    'label' => $m[1],
                    'title' => $title,
                    'lead' => $m[2],
                    'items' => array(),
                    'close' => ''
                );
                $title = '';
            } elseif ($style === 'paragraph' || $style === 'paragraphsub') {
                $last = count($found) - 1;

                if (preg_match('/^(\([a-z]+\))\s*(.*)$/s', $text, $m) && $style === 'paragraph') {
                    $found[$last]['items'][] = array(
                        'label' => $m[1],
                        'text' => $m[2]
                    );
                } else {
                    // A sub-paragraph, or the words a paragraph continues with after its own sub-list.
                    $item = count($found[$last]['items']) - 1;
                    $found[$last]['items'][$item]['text'] .= ' '.preg_replace('/^(\([ivx]+\))\s*/', '$1 ', $text);
                }
            } elseif ($style === 'subsection2') {
                $last = count($found) - 1;
                $found[$last]['close'] = trim($found[$last]['close'].' '.$text);
            }
        }

        return $found;
    };

    $control = function (string $id, array $provision): ?array {
        $text = $provision['lead'];

        foreach ($provision['items'] as $item) {
            $text .= ' '.$item['label'].' '.$item['text'];
        }

        $text = Source::normalise(trim($text.' '.$provision['close']));

        if (!preg_match('/\bmust\b/', $text)) {
            return null;
        }

        $objectives = array();
        $alternatives = false;

        foreach ($provision['items'] as $item) {
            if (preg_match('/[;,]\s*or$|\bor$/', $item['text'])) {
                $alternatives = true;
            }
        }

        if (count($provision['items']) > 0 && !$alternatives && $provision['close'] === '' && preg_match('/\bmust\b.*:$/', $provision['lead'])) {
            foreach ($provision['items'] as $item) {
                $objectives[] = array(
                    'id' => $id.$item['label'],
                    'text' => Source::normalise($item['text']),
                    'description' => ObjectiveText::sentence(ObjectiveText::stem($provision['lead']).' '.ObjectiveText::item(Source::normalise($item['text'])))
                );
            }
        }

        return array(
            'id' => $id,
            'number' => $id,
            'title' => $provision['title'],
            'text' => $text,
            'type' => 'subclause',
            'weight' => null,
            'partial_weight' => null,
            'objectives' => $objectives
        );
    };

    $parts = array(
        1 => 'Part 1: Consideration of personal information privacy',
        2 => 'Part 1: Consideration of personal information privacy',
        3 => 'Part 2: Collection of personal information',
        4 => 'Part 2: Collection of personal information',
        5 => 'Part 2: Collection of personal information',
        6 => 'Part 3: Dealing with personal information',
        7 => 'Part 3: Dealing with personal information',
        8 => 'Part 3: Dealing with personal information',
        9 => 'Part 3: Dealing with personal information',
        10 => 'Part 4: Integrity of personal information',
        11 => 'Part 4: Integrity of personal information',
        12 => 'Part 5: Access to, and correction of, personal information',
        13 => 'Part 5: Access to, and correction of, personal information'
    );
    $families = array();

    foreach ($parts as $app => $part) {
        $heading = null;

        foreach ($rows as $row) {
            if ($row[0] === 'ActHead5' && preg_match('/^'.$app.' Australian Privacy Principle '.$app.'[—-](.+)$/u', $row[1], $m)) {
                $heading = $row[1];
                $name = $m[1];
            }
        }

        if ($heading === null) {
            throw new RuntimeException('APP '.$app.' heading not found');
        }

        $controls = array();

        foreach ($provisions($heading) as $provision) {
            $built = $control('APP '.$provision['label'], $provision);

            if ($built !== null) {
                $controls[] = $built;
            }
        }

        $families[] = array(
            'number' => 'APP '.$app,
            'code' => 'APP '.$app,
            'name' => ucfirst($name),
            'part' => 'Schedule 1, '.$part,
            'controls' => $controls
        );
    }

    $controls = array();

    foreach (array('26WH', '26WK', '26WL') as $section) {
        foreach ($provisions($section.' ') as $provision) {
            $built = $control('s '.$section.$provision['label'], $provision);

            if ($built !== null) {
                $built['type'] = 'subsection';
                $controls[] = $built;
            }
        }
    }

    $families[] = array(
        'number' => 'Part IIIC',
        'code' => 'Part IIIC',
        'name' => 'Notification of eligible data breaches',
        'part' => 'Part IIIC',
        'controls' => $controls
    );

    return $families;
};
