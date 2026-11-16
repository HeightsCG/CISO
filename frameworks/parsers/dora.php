<?php
/**
 * Digital Operational Resilience Act, Regulation (EU) 2022/2554, from the Official
 * Journal text published by the Publications Office.
 *
 * - Groups: the articles that place duties on financial entities: Articles 5 to 14
 *   (ICT risk management), 17 to 19 (incident management, classification and
 *   reporting), 24 to 27 (resilience testing) and 28 to 30 (ICT third-party risk).
 *   Each group carries its chapter.
 * - Controls: every numbered paragraph of those articles (Article 7, which has no
 *   numbered paragraphs, is one control), except the ones listed in $not_entity_duties.
 * - Objectives: the lettered points of a paragraph.
 * - `excludes_microenterprises` marks paragraphs the Regulation applies to "financial
 *   entities, other than microenterprises".
 * - Left out as whole articles: 15, 20 to 22 (tasks of the ESAs and authorities), 16 (the
 *   simplified framework that replaces Articles 5 to 15 for the small entities it lists),
 *   23 (extends Chapter III to payment-related incidents), 31 onwards (oversight of
 *   critical ICT third-party providers, authorities, final provisions) and 45 (voluntary
 *   information sharing).
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';
require_once __DIR__.'/eu-act-shared.php';

return function (): array {
    $chapters = array(
        'II' => array(
            'name' => 'ICT risk management',
            'articles' => range(5, 14)
        ),
        'III' => array(
            'name' => 'ICT-related incident management, classification and reporting',
            'articles' => array(17, 18, 19)
        ),
        'IV' => array(
            'name' => 'Digital operational resilience testing',
            'articles' => array(24, 25, 26, 27)
        ),
        'V' => array(
            'name' => 'Managing of ICT third-party risk',
            'articles' => array(28, 29, 30)
        )
    );

    // Paragraphs that bind the ESAs, authorities or Member States, or only grant an option.
    $not_entity_duties = array(
        '6(9)',
        '11(11)',
        '18(3)',
        '18(4)',
        '19(2)',
        '19(6)',
        '19(7)',
        '19(8)',
        '26(7)',
        '26(9)',
        '26(10)',
        '26(11)',
        '28(9)',
        '28(10)',
        '30(5)'
    );
    $act = new EuAct('dora/reg-2022-2554.html');
    $families = array();
    $skipped = array();

    foreach ($chapters as $chapter => $info) {
        foreach ($info['articles'] as $article) {
            $controls = array();

            foreach ($act->paragraphs($article) as $number => $parts) {
                $ref = $number === 0 ? (string) $article : $article.'('.$number.')';

                if (in_array($ref, $not_entity_duties, true)) {
                    $skipped[] = $ref;
                    continue;
                }

                $control = EuAct::control('Art. '.$ref, $parts);
                $control['excludes_microenterprises'] = strpos($control['text'], 'other than microenterprises') !== false;
                $controls[] = $control;
            }

            $families[] = array(
                'number' => (string) $article,
                'code' => 'Art. '.$article,
                'name' => $act->title($article),
                'chapter' => 'Chapter '.$chapter,
                'chapter_name' => $info['name'],
                'controls' => $controls
            );
        }
    }

    if ($skipped !== $not_entity_duties) {
        throw new RuntimeException('Paragraphs listed as not entity duties were not all found: '.implode(', ', array_diff($not_entity_duties, $skipped)));
    }

    return $families;
};
