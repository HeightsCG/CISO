<?php
/**
 * NIST SP 800-30 Rev. 1 Guide for Conducting Risk Assessments. The assessable items are
 * the 15 tasks of the risk assessment process (Chapter 3), grouped by its four steps. Each
 * task's text is its TASK statement; its objectives are the bullets of the chapter's
 * "Summary of Key Activities" boxes, word for word from the committed pdftotext -raw
 * extract. Step 2 has a box per task; steps 1, 3 and 4 have one box per step, whose bullets
 * are assigned to tasks as listed in $step_boxes.
 */
return function (): array {
    $text = str_replace(array("\r", "\f"), '', Source::pdf_text('nist80030/NIST.SP.800-30r1.pdf'));
    $text = preg_replace('/Special Publication 800-30 Guide for Conducting Risk Assessments\n_+\n(?:CHAPTER|APPENDIX) [^\n]*PAGE [^\n]*\n/', "\n\x01\n", $text);

    $flat = function (string $text): string {
        $text = str_replace("\n\x01\n", "\n", $text);
        $text = preg_replace('/(?<=[a-z])-\n(?=[a-z])/', '-', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    };

    // Bullets of a key activities box; "- " sub-bullets are joined to their lead-in bullet.
    $box = function (string $title) use ($text, $flat): array {
        $start = strpos($text, "\nSummary of Key Activities – ".$title."\n");

        if ($start === false) {
            throw new RuntimeException('800-30: no key activities box "'.$title.'"');
        }

        $start += strlen("\nSummary of Key Activities – ".$title."\n");
        $lines = explode("\n", substr($text, $start));
        $items = array();
        $lead = null;

        foreach ($lines as $line) {
            if (strpos($line, '• ') === 0) {
                $items[] = array(
                    'text' => substr($line, strlen('• ')),
                    'sub' => false
                );
                $lead = count($items) - 1;
                continue;
            }

            if (strpos($line, '- ') === 0 && $lead !== null) {
                $items[] = array(
                    'text' => rtrim($items[$lead]['text'], ':').': '.substr($line, 2),
                    'sub' => true,
                    'lead' => $lead
                );
                continue;
            }

            // Continuation lines belong to the last item, until a blank or a heading-like line ends the box.
            // A page break ends a box: boxes do not run across pages.
            if ($line === "\x01") {
                break;
            }

            if (count($items) === 0 || trim($line) === '' || preg_match('/^(?:\d+(?:\.\d+)* [A-Z]{3,}|[A-Z][A-Z ]{6,}$|TASK \d)/', $line)) {
                break;
            }

            // A capitalized line after an item that already ends a sentence is the text after the box.
            if (preg_match('/[.;]$/', rtrim($items[count($items) - 1]['text'])) && preg_match('/^[A-Z]/', $line)) {
                break;
            }

            $items[count($items) - 1]['text'] .= "\n".$line;
        }

        // A lead-in bullet ending in a colon is carried by its sub-bullets.
        $out = array();

        foreach ($items as $i => $item) {
            $has_subs = false;

            foreach ($items as $other) {
                if ($other['sub'] && $other['lead'] === $i) {
                    $has_subs = true;
                }
            }

            if (!$has_subs) {
                $out[] = $flat($item['text']);
            }
        }

        return $out;
    };

    $tasks = array(
        '1' => array('Prepare for the Assessment', array('1-1', '1-2', '1-3', '1-4', '1-5')),
        '2' => array('Conduct the Assessment', array('2-1', '2-2', '2-3', '2-4', '2-5', '2-6')),
        '3' => array('Communicate and Share Assessment Results', array('3-1', '3-2')),
        '4' => array('Maintain the Assessment', array('4-1', '4-2'))
    );

    // Step boxes: bullet positions (0-based) for each task.
    $step_boxes = array(
        'Preparing for Risk Assessments' => array('1-1' => array(0), '1-2' => array(1), '1-3' => array(2), '1-4' => array(3), '1-5' => array(4)),
        'Communicating and Sharing Information' => array('3-1' => array(0, 1), '3-2' => array(2)),
        'Maintaining Risk Assessments' => array('4-1' => array(0, 1), '4-2' => array(2, 3, 4))
    );

    $activities = array();

    foreach ($step_boxes as $title => $assignments) {
        $bullets = $box($title);
        $used = 0;

        foreach ($assignments as $task => $positions) {
            foreach ($positions as $p) {
                if (!isset($bullets[$p])) {
                    throw new RuntimeException('800-30: box "'.$title.'" has no bullet '.($p + 1));
                }

                $activities[$task][] = $bullets[$p];
                $used++;
            }
        }

        if ($used !== count($bullets)) {
            throw new RuntimeException('800-30: box "'.$title.'" has '.count($bullets).' bullets, '.$used.' assigned');
        }
    }

    foreach ($tasks['2'][1] as $task) {
        $activities[$task] = $box('Task '.$task);
    }

    $families = array();

    foreach ($tasks as $step => $definition) {
        list($name, $ids) = $definition;
        $controls = array();

        foreach ($ids as $task) {
            if (!preg_match('/\n([A-Z][A-Z ,\/-]+)\nTASK '.$task.': (.+?)\nSupplemental Guidance:/s', $text, $m)) {
                throw new RuntimeException('800-30: TASK '.$task.' statement not found');
            }

            $objectives = array();

            foreach ($activities[$task] as $o => $activity) {
                $objectives[] = array(
                    'id' => $task.'.'.chr(97 + $o),
                    'text' => $activity,
                    'source' => 'sp80030'
                );
            }

            $controls[] = array(
                'id' => $task,
                'number' => 'Task '.$task,
                'title' => preg_replace_callback('/(?<=\s)(And|Or|Of|The|To|For)\b/', function ($w) {
                    return strtolower($w[0]);
                }, ucwords(strtolower($m[1]))),
                'text' => $flat($m[2]),
                'type' => 'task',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives,
                'source' => 'sp80030'
            );
        }

        $families[] = array(
            'number' => 'Step '.$step,
            'code' => 'S'.$step,
            'name' => 'Step '.$step.': '.$name,
            'controls' => $controls
        );
    }

    return $families;
};
