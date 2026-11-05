<?php
/**
 * Generated reports never abbreviate a list of references ("and 3 more",
 * "and others"): every reference is shown, or the text names the table that
 * holds the complete list.
 */
$files = array(
    'ReportAnalysis',
    'ReportBuilder',
    'ReportExecutiveSummary',
    'ReportRemediationPlan',
    'ReportAssessmentResults',
    'ReportStatementOfApplicability',
    'ReportEvidenceRegister',
    'ReportRiskAssessment',
    'ReportGapAnalysis'
);

$patterns = array(
    'and N more' => '/\'\s*more\'/',
    'and others' => '/and others\'/',
    'N others' => '/\'\s*others\'/'
);

return array(
    'report builders write complete reference lists' => function () use ($files, $patterns) {
        $hits = array();

        foreach ($files as $class) {
            $lines = file(ROOT.'/libs/Classes/'.$class.'.php');
            foreach ($lines as $number => $line) {
                if (preg_match('/^\s*(\*|\/\*|\/\/)/', $line)) {
                    continue;
                }
                foreach ($patterns as $label => $pattern) {
                    if (preg_match($pattern, $line)) {
                        $hits[] = $class.'.php:'.($number + 1).' ('.$label.')';
                    }
                }
            }
        }

        Assert::same(array(), $hits, 'abbreviated lists in report output');
    }
);
