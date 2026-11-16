<?php
/**
 * Virginia Consumer Data Protection Act (Code of Virginia, Title 59.1, Chapter 53), from
 * the chapter page of the Code of Virginia published by the Division of Legislative
 * Automated Systems.
 *
 * - Groups: the sections that place duties on controllers and processors: 59.1-577
 *   (consumer rights and how a controller must respond), 59.1-577.1 (social media
 *   platforms and minors), 59.1-578 (controller duties and transparency), 59.1-579
 *   (controllers and processors), 59.1-580 (data protection assessments) and 59.1-581
 *   (de-identified and pseudonymous data).
 * - Controls: every lettered subsection that says "shall" of the business. Left out:
 *   subsections where "shall" only construes, exempts, voids or dates the chapter
 *   ("shall be construed", "shall not apply", "shall be deemed"), or binds the
 *   Attorney General.
 * - Objectives: the numbered items of a subsection, only where every one must be met
 *   (the list completes a sentence that says shall, and no item ends in "or").
 * - Left out: 59.1-575 and 59.1-576 (definitions, scope and exemptions, carried as a
 *   rule), 59.1-582 (limitations), 59.1-583 and 59.1-584 (enforcement).
 *
 * In the page each section is a bold heading followed by <section class="body">, whose
 * <p> elements open with their label: "A.", "1.", "a.". The closing history note
 * ("2021, Sp. Sess. I, cc. 35, 36; ...") is not law text.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $sections = array(
        '59.1-577',
        '59.1-577.1',
        '59.1-578',
        '59.1-579',
        '59.1-580',
        '59.1-581'
    );
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents(Source::path('us-va-cdpa/va-code-59.1-chapter-53.html')));
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $families = array();

    foreach ($sections as $number) {
        $heading = null;

        foreach ($xpath->query('//b') as $b) {
            if (preg_match('/^§ '.preg_quote($number, '/').'\. (.+?)\.?$/u', Source::normalise($b->textContent), $m)) {
                $heading = $b;
                $name = $m[1];
            }
        }

        if ($heading === null) {
            throw new RuntimeException('Section '.$number.' not found');
        }

        $body = $heading->nextSibling;

        while ($body !== null && $body->nodeName !== 'section') {
            $body = $body->nextSibling;
        }

        $subsections = array();
        $letter = 'A';

        foreach ($xpath->query('./p', $body) as $p) {
            $text = Source::normalise($p->textContent);

            if ($text === '' || preg_match('/^\d{4}, (Sp\. Sess\. [IVX]+, )?cc?\. /', $text)) {
                continue;
            }

            if (preg_match('/^([A-Z])\. (.*)$/s', $text, $m) && $m[1] === $letter) {
                $subsections[] = array(
                    'label' => $m[1],
                    'lead' => $m[2],
                    'items' => array(),
                    'tail' => ''
                );
                $letter = chr(ord($letter) + 1);
                continue;
            }

            if (count($subsections) === 0) {
                // A section without lettered subsections is one block.
                $subsections[] = array(
                    'label' => '',
                    'lead' => '',
                    'items' => array(),
                    'tail' => ''
                );
            }

            $last = count($subsections) - 1;

            if (preg_match('/^(\d+)\. (.*)$/s', $text, $m) && (int) $m[1] === count($subsections[$last]['items']) + 1) {
                $subsections[$last]['items'][] = array(
                    'label' => $m[1],
                    'text' => $m[2]
                );
            } elseif (preg_match('/^([a-z])\. (.*)$/s', $text, $m) && count($subsections[$last]['items']) > 0) {
                $item = count($subsections[$last]['items']) - 1;
                $subsections[$last]['items'][$item]['text'] .= ' '.$m[1].'. '.$m[2];
            } elseif (count($subsections[$last]['items']) > 0) {
                $subsections[$last]['tail'] = trim($subsections[$last]['tail'].' '.$text);
            } else {
                $subsections[$last]['lead'] = trim($subsections[$last]['lead'].' '.$text);
            }
        }

        $controls = array();

        foreach ($subsections as $subsection) {
            $id = $number.($subsection['label'] === '' ? '' : '('.$subsection['label'].')');
            $text = $subsection['lead'];
            $alternatives = false;

            foreach ($subsection['items'] as $item) {
                $text .= ' '.$item['label'].'. '.$item['text'];
                $alternatives = $alternatives || preg_match('/[;,]\s*or$/', $item['text']) === 1;
            }

            $text = Source::normalise(trim($text.' '.$subsection['tail']));

            // "Shall" that construes, exempts, voids or dates the chapter imposes no duty on the business.
            $duty = preg_replace('/\bshall (not )?(be construed|apply|be deemed|be considered|be void)\b/', '', $text);

            if (!preg_match('/\bshall\b/', $duty) || preg_match('/^(The )?Attorney General shall\b/', $text) || preg_match('/^Nothing in this (section|chapter)\b/', $text)) {
                continue;
            }

            $objectives = array();

            if (count($subsection['items']) > 0 && !$alternatives && preg_match('/\bshall\b[^.]*:$/', $subsection['lead'])) {
                foreach ($subsection['items'] as $item) {
                    $objective = array(
                        'id' => $id.'('.$item['label'].')',
                        'text' => $item['text']
                    );

                    // An item that is a sentence of its own ("A controller shall respond ...") already reads alone.
                    if (!preg_match('/\bshall\b/', $item['text'])) {
                        $objective['description'] = ObjectiveText::sentence(ObjectiveText::stem($subsection['lead']).' '.ObjectiveText::lower_first(ObjectiveText::item($item['text'])));
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
            'code' => '§ '.$number,
            'name' => $name,
            'controls' => $controls
        );
    }

    return $families;
};
