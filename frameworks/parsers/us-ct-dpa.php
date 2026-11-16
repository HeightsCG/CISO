<?php
/**
 * Connecticut Data Privacy Act (Connecticut General Statutes, Chapter 743jj), from the
 * chapter page the Connecticut General Assembly publishes.
 *
 * - Groups: the sections that place duties on controllers and processors. Part I:
 *   42-518 (consumer rights and controller compliance), 42-519 (authorized agents and
 *   opt-out), 42-520 (controller duties), 42-521 (processor duties), 42-522 (data
 *   protection assessments), 42-523 (de-identified and pseudonymous data), 42-526
 *   (consumer health data) and 42-528 (social media platforms and minors). Part II:
 *   42-529a to 42-529c (heightened duties for online services offered to minors).
 * - Controls: every lettered subsection that says "shall" of the business. Left out:
 *   subsections where "shall" only grants consumers a right, construes, exempts, voids,
 *   dates or classifies a violation, or binds the Attorney General.
 * - Objectives: the numbered items a subsection lists inline ("shall: (1) ...; (2) ...;
 *   and (3) ..."), only where every one must be met (no item is joined by "or").
 * - Left out: definitions, applicability and exemptions (42-515 to 42-517, 42-529,
 *   42-529d), 42-524 (construction) and enforcement (42-525, 42-529e).
 *
 * In the page a section is a run of <p> elements: the first opens with
 * <span class="catchln">Sec. n. Catchline.</span>, each subsection with "(a)", and nested
 * paragraphs ("(A)", "(i)") follow as their own <p>. Source and history notes carry the
 * classes source-first, source, history-first and history.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $sections = array(
        'Part I' => array('42-518', '42-519', '42-520', '42-521', '42-522', '42-523', '42-526', '42-528'),
        'Part II' => array('42-529a', '42-529b', '42-529c')
    );
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents(Source::path('us-ct-dpa/chap_743jj.htm')));
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    // Splits "lead: (1) one; (2) two; and (3) three." into the lead and its numbered items.
    $split = function (string $text): array {
        $at = strpos($text, ': (1) ');

        if ($at === false) {
            return array($text, array());
        }

        $lead = substr($text, 0, $at + 1);
        $rest = substr($text, $at + 2);
        $items = array();
        $n = 1;

        while (true) {
            $next = $n + 1;
            $pos = preg_match('/; (?:and |or )?\('.$next.'\) /', $rest, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : false;
            $body = $pos === false ? $rest : substr($rest, 0, $pos + 1);
            $items[] = array(
                'label' => '('.$n.')',
                'text' => trim(substr($body, strlen('('.$n.') '))),
                'joined_by_or' => $pos !== false && strpos($m[0][0], '; or ') === 0
            );

            if ($pos === false) {
                break;
            }

            $rest = substr($rest, $pos + strlen($m[0][0]) - strlen('('.$next.') '));
            $n = $next;
        }

        return array($lead, $items);
    };

    $families = array();

    foreach ($sections as $part => $numbers) {
        foreach ($numbers as $number) {
            $span = $xpath->query('//span[@class="catchln"][@id="sec_'.$number.'"]')->item(0);

            if ($span === null) {
                throw new RuntimeException('Section '.$number.' not found');
            }

            $name = preg_replace('/^Sec\. '.preg_quote($number, '/').'\.\s*/', '', Source::normalise($span->textContent));
            $p = $span->parentNode;
            $subsections = array();
            $expect = 'a';
            $first = true;

            while ($p !== null) {
                if ($p->nodeType !== XML_ELEMENT_NODE) {
                    $p = $p->nextSibling;
                    continue;
                }

                if (!$first && ($p->nodeName !== 'p' || $xpath->query('.//span[@class="catchln"]', $p)->length > 0 || preg_match('/\b(source|history)/', $p->getAttribute('class')))) {
                    break;
                }

                $text = Source::normalise($p->textContent);

                if ($first) {
                    $text = trim(substr($text, strlen(Source::normalise($span->textContent))));
                    $first = false;
                }

                if (preg_match('/^\('.$expect.'\) ?(.*)$/s', $text, $m)) {
                    $subsections[] = array(
                        'label' => '('.$expect.')',
                        'text' => $m[1]
                    );
                    $expect = chr(ord($expect) + 1);
                } elseif (count($subsections) === 0) {
                    $subsections[] = array(
                        'label' => '',
                        'text' => $text
                    );
                } else {
                    $subsections[count($subsections) - 1]['text'] .= ' '.$text;
                }

                $p = $p->nextSibling;
            }

            $controls = array();

            foreach ($subsections as $subsection) {
                $id = $number.$subsection['label'];
                $text = $subsection['text'];
                $duty = preg_replace('/\bshall (not )?(be construed|apply|be deemed|be considered|be void|have the right|constitute|be confidential|be exempt|be enforced)\b|\bshall not be required\b/', '', $text);

                if (!preg_match('/\bshall\b/', $duty) || preg_match('/^(The )?Attorney General shall\b|^Nothing in /', $text)) {
                    continue;
                }

                list($lead, $items) = $split($text);
                $objectives = array();
                $alternatives = false;

                foreach ($items as $item) {
                    $alternatives = $alternatives || $item['joined_by_or'];
                }

                if (count($items) > 1 && !$alternatives && preg_match('/\bshall\b[^.]*:$/', $lead)) {
                    foreach ($items as $item) {
                        $objective = array(
                            'id' => $id.$item['label'],
                            'text' => $item['text']
                        );

                        if (!preg_match('/\bshall\b/', $item['text'])) {
                            $objective['description'] = ObjectiveText::sentence(ObjectiveText::stem($lead).' '.ObjectiveText::lower_first(ObjectiveText::item($item['text'])));
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
                'name' => rtrim($name, '.'),
                'part' => $part,
                'controls' => $controls
            );
        }
    }

    return $families;
};
