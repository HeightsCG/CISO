<?php
/**
 * Texas Data Privacy and Security Act (Business and Commerce Code, Chapter 541), from the
 * chapter page of the Texas Constitution and Statutes published by the Texas Legislative
 * Council.
 *
 * - Groups: the sections that place duties on controllers and processors: 541.051 to
 *   541.053 and 541.055 (consumer rights, responses, appeals and request methods;
 *   541.054 only voids waivers of those rights) and 541.101 to
 *   541.107 (controller duties, privacy notice, sale and targeted advertising, processor
 *   duties, data protection assessments, de-identified data, small businesses).
 * - Controls: every lettered subsection that says "shall", "must" or "may not" of the
 *   business; a section without subsections is one control. Left out: subsections where
 *   those words only construe or exempt ("may not be construed"), grant a consumer a
 *   right, or bind the attorney general.
 * - Objectives: the numbered items of a subsection. Texas joins every enumeration with
 *   "or", so the items are each required (or each prohibited, under "may not:") unless
 *   the lead-in makes them a choice ("unless", "if", "one of"), in which case the
 *   subsection is assessed whole.
 * - Left out: subchapter A (definitions, applicability, exemptions), subchapter D
 *   (enforcement) and subchapter E (construction and other provisions).
 *
 * In the page every provision is a <p> whose text-indent gives its level: 7ex for a
 * subsection (the first also carries the bold section heading), 13ex for (1), 19ex for
 * (A). History notes ("Added by Acts ...") are not law text.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $sections = array('541.051', '541.052', '541.053', '541.055', '541.101', '541.102', '541.103', '541.104', '541.105', '541.106', '541.107');
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents(Source::path('us-tx-dpsa/BC.541.htm')));
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $paragraphs = array();

    foreach ($xpath->query('//p') as $p) {
        $indent = preg_match('/text-indent:\s*(\d+)ex/', $p->getAttribute('style'), $m) ? (int) $m[1] : 0;
        $paragraphs[] = array(
            'indent' => $indent,
            'text' => Source::normalise(str_replace("\u{a0}", ' ', $p->textContent))
        );
    }

    $is_duty = function (string $text): bool {
        $text = preg_replace('/\b(shall|must|may) (not )?(be construed|apply|be deemed|be considered|be void|have the right)\b/', '', $text);

        return preg_match('/\b(shall|must|may not)\b/', $text) === 1 && preg_match('/^(The )?attorney general\b|^Nothing in /i', $text) === 0;
    };

    $families = array();

    foreach ($sections as $number) {
        $start = null;

        foreach ($paragraphs as $i => $p) {
            if (strpos($p['text'], 'Sec. '.$number.'. ') === 0) {
                $start = $i;
            }
        }

        if ($start === null) {
            throw new RuntimeException('Section '.$number.' not found');
        }

        if (!preg_match('/^Sec\. '.preg_quote($number, '/').'\. ([^a-z]+?)\.\s+(.*)$/', $paragraphs[$start]['text'], $m)) {
            throw new RuntimeException('Section '.$number.': heading not found');
        }

        $name = ucfirst(strtolower($m[1]));
        $subsections = array();
        $chunks = array(array('indent' => 7, 'text' => $m[2]));

        for ($i = $start + 1; $i < count($paragraphs) && strpos($paragraphs[$i]['text'], 'Sec. ') !== 0; $i++) {
            if ($paragraphs[$i]['text'] === '' || preg_match('/^(Added|Amended|Redesignated|Transferred) by Acts\b/', $paragraphs[$i]['text'])) {
                continue;
            }

            $chunks[] = $paragraphs[$i];
        }

        $expect = 'a';

        foreach ($chunks as $chunk) {
            if ($chunk['indent'] <= 7 && preg_match('/^\('.$expect.'\)\s+(.*)$/', $chunk['text'], $m)) {
                $subsections[] = array('label' => '('.$expect.')', 'lead' => $m[1], 'items' => array());
                $expect = chr(ord($expect) + 1);
            } elseif (count($subsections) === 0) {
                $subsections[] = array('label' => '', 'lead' => $chunk['text'], 'items' => array());
            } elseif ($chunk['indent'] >= 13 && preg_match('/^\((\d+)\)\s+(.*)$/', $chunk['text'], $m) && $chunk['indent'] < 19) {
                $subsections[count($subsections) - 1]['items'][] = array('label' => '('.$m[1].')', 'text' => $m[2]);
            } else {
                $last = count($subsections) - 1;
                $item = count($subsections[$last]['items']) - 1;

                if ($item >= 0) {
                    $subsections[$last]['items'][$item]['text'] .= ' '.$chunk['text'];
                } else {
                    $subsections[$last]['lead'] .= ' '.$chunk['text'];
                }
            }
        }

        $controls = array();

        foreach ($subsections as $subsection) {
            $id = $number.$subsection['label'];
            $text = $subsection['lead'];

            foreach ($subsection['items'] as $item) {
                $text .= ' '.$item['label'].' '.$item['text'];
            }

            $text = Source::normalise($text);

            if (!$is_duty($text)) {
                continue;
            }

            // Texas joins every enumeration with "or"; a list is a choice only when its lead-in says so.
            $alternatives = preg_match('/\b(unless|if|one of|any of|either)\b/i', $subsection['lead']) === 1;

            $objectives = array();

            if (count($subsection['items']) > 1 && !$alternatives && preg_match('/\b(shall|must|may not)\b[^.]*:$|^A controller:$|^A processor:$/', $subsection['lead'])) {
                foreach ($subsection['items'] as $item) {
                    $objective = array(
                        'id' => $id.$item['label'],
                        'text' => $item['text']
                    );

                    if (!preg_match('/^[A-Z]/', $item['text'])) {
                        $objective['description'] = ObjectiveText::sentence(rtrim($subsection['lead'], ':').' '.ObjectiveText::item($item['text']));
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
            'code' => 'Sec. '.$number,
            'name' => $name,
            'controls' => $controls
        );
    }

    return $families;
};
