<?php
/**
 * Montana Consumer Data Privacy Act (Montana Code Annotated 2025, Title 30, Chapter 14,
 * Part 28), from the section pages the Montana Legislature publishes on mca.legmt.gov.
 *
 * - Groups: the sections that place duties on controllers and processors: 30-14-2808
 *   (consumer rights and controller compliance), 2809 (authorized agents), 2811 (duty of
 *   care toward minors), 2812 (controller duties and limits), 2813 (processors), 2814
 *   (data protection assessments), 2815 (de-identified data), 2818 (processing minors'
 *   data by role) and 2819 (assessments for heightened risk to minors).
 * - Controls: every numbered subsection that says "shall", "must" or "may not" of the
 *   business; a subsection with no words of its own gives one control per lettered
 *   subsection instead. Left out: provisions where those words only construe or exempt,
 *   grant a right, or bind the attorney general.
 * - Objectives: the provisions beneath a control whose own words state the duty and end
 *   with a colon. Lists are joined by "or" throughout, so they are each required unless
 *   the lead-in makes them a choice ("unless", "if", "one of").
 * - Left out: definitions, applicability and exemptions (2801 to 2804), 2816 (activities
 *   the Act does not restrict), enforcement and penalties (2817, 2820; carried as rules
 *   and consequences).
 *
 * Each section page is its own source (mca2808 and so on), so every requirement cites the
 * page it was read from.
 *
 * Each page holds one section as <p class="line-indent"> elements, each opening with its
 * labels; the first also holds <span class="catchline">. A closing "History:" paragraph is
 * not law text.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $sections = array('2808', '2809', '2811', '2812', '2813', '2814', '2815', '2818', '2819');
    $romans = array('i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x', 'xi', 'xii');
    $families = array();

    $is_duty = function (string $text): bool {
        $text = preg_replace('/\b(shall|must|may) (not )?(be construed|apply|be deemed|be considered|be void|have the right|constitute)\b|\bdoes not require\b/', '', $text);

        return preg_match('/\b(shall|must|may not)\b/', $text) === 1 && preg_match('/^(The )?attorney general\b|^Nothing in /i', $text) === 0;
    };

    foreach ($sections as $part) {
        $number = '30-14-'.$part;
        $file = 'us-mt-cdpa/0300-0140-0280-0'.substr($part, 2).'0.html';
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents(Source::path($file)));
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $catchline = $xpath->query('//span[@class="catchline"]')->item(0);

        if ($catchline === null) {
            throw new RuntimeException($number.': catchline not found');
        }

        $name = rtrim(trim(preg_replace('/^'.preg_quote($number, '/').'\.\s*/u', '', Source::normalise(str_replace("\u{2003}", ' ', $catchline->textContent)))), '.');
        $chunks = array();

        foreach ($xpath->query('//div[contains(@class, "section-content")]//p') as $p) {
            $text = Source::normalise(str_replace(array("\u{2003}", "\t"), ' ', $p->textContent));

            if ($text === '' || strpos($text, 'History:') === 0) {
                continue;
            }

            if (count($chunks) === 0) {
                $text = trim(substr($text, strlen(Source::normalise(str_replace("\u{2003}", ' ', $catchline->textContent)))));
            }

            $chunks[] = $text;
        }

        // Tree from the labels: (1) level 0, (a) level 1, (i) level 2, (A) level 3.
        $nodes = array();
        $stack = array();
        $current = array('', '', '', '');

        foreach ($chunks as $chunk) {
            while (preg_match('/^\((\d+|[a-z]{1,4}|[A-Z])\)\s*/', $chunk, $m)) {
                $token = $m[1];

                if (ctype_digit($token)) {
                    $level = 0;
                } elseif (ctype_upper($token)) {
                    $level = 3;
                } elseif ($current[1] !== '' && in_array($token, $romans, true) && $token === ($current[2] === '' ? 'i' : $romans[array_search($current[2], $romans, true) + 1]) && !($token === 'i' && $current[1] === 'h' && $current[2] === '')) {
                    $level = 2;
                } else {
                    $level = 1;
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
                $nodes[] = array('label' => '('.$token.')', 'level' => $level, 'text' => '', 'parent' => $parent, 'children' => array());

                if ($parent !== null) {
                    $nodes[$parent]['children'][] = $index;
                }

                $stack[] = $index;
            }

            if (count($stack) === 0) {
                $nodes[] = array('label' => '', 'level' => 0, 'text' => '', 'parent' => null, 'children' => array());
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
            $labels = '';

            for ($n = $index; $n !== null; $n = $nodes[$n]['parent']) {
                $labels = $nodes[$n]['label'].$labels;
            }

            return $labels;
        };

        $source = 'mca'.$part;

        $build = function (int $index) use (&$nodes, $render, $path, $number, $is_duty, $source): ?array {
            $text = $render($index);

            if (!$is_duty($text)) {
                return null;
            }

            $id = $number.$path($index);
            $lead = $nodes[$index]['text'];
            $objectives = array();

            if (count($nodes[$index]['children']) > 1 && preg_match('/\b(shall|must|may not)\b[^.]*:$/', $lead) && !preg_match('/\b(unless|if|one of|either)\b/i', $lead)) {
                foreach ($nodes[$index]['children'] as $child) {
                    $item = $render($child);
                    $objective = array('id' => $id.$nodes[$child]['label'], 'text' => $item, 'source' => $source);

                    if (count($nodes[$child]['children']) === 0 && !preg_match('/\b(shall|must|may not)\b/', $item)) {
                        $objective['description'] = ObjectiveText::sentence(rtrim($lead, ':').' '.ObjectiveText::item($item));
                    }

                    $objectives[] = $objective;
                }
            }

            return array(
                'id' => $id,
                'number' => $id,
                'text' => $text,
                'type' => 'subsection',
                'source' => $source,
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

            $units = $node['text'] === '' && count($node['children']) > 0 ? $node['children'] : array($index);

            foreach ($units as $unit) {
                $built = $build($unit);

                if ($built !== null) {
                    $controls[] = $built;
                }
            }
        }

        unset($render, $build);
        $families[] = array(
            'number' => $number,
            'code' => $number.' MCA',
            'name' => $name,
            'controls' => $controls
        );
    }

    return $families;
};
