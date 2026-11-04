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

    const TEXT_FIELDS = array(
        'applicability_justification' => 'exclusion justification'
    );

    /**
     * @param array      $definition framework definition, or null for standards not built from one
     * @param array      $assessment assessments row (with client_name)
     * @param array      $controls   control rows of the assessment
     * @param array|null $score      AssessmentScore::score() result, when there is a definition
     */
    public static function run(?array $definition, array $assessment, array $controls, ?array $score): array
    {
        $issues = array();
        $unassessed = array_values(array_filter($controls, function ($control) {
            return ($control['item_result'] === 'Not Assessed' || (int) ($control['open_objectives'] ?? 0) > 0) && ($control['applicability'] ?? 'Applicable') !== 'Excluded';
        }));

        if (count($unassessed) > 0) {
            $issues[] = self::issue('block', 'unassessed', count($unassessed).' '.(count($unassessed) === 1 ? 'control has' : 'controls have').' not been assessed.', array_column($unassessed, 'control_identifier'));
        }

        foreach ($controls as $control) {
            if (($control['applicability'] ?? 'Applicable') === 'Excluded' && trim((string) ($control['applicability_justification'] ?? '')) === '') {
                $issues[] = self::issue('block', 'exclusion_unjustified', $control['control_identifier'].' is excluded without a justification.', array($control['control_identifier']));
            }
        }

        if ($definition !== null) {
            $issues = array_merge($issues, self::framework_checks($definition, $assessment, $score));
        }

        return array_merge($issues, self::writing_checks($definition, $assessment, $controls));
    }

    public static function blocking(array $issues): array
    {
        return array_values(array_filter($issues, function ($issue) {
            return $issue['severity'] === 'block';
        }));
    }

    private static function framework_checks(array $definition, array $assessment, ?array $score): array
    {
        $issues = array();

        foreach ($score['gates'] ?? array() as $gate) {
            if ($gate['assessed'] && !$gate['met']) {
                $issues[] = self::issue('warn', 'gate_not_met', $gate['control'].' is not met. '.$gate['summary'], array($gate['control']));
            }
        }

        return $issues;
    }

    private static function writing_checks(?array $definition, array $assessment, array $controls): array
    {
        $issues = array();
        $extra = array(
            (string) ($assessment['client_name'] ?? ''),
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

}
