<?php
/**
 * Cyber Essentials: Requirements for IT Infrastructure v3.3 (NCSC, April 2026), section E
 * "Requirements by technical control theme", from the PDF the NCSC publishes.
 *
 * - Groups are the five themes: Firewalls, Secure Configuration, Security Update
 *   Management, User Access Control, Malware protection. Each carries its "Applies to"
 *   line and its aim.
 * - Controls are the paragraphs of a theme's "Requirements" part that state a
 *   requirement. Cyber Essentials prints no requirement numbers, so a control's id is the
 *   theme number and its order in the theme (1.1, 1.2 ...), assigned here. A control's
 *   title is the sub-heading it sits under, or the theme name.
 * - `force` is "must" for a paragraph that says must, and "should" for a paragraph that
 *   says only should but introduces a list of measures (the password-based authentication
 *   measures). Other "should" sentences are advice and are left out, as are the notes
 *   ("Information:", "Please note:", "Caution:", "Example:").
 * - Objectives are the bulleted items that follow a control, with any sub-bullets
 *   ("o ...") kept inside their item.
 *
 * The text is read from the layout-preserving extract: page furniture (the running title,
 * date, copyright line and page number) is removed and paragraphs are split on blank lines.
 */
return function (): array {
    $text = Source::pdf_text('cyber-essentials/cyber-essentials-requirements-for-it-infrastructure-v3-3.pdf');
    $text = substr($text, strrpos($text, 'E. Requirements by technical control theme'));
    $text = substr($text, 0, strpos($text, 'F. Further guidance'));
    $lines = array();

    foreach (preg_split('/\R/', str_replace("\f", "\n", $text)) as $line) {
        $trimmed = trim($line);

        if (preg_match('/^(Cyber Essentials: Requirements for IT Infrastructure v3\.3|April 2026|All material is UK Crown Copyright ©|\d+)$/u', $trimmed)) {
            continue;
        }

        $lines[] = rtrim($line);
    }

    // Paragraphs: blank-line separated; a bullet starts its own paragraph.
    $paragraphs = array();
    $current = null;
    $bullet = null;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            $current = null;
            continue;
        }

        if (preg_match('/^•\s+(.*)$/u', $trimmed, $m)) {
            $paragraphs[] = array('kind' => 'bullet', 'text' => $m[1]);
            $current = count($paragraphs) - 1;
            $bullet = $current;
            continue;
        }

        // A sub-bullet belongs to the bullet above it, even across a blank line.
        if (preg_match('/^o\s+(.*)$/u', $trimmed, $m) && $bullet !== null) {
            $paragraphs[$bullet]['text'] .= ' - '.$m[1];
            $current = $bullet;
            continue;
        }

        if ($current === null) {
            $bullet = null;
        }

        if ($current === null) {
            $paragraphs[] = array('kind' => 'text', 'text' => $trimmed);
            $current = count($paragraphs) - 1;
        } else {
            $paragraphs[$current]['text'] .= ' '.$trimmed;
        }
    }

    $families = array();
    $theme = null;
    $in_requirements = false;
    $subheading = '';
    $control = null;

    foreach ($paragraphs as $p) {
        $p['text'] = Source::normalise($p['text']);

        if ($p['kind'] === 'text' && preg_match('/^([1-5])\. (Firewalls|Secure Configuration|Security Update Management|User Access Control|Malware protection)(?: Applies to: (.*))?$/', $p['text'], $m)) {
            $theme = $m[1];
            $families[$theme] = array(
                'number' => $theme,
                'code' => 'CE '.$theme,
                'name' => $m[2],
                'applies_to' => $m[3] ?? '',
                'aim' => '',
                'controls' => array()
            );
            $in_requirements = false;
            $subheading = '';
            $control = null;
            continue;
        }

        if ($theme === null) {
            continue;
        }

        if ($p['kind'] === 'text' && strpos($p['text'], 'Applies to: ') === 0) {
            $families[$theme]['applies_to'] = substr($p['text'], strlen('Applies to: '));
            continue;
        }

        if ($p['kind'] === 'text' && $p['text'] === 'Requirements') {
            $in_requirements = true;
            continue;
        }

        if (!$in_requirements) {
            continue;
        }

        if ($p['kind'] === 'bullet') {
            if ($control !== null) {
                $last = count($families[$theme]['controls']) - 1;
                $families[$theme]['controls'][$last]['objectives'][] = array(
                    'id' => $families[$theme]['controls'][$last]['id'].'.'.chr(ord('a') + count($families[$theme]['controls'][$last]['objectives'])),
                    'text' => $p['text']
                );
            }

            continue;
        }

        $control = null;

        if (preg_match('/^(Information|Please note|Caution|Example):|^\*/', $p['text'])) {
            continue;
        }

        // A sub-heading: a short line that is not a sentence.
        if (!preg_match('/[.:]$/', $p['text']) && str_word_count($p['text']) <= 16) {
            $subheading = $p['text'];
            continue;
        }

        $must = preg_match('/\bmust\b/', $p['text']) === 1;
        $should = !$must && preg_match('/\bshould\b/', $p['text']) === 1 && substr($p['text'], -1) === ':';

        if (!$must && !$should) {
            continue;
        }

        $id = $theme.'.'.(count($families[$theme]['controls']) + 1);
        $families[$theme]['controls'][] = array(
            'id' => $id,
            'number' => $id,
            'title' => $subheading === '' ? $families[$theme]['name'] : $subheading,
            'text' => $p['text'],
            'type' => 'requirement',
            'force' => $must ? 'must' : 'should',
            'weight' => null,
            'partial_weight' => null,
            'objectives' => array()
        );
        $control = $id;
    }

    // A requirement's text includes its list; its bullets are its elements.
    foreach ($families as $t => $family) {
        foreach ($family['controls'] as $c => $built) {
            foreach ($built['objectives'] as $objective) {
                $families[$t]['controls'][$c]['text'] .= ' • '.$objective['text'];
            }
        }
    }

    return array_values($families);
};
