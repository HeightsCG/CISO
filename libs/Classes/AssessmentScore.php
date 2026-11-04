<?php
/**
 * Scores one assessment against its framework definition. Everything here is
 * read from the definition (scoring model, weights, partial credit, threshold,
 * deferral, gate and pass rules) and from the stored results; nothing about a
 * particular framework is written into the code.
 *
 * Results use the stored scale: Implemented, Partially Implemented, Not
 * Implemented, Not Applicable, Not Assessed. Controls that are excluded, or
 * belong to a category not in scope (SOC 2), are left out entirely.
 */
class AssessmentScore {

    const MET = 'Implemented';
    const PARTIAL = 'Partially Implemented';
    const NOT_MET = 'Not Implemented';
    const NA = 'Not Applicable';
    const NOT_ASSESSED = 'Not Assessed';

    /**
     * @param array $definition the built framework definition
     * @param array $assessment the assessments row row
     * @param array $controls   assessment_items rows of kind 'control'
     */
    public static function score(array $definition, array $assessment, array $controls): array
    {
        $rules = self::rules($definition);
        $partial_value = self::partial_value($rules);
        $counts = array(
            self::MET => 0,
            self::PARTIAL => 0,
            self::NOT_MET => 0,
            self::NA => 0,
            self::NOT_ASSESSED => 0
        );
        $families = array();
        $in_scope = array();
        $excluded = 0;

        foreach ($controls as $control) {
            if (($control['applicability'] ?? 'Applicable') === 'Excluded') {
                $excluded++;
                continue;
            }

            $result = $control['item_result'];
            $counts[$result] = ($counts[$result] ?? 0) + 1;
            $in_scope[] = $control;
            $family = $control['family'] === '' ? 'Ungrouped' : $control['family'];

            if (!isset($families[$family])) {
                $families[$family] = array(
                    'name' => $family,
                    'counts' => array(
                        self::MET => 0,
                        self::PARTIAL => 0,
                        self::NOT_MET => 0,
                        self::NA => 0,
                        self::NOT_ASSESSED => 0
                    )
                );
            }

            $families[$family]['counts'][$result]++;
        }

        $applicable = count($in_scope) - $counts[self::NA];

        foreach ($families as $name => $family) {
            $family_applicable = array_sum($family['counts']) - $family['counts'][self::NA];
            $families[$name]['applicable'] = $family_applicable;
            $families[$name]['percentage'] = self::percent($family['counts'][self::MET] + $partial_value * $family['counts'][self::PARTIAL], $family_applicable);
        }

        $score = array(
            'model' => $definition['scoring']['model'],
            'complete' => $counts[self::NOT_ASSESSED] === 0,
            'counts' => $counts,
            'in_scope' => count($in_scope),
            'applicable' => $applicable,
            'excluded' => $excluded,
            'percentage' => self::percent($counts[self::MET] + $partial_value * $counts[self::PARTIAL], $applicable),
            'families' => array_values($families),
            'points' => null,
            'gates' => self::gates($rules, $in_scope),
            'plan' => null,
            'outcome' => null
        );

        if ($score['model'] === 'points') {
            $score['points'] = self::points($definition, $in_scope);
        }

        $score['plan'] = self::plan($rules, $in_scope);
        $score['outcome'] = self::outcome($rules, $score);

        return $score;
    }

    private static function rules(array $definition): array
    {
        $rules = array();

        foreach ($definition['rules'] as $rule) {
            $rules[$rule['type']][] = $rule;
        }

        return $rules;
    }

    private static function percent(float $numerator, int $denominator): float
    {
        return $denominator === 0 ? 0.0 : round($numerator / $denominator * 100, 1);
    }

    /** What a partially met control counts for in a percentage score, from a partial_credit rule with a value. */
    private static function partial_value(array $rules): float
    {
        foreach ($rules['partial_credit'] ?? array() as $rule) {
            if (isset($rule['value'])) {
                return (float) $rule['value'];
            }
        }

        return 0.0;
    }

    /**
     * Points model (SPRS / CMMC): start at the maximum and subtract each
     * control that is not met. A control marked partially effective subtracts
     * its partial weight instead of its full weight. Controls not yet assessed
     * subtract nothing; the score is then provisional.
     */
    private static function points(array $definition, array $controls): array
    {
        $maximum = (int) $definition['scoring']['maximum'];
        $deductions = array();
        $total = 0;

        foreach ($controls as $control) {
            if (!in_array($control['item_result'], array(self::NOT_MET, self::PARTIAL), true)) {
                continue;
            }

            $weight = (int) ($control['control_weight'] ?? 0);
            $partial = $control['partial_weight'] === null ? null : (int) $control['partial_weight'];
            $points = ((int) ($control['partial_credit'] ?? 0) === 1 && $partial !== null) ? $partial : $weight;

            if ($points === 0) {
                continue;
            }

            $deductions[] = array(
                'id' => $control['control_identifier'],
                'points' => $points
            );
            $total += $points;
        }

        return array(
            'maximum' => $maximum,
            'floor' => (int) $definition['scoring']['floor'],
            'score' => $maximum - $total,
            'deducted' => $total,
            'deductions' => $deductions
        );
    }

    /** Gate rules tied to a control in this framework (e.g. the SSP): met when that control is met or not applicable. */
    private static function gates(array $rules, array $controls): array
    {
        $gates = array();

        foreach ($rules['gate'] ?? array() as $rule) {
            if (!isset($rule['control'])) {
                continue;
            }

            foreach ($controls as $control) {
                if ($control['control_identifier'] === $rule['control']) {
                    $gates[] = array(
                        'id' => $rule['id'],
                        'control' => $rule['control'],
                        'met' => in_array($control['item_result'], array(self::MET, self::NA), true),
                        'assessed' => $control['item_result'] !== self::NOT_ASSESSED,
                        'summary' => $rule['summary']
                    );
                }
            }
        }

        return $gates;
    }

    /**
     * Which unmet controls may go on a plan of action and which must be closed
     * first, from the framework's deferral rule. Without a deferral rule every
     * unmet control simply goes on the remediation plan.
     */
    private static function plan(array $rules, array $controls): array
    {
        $rule = $rules['deferral'][0] ?? null;
        $deferrable = array();
        $must_close = array();

        foreach ($controls as $control) {
            if (!in_array($control['item_result'], array(self::NOT_MET, self::PARTIAL), true)) {
                continue;
            }

            $id = $control['control_identifier'];

            if ($rule === null) {
                $deferrable[] = $id;
                continue;
            }

            if (($rule['permitted'] ?? true) === false || in_array($id, $rule['excluded'] ?? array(), true)) {
                $must_close[] = $id;
                continue;
            }

            if (!isset($rule['max_weight'])) {
                $deferrable[] = $id;
                continue;
            }

            $weight = (int) ($control['control_weight'] ?? 0);
            $allowed = $weight <= (int) $rule['max_weight'];

            foreach ($rule['exceptions'] ?? array() as $exception) {
                if ($exception['control'] === $id && ($exception['when'] ?? '') === 'partial' && (int) ($control['partial_credit'] ?? 0) === 1) {
                    $allowed = true;
                }
            }

            if ($allowed) {
                $deferrable[] = $id;
            } else {
                $must_close[] = $id;
            }
        }

        return array(
            'rule' => $rule === null ? null : $rule['id'],
            'deferrable' => $deferrable,
            'must_close' => $must_close
        );
    }

    /**
     * The status the results support: the pass outcome when every applicable
     * control is met; otherwise the threshold outcome (e.g. Conditional) when
     * the score ratio reaches it, nothing must be closed first and every gate
     * is met; otherwise not yet eligible. Null for frameworks with neither rule.
     */
    private static function outcome(array $rules, array $score): ?array
    {
        $pass = $rules['pass'][0] ?? null;
        $threshold = $rules['threshold'][0] ?? null;

        if ($pass === null && $threshold === null) {
            return null;
        }

        $unmet = $score['counts'][self::NOT_MET] + $score['counts'][self::PARTIAL];

        if ($score['counts'][self::NOT_ASSESSED] === $score['in_scope']) {
            return array(
                'status' => 'not_started',
                'label' => '',
                'reasons' => array()
            );
        }
        $gates_failed = array_values(array_filter($score['gates'], function ($gate) {
            return $gate['assessed'] && !$gate['met'];
        }));
        $reasons = array();

        if (!$score['complete']) {
            $reasons[] = $score['counts'][self::NOT_ASSESSED].' still to assess';
        }

        foreach ($gates_failed as $gate) {
            $reasons[] = $gate['control'].' is not met: '.$gate['summary'];
        }

        if ($pass !== null && $unmet === 0 && count($gates_failed) === 0) {
            return array(
                'status' => $score['complete'] ? 'pass' : 'provisional_pass',
                'label' => $pass['outcome'] ?? 'All requirements met',
                'reasons' => $reasons
            );
        }

        if ($threshold !== null && $score['points'] !== null) {
            $ratio = $score['points']['maximum'] === 0 ? 0 : $score['points']['score'] / $score['points']['maximum'];
            $reaches = $ratio >= (float) $threshold['value'];

            if (!$reaches) {
                $reasons[] = 'Score '.$score['points']['score'].' is below '.ceil($threshold['value'] * $score['points']['maximum']).' ('.($threshold['value'] * 100).'% of '.$score['points']['maximum'].')';
            }

            if (count($score['plan']['must_close']) > 0) {
                $reasons[] = count($score['plan']['must_close']).' unmet '.(count($score['plan']['must_close']) === 1 ? 'requirement cannot' : 'requirements cannot').' go on a plan of action: '.implode(', ', $score['plan']['must_close']);
            }

            if ($reaches && count($score['plan']['must_close']) === 0 && count($gates_failed) === 0) {
                return array(
                    'status' => $score['complete'] ? 'conditional' : 'provisional_conditional',
                    'label' => $threshold['outcome'],
                    'reasons' => $reasons
                );
            }
        } elseif ($unmet > 0) {
            $reasons[] = $unmet.' applicable '.($unmet === 1 ? 'requirement is' : 'requirements are').' not met';
        }

        return array(
            'status' => 'not_eligible',
            'label' => 'Not yet eligible',
            'reasons' => $reasons
        );
    }

}
