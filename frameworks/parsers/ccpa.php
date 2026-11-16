<?php
/**
 * California Consumer Privacy Act of 2018 as amended by the California Privacy Rights Act
 * (Civil Code sections 1798.100 to 1798.199.100), from the text the California Privacy
 * Protection Agency publishes.
 *
 * - Groups: the sections that place duties on businesses, service providers and
 *   contractors: 1798.100 (general duties), 1798.105 to 1798.125 (the consumer rights a
 *   business must honour), 1798.130 (notice, disclosure, correction and deletion
 *   requirements) and 1798.135 (methods of limiting sale, sharing and use).
 * - Controls: each lettered subdivision of a section, except those in $not_duties.
 * - Objectives: the numbered paragraphs directly beneath a subdivision; deeper
 *   paragraphs stay in the objective's text with their own labels.
 * - The text is read from the layout-preserving PDF extract. A paragraph starts on a
 *   line that opens with a label followed by two or more spaces; a label wrapped onto a
 *   new line inside a cross-reference ("subdivision\n(a) of Section ...") has one space
 *   and stays part of its sentence. Labels must run in order, or the build fails.
 * - Left out as whole sections: 1798.140 (definitions), 1798.145 to 1798.148
 *   (exemptions), 1798.150 and 1798.155 (private action and administrative fines,
 *   carried as consequences), 1798.160 onwards (the fund, preemption, rulemaking, the
 *   Agency).
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $sections = array(
        '1798.100',
        '1798.105',
        '1798.106',
        '1798.110',
        '1798.115',
        '1798.120',
        '1798.121',
        '1798.125',
        '1798.130',
        '1798.135'
    );

    // Subdivisions that state an exception, a limit on the duty, or a rule of interpretation.
    $not_duties = array(
        '1798.100(f)',
        '1798.105(d)',
        '1798.121(d)',
        '1798.125(b)',
        '1798.130(b)',
        '1798.130(c)',
        '1798.135(d)',
        '1798.135(g)'
    );
    $romans = array('i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x');
    $text = Source::pdf_text('ccpa/ccpa_statute.pdf');
    $lines = preg_split('/\R/', str_replace("\f", "\n", $text));
    $families = array();
    $skipped = array();

    // The contents list repeats every heading; the body starts at the last "1798.100." heading.
    $starts = array();

    foreach ($lines as $n => $line) {
        if (preg_match('/^(1798\.\d+(?:\.\d+)?)\.\s+(\S.*?)\s*$/', $line, $m) && strpos($line, '....') === false) {
            $starts[$n] = array($m[1], $m[2]);
        } elseif (preg_match('/^(1798\.\d+(?:\.\d+)?)\.\s*$/', $line, $m)) {
            $starts[$n] = array($m[1], '');
        }
    }

    $positions = array_keys($starts);

    foreach ($sections as $number) {
        $at = null;

        foreach ($positions as $i => $n) {
            if ($starts[$n][0] === $number) {
                $at = $i;
            }
        }

        if ($at === null) {
            throw new RuntimeException('Section '.$number.' not found');
        }

        $from = $positions[$at];
        $to = $positions[$at + 1] ?? count($lines);
        $name = $starts[$from][1];
        $first = $from + 1;

        // A long heading wraps onto following lines until the blank line before the text.
        while ($first < $to && trim($lines[$first]) !== '' && !preg_match('/^\s*\([a-z]\)\s{2,}/', $lines[$first])) {
            $name .= ' '.trim($lines[$first]);
            $first++;
        }

        $nodes = array();
        $stack = array();
        $current = array('', '', '', '');

        for ($n = $first; $n < $to; $n++) {
            $line = rtrim($lines[$n]);

            if (trim($line) === '' || preg_match('/^\s*Page \d+ of \d+\s*$/', $line)) {
                continue;
            }

            if (!preg_match('/^\s*\((?:[a-z]+|\d+|[A-Z]+)\)\s{2,}\S/', $line)) {
                if (count($stack) === 0) {
                    throw new RuntimeException($number.': text before the first subdivision: '.trim($line));
                }

                $nodes[$stack[count($stack) - 1]]['text'] .= ' '.trim($line);
                continue;
            }

            // A subdivision with no words of its own runs straight into its first paragraph: "(c)   (1)   A business ...".
            $rest = $line;

            while (preg_match('/^\s*\(([a-z]+|\d+|[A-Z]+)\)\s{2,}(\S.*)$/', $rest, $m)) {
                $token = $m[1];
                $rest = $m[2];

                if (ctype_digit($token)) {
                    $level = 1;
                    $expected = (string) ((int) $current[1] + 1);
                } elseif (ctype_upper($token)) {
                    $level = 2;
                    $expected = $current[2] === '' ? 'A' : chr(ord($current[2]) + 1);
                } elseif ($current[2] !== '' && in_array($token, $romans, true) && $token === ($current[3] === '' ? 'i' : $romans[array_search($current[3], $romans, true) + 1])) {
                    $level = 3;
                    $expected = $token;
                } else {
                    $level = 0;
                    $expected = $current[0] === '' ? 'a' : chr(ord($current[0]) + 1);
                }

                if ($token !== $expected) {
                    throw new RuntimeException($number.': ('.$token.') does not follow ('.$current[$level].') at level '.$level);
                }

                $current[$level] = $token;

                for ($i = $level + 1; $i < 4; $i++) {
                    $current[$i] = '';
                }

                while (count($stack) > 0 && $nodes[$stack[count($stack) - 1]]['level'] >= $level) {
                    array_pop($stack);
                }

                $parent = count($stack) === 0 ? null : $stack[count($stack) - 1];
                $index = count($nodes);
                $nodes[] = array(
                    'label' => $token,
                    'level' => $level,
                    'text' => '',
                    'parent' => $parent,
                    'children' => array()
                );

                if ($parent !== null) {
                    $nodes[$parent]['children'][] = $index;
                }

                $stack[] = $index;
            }

            $nodes[$stack[count($stack) - 1]]['text'] = trim($rest);
        }

        $render = function (int $index) use (&$render, &$nodes): string {
            $out = $nodes[$index]['text'];

            foreach ($nodes[$index]['children'] as $child) {
                $out .= ' ('.$nodes[$child]['label'].') '.$render($child);
            }

            return Source::normalise($out);
        };

        $controls = array();

        foreach ($nodes as $index => $node) {
            if ($node['parent'] !== null) {
                continue;
            }

            $id = $number.'('.$node['label'].')';

            if (in_array($id, $not_duties, true)) {
                $skipped[] = $id;
                continue;
            }

            $lead = Source::normalise($node['text']);
            $objectives = array();

            foreach ($node['children'] as $child) {
                $own = $render($child);
                $objective = array(
                    'id' => $id.'('.$nodes[$child]['label'].')',
                    'text' => $own
                );

                if (ObjectiveText::introduces_list($lead) && count($nodes[$child]['children']) === 0) {
                    $objective['description'] = ObjectiveText::sentence(ObjectiveText::stem($lead).' '.ObjectiveText::item($own));
                }

                $objectives[] = $objective;
            }

            $controls[] = array(
                'id' => $id,
                'number' => $id,
                'text' => $render($index),
                'type' => 'subdivision',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives
            );
        }

        unset($render);
        $families[] = array(
            'number' => $number,
            'code' => '',
            'name' => Source::normalise($name),
            'controls' => $controls
        );
    }

    if ($skipped !== $not_duties) {
        throw new RuntimeException('Subdivisions listed as not duties were not all found: '.implode(', ', array_diff($not_duties, $skipped)));
    }

    return $families;
};
