<?php
/**
 * Oregon Consumer Privacy Act (Oregon Revised Statutes 646A.570 to 646A.589), from the
 * ORS chapter 646A page the Oregon Legislature publishes (2025 edition).
 *
 * - Groups: the sections that place duties on controllers and processors: 646A.574
 *   (consumer rights), 646A.576 (requests and responses), 646A.578 (controller duties and
 *   the privacy notice), 646A.581 (processors), 646A.583 (de-identified data) and
 *   646A.586 (data protection assessments).
 * - Controls: every numbered subsection that says "shall", "must" or "may not" of the
 *   business. Left out: subsections where those words only construe or exempt, grant a
 *   right, or bind the Attorney General.
 * - Objectives: the lettered paragraphs of a subsection whose lead-in states the duty
 *   ("a controller shall:"). Lists are joined by "or" throughout, so the paragraphs are
 *   each required unless the lead-in makes them a choice ("unless", "if", "one of").
 * - Left out: 646A.570 and 646A.572 (definitions, scope and exclusions) and 646A.589
 *   (enforcement, carried as a consequence).
 *
 * The page is a Word export: each provision is its own <p>, opening with its label;
 * the first paragraph of a section carries the section number and catchline, and the
 * last ends with the history note in brackets ("[2023 c.369 §3]").
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $sections = array('646A.574', '646A.576', '646A.578', '646A.581', '646A.583', '646A.586');
    $html = mb_convert_encoding(file_get_contents(Source::path('us-or-cpa/ors646a.html')), 'UTF-8', 'Windows-1252');
    preg_match_all('/<p[^>]*>(.*?)<\/p>/s', $html, $m);
    $paragraphs = array();

    foreach ($m[1] as $inner) {
        $text = Source::normalise(str_replace("\u{a0}", ' ', html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if ($text !== '') {
            $paragraphs[] = $text;
        }
    }

    $is_duty = function (string $text): bool {
        $text = preg_replace('/\b(shall|must|may) (not )?(be construed|apply|be deemed|be considered|be void|have the right)\b|\bdoes not require\b/', '', $text);

        return preg_match('/\b(shall|must|may not)\b/', $text) === 1 && preg_match('/^(The )?Attorney General\b|^Nothing in /', $text) === 0;
    };

    $families = array();

    foreach ($sections as $number) {
        $start = null;

        foreach ($paragraphs as $i => $p) {
            // The body copy of a section, not the table of contents: its catchline ends with a period.
            if (preg_match('/^'.preg_quote($number, '/').' .+?\.\s/u', $p)) {
                $start = $i;
            }
        }

        if ($start === null) {
            throw new RuntimeException('Section '.$number.' not found');
        }

        if (!preg_match('/^'.preg_quote($number, '/').' (.+?)\.\s+(\(1\).*)$/u', $paragraphs[$start], $h)) {
            throw new RuntimeException('Section '.$number.': heading not found');
        }

        $name = $h[1];
        $chunks = array($h[2]);

        for ($i = $start + 1; $i < count($paragraphs) && !preg_match('/^646A\.\d+ /', $paragraphs[$i]); $i++) {
            $chunks[] = $paragraphs[$i];
        }

        $subsections = array();
        $expect = 1;

        foreach ($chunks as $chunk) {
            $chunk = trim(preg_replace('/\s*\[[^\]]*\d{4} c\.\d+[^\]]*\]$/u', '', $chunk));

            if (preg_match('/^\('.$expect.'\)\s*(.*)$/s', $chunk, $s)) {
                $subsections[] = array('label' => '('.$expect.')', 'lead' => $s[1], 'items' => array());
                $expect++;
                continue;
            }

            $last = count($subsections) - 1;

            if (preg_match('/^\(([a-z])\)\s+(.*)$/s', $chunk, $s) && $s[1] === chr(ord('a') + count($subsections[$last]['items']))) {
                $subsections[$last]['items'][] = array('label' => '('.$s[1].')', 'text' => $s[2]);
            } elseif (count($subsections[$last]['items']) > 0) {
                $subsections[$last]['items'][count($subsections[$last]['items']) - 1]['text'] .= ' '.$chunk;
            } else {
                $subsections[$last]['lead'] .= ' '.$chunk;
            }
        }

        $controls = array();

        foreach ($subsections as $subsection) {
            $id = $number.$subsection['label'];

            // A first subsection that opens straight into a paragraph: "(1)(a) ...".
            if (preg_match('/^\(a\)\s+(.*)$/s', $subsection['lead'], $s)) {
                array_unshift($subsection['items'], array('label' => '(a)', 'text' => $s[1]));
                $subsection['lead'] = '';
            }

            $text = $subsection['lead'];

            foreach ($subsection['items'] as $item) {
                $text .= ' '.$item['label'].' '.$item['text'];
            }

            $text = Source::normalise($text);

            if (!$is_duty($text)) {
                continue;
            }

            $objectives = array();
            $choice = preg_match('/\b(unless|if|one of|either)\b/i', $subsection['lead']) === 1;

            if (count($subsection['items']) > 1 && !$choice && preg_match('/\b(shall|must|may not)\b[^.]*:$/', $subsection['lead'])) {
                foreach ($subsection['items'] as $item) {
                    $objective = array(
                        'id' => $id.$item['label'],
                        'text' => Source::normalise($item['text'])
                    );

                    if (!preg_match('/\b(shall|must|may not)\b/', $item['text'])) {
                        $objective['description'] = ObjectiveText::sentence(rtrim($subsection['lead'], ':').' '.ObjectiveText::lower_first(ObjectiveText::item($objective['text'])));
                    }

                    $objectives[] = $objective;
                }
            }

            $controls[] = array(
                'id' => $id,
                'number' => $id,
                'text' => $text,
                'type' => 'subsection',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives
            );
        }

        $families[] = array(
            'number' => $number,
            'code' => 'ORS '.$number,
            'name' => $name,
            'controls' => $controls
        );
    }

    return $families;
};
