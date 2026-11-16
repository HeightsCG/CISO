<?php
/**
 * Colorado Privacy Act checks. The parser reads provisions by their layout; these hold
 * the result against a reading-order extract of the same printout.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/us-co-cpa.json'), true);
};

$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

// Reading-order text of Part 13, page footers removed, letters and digits only.
$part = function () use ($flat) {
    static $text = null;

    if ($text === null) {
        $raw = file_get_contents(Source::path('us-co-cpa/crs2024-title-06.raw.txt'));
        $raw = substr($raw, strpos($raw, 'COLORADO PRIVACY ACT'));
        $raw = substr($raw, 0, strpos($raw, 'PART 14'));
        $text = $flat(preg_replace('/Colorado Revised Statutes 2024 Page \d+ of \d+ Uncertified Printout|\[Editor\'s note:[^\]]*\]/', ' ', $raw));
    }

    return $text;
};

return array(
    'eight sections that bind controllers and processors' => function () use ($definition) {
        Assert::same(array(
            '6-1-1305',
            '6-1-1305.5',
            '6-1-1306',
            '6-1-1307',
            '6-1-1308',
            '6-1-1308.5',
            '6-1-1309',
            '6-1-1314'
        ), array_column($definition()['families'], 'number'), 'sections');
    },

    'every requirement states a duty and is printed in Part 13' => function () use ($definition, $part, $flat) {
        $text = $part();
        $total = 0;

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(preg_match('/\b(shall|must)\b/', $control['text']) === 1, $control['id'].' says shall or must');
                Assert::true(strpos($text, $flat(implode(' ', array_slice(explode(' ', $control['text']), 0, 10)))) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $objective) {
                    Assert::true(strpos($objective['id'], $control['id'].'(') === 0, $objective['id'].' belongs to '.$control['id']);
                    Assert::true(strpos($text, $flat(implode(' ', array_slice(explode(' ', $objective['text']), 0, 8)))) !== false, $objective['id'].' text');
                }

                $total++;
            }
        }

        Assert::same(41, $total, 'requirements');
    },

    'the seven named duties of controllers in 6-1-1308' => function () use ($definition) {
        $titles = array();

        foreach ($definition()['families'] as $family) {
            if ($family['number'] === '6-1-1308') {
                foreach ($family['controls'] as $control) {
                    $titles[$control['title']] = true;
                }
            }
        }

        Assert::same(array(
            'Duty of transparency',
            'Duty of purpose specification',
            'Duty of data minimization',
            'Duty to avoid secondary use',
            'Duty of care',
            'Duty to avoid unlawful discrimination',
            'Duty regarding sensitive data'
        ), array_keys($titles), 'duties');
    },

    'a heading over lettered paragraphs makes each paragraph a requirement' => function () use ($definition) {
        $ids = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $ids[] = $control['id'];
            }
        }

        Assert::true(in_array('6-1-1308(1)(a)', $ids, true) && !in_array('6-1-1308(1)', $ids, true), 'duty of transparency is split');
        Assert::true(in_array('6-1-1308(5)', $ids, true), 'duty of care is one requirement');
    }
);
