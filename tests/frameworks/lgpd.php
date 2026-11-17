<?php
/**
 * LGPD checks: the in-scope articles are present in Portuguese, and no amendment note,
 * vetoed or revoked provision, or ANPD power is left in the obligations.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/lgpd.json'), true);
};

return array(
    'the articles binding processing agents are present' => function () use ($definition) {
        $articles = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $articles[preg_replace('/,.*$/', '', $control['id'])] = true;
            }
        }

        foreach (array(6, 7, 8, 9, 10, 11, 14, 15, 16, 18, 19, 20, 33, 37, 39, 41, 46, 47, 48, 49, 50) as $n) {
            Assert::true(isset($articles['Art. '.$n]), 'Art. '.$n);
        }

        foreach (array(1, 5, 23, 52, 55) as $n) {
            Assert::true(!isset($articles['Art. '.$n]), 'Art. '.$n.' out of scope');
        }
    },

    'text is clean of notes, vetoes and ANPD powers' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $texts = array_merge(array($control['text']), array_column($control['objectives'], 'text'));

                foreach ($texts as $text) {
                    Assert::true(preg_match('/\((?:[^()]*)(?:acrescid|redação dada|Revogad|VETAD|convertida)/ui', $text) === 0, $control['id'].' has no amendment note');
                }

                Assert::true(preg_match('/^(?:A autoridade nacional|O juiz)\b/u', $control['text']) === 0, $control['id'].' is a duty');
            }
        }
    },

    'rights of the data subject list the nine Art. 18 rights' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                if ($control['id'] === 'Art. 18') {
                    Assert::same(9, count($control['objectives']), 'Art. 18 incisos');

                    return;
                }
            }
        }

        Assert::true(false, 'Art. 18 missing');
    }
);
