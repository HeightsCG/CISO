<?php
/**
 * Checks an assessment before it is completed or issued as Final. Blocking
 * issues stop completion and final deliverables; warnings are shown but do
 * not stop anything.
 *
 * Each issue: severity ('block' or 'warn'), code, message, and the control
 * identifiers it concerns (if any).
 */
class AssessmentChecks {

    const FINDING_FIELDS = array(
        'gap_description' => 'gap',
        'business_impact' => 'business impact',
        'recommendation' => 'recommendation'
    );

    const TEXT_FIELDS = array(
        'notes' => 'observation',
        'implementation_description' => 'implementation description',
        'gap_description' => 'gap',
        'business_impact' => 'business impact',
        'recommendation' => 'recommendation',
        'applicability_justification' => 'exclusion justification'
    );

    /**
     * @param array      $definition framework definition, or null for standards not built from one
     * @param array      $assessment assessments row (with project_name, client_name)
     * @param array      $project    projects row (project_name, description)
     * @param array      $controls   control rows of the assessment
     * @param array|null $score      AssessmentScore::score() result, when there is a definition
     */
    public static function run(?array $definition, array $assessment, array $project, array $controls, ?array $score): array
    {
        $issues = array();
        $out_of_scope = $score['out_of_scope_families'] ?? array();
        $in_scope = array_values(array_filter($controls, function ($control) use ($out_of_scope) {
            return !in_array($control['family'], $out_of_scope, true);
        }));

        $unassessed = array_values(array_filter($in_scope, function ($control) {
            return $control['item_result'] === 'Not Assessed' && ($control['applicability'] ?? 'Applicable') !== 'Excluded';
        }));

        if (count($unassessed) > 0) {
            $issues[] = self::issue('block', 'unassessed', count($unassessed).' '.(count($unassessed) === 1 ? 'control has' : 'controls have').' not been assessed.', array_column($unassessed, 'control_identifier'));
        }

        foreach ($in_scope as $control) {
            if (!in_array($control['item_result'], array('Partially Implemented', 'Not Implemented'), true)) {
                continue;
            }

            $missing = array();

            foreach (self::FINDING_FIELDS as $field => $label) {
                if (trim((string) ($control[$field] ?? '')) === '') {
                    $missing[] = $label;
                }
            }

            if (count($missing) > 0) {
                $issues[] = self::issue('block', 'finding_incomplete', $control['control_identifier'].' is '.strtolower($control['item_result']).' but has no '.self::join($missing).'.', array($control['control_identifier']));
            }

            if (($control['risk_rating'] ?? '') === '') {
                $issues[] = self::issue('warn', 'risk_not_rated', $control['control_identifier'].' has a finding with no risk rating.', array($control['control_identifier']));
            }
        }

        foreach ($controls as $control) {
            if (($control['applicability'] ?? 'Applicable') === 'Excluded' && trim((string) ($control['applicability_justification'] ?? '')) === '') {
                $issues[] = self::issue('block', 'exclusion_unjustified', $control['control_identifier'].' is excluded without a justification.', array($control['control_identifier']));
            }
        }

        if ($assessment['assessment_type'] === 'Final' && (int) $assessment['baseline_assessment_id'] === 0 && (int) $assessment['no_baseline'] === 0) {
            $issues[] = self::issue('block', 'final_needs_baseline', 'A Final assessment needs a Baseline to compare against, or the "no baseline" box ticked on the Settings tab.', array());
        }

        if ($definition !== null) {
            $issues = array_merge($issues, self::framework_checks($definition, $assessment, $project, $score));
        }

        return array_merge($issues, self::writing_checks($definition, $assessment, $project, $controls));
    }

    public static function blocking(array $issues): array
    {
        return array_values(array_filter($issues, function ($issue) {
            return $issue['severity'] === 'block';
        }));
    }

    private static function framework_checks(array $definition, array $assessment, array $project, ?array $score): array
    {
        $issues = array();

        foreach ($score['gates'] ?? array() as $gate) {
            if ($gate['assessed'] && !$gate['met']) {
                $issues[] = self::issue('warn', 'gate_not_met', $gate['control'].' is not met. '.$gate['summary'], array($gate['control']));
            }
        }

        if (($score['model'] ?? '') === 'maturity' && ((int) ($assessment['tier_governance'] ?? 0) === 0 || (int) ($assessment['tier_management'] ?? 0) === 0)) {
            $issues[] = self::issue('warn', 'tiers_not_set', 'The current Tiers for governance and management are not set on the Settings tab.', array());
        }

        return $issues;
    }

    private static function writing_checks(?array $definition, array $assessment, array $project, array $controls): array
    {
        $issues = array();
        $extra = array(
            (string) ($assessment['client_name'] ?? ''),
            (string) $project['project_name'],
            (string) $assessment['assessment_name']
        );

        foreach ($definition['glossary'] ?? array() as $entry) {
            $extra[] = $entry['term'];
        }

        foreach ($definition['aliases'] ?? array() as $alias) {
            $extra[] = $alias;
        }

        $texts = array();

        foreach ($controls as $control) {
            foreach (self::TEXT_FIELDS as $field => $label) {
                $texts[] = array(
                    'label' => $control['control_identifier'].' '.$label,
                    'id' => $control['control_identifier'],
                    'text' => (string) ($control[$field] ?? '')
                );
            }
        }

        foreach ($texts as $entry) {
            if (trim($entry['text']) === '') {
                continue;
            }

            $ids = $entry['id'] === null ? array() : array($entry['id']);
            $repeated = SpellCheck::repeated_words($entry['text']);

            if (count($repeated) > 0) {
                $issues[] = self::issue('warn', 'repeated_word', $entry['label'].': repeated word "'.implode('", "', $repeated).'".', $ids);
            }

            $unknown = SpellCheck::unknown_words($entry['text'], $extra);

            if (count($unknown) > 0) {
                $issues[] = self::issue('warn', 'spelling', $entry['label'].': check the spelling of "'.implode('", "', array_slice($unknown, 0, 8)).'"'.(count($unknown) > 8 ? ' and '.(count($unknown) - 8).' more' : '').'.', $ids);
            }
        }

        return $issues;
    }

    private static function issue(string $severity, string $code, string $message, array $ids): array
    {
        return array(
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'controls' => $ids
        );
    }

    private static function join(array $words): string
    {
        if (count($words) <= 1) {
            return implode('', $words);
        }

        $last = array_pop($words);

        return implode(', ', $words).' and '.$last;
    }

}
