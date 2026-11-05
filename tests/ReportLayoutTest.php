<?php
/**
 * Report text helpers shared by the gap analysis and the risk assessment:
 * complete grouped reference lists, and long paragraphs split at sentence
 * boundaries without breaking abbreviations or references.
 */
require_once ROOT.'/libs/Classes/ReportBuilder.php';
require_once ROOT.'/libs/Classes/ReportGapAnalysis.php';
require_once ROOT.'/libs/Classes/ReportRiskAssessment.php';

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
    }
);
