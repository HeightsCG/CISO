<?php
/**
 * The summary pieces of a generated risk assessment report (posture, key
 * messages and a roadmap by rating), worked out from the report's numbers.
 * Consultant-written narrative always takes precedence in the report.
 */
class ReportNarrative {

    const POSTURES = array(
        'Low',
        'Moderate',
        'Elevated',
        'High',
        'Critical'
    );

    const PHASES = array(
        'Immediate (0 to 30 days)',
        'Short term (30 to 90 days)',
        'Medium term (90 to 180 days)'
    );

    /** The analysis for an assembled risk report, built from its numbers. */
    public static function for_risk_report(array $r): array
    {
        $high = (int) $r['totals']['High'];
        $medium = (int) $r['totals']['Medium'];
        $low = (int) $r['totals']['Low'];

        if ($high >= 3) {
            $posture = 'High';
        } elseif ($high >= 1) {
            $posture = 'Elevated';
        } elseif ($medium >= 1) {
            $posture = 'Moderate';
        } else {
            $posture = 'Low';
        }

        $messages = array();

        foreach (array_slice($r['findings_ranked'], 0, 4) as $finding) {
            $messages[] = 'Address '.$finding['finding_ref'].', rated '.strtolower($finding['rating']).': '.ReportBuilder::plain($finding['finding_title']).'.';
        }

        if (count($messages) === 0) {
            $messages[] = 'No significant gaps were recorded among the controls assessed.';
        }

        $roadmap = array();
        $phase_for = array(
            'High' => self::PHASES[0],
            'Medium' => self::PHASES[1],
            'Low' => self::PHASES[2]
        );

        foreach ($r['findings_ranked'] as $finding) {
            $roadmap[] = array(
                'phase' => $phase_for[$finding['rating']],
                'title' => ReportBuilder::plain($finding['finding_title']),
                'detail' => ReportBuilder::plain($finding['recommendation']),
                'refs' => array(
                    $finding['finding_ref']
                )
            );
        }

        return array(
            'posture_rating' => $posture,
            'posture_headline' => ($high + $medium + $low).' findings were identified: '.$high.' high, '.$medium.' medium and '.$low.' low.',
            'key_messages' => $messages,
            'executive_summary' => '',
            'domain_commentary' => array(),
            'finding_text' => array(),
            'roadmap' => array_slice($roadmap, 0, 15),
            'conclusion' => ''
        );
    }

}
