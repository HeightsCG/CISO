<?php
/**
 * General Data Protection Regulation, Regulation (EU) 2016/679, from the consolidated
 * text published by the Publications Office (original act with its corrigenda).
 *
 * - Groups: the articles that place duties on controllers and processors or define the
 *   rights they must honour: Articles 5 to 11 (principles), 12 to 22 (rights of the data
 *   subject), 24 to 39 (controller and processor) and 44, 46 to 49 (transfers). Each
 *   group carries its chapter.
 * - Controls: every paragraph of those articles (an article without numbered paragraphs
 *   is one control), except the ones listed in $not_entity_duties.
 * - Objectives: the lettered points of a paragraph.
 * - Left out as whole articles: 1 to 4 (scope and definitions), 23 (restrictions Member
 *   States may legislate), 40 to 43 (codes of conduct and certification, which are
 *   voluntary), 45 (Commission adequacy decisions) and 50 onwards (supervisory
 *   authorities, cooperation, remedies, penalties, final provisions).
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';
require_once __DIR__.'/eu-act-shared.php';

return function (): array {
    $chapters = array(
        'II' => array(
            'name' => 'Principles',
            'articles' => range(5, 11)
        ),
        'III' => array(
            'name' => 'Rights of the data subject',
            'articles' => range(12, 22)
        ),
        'IV' => array(
            'name' => 'Controller and processor',
            'articles' => range(24, 39)
        ),
        'V' => array(
            'name' => 'Transfers of personal data to third countries or international organisations',
            'articles' => array(44, 46, 47, 48, 49)
        )
    );

    // Paragraphs that bind Member States, the Commission or a supervisory authority, or only grant an option.
    $not_entity_duties = array(
        '6(2)',
        '6(3)',
        '8(3)',
        '9(4)',
        '12(8)',
        '24(3)',
        '25(3)',
        '27(5)',
        '28(5)',
        '28(6)',
        '28(7)',
        '28(8)',
        '30(5)',
        '32(3)',
        '34(4)',
        '35(4)',
        '35(5)',
        '35(6)',
        '35(8)',
        '35(10)',
        '36(2)',
        '36(4)',
        '36(5)',
        '37(2)',
        '37(3)',
        '37(4)',
        '38(4)',
        '46(4)',
        '46(5)',
        '47(1)',
        '47(3)',
        '49(3)',
        '49(4)',
        '49(5)'
    );
    $act = new EuAct('gdpr/reg-2016-679-consolidated-2016-05-04.html');
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

                $controls[] = EuAct::control('Art. '.$ref, $parts);
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
