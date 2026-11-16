<?php
/**
 * NIST AI RMF 1.0. The parser reads Tables 1 to 4 of NIST AI 100-1 by column position in
 * the layout-preserving extract; these checks hold the result against a second extract
 * of the same PDF made in reading order (pdftotext -raw), and against the Playbook data.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-ai-rmf.json'), true);
};

// Reading-order extract; justified lines lose their spaces, so compare letters and digits only.
$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

$raw = function () use ($flat) {
    $text = file_get_contents(Source::path('ai-rmf/NIST.AI.100-1.raw.txt'));
    $text = substr($text, strpos($text, 'Table 1: Categories and subcategories for the GOVERN function.'));

    return $flat($text);
};

return array(
    '4 functions, 19 categories and 72 subcategories' => function () use ($definition) {
        $d = $definition();
        $categories = array();
        $subcategories = array();

        foreach ($d['families'] as $family) {
            $categories[$family['function']] = ($categories[$family['function']] ?? 0) + 1;
            $subcategories[$family['function']] = ($subcategories[$family['function']] ?? 0) + count($family['controls']);
        }

        Assert::same(array(
            'GOVERN' => 6,
            'MAP' => 5,
            'MEASURE' => 4,
            'MANAGE' => 4
        ), $categories, 'categories per function');
        Assert::same(array(
            'GOVERN' => 19,
            'MAP' => 18,
            'MEASURE' => 22,
            'MANAGE' => 13
        ), $subcategories, 'subcategories per function');
        Assert::same(72, $d['counts']['controls'], 'subcategories');
    },

    'subcategory statements match the reading-order extract of AI 100-1' => function () use ($definition, $raw, $flat) {
        $text = $raw();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::true(strpos($text, $flat($control['id'].': '.$control['text'])) !== false, $control['id'].' statement');
                Assert::true(substr($control['text'], -1) === '.', $control['id'].' ends as a sentence');
            }
        }
    },

    'category statements are whole sentences found in AI 100-1' => function () use ($definition, $raw, $flat) {
        $text = $raw();

        foreach ($definition()['families'] as $family) {
            Assert::true(strlen($family['name']) <= 200 && substr($family['name'], -1) === '.', $family['code'].' statement is a sentence that fits the family column');

            // A statement broken by a page turn is not contiguous in reading order; its opening and closing words are.
            $words = explode(' ', $family['name']);
            Assert::true(strpos($text, $flat($family['code'].': '.implode(' ', array_slice($words, 0, 3)))) !== false, $family['code'].' opening');
            Assert::true(strpos($text, $flat(implode(' ', array_slice($words, -3)))) !== false, $family['code'].' closing');
        }
    },

    'every subcategory is in the Playbook under the same category and carries its suggested actions' => function () use ($definition) {
        $playbook = array();

        foreach (json_decode(file_get_contents(Source::path('ai-rmf/playbook.json')), true) as $entry) {
            $playbook[$entry['title']] = str_replace('-', ' ', $entry['category']);
        }

        Assert::same(72, count($playbook), 'Playbook entries');

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                Assert::same($family['code'], $playbook[$control['id']] ?? null, $control['id'].' category');
                Assert::true(trim($control['guidance']) !== '', $control['id'].' has suggested actions');
            }
        }
    },

    'the publication, not the Playbook, supplies the statement where they differ' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                if ($control['id'] === 'GOVERN 1.2') {
                    Assert::same('The characteristics of trustworthy AI are integrated into organizational policies, processes, procedures, and practices.', $control['text'], 'GOVERN 1.2');
                }
            }
        }
    }
);
