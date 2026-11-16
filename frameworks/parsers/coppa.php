<?php
/**
 * Children's Online Privacy Protection Rule, 16 CFR Part 312, from the eCFR XML snapshot.
 *
 * - Groups: the sections that place duties on operators: 312.4 (notice), 312.5 (parental
 *   consent), 312.6 (parental review), 312.7 (no conditioning of participation), 312.8
 *   (security) and 312.10 (retention and deletion).
 * - Controls: each lettered paragraph of a section; a section printed as one block of
 *   text (312.7, 312.10) is one control. 312.6(b) (a liability shield) and 312.6(c) (an
 *   option to end service) state no duty and are left out.
 * - Objectives: the numbered paragraphs directly beneath a control; deeper paragraphs
 *   stay in the objective's text with their own labels.
 * - Left out as whole sections: 312.1 and 312.2 (scope, definitions), 312.3 (a summary of
 *   the duties the later sections set out), 312.9 (enforcement), 312.11 and 312.12
 *   (safe harbor programs and voluntary approvals, addressed to industry groups) and
 *   312.13 (severability).
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';
require_once __DIR__.'/cfr-shared.php';

return function (): array {
    $sections = array(
        '312.4' => array(),
        '312.5' => array(),
        '312.6' => array('b', 'c'),
        '312.7' => array(),
        '312.8' => array(),
        '312.10' => array()
    );
    $families = array();

    foreach ($sections as $number => $left_out) {
        // PHP turns "312.10" style keys into floats only when numeric-looking integers; keep it text.
        $number = (string) $number;
        $section = Cfr::section('coppa/16-cfr-312.xml', $number);
        $controls = array();

        if (count($section['nodes']) === 0) {
            $controls[] = array(
                'id' => $number,
                'number' => $number,
                'title' => $section['name'],
                'text' => $section['lead'],
                'type' => 'requirement',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => array()
            );
        }

        foreach ($section['nodes'] as $node) {
            if (in_array($node['label'], $left_out, true)) {
                continue;
            }

            $id = $number.'('.$node['label'].')';
            $objectives = array();

            foreach ($node['children'] as $child) {
                $own = Cfr::render($child);
                $objective = array(
                    'id' => $id.'('.$child['label'].')',
                    'text' => $own
                );

                if (ObjectiveText::introduces_list($node['text']) && $child['title'] === '' && count($child['children']) === 0) {
                    $objective['description'] = ObjectiveText::sentence(ObjectiveText::stem($node['text']).' '.ObjectiveText::item($own));
                }

                $objectives[] = $objective;
            }

            $controls[] = array(
                'id' => $id,
                'number' => $id,
                'title' => $node['title'] === '' ? $section['name'] : rtrim($node['title'], '.'),
                'text' => Cfr::render($node, false),
                'type' => 'requirement',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives
            );
        }

        $families[] = array(
            'number' => $number,
            'code' => '',
            'name' => $section['name'],
            'controls' => $controls
        );
    }

    return $families;
};
