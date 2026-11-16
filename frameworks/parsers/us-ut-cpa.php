<?php
/**
 * Utah Consumer Privacy Act (Utah Code, Title 13, Chapter 61), from the chapter XML the
 * Utah Legislature publishes (le.utah.gov/xcode, version C13-61_2022050420231231, which
 * carries amendments through the 2026 General Session).
 *
 * - Groups: the sections that place duties on controllers and processors: 13-61-203
 *   (how a controller responds to consumer requests) and Part 3 (13-61-301 to 13-61-303:
 *   responsibility by role, controller responsibilities, de-identified and pseudonymous
 *   data). 13-61-201 and 13-61-202 grant the consumer rights and say how consumers and
 *   their parents or guardians exercise them; the controller's duty to honour them is
 *   13-61-203(1).
 * - Controls: every subsection that says "shall", "must" or "may not" of the business.
 *   A subsection with no words of its own ("(1)(a) ... (b) ...") gives one control per
 *   lettered subsection instead. Left out: provisions where those words only construe or
 *   exempt, grant a right, or bind the attorney general or the Division of Consumer
 *   Protection.
 * - Objectives: the subsections beneath a control whose own words state the duty and end
 *   with a colon. Utah joins lists with "or" throughout, so they are each required unless
 *   the lead-in makes them a choice ("unless", "if", "one of").
 * - Left out: Part 1 (definitions, applicability, exemptions), 13-61-304 (limitations),
 *   13-61-305 (no private cause of action), Part 4 (enforcement) and Part 5 (motor vehicle
 *   data privacy, effective 1 January 2027).
 * - Where the XML holds two versions of a section, the one in effect on the source date is
 *   used: a version with an <enddate> already passed, or an <effdate> still to come, is
 *   skipped.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $as_of = '2026-11-01';
    $sections = array('13-61-203', '13-61-301', '13-61-302', '13-61-303');
    $xml = simplexml_load_file(Source::path('us-ut-cpa/C13-61.xml'));

    $date = function (?string $us): ?string {
        if ($us === null || trim($us) === '') {
            return null;
        }

        list($m, $d, $y) = explode('/', trim($us));

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    };

    // A subsection's own words, without the subsections inside it.
    $own = function (SimpleXMLElement $node): string {
        $dom = dom_import_simplexml($node);
        $text = '';

        foreach ($dom->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE || ($child->nodeType === XML_ELEMENT_NODE && !in_array($child->nodeName, array('subsection', 'histories', 'catchline', 'effdate', 'enddate'), true))) {
                $text .= $child->textContent;
            }
        }

        return Source::normalise($text);
    };

    $render = function (SimpleXMLElement $node) use (&$render, $own): string {
        $text = $own($node);

        foreach ($node->subsection as $child) {
            $label = substr((string) $child['number'], strrpos((string) $child['number'], '('));
            $text .= ' '.$label.' '.$render($child);
        }

        return Source::normalise($text);
    };

    $is_duty = function (string $text): bool {
        $text = preg_replace('/\b(shall|must|may) (not )?(be construed|apply|be deemed|be considered|be void|have the right)\b|\bdoes not require\b/', '', $text);

        return preg_match('/\b(shall|must|may not)\b/', $text) === 1 && preg_match('/^(The )?(attorney general|division)\b|^Nothing in /i', $text) === 0;
    };

    $build = function (SimpleXMLElement $node) use ($own, $render, $is_duty): ?array {
        $id = (string) $node['number'];
        $text = $render($node);

        if (!$is_duty($text)) {
            return null;
        }

        $lead = $own($node);
        $objectives = array();
        $children = $node->subsection;

        if (count($children) > 1 && preg_match('/\b(shall|must|may not)\b[^.]*:$/', $lead) && !preg_match('/\b(unless|if|one of|either)\b/i', $lead)) {
            foreach ($children as $child) {
                $item = $render($child);
                $objective = array(
                    'id' => (string) $child['number'],
                    'text' => $item
                );

                if (count($child->subsection) === 0 && !preg_match('/\b(shall|must|may not)\b/', $item)) {
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
            'weight' => null,
            'partial_weight' => null,
            'objectives' => $objectives
        );
    };

    $families = array();

    foreach ($sections as $number) {
        $section = null;

        foreach ($xml->xpath('//section[@number="'.$number.'"]') as $candidate) {
            $effective = $date((string) $candidate->effdate);
            $ends = $date((string) $candidate->enddate);

            if (($effective === null || $effective <= $as_of) && ($ends === null || $ends > $as_of)) {
                $section = $candidate;
            }
        }

        if ($section === null) {
            throw new RuntimeException('No version of '.$number.' in effect on '.$as_of);
        }

        $controls = array();

        foreach ($section->subsection as $subsection) {
            $units = $own($subsection) === '' && count($subsection->subsection) > 0 ? $subsection->subsection : array($subsection);

            foreach ($units as $unit) {
                $built = $build($unit);

                if ($built !== null) {
                    $controls[] = $built;
                }
            }
        }

        $families[] = array(
            'number' => $number,
            'code' => '§ '.$number,
            'name' => rtrim(Source::normalise((string) $section->catchline), '.'),
            'controls' => $controls
        );
    }

    return $families;
};
