<?php
/**
 * Report text helpers shared by the gap analysis and the risk assessment:
 * complete grouped reference lists, and long paragraphs split at sentence
 * boundaries without breaking abbreviations or references.
 */
require_once ROOT.'/libs/Classes/ReportBuilder.php';
require_once ROOT.'/libs/Classes/ReportGapAnalysis.php';
require_once ROOT.'/libs/Classes/ReportRiskAssessment.php';
require_once ROOT.'/libs/Classes/ReportRenderer.php';

$controls = array(
    array(
        'ref' => 'CC7.3',
        'objectives' => array(
            array(
                'ref' => 'CC7.3-PF06',
                'short' => 'PF06'
            ),
            array(
                'ref' => 'CC7.3-PF07',
                'short' => 'PF07'
            )
        )
    ),
    array(
        'ref' => 'GV.OC-01',
        'objectives' => array()
    ),
    array(
        'ref' => 'GV.OC-02',
        'objectives' => array()
    )
);

return array(
    'grouped refs keep every objective under its control' => function () use ($controls) {
        Assert::same('CC7.3 (PF06, PF07); P6.3', ReportGapAnalysis::grouped_refs(array(
            'CC7.3',
            'P6.3',
            'CC7.3-PF06',
            'CC7.3-PF07'
        ), $controls), 'grouped');
    },
    'grouped refs group objectives whose control is not listed' => function () use ($controls) {
        Assert::same('CC7.3 (PF07)', ReportGapAnalysis::grouped_refs(array(
            'CC7.3-PF07'
        ), $controls), 'objective only');
    },
    'grouped refs leave hyphenated control ids alone' => function () use ($controls) {
        Assert::same('GV.OC-01; GV.OC-02', ReportGapAnalysis::grouped_refs(array(
            'GV.OC-01',
            'GV.OC-02',
            'GV.OC-01'
        ), $controls), 'csf');
    },
    'short paragraphs are unchanged' => function () {
        $text = 'One sentence. Another one.';
        Assert::same($text, ReportRiskAssessment::paragraphs($text), 'short');
    },
    'long paragraphs split at sentence boundaries only' => function () {
        $sentence = 'The control under CC1.3 is recorded as partially implemented, e.g. Access reviews happen, and the remaining elements of the requirement are not yet in place across the organization.';
        $text = $sentence.' '.$sentence.' '.$sentence;
        $out = ReportRiskAssessment::paragraphs($text);
        $parts = explode("\n\n", $out);
        Assert::true(count($parts) >= 2, 'split into paragraphs');
        Assert::same($text, implode(' ', $parts), 'no text lost or reordered');
        foreach ($parts as $part) {
            Assert::true(preg_match('/^The control/', $part) === 1, 'each paragraph starts a sentence');
        }
    },
    'a short closing sentence stays with the paragraph before it' => function () {
        $long = str_repeat('This sentence describes the recorded condition in detail for the reader. ', 6);
        $out = ReportRiskAssessment::paragraphs(trim($long).' Short end.');
        Assert::true(substr($out, -10) === 'Short end.' && strpos($out, "\n\nShort end.") === false, 'tail merged');
    },

    'a long card keeps only its first detail rows with the header and lets the rest split between rows' => function () {
        $finding = new ReflectionMethod('ReportRenderer', 'pdf_finding');
        $finding->setAccessible(true);
        $e = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };
        $rows = array(
            array(
                'Assessment result',
                'Not Implemented'
            ),
            array(
                'Weakness',
                str_repeat('The requirement is recorded as not implemented. ', 10)
            ),
            array(
                'Corrective action',
                str_repeat('Bring every objective to the expected condition. ', 10)
            ),
            array(
                'Dates',
                'Detected: Not set'
            )
        );
        $card = function (array $rows, array $sections) {
            return array(
                'kind' => 'finding',
                'value' => array(
                    'ref' => 'POAM-1-001',
                    'heading' => 'AC-1 Policy',
                    'severity' => 'High',
                    'rating' => 'High',
                    'rows' => $rows,
                    'sections' => $sections
                )
            );
        };
        $html = $finding->invoke(null, $card($rows, array(
            array(
                'Milestones, evidence and comments',
                'Comments: None recorded'
            )
        )), $e, '<h2>Open items</h2>');
        $parts = explode('<div class="fcard rest', $html);
        Assert::same(2, count($parts), 'a kept top and a flowing rest');
        Assert::true(strpos($parts[0], 'page-break-inside:avoid') !== false && strpos($parts[0], '<h2>Open items</h2>') !== false, 'the heading stays with the top of the first card');
        Assert::true(strpos($parts[0], 'Weakness') !== false && strpos($parts[0], 'Corrective action') === false, 'only the first rows stay with the header');
        Assert::true(strpos($parts[1], '<table class="rows"><tr><td class="label">Corrective action') !== false && strpos($parts[1], 'page-break-inside:avoid') === false, 'the remaining rows flow and split between rows');
        Assert::true(strpos($parts[1], 'Comments: None recorded') !== false, 'the closing section flows with them');

        $short = $finding->invoke(null, $card(array(
            array(
                'Assessment result',
                'Not Implemented'
            )
        ), array()), $e, '');
        Assert::true(strpos($short, 'fcard rest') === false && strpos($short, 'page-break-inside:avoid') !== false, 'a short card is kept whole');
    }
);
