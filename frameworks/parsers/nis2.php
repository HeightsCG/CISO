<?php
/**
 * NIS 2 Directive (EU) 2022/2555, from the Official Journal text published by the
 * Publications Office.
 *
 * - Groups: the three articles of Chapter IV that place duties on essential and important
 *   entities: Article 20 (governance), Article 21 (cybersecurity risk-management measures)
 *   and Article 23 (reporting obligations). The rest of the Directive is addressed to
 *   Member States, authorities, CSIRTs and the Commission.
 * - Controls: the paragraphs of those articles that state an entity duty. Where a paragraph
 *   is a lead-in followed by lettered points that are assessed one by one (the ten measures
 *   of Article 21(2), the reporting stages of Article 23(4)), each point is a control and
 *   carries a `description` that joins the lead-in to it.
 * - Left out because they bind someone else or define a term: Article 21(5) (Commission
 *   implementing acts), Article 23(3) (what makes an incident significant) and
 *   Article 23(5) to (11) (CSIRT, authority and Commission tasks).
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $scope = array(
        20 => array(
            1 => 'paragraph',
            2 => 'paragraph'
        ),
        21 => array(
            1 => 'paragraph',
            2 => 'points',
            3 => 'paragraph',
            4 => 'paragraph'
        ),
        23 => array(
            1 => 'paragraph',
            2 => 'paragraph',
            4 => 'points'
        )
    );
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents(Source::path('nis2/dir-2022-2555.html')));
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $clean = function (string $text): string {
        return Source::normalise(str_replace("\u{a0}", ' ', $text));
    };

    // A point's text with any nested points kept inline behind their labels.
    $cell = function (DOMNode $node) use (&$cell, $clean): string {
        $parts = array();

        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'table') {
                $cells = (new DOMXPath($child->ownerDocument))->query('./tbody/tr/td | ./tr/td', $child);
                $parts[] = $clean($cells->item(0)->textContent).' '.$cell($cells->item(1));
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $parts[] = $clean($child->textContent);
            }
        }

        return $clean(implode(' ', array_filter($parts, 'strlen')));
    };

    $families = array();

    foreach ($scope as $article => $paragraphs) {
        $title = $xpath->query('//*[@id="art_'.$article.'.tit_1"]')->item(0);

        if ($title === null) {
            throw new RuntimeException('Article '.$article.' has no title');
        }

        $controls = array();

        foreach ($paragraphs as $number => $mode) {
            $div = $xpath->query('//*[@id="'.sprintf('%03d.%03d', $article, $number).'"]')->item(0);

            if ($div === null) {
                throw new RuntimeException('Article '.$article.'('.$number.') not found');
            }

            $lead = array();
            $points = array();
            $tail = array();

            foreach ($div->childNodes as $child) {
                if ($child->nodeName === 'table') {
                    $cells = $xpath->query('./tbody/tr/td | ./tr/td', $child);
                    $points[] = array(
                        'label' => $clean($cells->item(0)->textContent),
                        'text' => $cell($cells->item(1))
                    );
                } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                    if (count($points) === 0) {
                        $lead[] = $clean($child->textContent);
                    } else {
                        $tail[] = $clean($child->textContent);
                    }
                }
            }

            $lead = implode(' ', $lead);

            if (!preg_match('/^'.$number.'\. (.+)$/s', $lead, $m)) {
                throw new RuntimeException('Article '.$article.'('.$number.') does not open with its number');
            }

            $lead = $m[1];
            $id = 'Art. '.$article.'('.$number.')';

            if ($mode === 'paragraph') {
                $text = $lead;

                foreach ($points as $point) {
                    $text .= ' '.$point['label'].' '.$point['text'];
                }

                $controls[] = array(
                    'id' => $id,
                    'number' => $id,
                    'text' => $clean($text.' '.implode(' ', $tail)),
                    'type' => 'paragraph',
                    'weight' => null,
                    'partial_weight' => null,
                    'objectives' => array()
                );
                continue;
            }

            if (count($points) === 0 || !ObjectiveText::introduces_list($lead)) {
                throw new RuntimeException($id.' is not a lead-in with points');
            }

            foreach ($points as $point) {
                $controls[] = array(
                    'id' => $id.$point['label'],
                    'number' => $id.$point['label'],
                    'text' => $point['text'],
                    'description' => ObjectiveText::sentence($lead.' '.ObjectiveText::item($point['text'])),
                    'type' => 'point',
                    'lead_in' => $lead,
                    'weight' => null,
                    'partial_weight' => null,
                    'objectives' => array()
                );
            }
        }

        $families[] = array(
            'number' => (string) $article,
            'code' => 'Art. '.$article,
            'name' => $clean($title->textContent),
            'controls' => $controls
        );
    }

    return $families;
};
