<?php
/**
 * Cyber Resilience Act, Regulation (EU) 2024/2847, from the Official Journal text
 * published by the Publications Office.
 *
 * - Groups, each carrying the economic operator `role` it binds:
 *   Article 13 and 14 (manufacturers), Annex I Part I and Part II (the essential
 *   cybersecurity requirements Article 13 makes binding), Annex II (information and
 *   instructions to the user, Article 13(18)), Annex VII (technical documentation,
 *   Article 13(12)), Article 19 (importers), 20 (distributors), 23 (all economic
 *   operators) and 24 (open-source software stewards).
 * - Controls from articles: every paragraph except those in $not_operator_duties; its
 *   lettered points are objectives.
 * - Controls from annexes: every numbered item. Annex I Part I, point (2) is a lead-in
 *   followed by the thirteen product requirements (a) to (m), which are assessed one by
 *   one, so each is a control. Elsewhere an item's lettered sub-items are objectives.
 *   An item that completes an introductory sentence carries a `description` joining them.
 * - Left out as whole articles: 1 to 12 (scope, definitions, product classification),
 *   15 to 17 (voluntary reporting, the reporting platform), 18 (authorised representatives,
 *   which are optional), 21 and 22 (when another operator counts as the manufacturer),
 *   25 onwards (conformity procedures, notified bodies, market surveillance, penalties).
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';
require_once __DIR__.'/eu-act-shared.php';

return function (): array {
    $act = new EuAct('eu-cra/reg-2024-2847.html');

    // Paragraphs that bind the Commission or a CSIRT, define a term, or only grant an option.
    $not_operator_duties = array(
        '13(11)',
        '13(24)',
        '13(25)',
        '14(5)',
        '14(6)',
        '14(9)',
        '14(10)'
    );
    $skipped = array();

    $article = function (int $number, string $role) use ($act, $not_operator_duties, &$skipped): array {
        $controls = array();

        foreach ($act->paragraphs($number) as $paragraph => $parts) {
            $ref = $paragraph === 0 ? (string) $number : $number.'('.$paragraph.')';

            if (in_array($ref, $not_operator_duties, true)) {
                $skipped[] = $ref;
                continue;
            }

            $controls[] = EuAct::control('Art. '.$ref, $parts);
        }

        return array(
            'number' => (string) $number,
            'code' => 'Art. '.$number,
            'name' => $act->title($number),
            'role' => $role,
            'controls' => $controls
        );
    };

    $sentence = function (string $lead, string $text): string {
        return ObjectiveText::sentence(ObjectiveText::stem($lead).' '.ObjectiveText::item($text));
    };

    // $split names the item labels whose points become controls of their own.
    $annex = function (string $id, int $part, string $code, string $name, array $split = array()) use ($act, $sentence): array {
        $section = $act->annex($id)[$part];
        $controls = array();

        foreach ($section['items'] as $item) {
            $label = '('.trim($item['label'], '().').')';
            $ref = $code.$label;

            if (in_array($label, $split, true)) {
                foreach ($item['points'] as $point) {
                    $controls[] = array(
                        'id' => $ref.$point['label'],
                        'number' => $ref.$point['label'],
                        'text' => $point['text'],
                        'description' => $sentence($item['text'], $point['text']),
                        'type' => 'requirement',
                        'weight' => null,
                        'partial_weight' => null,
                        'objectives' => array()
                    );
                }

                continue;
            }

            $text = $item['text'];
            $objectives = array();

            foreach ($item['points'] as $point) {
                $text .= ' '.$point['label'].' '.$point['text'];
                $objective = array(
                    'id' => $ref.$point['label'],
                    'text' => $point['text']
                );

                if (ObjectiveText::introduces_list($item['text'])) {
                    $objective['description'] = $sentence($item['text'], $point['text']);
                }

                $objectives[] = $objective;
            }

            $control = array(
                'id' => $ref,
                'number' => $ref,
                'text' => EuAct::clean($text),
                'type' => 'requirement',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives
            );

            if (ObjectiveText::introduces_list($section['lead'])) {
                $control['description'] = $sentence($section['lead'], $control['text']);
            }

            $controls[] = $control;
        }

        return array(
            'number' => $code,
            'code' => $code,
            'name' => $name === '' ? preg_replace('/^Part [IV]+ /', '', $section['title']) : $name,
            'role' => 'manufacturer',
            'controls' => $controls
        );
    };

    $families = array(
        $article(13, 'manufacturer'),
        $article(14, 'manufacturer'),
        $annex('anx_I', 0, 'Annex I, Part I', '', array('(2)')),
        $annex('anx_I', 1, 'Annex I, Part II', ''),
        $annex('anx_II', 0, 'Annex II', 'Information and instructions to the user'),
        $annex('anx_VII', 0, 'Annex VII', 'Content of the technical documentation'),
        $article(19, 'importer'),
        $article(20, 'distributor'),
        $article(23, 'any'),
        $article(24, 'open-source software steward')
    );

    if ($skipped !== $not_operator_duties) {
        throw new RuntimeException('Paragraphs listed as not operator duties were not all found: '.implode(', ', array_diff($not_operator_duties, $skipped)));
    }

    return $families;
};
