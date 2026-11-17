<?php
/**
 * Essential Eight checks: eight strategies at three maturity levels, and every requirement
 * sentence in Appendices A to C is captured once (counted independently from the raw extract).
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/essential-eight.json'), true);
};

return array(
    'eight strategies, each with Maturity Levels One to Three' => function () use ($definition) {
        $families = $definition()['families'];

        Assert::same(8, count($families), 'strategies');

        foreach ($families as $family) {
            Assert::same(array('E8-'.$family['code'].'-ML1', 'E8-'.$family['code'].'-ML2', 'E8-'.$family['code'].'-ML3'), array_column($family['controls'], 'id'), $family['name'].' levels');
        }
    },

    'requirement counts match the sentences of each appendix' => function () use ($definition) {
        $raw = Source::pdf_text('essential-eight/essential-eight-maturity-model-november-2023.pdf');
        $appendices = array(
            'ML1' => array('Appendix A: Maturity Level One', 'Appendix B: Maturity Level Two'),
            'ML2' => array('Appendix B: Maturity Level Two', 'Appendix C: Maturity Level Three'),
            'ML3' => array('Appendix C: Maturity Level Three', 'Appendix D: Comparison')
        );

        foreach ($appendices as $level => $bounds) {
            $from = strrpos($raw, $bounds[0]);
            $text = substr($raw, $from, strrpos($raw, $bounds[1]) - $from);
            $expected = preg_match_all('/\.(?:\n|\f?Essential Eight maturity model|$)/', $text);
            $built = 0;

            foreach ($definition()['families'] as $family) {
                foreach ($family['controls'] as $control) {
                    if (substr($control['id'], -3) === $level) {
                        $built += count($control['objectives']);
                    }
                }
            }

            Assert::same($expected, $built, $level.' requirements');
        }
    }
);
