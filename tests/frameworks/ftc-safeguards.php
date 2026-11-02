<?php
/**
 * FTC Safeguards Rule checks. Element and paragraph IDs are re-derived from the
 * plain text of § 314.4 with a line regex (the parser walks the XML), and rule
 * parameters are read back out of the rule text.
 */
$file = 'ftc/16-cfr-314.xml';

$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/ftc-safeguards.json'), true);
};

$section = function () use ($file) {
    return Source::between(Source::xml_text($file), '§ 314.4 Elements.', '§ 314.5');
};

$rules = function () {
    return array_column(FrameworkBuilder::meta('ftc-safeguards')['rules'], null, 'id');
};

return array(
    'ten elements (a) to (j) with the published paragraph counts' => function () use ($definition, $section) {
        // Every line of 314.4 opening "(x)" or "(x)(n)" starts an element or a numbered paragraph.
        preg_match_all('/^\(([a-j])\)(?:\((\d+)\))?|^\((\d+)\)/m', $section(), $m, PREG_SET_ORDER);
        $expected = array();
        $letter = null;

        foreach ($m as $hit) {
            if (($hit[1] ?? '') !== '' && ($letter === null || $hit[1] === chr(ord($letter) + 1))) {
                $letter = $hit[1];
                $expected[$letter] = array();

                if (($hit[2] ?? '') !== '') {
                    $expected[$letter][] = '314.4('.$letter.')('.$hit[2].')';
                }
            } elseif (($hit[3] ?? '') !== '') {
                $expected[$letter][] = '314.4('.$letter.')('.$hit[3].')';
            }
        }

        $built = array();

        foreach ($definition()['families'][0]['controls'] as $control) {
            $built[substr($control['id'], 6, 1)] = array_column($control['objectives'], 'id');
        }

        Assert::same(array_keys($expected), array_keys($built), 'elements');
        Assert::same($expected, $built, 'numbered paragraphs per element');
        Assert::same(10, count($built), 'element count');
        Assert::same(33, $definition()['counts']['objectives'], 'paragraph count');
        Assert::same(8, count($built['c']), '314.4(c) has eight safeguards');
    },

    'small-institution exemption matches 314.6' => function () use ($definition, $rules, $file) {
        $text = Source::normalise(Source::xml_text($file));
        preg_match('/Section 314\.4\(b\)\(1\), \(d\)\(2\), \(h\), and \(i\) do not apply to financial institutions that maintain customer information concerning fewer than (\w+) thousand consumers/', $text, $m);
        Assert::same('five', $m[1] ?? null, '314.6 threshold wording');
        $rule = $rules()['small_institution_exemption'];
        Assert::same(5000, $rule['value'], 'threshold');
        Assert::same('<', $rule['operator'], 'operator');
        $expected = array(
            '314.4(b)(1)',
            '314.4(d)(2)',
            '314.4(h)',
            '314.4(i)'
        );
        Assert::same($expected, $rule['exempt'], 'exempt paragraphs');
        $flagged = array();

        foreach ($definition()['families'][0]['controls'] as $control) {
            if ($control['small_institution_exempt']) {
                $flagged[] = $control['id'];
            }
        }

        $whole = array(
            '314.4(h)',
            '314.4(i)'
        );
        Assert::same($whole, $flagged, 'elements exempt in full');
    },

    'notification and reporting parameters match the rule text' => function () use ($rules, $section, $file) {
        $text = Source::normalise($section());
        preg_match('/involves the information of at least (\d+) consumers, you must notify the Federal Trade Commission as soon as possible, and no later than (\d+) days after discovery/', $text, $m);
        $r = $rules();
        Assert::same((int) $m[1], $r['ftc_notification']['threshold_consumers'], 'consumer threshold');
        Assert::same((int) $m[2], $r['ftc_notification']['days'], 'notification days');
        Assert::true(strpos($text, '(i) Require your Qualified Individual to report in writing, regularly and at least annually') !== false, 'board report is 314.4(i), annual');
        Assert::true(strpos($text, 'at least every six months') !== false, 'vulnerability assessment interval');
        Assert::same(6, $r['vulnerability_assessment']['months'], 'vulnerability months');
        Assert::true(strpos($text, 'no later than two years after the last date') !== false, 'disposal period');
        Assert::same(2, $r['disposal']['years'], 'disposal years');
        $all = Source::normalise(Source::xml_text($file));
        Assert::true(strpos($all, 'Section 314.4(j) is effective as of May 13, 2024.') !== false, '314.5 date');
        Assert::same('2024-05-13', $r['notification_effective']['date'], 'effective date');
    }
);
