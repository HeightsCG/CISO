<?php
/**
 * NIST SP 800-63-4 checks: every SHALL statement in the numbered sections of the four
 * volumes (outside the Notations sections) is captured once, word for word.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-800-63.json'), true);
};

return array(
    'every SHALL in the body of each volume is an objective' => function () use ($definition) {
        $captured = array();

        foreach ($definition()['families'] as $family) {
            $volume = explode('-', $family['code'])[0];

            foreach ($family['controls'] as $control) {
                foreach ($control['objectives'] as $objective) {
                    $captured[$volume] = ($captured[$volume] ?? 0) + substr_count($objective['text'], 'SHALL');
                    Assert::true(strpos($objective['text'], 'NIST SP 800-63') === false, $objective['id'].' has no page furniture');
                }
            }
        }

        foreach (array('63' => '800-63-4', '63A' => '800-63A-4', '63B' => '800-63B-4', '63C' => '800-63C-4') as $volume => $docno) {
            $text = Source::pdf_text('nist80063/NIST.SP.'.$docno.'.pdf');
            $body = substr($text, strpos($text, "\n1. Introduction\n"));
            $body = substr($body, 0, strpos($body, "\nReferences\n"));
            $notation = substr_count($body, '“ SHALL ”') + substr_count($body, '“ SHALL NOT ”');

            Assert::same(substr_count($body, 'SHALL') - $notation, $captured[$volume], $docno.' SHALL statements');
        }
    },

    'password verifier requirements include the 15-character minimum' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                if ($control['id'] === '63B-3.1.1.2') {
                    Assert::true(strpos($control['objectives'][0]['text'], 'minimum of 15 characters') !== false, 'first verifier requirement');

                    return;
                }
            }
        }

        Assert::true(false, '63B-3.1.1.2 missing');
    }
);
