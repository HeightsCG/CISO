<?php
/**
 * UK GDPR: Regulation (EU) 2016/679 as it forms part of United Kingdom law, from the
 * revised text legislation.gov.uk publishes. It carries the amendments made on EU exit
 * and by the Data (Use and Access) Act 2025 (Articles 8A, 22A to 22D, 44A and others).
 *
 * The committed snapshot differs from the download in one respect: the editorial
 * annotation identifiers ("key-" and 32 hexadecimal characters, about 3,700 of them) are
 * renamed "ref-", because secret scanners take the original form for Mailgun API keys
 * and block the push. After a fresh download:
 *     sed -E -i '' 's/key-([0-9a-f]{32})/ref-\1/g' frameworks/sources/uk-gdpr/eur-2016-679.xml
 *
 * - Groups: the articles that place duties on controllers and processors or define the
 *   rights they must honour, each carrying its chapter.
 * - Controls: the paragraphs of each article named in $scope. The UK text interleaves
 *   duties with regulation-making powers of the Secretary of State, functions of the
 *   Information Commission and interpretive provisions, so the paragraphs kept are
 *   listed one by one; every other paragraph of those articles is left out.
 * - Objectives: the lettered points of a paragraph.
 * - Left out as whole articles: 1 to 4A (scope and definitions), 8ZA, 11A, 22D, 23, 45A
 *   to 45C, 47A and 49A (powers to make regulations), 22A (definitions for 22B and 22C),
 *   40 to 43 (codes of conduct and certification, which are voluntary) and 50 onwards
 *   (the Information Commission, remedies, penalties, final provisions). Articles 22, 44,
 *   45 and 48 are repealed.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';
require_once __DIR__.'/eu-act-shared.php';
require_once __DIR__.'/uk-legislation-shared.php';

return function (): array {
    $chapters = array(
        'II' => 'Principles',
        'III' => 'Rights of the data subject',
        'IV' => 'Controller and processor',
        'V' => 'Transfers of personal data to third countries or international organisations'
    );

    // article => chapter, paragraphs kept ('0' is an article without numbered paragraphs)
    $scope = array(
        '5' => array('II', array('1', '2')),
        '6' => array('II', array('1')),
        '7' => array('II', array('1', '2', '3', '4')),
        '8' => array('II', array('1', '2')),
        '8A' => array('II', array('2', '3', '4')),
        '9' => array('II', array('1', '2', '3')),
        '10' => array('II', array('1')),
        '11' => array('II', array('1', '2')),
        '12' => array('III', array('1', '2', '3', '4', '5', '6', '7')),
        '13' => array('III', array('1', '2', '3', '4', '5', '7')),
        '14' => array('III', array('1', '2', '3', '4', '5', '7')),
        '15' => array('III', array('1', '1A', '2', '3', '4')),
        '16' => array('III', array('0')),
        '17' => array('III', array('1', '2', '3')),
        '18' => array('III', array('1', '2', '3')),
        '19' => array('III', array('0')),
        '20' => array('III', array('1', '2', '3', '4')),
        '21' => array('III', array('1', '2', '3', '4', '5', '6')),
        '22B' => array('III', array('1', '2', '3', '4')),
        '22C' => array('III', array('1', '2')),
        '24' => array('IV', array('1', '2')),
        '25' => array('IV', array('1', '1A', '1B', '2')),
        '26' => array('IV', array('1', '2', '3')),
        '27' => array('IV', array('1', '2', '4')),
        '28' => array('IV', array('1', '2', '3', '4', '9', '10')),
        '29' => array('IV', array('0')),
        '30' => array('IV', array('1', '2', '3', '4')),
        '31' => array('IV', array('0')),
        '32' => array('IV', array('1', '2', '4')),
        '33' => array('IV', array('1', '2', '3', '4', '5')),
        '34' => array('IV', array('1', '2', '3')),
        '35' => array('IV', array('1', '2', '3', '7', '9', '11')),
        '36' => array('IV', array('1', '3')),
        '37' => array('IV', array('1', '5', '6', '7')),
        '38' => array('IV', array('1', '2', '3', '5', '6')),
        '39' => array('IV', array('1', '2')),
        '44A' => array('V', array('1', '2', '3')),
        '46' => array('V', array('1A', '2', '3')),
        '47' => array('V', array('2')),
        '49' => array('V', array('1', '2', '6'))
    );
    $law = new UkLegislation('uk-gdpr/eur-2016-679.xml');
    $families = array();

    foreach ($scope as $article => $info) {
        // PHP turns a numeric array key into an integer; article numbers are text ("8A").
        $article = (string) $article;
        $paragraphs = $law->paragraphs('article-'.$article);
        $controls = array();

        foreach ($info[1] as $number) {
            if (!isset($paragraphs[$number])) {
                throw new RuntimeException('Article '.$article.'('.$number.') is not in the text');
            }

            $ref = $number === '0' ? $article : $article.'('.$number.')';
            $controls[] = EuAct::control('Art. '.$ref, $paragraphs[$number]);
        }

        $families[] = array(
            'number' => $article,
            'code' => 'Art. '.$article,
            'name' => $law->title('article-'.$article),
            'chapter' => 'Chapter '.$info[0],
            'chapter_name' => $chapters[$info[0]],
            'paragraphs_in_text' => count($paragraphs),
            'controls' => $controls
        );
    }

    return $families;
};
