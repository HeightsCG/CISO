<?php
/**
 * Lays stored AI narratives over a ReportAnalysis array. Only sections whose
 * stored hash matches the current inputs, and that the assessor has not
 * rejected, are used; everything else keeps the calculated text. A Final
 * document ($mode 'final') uses only current sections the assessor approved.
 * Nothing here calls the AI service, so it is safe on every report download.
 *
 * Added keys (deterministic keys are never removed or changed):
 *   findings[*]: recorded_condition_ai (what the responses record),
 *     risk_scenarios_ai (list of conditional {scenario, consequence}),
 *     suggested_approach_ai (options), confirm_ai (facts to confirm),
 *     purpose_ai, outcome_ai, validation_ai, source_refs_ai, ai_status,
 *     initiative_ai; observation_ai and actions_ai remain as aliases of
 *     recorded_condition_ai and suggested_approach_ai; considerations_ai
 *     is null (no longer written)
 *   families[*]: narrative_ai
 *   initiatives_ai, patterns_ai, executive_ai (with exposure_overview),
 *   review, quality (ReportQuality issues on the sections in use),
 *   narrative_status
 */
class ReportNarratives
{
    const FIELD_LABELS = array(
        'recorded_condition' => 'Recorded condition',
        'risk_scenarios' => 'Potential exposure',
        'suggested_approach' => 'Suggested approach',
        'confirm' => 'To be confirmed',
        'exposure_overview' => 'Exposure overview',
        'refs' => 'Refs',
        'source_refs' => 'Source refs'
    );

    /**
     * The analysis with every usable narrative applied and readiness issues
     * for the rest. $mode 'draft' uses current sections that are not
     * rejected; 'final' uses current sections that are approved.
     */
    public static function apply(array $analysis, int $company_id, string $mode = 'draft'): array
    {
        $rows = (new ReportNarrativesModel())->load_narratives((int) $analysis['assessment']['id'], $company_id);
        $states = self::states($analysis, $rows);
        $in_use = array();

        $summary = array(
            'sections' => count($states),
            'approved' => 0,
            'draft' => 0,
            'rejected' => 0,
            'stale' => 0,
            'missing' => 0
        );

        foreach ($states as $key => $state) {
            if ($state['state'] === 'missing') {
                $summary['missing']++;
                continue;
            }
            if ($state['state'] === 'stale') {
                $summary['stale']++;
                continue;
            }
            $summary[strtolower($state['narrative_status'])]++;

            if (!self::usable_in($state, $mode)) {
                continue;
            }
            $in_use[$key] = $state['content'];

            switch (ReportAI::kind($key)) {
                case 'domain':
                    $analysis = self::apply_domain($analysis, substr($key, 7), $state['content'], $state['narrative_status']);
                    break;
                case 'remediation':
                    $analysis = self::apply_remediation($analysis, $state['content']);
                    break;
                case 'patterns':
                    $analysis['patterns_ai'] = self::patterns($state['content']);
                    break;
                case 'executive':
                    $analysis['executive_ai'] = $state['content'];
                    break;
                case 'review':
                    $analysis['review'] = $state['content']['issues'] ?? array();
                    break;
            }
        }

        $analysis['narrative_status'] = $summary;
        $analysis['quality'] = count($in_use) > 0 ? ReportQuality::check($analysis, $in_use) : array();

        if (count($rows) > 0) {
            $analysis['readiness'] = array_merge($analysis['readiness'], self::readiness($analysis, $summary));
        }

        return $analysis;
    }

    /**
     * Each section's state in generation order: its inputs and hash, the
     * stored row, missing | current | stale, and the content in use (the
     * assessor's edit when there is one). Later sections are built from the
     * usable content of earlier ones, exactly as ReportAI does when it writes them.
     */
    public static function states(array $analysis, array $rows): array
    {
        $by_key = array();
        foreach ($rows as $row) {
            $by_key[$row['section_key']] = $row;
        }

        $usable = array();
        $states = array();

        foreach (ReportAI::section_keys($analysis) as $key) {
            $input = ReportAI::input($analysis, $key, $usable);
            $hash = ReportAI::hash($input, $key);
            $row = $by_key[$key] ?? null;
            $state = $row === null ? 'missing' : ($row['input_hash'] === $hash ? 'current' : 'stale');
            $content = null;

            if ($row !== null) {
                $content = json_decode((string) ($row['edited_content'] ?? $row['content']), true);
                if (!is_array($content)) {
                    $content = null;
                }
            }

            $is_usable = $state === 'current' && $content !== null && $row['narrative_status'] !== 'Rejected';
            if ($is_usable) {
                $usable[$key] = $content;
            }

            $states[$key] = array(
                'section_key' => $key,
                'kind' => ReportAI::kind($key),
                'label' => ReportAI::label($key),
                'input' => $input,
                'hash' => $hash,
                'state' => $state,
                'usable' => $is_usable,
                'narrative_status' => $row['narrative_status'] ?? null,
                'edited' => $row !== null && $row['edited_content'] !== null,
                'revised' => $row !== null && ($row['revision_notes'] ?? null) !== null,
                'generated' => $row['date_updated'] ?? null,
                'model' => $row['model'] ?? null,
                'rejected' => $row !== null ? (json_decode((string) $row['rejected_fields'], true) ?: array()) : array(),
                'content' => $content,
                'stale_reason' => ''
            );
        }

        foreach ($states as $key => $state) {
            if ($state['state'] === 'stale') {
                $states[$key]['stale_reason'] = self::stale_reason($analysis, $states, $key);
            }
        }

        return $states;
    }

    /**
     * Why a stored section no longer matches its inputs, in the reader's
     * terms: criteria whose answers changed after it was written, sections it
     * was built from that were rewritten after it, or else a change to the
     * writing rules or model.
     */
    private static function stale_reason(array $analysis, array $states, string $key): string
    {
        $written = (string) $states[$key]['generated'];
        $terms = $analysis['terms'];
        $when = function (string $value) {
            return date('M j, H:i', strtotime($value)).' UTC';
        };

        $changed = array();
        $family = strpos($key, 'domain:') === 0 ? substr($key, 7) : null;
        foreach ($analysis['controls'] as $control) {
            if (($family === null || $control['family'] === $family) && $control['updated'] > $written) {
                $changed[] = $control['ref'];
            }
        }

        $parts = array();

        if (count($changed) > 0) {
            $parts[] = 'Answers changed after it was written ('.$when($written).') for '.count($changed).' '.(count($changed) === 1 ? $terms['control'] : $terms['controls']).': '.implode(', ', array_slice($changed, 0, 8)).(count($changed) > 8 ? ' and '.(count($changed) - 8).' more' : '').'.';
        }

        if ($family === null) {
            $rewritten = array();
            foreach ($states as $other_key => $other) {
                if ($other_key !== $key && $other['generated'] !== null && (string) $other['generated'] > $written) {
                    $rewritten[] = $other['label'];
                }
            }
            if (count($rewritten) > 0) {
                $parts[] = 'It was built from sections that were rewritten afterwards: '.implode(', ', $rewritten).'.';
            }
        }

        if (count($parts) === 0) {
            $parts[] = 'The writing rules or AI model changed after it was written ('.$when($written).').';
        }

        return implode(' ', $parts);
    }

    /** Whether a section's text goes into a document of this mode. */
    public static function usable_in(array $state, string $mode): bool
    {
        if (!$state['usable']) {
            return false;
        }

        return $mode !== 'final' || $state['narrative_status'] === 'Approved';
    }

    /**
     * The editable fields of a section kind for the review screen, read from
     * the output schema: path, label and type ('text', 'list' of strings, or
     * 'objects' with their own fields). Enumerated text carries its options.
     */
    public static function fields(string $kind): array
    {
        return self::schema_fields(ReportAI::schema($kind), '');
    }

    private static function schema_fields(array $schema, string $prefix): array
    {
        $fields = array();

        foreach ($schema['properties'] ?? array() as $name => $property) {
            $field = array(
                'path' => $prefix.$name,
                'label' => self::FIELD_LABELS[$name] ?? ucfirst(str_replace('_', ' ', $name)),
                'type' => 'text'
            );

            if (($property['type'] ?? '') === 'array') {
                if (($property['items']['type'] ?? '') === 'object') {
                    $field['type'] = 'objects';
                    $field['fields'] = self::schema_fields($property['items'], '');
                } else {
                    $field['type'] = 'list';
                }
            }

            if (isset($property['enum'])) {
                $field['options'] = $property['enum'];
            }

            $fields[] = $field;
        }

        return $fields;
    }

    private static function apply_domain(array $analysis, string $family, array $content, string $narrative_status): array
    {
        if (isset($analysis['families'][$family])) {
            $paragraphs = array_values(array_filter($content['narrative'] ?? array(), 'is_string'));
            if (count($paragraphs) > 0) {
                $analysis['families'][$family]['narrative_ai'] = implode("\n\n", $paragraphs);
            }
        }

        $entries = array();
        foreach ($content['controls'] ?? array() as $entry) {
            if (isset($entry['ref'])) {
                $entries[$entry['ref']] = $entry;
            }
        }

        foreach ($analysis['findings'] as $n => $finding) {
            $entry = $entries[$finding['control']['ref']] ?? null;
            if ($entry === null || $finding['domain'] !== $family) {
                continue;
            }
            $recorded = self::text($entry['recorded_condition'] ?? ($entry['observation'] ?? null));
            $approach = self::lines($entry['suggested_approach'] ?? ($entry['recommended_actions'] ?? array()));
            $analysis['findings'][$n]['recorded_condition_ai'] = $recorded;
            $analysis['findings'][$n]['observation_ai'] = $recorded;
            $analysis['findings'][$n]['purpose_ai'] = self::text($entry['control_purpose'] ?? null);
            $analysis['findings'][$n]['risk_scenarios_ai'] = self::scenarios($entry['risk_scenarios'] ?? array());
            $analysis['findings'][$n]['suggested_approach_ai'] = $approach;
            $analysis['findings'][$n]['actions_ai'] = $approach;
            $analysis['findings'][$n]['confirm_ai'] = self::lines($entry['confirm'] ?? array());
            $analysis['findings'][$n]['outcome_ai'] = self::text($entry['expected_outcome'] ?? null);
            $analysis['findings'][$n]['validation_ai'] = self::lines($entry['validation_criteria'] ?? array());
            $analysis['findings'][$n]['considerations_ai'] = null;
            $analysis['findings'][$n]['source_refs_ai'] = self::lines($entry['source_refs'] ?? array()) ?? array();
            $analysis['findings'][$n]['ai_status'] = $narrative_status;
        }

        return $analysis;
    }

    private static function apply_remediation(array $analysis, array $content): array
    {
        $gaps = array();
        foreach ($analysis['gaps'] as $gap) {
            $gaps[$gap['ref']] = $gap;
        }

        $initiatives = array();
        $ids = array();
        $number = 0;

        foreach ($content['initiatives'] ?? array() as $entry) {
            $controls = array_values(array_filter($entry['controls'] ?? array(), function ($ref) use ($gaps) {
                return isset($gaps[$ref]);
            }));
            if (count($controls) === 0 || !is_string($entry['title'] ?? null)) {
                continue;
            }
            $number++;
            $id = 'R-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $ids[$entry['title']] = $id;

            $records = array();
            $priority = 'Low';
            foreach ($controls as $ref) {
                $records[] = $gaps[$ref];
                if (ReportAnalysis::PRIORITY_RANK[$gaps[$ref]['priority']] > ReportAnalysis::PRIORITY_RANK[$priority]) {
                    $priority = $gaps[$ref]['priority'];
                }
            }
            $owners = array_values(array_unique(array_filter(array_map(function ($gap) {
                return $gap['assessor']['owner_name'] ?? '';
            }, $records))));
            $targets = array_values(array_unique(array_filter(array_map(function ($gap) {
                return $gap['assessor']['target_date'] ?? '';
            }, $records))));

            $initiatives[] = array(
                'id' => $id,
                'title' => $entry['title'],
                'phase' => $entry['phase'] ?? ReportAnalysis::PHASES[1],
                'priority' => $priority,
                'problem' => self::text($entry['problem'] ?? null),
                'activities' => self::lines($entry['activities'] ?? array()) ?? array(),
                'deliverables' => self::lines($entry['deliverables'] ?? array()) ?? array(),
                'implementation_considerations' => self::text($entry['implementation_considerations'] ?? null),
                'validation' => self::lines($entry['validation'] ?? array()) ?? array(),
                'controls' => $controls,
                'gaps' => $records,
                'points' => self::lines($entry['points'] ?? array()) ?? array(),
                'depends_on' => self::lines($entry['depends_on'] ?? array()) ?? array(),
                'depends_on_ids' => array(),
                'owner' => count($owners) === 1 ? $owners[0] : null,
                'target' => count($targets) === 1 ? $targets[0] : null
            );
        }

        foreach ($initiatives as $n => $initiative) {
            foreach ($initiative['depends_on'] as $title) {
                if (isset($ids[$title])) {
                    $initiatives[$n]['depends_on_ids'][] = $ids[$title];
                }
            }
        }

        $initiative_of = array();
        foreach ($initiatives as $initiative) {
            foreach ($initiative['controls'] as $ref) {
                $initiative_of[$ref] = $initiative['id'];
            }
        }
        foreach ($analysis['findings'] as $n => $finding) {
            $analysis['findings'][$n]['initiative_ai'] = $initiative_of[$finding['control']['ref']] ?? null;
        }

        $analysis['initiatives_ai'] = $initiatives;
        $analysis['initiatives_ai_uncovered'] = array_values(array_diff(array_keys($gaps), array_keys($initiative_of)));

        return $analysis;
    }

    private static function patterns(array $content): array
    {
        $out = array();

        foreach ($content['patterns'] ?? array() as $entry) {
            if (!is_string($entry['label'] ?? null) || !is_string($entry['description'] ?? null)) {
                continue;
            }
            $out[] = array(
                'label' => $entry['label'],
                'kind' => $entry['kind'] ?? 'governance',
                'description' => $entry['description'],
                'refs' => self::lines($entry['refs'] ?? array()) ?? array()
            );
        }

        return $out;
    }

    private static function readiness(array $analysis, array $summary): array
    {
        $issues = array();

        if ($summary['stale'] > 0) {
            $issues[] = array(
                'Medium',
                $summary['stale'].' AI-written narrative '.($summary['stale'] === 1 ? 'section is' : 'sections are').' out of date with the current results; the calculated text is used until '.($summary['stale'] === 1 ? 'it is' : 'they are').' regenerated.'
            );
        }

        if ($summary['missing'] > 0) {
            $issues[] = array(
                'Low',
                $summary['missing'].' narrative '.($summary['missing'] === 1 ? 'section has' : 'sections have').' not been generated; the calculated text is used.'
            );
        }

        if ($summary['draft'] > 0) {
            $issues[] = array(
                'Medium',
                $summary['draft'].' AI-written narrative '.($summary['draft'] === 1 ? 'section has' : 'sections have').' not been approved by the assessor.'
            );
        }

        if (count($analysis['initiatives_ai_uncovered'] ?? array()) > 0) {
            $issues[] = array(
                'Medium',
                'The AI remediation plan does not cover '.ReportAnalysis::join_list($analysis['initiatives_ai_uncovered']).'.'
            );
        }

        $high = count(array_filter($analysis['review'] ?? array(), function ($issue) {
            return ($issue['severity'] ?? '') === 'High';
        }));
        if ($high > 0) {
            $issues[] = array(
                'Medium',
                'The narrative quality review raised '.$high.' high-severity '.($high === 1 ? 'issue' : 'issues').' to resolve before issue.'
            );
        }

        $rules = count(array_filter($analysis['quality'] ?? array(), function ($issue) {
            return $issue['severity'] === 'High';
        }));
        if ($rules > 0) {
            $issues[] = array(
                'Medium',
                'Automated narrative checks found '.$rules.' high-severity '.($rules === 1 ? 'issue' : 'issues').' (unsupported statements, contradictions or inconsistent figures) to resolve before issue.'
            );
        }

        return $issues;
    }

    private static function text($value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function lines($value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $out = array_values(array_filter(array_map(function ($line) {
            return is_string($line) ? trim($line) : '';
        }, $value), function ($line) {
            return $line !== '';
        }));

        return count($out) > 0 ? $out : null;
    }

    private static function scenarios($value): ?array
    {
        $out = array();

        foreach (is_array($value) ? $value : array() as $pair) {
            $scenario = self::text($pair['scenario'] ?? null);
            $consequence = self::text($pair['consequence'] ?? null);
            if ($scenario !== null && $consequence !== null) {
                $out[] = array(
                    'scenario' => $scenario,
                    'consequence' => $consequence
                );
            }
        }

        return count($out) > 0 ? $out : null;
    }
}
