<?php
/**
 * PIPEDA checks. The parser walks the Justice Canada XML; these read Schedule 1 and the
 * kept sections as plain text and classify the clauses a second way.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/pipeda.json'), true);
};

$controls = function () use ($definition) {
    $all = array();

    foreach ($definition()['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $control['family'] = $family['number'];
            $all[$control['id']] = $control;
        }
    }

    return $all;
};

// Schedule 1 as plain text, each clause number between § signs.
$schedule = function () {
    $xml = file_get_contents(Source::path('pipeda/P-8.6.xml'));
    $xml = substr($xml, strpos($xml, '<Label>SCHEDULE 1</Label>'));
    $xml = substr($xml, 0, strpos($xml, '</Schedule>'));
    $xml = preg_replace('/<Emphasis style="bold">(4\.\d+\.\d+)<\/Emphasis>/', "\n§$1§ ", $xml);
    // Headings and notes end a clause; normalise() folds newlines, so mark them with a sign it keeps.
    $xml = preg_replace('/<TitleText>|<Note\b/', '¶$0', $xml);

    return Source::normalise(html_entity_decode(strip_tags(str_replace('<', ' <', $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8'));
};

return array(
    'ten principles and three Part 1 groups' => function () use ($definition) {
        $names = array_column($definition()['families'], 'name');

        Assert::same(13, count($names), 'groups');
        Assert::same('Principle 1: Accountability', $names[0], 'first principle');
        Assert::same('Principle 10: Challenging Compliance', $names[9], 'tenth principle');
        Assert::same(array('Consent and purposes', 'Access requests', 'Breaches of security safeguards'), array_slice($names, 10), 'Part 1 groups');
    },

    'every numbered clause is kept or says neither shall, must nor should' => function () use ($controls, $schedule) {
        preg_match_all('/§(4\.\d+\.\d+)§ (.*?)(?=§4\.\d+\.\d+§|¶|$)/s', $schedule(), $m, PREG_SET_ORDER);
        $all = $controls();
        $kept = 0;

        Assert::true(count($m) >= 40, 'clauses found in the plain text');

        foreach ($m as $clause) {
            $says = preg_match('/\b(shall|must|should)\b/i', $clause[2]) === 1;
            Assert::same($says, isset($all[$clause[1]]), $clause[1].' kept when it says shall, must or should');
            $kept += $says ? 1 : 0;
        }

        Assert::same(37, $kept, 'clauses kept');
    },

    'force follows the wording, as section 5(2) directs' => function () use ($controls) {
        foreach ($controls() as $control) {
            if ($control['type'] !== 'clause') {
                Assert::same('obligation', $control['force'], $control['id'].' is an obligation');
                continue;
            }

            $binding = preg_match('/\b(shall|must)\b/i', $control['text']) === 1;
            Assert::same($binding ? 'obligation' : 'recommendation', $control['force'], $control['id'].' force');
        }

        Assert::same('recommendation', $controls()['4.7.3']['force'], 'methods of protection are recommended');
        Assert::same(3, count($controls()['4.7.3']['objectives']), 'physical, organizational and technological measures');
    },

    'the explanatory notes to 4.3 and 4.9 are not requirements' => function () use ($controls) {
        foreach ($controls() as $control) {
            Assert::true($control['text'] !== '' && strpos($control['text'], 'Note:') !== 0, $control['id'].' is not a note');
        }
    },

    'Part 1 subsections are the ones that bind the organization' => function () use ($controls) {
        $ids = array_keys(array_filter($controls(), function ($c) {
            return $c['type'] === 'subsection';
        }));

        Assert::same(array(
            's. 5(3)',
            's. 6.1',
            's. 8(2)',
            's. 8(3)',
            's. 8(7)',
            's. 8(8)',
            's. 9(1)',
            's. 9(5)',
            's. 10',
            's. 10.1(1)',
            's. 10.1(2)',
            's. 10.1(3)',
            's. 10.1(4)',
            's. 10.1(5)',
            's. 10.1(6)',
            's. 10.2(1)',
            's. 10.2(2)',
            's. 10.3(1)',
            's. 10.3(2)'
        ), $ids, 'Part 1 subsections');

        foreach ($controls() as $control) {
            if ($control['type'] === 'subsection' && $control['id'] !== 's. 5(3)' && $control['id'] !== 's. 6.1') {
                Assert::true(preg_match('/\bshall\b/', $control['text']) === 1, $control['id'].' says shall');
            }
        }
    }
);
