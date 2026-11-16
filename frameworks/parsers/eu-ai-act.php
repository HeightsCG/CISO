<?php
/**
 * EU Artificial Intelligence Act, Regulation (EU) 2024/1689, from the Official Journal
 * text published by the Publications Office.
 *
 * - Groups: the articles that place duties on operators of AI systems and providers of
 *   general-purpose AI models. Each group carries its chapter, the operator `role` it
 *   binds and the kind of system it covers (`system_type`), so an assessment can mark
 *   whole articles not applicable (a deployer has no Article 16 duties).
 * - Controls: every paragraph of those articles (an article without numbered paragraphs
 *   is one control), except the ones listed in $not_operator_duties.
 * - Objectives: the lettered points of a paragraph.
 * - Left out as whole articles: 1 to 3 (scope, definitions), 6 and 7 (classification
 *   rules, carried as an applicability rule), 28 to 42 and 44 to 46 (notifying
 *   authorities, notified bodies, standards, certificates), 51, 52 and 56 (classification
 *   of general-purpose models, codes of practice), 57 to 71 and 74 onwards (sandboxes,
 *   governance, market surveillance, penalties, final provisions).
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';
require_once __DIR__.'/eu-act-shared.php';

return function (): array {
    $chapters = array(
        'I' => 'General provisions',
        'II' => 'Prohibited AI practices',
        'III' => 'High-risk AI systems',
        'IV' => 'Transparency obligations for providers and deployers of certain AI systems',
        'V' => 'General-purpose AI models',
        'IX' => 'Post-market monitoring, information sharing and market surveillance'
    );

    // article => chapter, role, system type
    $scope = array(
        4 => array('I', 'provider, deployer', 'all'),
        5 => array('II', 'any', 'prohibited'),
        8 => array('III', 'provider', 'high_risk'),
        9 => array('III', 'provider', 'high_risk'),
        10 => array('III', 'provider', 'high_risk'),
        11 => array('III', 'provider', 'high_risk'),
        12 => array('III', 'provider', 'high_risk'),
        13 => array('III', 'provider', 'high_risk'),
        14 => array('III', 'provider', 'high_risk'),
        15 => array('III', 'provider', 'high_risk'),
        16 => array('III', 'provider', 'high_risk'),
        17 => array('III', 'provider', 'high_risk'),
        18 => array('III', 'provider', 'high_risk'),
        19 => array('III', 'provider', 'high_risk'),
        20 => array('III', 'provider', 'high_risk'),
        21 => array('III', 'provider', 'high_risk'),
        22 => array('III', 'provider', 'high_risk'),
        23 => array('III', 'importer', 'high_risk'),
        24 => array('III', 'distributor', 'high_risk'),
        25 => array('III', 'any', 'high_risk'),
        26 => array('III', 'deployer', 'high_risk'),
        27 => array('III', 'deployer', 'high_risk'),
        43 => array('III', 'provider', 'high_risk'),
        47 => array('III', 'provider', 'high_risk'),
        48 => array('III', 'provider', 'high_risk'),
        49 => array('III', 'provider, deployer', 'high_risk'),
        50 => array('IV', 'provider, deployer', 'certain'),
        53 => array('V', 'provider', 'general_purpose'),
        54 => array('V', 'provider', 'general_purpose'),
        55 => array('V', 'provider', 'general_purpose_systemic'),
        72 => array('IX', 'provider', 'high_risk'),
        73 => array('IX', 'provider', 'high_risk')
    );

    // Paragraphs that bind Member States, authorities, the Commission, the AI Office or an
    // authorised representative, set conditions for law enforcement use, or only grant an option.
    $not_operator_duties = array(
        '5(2)',
        '5(3)',
        '5(4)',
        '5(5)',
        '5(6)',
        '5(7)',
        '5(8)',
        '9(7)',
        '11(3)',
        '15(2)',
        '18(2)',
        '21(3)',
        '22(3)',
        '22(4)',
        '25(5)',
        '26(3)',
        '27(5)',
        '43(5)',
        '43(6)',
        '47(5)',
        '50(6)',
        '50(7)',
        '53(2)',
        '53(4)',
        '53(5)',
        '53(6)',
        '53(7)',
        '54(3)',
        '54(4)',
        '54(5)',
        '54(6)',
        '55(2)',
        '55(3)',
        '73(5)',
        '73(7)',
        '73(8)',
        '73(11)'
    );
    $act = new EuAct('eu-ai-act/reg-2024-1689.html');
    $families = array();
    $skipped = array();

    foreach ($scope as $article => $info) {
        $controls = array();

        foreach ($act->paragraphs($article) as $number => $parts) {
            $ref = $number === 0 ? (string) $article : $article.'('.$number.')';

            if (in_array($ref, $not_operator_duties, true)) {
                $skipped[] = $ref;
                continue;
            }

            $controls[] = EuAct::control('Art. '.$ref, $parts);
        }

        $families[] = array(
            'number' => (string) $article,
            'code' => 'Art. '.$article,
            'name' => $act->title($article),
            'chapter' => 'Chapter '.$info[0],
            'chapter_name' => $chapters[$info[0]],
            'role' => $info[1],
            'system_type' => $info[2],
            'controls' => $controls
        );
    }

    if ($skipped !== $not_operator_duties) {
        throw new RuntimeException('Paragraphs listed as not operator duties were not all found: '.implode(', ', array_diff($not_operator_duties, $skipped)));
    }

    return $families;
};
