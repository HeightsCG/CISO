<?php
/**
 * NIST SP 800-30 Rev. 1 checks: the 15 tasks of the four process steps are present, each
 * with its TASK statement and at least one key activity from the publication.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-800-30.json'), true);
};

return array(
    'the 15 risk assessment tasks are present in step order' => function () use ($definition) {
        $ids = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $ids[] = $control['id'];
            }
        }

        Assert::same(array('1-1', '1-2', '1-3', '1-4', '1-5', '2-1', '2-2', '2-3', '2-4', '2-5', '2-6', '3-1', '3-2', '4-1', '4-2'), $ids, 'tasks');
    },

    'each task has its statement and key activities, with no page furniture' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(strlen($control['text']) > 40, $control['id'].' statement');
                Assert::true(count($control['objectives']) >= 1, $control['id'].' key activities');

                foreach ($control['objectives'] as $objective) {
                    Assert::true(strpos($objective['text'], 'Special Publication') === false, $objective['id'].' clean');
                    Assert::true(strlen($objective['text']) < 400, $objective['id'].' did not run past its box');
                }
            }
        }
    }
);
