<?php
/**
 * NIST SP 800-88 Rev. 2 checks: the sanitization program sections are present, every practice
 * is a should, must, shall or responsibility statement, and no footnote or page text leaks in.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-800-88.json'), true);
};

return array(
    'program sections and roles are present' => function () use ($definition) {
        $ids = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $ids[] = $control['id'];
            }
        }

        foreach (array('3.1.2', '3.2.3', '4.1', '4.4', '4.5.1', '4.5.2', '4.6', '4.7.2', '4.7.10') as $id) {
            Assert::true(in_array($id, $ids, true), $id.' present');
        }
    },

    'practices are normative sentences without footnote text' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                foreach ($control['objectives'] as $objective) {
                    Assert::true(preg_match('/\b(should|must|shall|responsible|responsibility)\b/i', $objective['text']) === 1, $objective['id'].' is normative');
                    Assert::true(preg_match('/NIST SP 800-88r2|September 2025|[A-Za-z)\]]\d{1,2} \d{1,2} /', $objective['text']) === 0, $objective['id'].' has no footnote or page text');
                }
            }
        }
    }
);
