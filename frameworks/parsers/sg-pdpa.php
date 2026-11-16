<?php
/**
 * Personal Data Protection Act 2012 (Singapore), 2020 Revised Edition, informal
 * consolidation in force from 16 October 2026, from the PDF Singapore Statutes Online
 * publishes.
 *
 * - Groups: the sections of Parts 3 to 6A that place duties on organisations:
 *   accountability (Part 3), consent and purpose (Part 4), access and correction
 *   (Part 5), care of personal data (Part 6) and data breach notification (Part 6A).
 *   Each group carries its Part.
 * - Controls: every subsection that says "must", or that permits something "only" on
 *   conditions (section 18); a section without subsections is one control. Subsections
 *   that say "must" only of the Commission, or define, permit or exempt, are left out.
 * - Objectives: the lettered paragraphs of a control, only where every one must be met
 *   (the list completes a sentence that says must, and no paragraph ends in "or").
 * - Left out: Parts 1 and 2 (preliminary, the Commission), sections 15 and 17 and the
 *   Schedules (when consent is deemed, and exceptions to consent), section 19 (data collected before 2 July 2014), 26A and 26B
 *   (definitions), Part 9 (the Do Not Call Registry, which governs marketing messages to
 *   Singapore telephone numbers) and Parts 9A onwards (offences, enforcement, appeals).
 *
 * The text is read from the layout-preserving extract. A section opens on a line such as
 * " 11.—(1) text" or " 12. text"; a subsection on " (2) text"; paragraphs and
 * sub-paragraphs are indented further. Labels must run in order, or the build fails.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $scope = array(
        'Part 3: General rules with respect to protection of and accountability for personal data' => array('11', '12'),
        'Part 4: Collection, use and disclosure of personal data' => array('13', '14', '15A', '16', '18', '20'),
        'Part 5: Access to and correction of personal data' => array('21', '22', '22A'),
        'Part 6: Care of personal data' => array('23', '24', '25', '26'),
        'Part 6A: Notification of data breaches' => array('26C', '26D', '26E')
    );
    $text = Source::pdf_text('sg-pdpa/PDPA2012-2026-10-16.pdf');
    $lines = preg_split('/\R/', str_replace("\f", "\n", $text));

    // Section names from the arrangement of sections at the front.
    $names = array();

    foreach ($lines as $line) {
        if (preg_match('/^\s{1,4}(\d+[A-Z]?)\.\s{2,}(\S.*?)\s*$/', $line, $m) && !isset($names[$m[1]])) {
            $names[$m[1]] = $m[2];
        }

        if (strpos($line, 'Compliance with Act') === 0) {
            break;
        }
    }

    // The body, without page furniture, amendment notes, Part and Division headings.
    $body = array();
    $started = false;

    foreach ($lines as $line) {
        if (!$started && preg_match('/^ 11\.—\(1\)/u', $line)) {
            $started = true;
        }

        if (!$started) {
            continue;
        }

        $trimmed = trim($line);

        if ($trimmed === '' || strpos($trimmed, 'Informal Consolidation') === 0 || $trimmed === 'Personal Data Protection'
            || preg_match('/^(\d+\s+)?Act 2012(\s+2020 Ed\.)?(\s+\d+)?$|^2020 Ed\.\s+Act 2012\s+\d+$|^\[[\d\/;, A-Za-z.]+\]$|^PART \d+[A-Z]?$|^Division \d+ — |^[A-Z ,\-]+$/u', $trimmed)) {
            continue;
        }

        $body[] = rtrim($line);
    }

    $section_at = function (string $number) use ($body): int {
        foreach ($body as $i => $line) {
            if (preg_match('/^ '.preg_quote($number, '/').'\.(—\(1\))?\s/u', $line)) {
                return $i;
            }
        }

        throw new RuntimeException('Section '.$number.' not found');
    };

    $families = array();

    foreach ($scope as $part => $numbers) {
        foreach ($numbers as $number) {
            $start = $section_at($number);
            $nodes = array();
            $current = null;
            $letter_indent = null;
            $expect = array('sub' => 1, 'letter' => 'a', 'roman' => 'i');
            $romans = array('i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x');

            for ($i = $start; $i < count($body); $i++) {
                $line = $body[$i];

                // The next section, or the heading line just before it, ends this one.
                if ($i > $start && preg_match('/^ \d+[A-Z]?\.(—\(1\))?\s/u', $line)) {
                    break;
                }

                if ($i > $start && in_array(trim($line), $names, true) && !preg_match('/^\s{3,}/', $line)) {
                    continue;
                }

                if ($i === $start) {
                    preg_match('/^ '.preg_quote($number, '/').'\.(?:—\((\d+)\))?\s+(.*)$/u', $line, $m);
                    $label = $m[1] === '' ? '' : $m[1];
                    $nodes[] = array(
                        'label' => $label,
                        'lead' => $m[2],
                        'items' => array(),
                        'close' => ''
                    );
                    $expect['sub'] = $label === '' ? 1 : 2;
                    $letter_indent = null;
                    continue;
                }

                if (preg_match('/^ \((\d+[A-Z]?)\)\s+(.*)$/', $line, $m)) {
                    $is_next = ctype_digit($m[1]) ? (int) $m[1] === $expect['sub'] : (int) $m[1] === $expect['sub'] - 1;

                    if ($is_next) {
                        if (ctype_digit($m[1])) {
                            $expect['sub']++;
                        }

                        $nodes[] = array(
                            'label' => $m[1],
                            'lead' => $m[2],
                            'items' => array(),
                            'close' => ''
                        );
                        $letter_indent = null;
                        $expect['letter'] = 'a';
                        continue;
                    }
                }

                $last = count($nodes) - 1;

                if (preg_match('/^(\s{4,})\(([a-z]{1,2}|[ivx]+)\)\s+(.*)$/', $line, $m)) {
                    $indent = strlen($m[1]);
                    $is_roman = in_array($m[2], $romans, true) && $letter_indent !== null && $indent > $letter_indent + 2;

                    if (!$is_roman && $m[2] === $expect['letter']) {
                        $letter_indent = $letter_indent ?? $indent;
                        $nodes[$last]['items'][] = array(
                            'label' => '('.$m[2].')',
                            'text' => $m[3]
                        );
                        $expect['letter'] = chr(ord($m[2]) + 1);
                        $expect['roman'] = 'i';
                        continue;
                    }

                    if ($is_roman && $m[2] === $expect['roman']) {
                        $item = count($nodes[$last]['items']) - 1;
                        $nodes[$last]['items'][$item]['text'] .= ' ('.$m[2].') '.$m[3];
                        $expect['roman'] = $romans[array_search($m[2], $romans, true) + 1];
                        continue;
                    }

                    throw new RuntimeException('Section '.$number.': ('.$m[2].') is out of order');
                }

                // Words after a list at the subsection's own indent close it; anything else continues.
                if (count($nodes[$last]['items']) > 0 && $letter_indent !== null && preg_match('/^(\s*)\S/', $line, $m) && strlen($m[1]) < $letter_indent) {
                    $nodes[$last]['close'] = trim($nodes[$last]['close'].' '.trim($line));
                } elseif (count($nodes[$last]['items']) > 0) {
                    $item = count($nodes[$last]['items']) - 1;
                    $nodes[$last]['items'][$item]['text'] .= ' '.trim($line);
                } else {
                    $nodes[$last]['lead'] .= ' '.trim($line);
                }
            }

            $controls = array();

            foreach ($nodes as $node) {
                $id = 's '.$number.($node['label'] === '' ? '' : '('.$node['label'].')');
                $lead = Source::normalise($node['lead']);
                $full = $lead;
                $alternatives = false;

                foreach ($node['items'] as $k => $item) {
                    $node['items'][$k]['text'] = Source::normalise($item['text']);
                    $full .= ' '.$item['label'].' '.$node['items'][$k]['text'];
                    $alternatives = $alternatives || preg_match('/[;,]\s*or$/', $node['items'][$k]['text']) === 1;
                }

                $full = Source::normalise($full.' '.$node['close']);

                // A duty of the organisation: "must", or "may ... only", said of someone other than the Commission.
                $duty = preg_match('/\bmust\b/', $full) === 1 || preg_match('/\bmay\b[^.]{0,80}\bonly\b/', $lead) === 1;

                if (!$duty || preg_match('/^The Commission\b[^.]*\bmust\b/', $full)) {
                    continue;
                }

                $objectives = array();

                if (count($node['items']) > 0 && !$alternatives && $node['close'] === '' && preg_match('/\b(must|only)\b.*-$/', $lead)) {
                    foreach ($node['items'] as $item) {
                        $objectives[] = array(
                            'id' => $id.$item['label'],
                            'text' => $item['text'],
                            'description' => ObjectiveText::sentence(rtrim($lead, ' -').' '.ObjectiveText::item($item['text']))
                        );
                    }
                }

                $controls[] = array(
                    'id' => $id,
                    'number' => $id,
                    'text' => $full,
                    'type' => $node['label'] === '' ? 'section' : 'subsection',
                    'weight' => null,
                    'partial_weight' => null,
                    'objectives' => $objectives
                );
            }

            if (!isset($names[$number])) {
                throw new RuntimeException('Section '.$number.' has no name');
            }

            $families[] = array(
                'number' => $number,
                'code' => 's '.$number,
                'name' => $names[$number],
                'part' => $part,
                'controls' => $controls
            );
        }
    }

    return $families;
};
