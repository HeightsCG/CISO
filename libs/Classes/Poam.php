<?php
/**
 * The Plan of Action and Milestones (POA&M) for one assessment: persistent
 * tracking records for its deficient controls, kept in step with the
 * recorded results by an idempotent sync.
 *
 * Sync (runs when the register opens and when the report builds), inside one
 * transaction holding the assessment row lock:
 * - a gap with no record gets a new record, POAM-{assessment}-{seq};
 * - a gap with an open record refreshes the record's source_* snapshot only;
 *   management fields (owner, dates, status, overrides...) are never written;
 * - a gap whose latest record is closed, and whose control was recorded again
 *   after the closure, gets a new record linked through reopened_from_id;
 * - an open record whose control is no longer a gap is flagged
 *   (source_resolved) but never closed and never deleted.
 * Sequence numbers are allocated under the lock from the highest ever used,
 * so a reference is never reused. Every change writes poam_history rows.
 *
 * Closure is a separate staff step: a note, closure evidence or a stated
 * reason, and an explicit override acknowledgement when the control is not
 * recorded Implemented. No edit can set Closed.
 *
 * Framework rules come from the definition data (deferral, deadline and
 * plan_handling rules, and AssessmentScore's plan of action split); PROFILES
 * only says which presentation each framework key gets. Nothing here invents
 * owners, budgets, dates or milestones.
 */
class Poam
{
    const STATUSES = array(
        'Open',
        'In Progress',
        'Ready for Validation',
        'Closed'
    );

    /** What an edit may set: every status except Closed. */
    const EDITABLE_STATUSES = array(
        'Open',
        'In Progress',
        'Ready for Validation'
    );

    const MILESTONE_STATUSES = array(
        'Not Started',
        'In Progress',
        'Completed'
    );

    const PRIORITIES = array(
        'High',
        'Medium',
        'Low'
    );

    const RISK_RATINGS = array(
        'Low',
        'Moderate',
        'High',
        'Critical'
    );

    const DEVIATION_TYPES = array(
        'Risk Adjustment',
        'False Positive',
        'Operational Requirement'
    );

    const EVIDENCE_ROLES = array(
        'Progress',
        'Closure'
    );

    const CHANGE_SOURCES = array(
        'Sync',
        'User',
        'Closure',
        'Reopen'
    );

    /** Management fields staff may edit, and the kind of value each holds. */
    const STAFF_FIELDS = array(
        'owner_name' => 'text200',
        'resources' => 'text',
        'detection_date' => 'date',
        'scheduled_completion_date' => 'date',
        'completion_date' => 'past_date',
        'poam_status' => 'status',
        'comments' => 'text',
        'weakness_override' => 'text',
        'action_override' => 'text',
        'priority_override' => 'priority',
        'risk_rating' => 'risk',
        'vendor_dependency' => 'flag',
        'vendor_product' => 'text200',
        'vendor_checkin_date' => 'date',
        'deviation_type' => 'deviation',
        'deviation_rationale' => 'text'
    );

    /** The subset a portal (client) user may edit on their own client's items. */
    const PORTAL_FIELDS = array(
        'owner_name',
        'resources',
        'poam_status',
        'comments'
    );

    /** The snapshot the sync owns; rewritten only while a record is open. */
    const SNAPSHOT_FIELDS = array(
        'source_item_id',
        'source_ref',
        'source_title',
        'source_family',
        'source_result',
        'source_open_objectives',
        'source_weakness',
        'source_action',
        'source_priority',
        'source_priority_basis',
        'source_initiative_ref',
        'source_initiative_title',
        'source_eligibility',
        'source_eligibility_note',
        'source_points'
    );

    /**
     * How each framework's POA&M rules are presented; the rules themselves
     * are read from the definition. Keys without an entry (custom standards)
     * get the generic tracker.
     */
    const PROFILES = array(
        'cmmc-l1' => 'not_permitted',
        'cmmc-l2' => 'conditional',
        'cmmc-l3' => 'conditional',
        'nist-800-171-r2' => 'sprs',
        'fedramp-low' => 'fedramp',
        'fedramp-moderate' => 'fedramp',
        'fedramp-high' => 'fedramp',
        'easa-part-is-d' => 'corrective_action',
        'easa-part-is-i' => 'corrective_action',
        'nist-800-53-r5' => 'plan_handling',
        'nist-800-171-r3' => 'plan_handling',
        'nist-csf-2' => 'plan_handling',
        'tsa-aviation-cyber' => 'plan_handling',
        'hipaa-security' => 'none',
        'ftc-safeguards' => 'none',
        'nydfs-500' => 'none',
        'soc2' => 'none',
        'iso-27001-2022' => 'none',
        'pci-dss-4' => 'none'
    );

    /** Milestones due within this many days count as upcoming. */
    const UPCOMING_DAYS = 30;

    const NOT_ASSIGNED = 'Not assigned';

    const NOT_SET = 'Not set';

    const NONE_RECORDED = 'None recorded';

    /* ------------------------------------------------------------- framework */

    public static function ref(int $assessment_id, int $seq): string
    {
        return 'POAM-'.$assessment_id.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public static function profile(string $framework_key): string
    {
        return self::PROFILES[$framework_key] ?? 'none';
    }

    /**
     * The POA&M rules of a framework, from its definition: the profile, a
     * plain statement of what the framework requires, a banner when a POA&M
     * is not permitted, and every deferral, deadline and plan_handling rule
     * that concerns the plan with its citation and source title.
     */
    public static function framework_rules(string $framework_key, ?array $definition): array
    {
        $profile = self::profile($framework_key);
        $name = (string) ($definition['display_name'] ?? $definition['name'] ?? '');
        $sources = array();

        foreach ($definition['sources'] ?? array() as $source) {
            $sources[(string) ($source['id'] ?? '')] = (string) ($source['title'] ?? '');
        }

        $rules = array();

        foreach ($definition['rules'] ?? array() as $rule) {
            if (!self::plan_rule($profile, $rule)) {
                continue;
            }
            $rules[] = array(
                'id' => (string) $rule['id'],
                'type' => (string) $rule['type'],
                'summary' => (string) ($rule['summary'] ?? ''),
                'cite' => (string) ($rule['cite'] ?? ''),
                'quote' => (string) ($rule['quote'] ?? ''),
                'source' => $sources[(string) ($rule['source'] ?? '')] ?? '',
                'days' => isset($rule['days']) ? (int) $rule['days'] : null,
                'days_by_risk' => is_array($rule['days_by_risk'] ?? null) ? $rule['days_by_risk'] : array(),
                'from' => (string) ($rule['from'] ?? ''),
                'period' => (string) ($rule['period'] ?? ''),
                'value' => isset($rule['value']) && is_numeric($rule['value']) ? (float) $rule['value'] : null,
                'raw' => $rule
            );
        }

        return array(
            'framework_key' => $framework_key,
            'framework_name' => $name,
            'profile' => $profile,
            'internal_only' => $profile === 'not_permitted',
            'mandated' => !in_array($profile, array(
                'none',
                'not_permitted'
            ), true),
            'banner' => self::banner($profile, $name, $rules),
            'statement' => self::statement($profile, $name, $rules),
            'rules' => $rules,
            'closeout_days' => self::rule_value($rules, 'deadline', 'days', $profile === 'conditional'),
            'days_by_risk' => $profile === 'fedramp' ? self::fedramp_days($rules) : array()
        );
    }

    /** Whether a definition rule belongs in the POA&M's framework section. */
    private static function plan_rule(string $profile, array $rule): bool
    {
        $type = (string) ($rule['type'] ?? '');

        if (in_array($type, array(
            'deferral',
            'plan_handling'
        ), true)) {
            return true;
        }

        if ($type === 'threshold') {
            return $profile === 'conditional' && strpos((string) ($rule['outcome'] ?? ''), 'Conditional') === 0;
        }

        if ($type !== 'deadline') {
            return false;
        }

        if ($profile === 'conditional') {
            return strpos((string) $rule['id'], 'poam') !== false;
        }

        if ($profile === 'fedramp') {
            return isset($rule['days_by_risk']) || (string) $rule['id'] === 'vendor_dependency_mitigation';
        }

        if ($profile === 'corrective_action') {
            return isset($rule['period']);
        }

        return false;
    }

    private static function rule_value(array $rules, string $type, string $key, bool $wanted)
    {
        if (!$wanted) {
            return null;
        }

        foreach ($rules as $rule) {
            if ($rule['type'] === $type && $rule[$key] !== null) {
                return $rule[$key];
            }
        }

        return null;
    }

    private static function fedramp_days(array $rules): array
    {
        foreach ($rules as $rule) {
            if (count($rule['days_by_risk']) > 0) {
                return $rule['days_by_risk'];
            }
        }

        return array();
    }

    private static function banner(string $profile, string $name, array $rules): ?string
    {
        if ($profile !== 'not_permitted') {
            return null;
        }

        $cite = '';
        foreach ($rules as $rule) {
            if ($rule['type'] === 'deferral') {
                $cite = $rule['cite'];
            }
        }

        return $name.' does not permit a POA&M'.($cite !== '' ? ' ('.$cite.')' : '').': every requirement must be met at the time of the self-assessment. This register records remediation work for internal management and must not be presented as a POA&M for '.$name.' purposes.';
    }

    private static function statement(string $profile, string $name, array $rules): string
    {
        $subject = $name !== '' ? $name : 'This standard';

        if ($profile === 'conditional') {
            $days = self::rule_value($rules, 'deadline', 'days', true);
            $minimum = self::rule_value($rules, 'threshold', 'value', true);
            $text = $subject.' allows a POA&M for Conditional status only when every open item is eligible under the deferral rule below'.($minimum !== null ? ' and the assessment score is at least '.round($minimum * 100).'% of the maximum' : '').'. Each item shows its eligibility.';
            return $days !== null ? $text.' Every item must be closed and confirmed by a POA&M closeout assessment within '.$days.' days of the Conditional CMMC Status Date, or the Conditional status expires.' : $text;
        }

        if ($profile === 'sprs') {
            return $subject.' requires a plan of action for every requirement not yet implemented, but the plan earns no points: a requirement on the plan is still scored as not implemented, and its SPRS deduction is shown.';
        }

        if ($profile === 'fedramp') {
            return $subject.' requires weaknesses to be tracked in a POA&M and remediated within timeframes from discovery set by risk rating, as listed below with their source. The derived deadline is shown only where a risk rating and a detection date have been recorded. Remediation priority, remediation initiative, milestone and evidence counts are internal tracking fields, not FedRAMP requirements.';
        }

        if ($profile === 'corrective_action') {
            return $subject.' requires a corrective action plan for each finding notified by the competent authority, completed within the period agreed with the authority.';
        }

        if ($profile === 'plan_handling') {
            return $subject.' calls for deficiencies to be tracked in a plan of action, but mandates no POA&M format. This register uses a generic POA&M layout.';
        }

        if ($profile === 'not_permitted') {
            return $subject.' does not permit a POA&M. This register is for internal remediation tracking only.';
        }

        return $subject.' has no mandated POA&M format. This register is a generic remediation tracker.';
    }

    /* -------------------------------------------------------------- snapshot */

    /**
     * The snapshot of every gap, keyed by control reference. $analysis is a
     * ReportAnalysis build (narratives applied or not); $scoring is
     * AssessmentScore::score() or null for standards without a definition.
     */
    public static function snapshots(array $analysis, ?array $scoring, array $rules): array
    {
        $terms = $analysis['terms'] ?? array();
        $initiatives = array();
        $titles = array();

        foreach (self::initiatives($analysis) as $initiative) {
            $titles[$initiative['id']] = $initiative['title'];
            foreach ($initiative['controls'] as $ref) {
                $initiatives[$ref][] = $initiative['id'];
            }
        }

        $points = array();
        foreach ($scoring['points']['deductions'] ?? array() as $deduction) {
            $points[(string) $deduction['id']] = (int) $deduction['points'];
        }

        $out = array();

        foreach ($analysis['gaps'] as $gap) {
            $ids = $initiatives[$gap['ref']] ?? array();
            $eligibility = self::eligibility($rules, $scoring, $gap, $points[$gap['ref']] ?? null);
            $open = array();
            foreach ($gap['open'] as $objective) {
                $open[] = array(
                    'ref' => (string) $objective['ref'],
                    'short' => (string) $objective['short'],
                    'title' => (string) $objective['title'],
                    'result' => (string) $objective['result']
                );
            }
            $out[$gap['ref']] = array(
                'source_item_id' => (int) $gap['id'],
                'source_ref' => (string) $gap['ref'],
                'source_title' => self::cut((string) $gap['title'], 400),
                'source_family' => self::cut((string) $gap['family'], 200),
                'source_result' => (string) $gap['result'],
                'source_open_objectives' => json_encode($open, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'source_weakness' => self::weakness($gap, $terms),
                'source_action' => self::action($gap, $terms),
                'source_priority' => (string) ($gap['priority'] ?? ''),
                'source_priority_basis' => self::cut((string) ($gap['priority_basis'] ?? ''), 500),
                'source_initiative_ref' => self::cut(implode(', ', $ids), 40),
                'source_initiative_title' => self::cut(implode('; ', array_map(function ($id) use ($titles) {
                    return $titles[$id] ?? '';
                }, $ids)), 400),
                'source_eligibility' => $eligibility['eligibility'],
                'source_eligibility_note' => self::cut($eligibility['note'], 500),
                'source_points' => $points[$gap['ref']] ?? null,
                'updated' => (string) ($gap['updated'] ?? '')
            );
        }

        return $out;
    }

    /** The remediation plan's initiatives, so each item names the initiative that covers it. */
    private static function initiatives(array $analysis): array
    {
        if (class_exists('ReportRemediationPlan') && method_exists('ReportRemediationPlan', 'plan_initiatives')) {
            return ReportRemediationPlan::plan_initiatives($analysis);
        }

        return $analysis['initiatives'] ?? array();
    }

    /**
     * Eligibility of one gap under the framework's rules: eligible or
     * not_eligible (conditional frameworks, from AssessmentScore's plan of
     * action split), not_permitted (no POA&M at all), or '' (no rule).
     */
    public static function eligibility(array $rules, ?array $scoring, array $gap, ?int $points): array
    {
        $profile = $rules['profile'];

        if ($profile === 'not_permitted') {
            return array(
                'eligibility' => 'not_permitted',
                'note' => 'A POA&M is not permitted for this framework; tracked for internal remediation only.'
            );
        }

        if ($profile === 'sprs') {
            $cite = '';
            foreach ($rules['rules'] as $rule) {
                if ($rule['type'] === 'plan_handling') {
                    $cite = $rule['cite'];
                }
            }
            return array(
                'eligibility' => '',
                'note' => ($points !== null && $points > 0 ? 'Deducts '.$points.' '.($points === 1 ? 'point' : 'points').' from the SPRS score. ' : '').($points === 0 ? 'Carries no SPRS point value. ' : '').'Still scored as not implemented while on the plan'.($cite !== '' ? ' ('.$cite.')' : '').'.'
            );
        }

        if ($profile !== 'conditional' || $scoring === null) {
            return array(
                'eligibility' => '',
                'note' => ''
            );
        }

        $deferral = null;
        foreach ($rules['rules'] as $rule) {
            if ($rule['type'] === 'deferral') {
                $deferral = $rule;
            }
        }

        $ref = (string) $gap['ref'];
        $cite = $deferral !== null && $deferral['cite'] !== '' ? ' ('.$deferral['cite'].')' : '';
        $raw = $deferral['raw'] ?? array();

        if (in_array($ref, $scoring['plan']['deferrable'] ?? array(), true)) {
            $note = 'Eligible for the POA&M'.$cite.'.';
            foreach ($raw['exceptions'] ?? array() as $exception) {
                if (($exception['control'] ?? '') === $ref) {
                    $note = 'Eligible for the POA&M under the exception for '.$ref.(isset($exception['condition']) ? ' when '.$exception['condition'] : ' when it is partially met').$cite.'.';
                }
            }
            if ($points !== null) {
                $note .= ' Point value '.$points.'.';
            }
            return array(
                'eligibility' => 'eligible',
                'note' => $note
            );
        }

        if (in_array($ref, $raw['excluded'] ?? array(), true)) {
            return array(
                'eligibility' => 'not_eligible',
                'note' => 'Can never be on a POA&M'.$cite.'; must be MET at the assessment for Conditional status.'
            );
        }

        if (isset($raw['max_weight']) && $points !== null) {
            return array(
                'eligibility' => 'not_eligible',
                'note' => 'Point value '.$points.' exceeds the '.(int) $raw['max_weight'].'-point limit for POA&M items'.$cite.'; must be MET at the assessment for Conditional status.'
            );
        }

        return array(
            'eligibility' => 'not_eligible',
            'note' => 'Not eligible for the POA&M'.$cite.'; must be MET at the assessment for Conditional status.'
        );
    }

    /** The weakness, from the recorded result and open objectives only. */
    private static function weakness(array $gap, array $terms): string
    {
        $control = $terms['control'] ?? 'control';
        $objectives = $terms['objectives'] ?? 'objectives';
        $text = ucfirst($control).' '.$gap['ref'].' ('.rtrim((string) $gap['title'], '.').') is recorded as '.strtolower((string) $gap['result']).'.';
        $answered = array();
        $unanswered = 0;

        foreach ($gap['open'] as $objective) {
            if ($objective['result'] === 'Not Assessed') {
                $unanswered++;
                continue;
            }
            $answered[] = $objective['short'].' ('.strtolower((string) $objective['result']).')';
        }

        if (count($answered) > 0) {
            $text .= ' Open '.$objectives.': '.implode(', ', $answered).'; the corrective action lists what each requires.';
        }

        if ($unanswered > 0) {
            $text .= ' '.$unanswered.' '.($unanswered === 1 ? ($terms['objective'] ?? 'objective').' is' : $objectives.' are').' not yet assessed.';
        }

        if (isset($gap['assessor']['gap_description'])) {
            $text .= ' Assessor observation: '.$gap['assessor']['gap_description'];
        }

        return $text;
    }

    /** The corrective action: the assessor's recommendation, else the open objectives to bring to the expected condition. */
    private static function action(array $gap, array $terms): string
    {
        $answered = array_values(array_filter($gap['open'], function ($objective) {
            return $objective['result'] !== 'Not Assessed';
        }));
        $unanswered = count($gap['open']) - count($answered);

        if (isset($gap['assessor']['recommendation'])) {
            $action = 'Assessor recommendation: '.$gap['assessor']['recommendation'];
        } elseif (count($answered) > 0) {
            $action = 'Bring to the expected condition: '.implode('; ', array_map(function ($objective) {
                return $objective['short'].' '.rtrim((string) $objective['title'], '.');
            }, $answered)).'.';
        } else {
            $action = 'Confirm the specific shortfall with the assessor and bring the '.($terms['control'] ?? 'control').' to the expected condition.';
        }

        if ($unanswered > 0) {
            $action .= ' Assess the '.$unanswered.' unanswered '.($unanswered === 1 ? ($terms['objective'] ?? 'objective') : ($terms['objectives'] ?? 'objectives')).'.';
        }

        return $action;
    }

    public static function snapshot_hash(array $snapshot): string
    {
        $values = array();

        foreach (self::SNAPSHOT_FIELDS as $field) {
            $values[$field] = $snapshot[$field] === null ? null : (string) $snapshot[$field];
        }

        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /* ------------------------------------------------------------------ sync */

    /**
     * The sync's operations, without touching the database. $snapshots from
     * snapshots(); $records every stored record of the assessment (any
     * status); $results control ref => current result for every control.
     * Returns a list of operations:
     * - insert: snapshot, reopened_from (record or null), change_source, note
     * - update: record, set (column => value), change_source, note
     */
    public static function plan_sync(array $snapshots, array $records, array $results): array
    {
        $latest = array();

        foreach ($records as $record) {
            $ref = (string) $record['source_ref'];
            if (!isset($latest[$ref]) || (int) $record['poam_seq'] > (int) $latest[$ref]['poam_seq']) {
                $latest[$ref] = $record;
            }
        }

        $ops = array();

        foreach ($snapshots as $ref => $snapshot) {
            $record = $latest[$ref] ?? null;

            if ($record === null) {
                $ops[] = array(
                    'op' => 'insert',
                    'snapshot' => $snapshot,
                    'reopened_from' => null,
                    'change_source' => 'Sync',
                    'note' => 'Created from the assessment: '.$ref.' is recorded as '.strtolower($snapshot['source_result']).'.'
                );
                continue;
            }

            if ($record['poam_status'] === 'Closed') {
                if ($snapshot['updated'] !== '' && (string) $record['date_closed'] !== '' && $snapshot['updated'] > (string) $record['date_closed']) {
                    $ops[] = array(
                        'op' => 'insert',
                        'snapshot' => $snapshot,
                        'reopened_from' => $record,
                        'change_source' => 'Reopen',
                        'note' => $ref.' was recorded as '.strtolower($snapshot['source_result']).' after '.$record['poam_ref'].' was closed.'
                    );
                }
                continue;
            }

            $set = array();
            foreach (self::SNAPSHOT_FIELDS as $field) {
                if (self::differs($record[$field] ?? null, $snapshot[$field])) {
                    $set[$field] = $snapshot[$field];
                }
            }
            if ((int) $record['source_resolved'] === 1) {
                $set['source_resolved'] = 0;
            }
            if (count($set) > 0) {
                $set['source_hash'] = self::snapshot_hash($snapshot);
                $set['source_date_updated'] = $snapshot['updated'] !== '' ? $snapshot['updated'] : null;
                $ops[] = array(
                    'op' => 'update',
                    'record' => $record,
                    'set' => $set,
                    'change_source' => 'Sync',
                    'note' => isset($set['source_resolved']) ? $ref.' is recorded as '.strtolower($snapshot['source_result']).' again.' : 'Refreshed from the assessment.'
                );
            }
        }

        foreach ($latest as $ref => $record) {
            if (isset($snapshots[$ref]) || $record['poam_status'] === 'Closed') {
                continue;
            }
            $result = $results[$ref] ?? 'Removed from the assessment';
            $set = array();
            if ((int) $record['source_resolved'] !== 1) {
                $set['source_resolved'] = 1;
            }
            if ((string) $record['source_result'] !== $result) {
                $set['source_result'] = $result;
            }
            if (count($set) > 0) {
                $ops[] = array(
                    'op' => 'update',
                    'record' => $record,
                    'set' => $set,
                    'change_source' => 'Sync',
                    'note' => 'Assessment records '.$result.'; closure not yet confirmed.'
                );
            }
        }

        return $ops;
    }

    private static function differs($stored, $fresh): bool
    {
        if ($stored === null || $fresh === null) {
            return $stored !== $fresh && !($stored === null && $fresh === '') && !($stored === '' && $fresh === null);
        }

        return (string) $stored !== (string) $fresh;
    }

    /**
     * Brings the assessment's records in step with its recorded results.
     * $analysis is reused when the caller already built it (the report).
     * Returns array('created', 'refreshed', 'resolved', 'reopened') counts,
     * or array('error' => ...).
     */
    public static function sync(int $assessment_id, int $company_id, int $user_id, ?array $analysis = null): array
    {
        if ($analysis === null) {
            $analysis = (new ReportAnalysis($assessment_id, $company_id))->build();
            if ($analysis !== null && class_exists('ReportNarratives')) {
                $analysis = ReportNarratives::apply($analysis, $company_id);
            }
        }

        if ($analysis === null) {
            return array(
                'error' => 'That assessment could not be found'
            );
        }

        $context = self::context($analysis, $company_id);
        $snapshots = self::snapshots($analysis, $context['scoring'], $context['rules']);
        $results = array();

        foreach ($analysis['controls'] as $control) {
            $results[$control['ref']] = $control['result'];
        }

        return (new PoamModel())->sync_items($assessment_id, $company_id, $user_id, function (array $records) use ($snapshots, $results) {
            return self::plan_sync($snapshots, $records, $results);
        });
    }

    /** The framework rules and scoring an assessment's POA&M reads. */
    public static function context(array $analysis, int $company_id): array
    {
        $key = (string) ($analysis['assessment']['framework_key'] ?? '');
        $definition = $analysis['definition'] ?? FrameworkLibrary::get($key);
        $scoring = null;

        if ($definition !== null && isset($definition['rules'], $definition['scoring']['model'])) {
            $controls = array_values(array_filter((new AssessmentsModel())->load_items((int) $analysis['assessment']['id'], $company_id), function ($row) {
                return $row['control_kind'] === 'control';
            }));
            $scoring = AssessmentScore::score($definition, $analysis['assessment'], $controls);
        }

        return array(
            'definition' => $definition,
            'scoring' => $scoring,
            'rules' => self::framework_rules($key, $definition)
        );
    }

    /* --------------------------------------------------------------- editing */

    /**
     * One posted management field as it would be stored:
     * array('value' => ...) or array('error' => message). A portal user may
     * only set PORTAL_FIELDS; no field can set Closed.
     */
    public static function clean_field(string $field, string $value, string $today, bool $portal): array
    {
        if (!isset(self::STAFF_FIELDS[$field])) {
            return array(
                'error' => 'That field cannot be changed'
            );
        }

        if ($portal && !in_array($field, self::PORTAL_FIELDS, true)) {
            return array(
                'error' => 'You cannot change the '.self::label($field)
            );
        }

        $clean = self::clean_value(self::STAFF_FIELDS[$field], $field, trim($value), $today);

        if (is_array($clean)) {
            return $clean;
        }

        return array(
            'value' => $clean
        );
    }

    /** Why the scheduled completion date cannot stand against the detection date, or ''. */
    public static function date_order_error($detection_date, $scheduled_completion_date): string
    {
        if ((string) $detection_date === '' || (string) $scheduled_completion_date === '') {
            return '';
        }

        if ((string) $scheduled_completion_date < (string) $detection_date) {
            return 'The scheduled completion date is before the detection date';
        }

        return '';
    }

    /** One posted value as stored, or array('error' => ...). */
    private static function clean_value(string $kind, string $field, string $value, string $today)
    {
        if ($kind === 'text200') {
            if (mb_strlen($value) > 200) {
                return array(
                    'error' => ucfirst(self::label($field)).' must be 200 characters or fewer'
                );
            }
            return $value;
        }

        if ($kind === 'text') {
            if (mb_strlen($value) > 10000) {
                return array(
                    'error' => ucfirst(self::label($field)).' is too long'
                );
            }
            return $value === '' ? null : $value;
        }

        if ($kind === 'date' || $kind === 'past_date') {
            if ($value === '') {
                return null;
            }
            if (!self::valid_date($value)) {
                return array(
                    'error' => 'Enter a valid '.self::label($field)
                );
            }
            if ($kind === 'past_date' && $value > $today) {
                return array(
                    'error' => 'The '.self::label($field).' cannot be in the future'
                );
            }
            return $value;
        }

        if ($kind === 'status') {
            if ($value === 'Closed') {
                return array(
                    'error' => 'Closure is confirmed separately, with a note and evidence'
                );
            }
            if (!in_array($value, self::EDITABLE_STATUSES, true)) {
                return array(
                    'error' => 'Choose a valid status'
                );
            }
            return $value;
        }

        $lists = array(
            'priority' => self::PRIORITIES,
            'risk' => self::RISK_RATINGS,
            'deviation' => self::DEVIATION_TYPES
        );

        if (isset($lists[$kind])) {
            if ($value !== '' && !in_array($value, $lists[$kind], true)) {
                return array(
                    'error' => 'Choose a valid '.self::label($field)
                );
            }
            return $value;
        }

        if ($kind === 'flag') {
            return in_array($value, array(
                '1',
                'true',
                'on'
            ), true) ? 1 : 0;
        }

        return array(
            'error' => 'That field cannot be changed'
        );
    }

    /**
     * A validated milestone: array('milestone' => columns) or array('error').
     * A completion date marks it Completed; Completed needs that date.
     */
    public static function clean_milestone(string $text, string $due_date, string $completed_date, string $milestone_status, string $today): array
    {
        $text = trim($text);
        $due_date = trim($due_date);
        $completed_date = trim($completed_date);

        if ($text === '') {
            return array(
                'error' => 'Describe the milestone'
            );
        }

        if (mb_strlen($text) > 2000) {
            return array(
                'error' => 'The milestone must be 2000 characters or fewer'
            );
        }

        if ($due_date !== '' && !self::valid_date($due_date)) {
            return array(
                'error' => 'Enter a valid due date'
            );
        }

        if ($completed_date !== '' && !self::valid_date($completed_date)) {
            return array(
                'error' => 'Enter a valid completion date'
            );
        }

        if ($completed_date !== '' && $completed_date > $today) {
            return array(
                'error' => 'The completion date cannot be in the future'
            );
        }

        if ($milestone_status === '') {
            $milestone_status = 'Not Started';
        }

        if (!in_array($milestone_status, self::MILESTONE_STATUSES, true)) {
            return array(
                'error' => 'Choose a valid milestone status'
            );
        }

        if ($completed_date !== '') {
            $milestone_status = 'Completed';
        }

        if ($milestone_status === 'Completed' && $completed_date === '') {
            return array(
                'error' => 'Enter the date the milestone was completed'
            );
        }

        return array(
            'milestone' => array(
                'milestone_text' => $text,
                'due_date' => $due_date === '' ? null : $due_date,
                'completed_date' => $completed_date === '' ? null : $completed_date,
                'milestone_status' => $milestone_status
            )
        );
    }

    /**
     * Why a closure cannot be confirmed, or '' when it can: staff only, an
     * open record, a note, closure evidence or a stated reason, and the
     * override acknowledgement when the control is not recorded Implemented.
     */
    public static function closure_error(string $user_type, string $poam_status, string $note, int $closure_evidence, string $reason, bool $override, string $control_result): string
    {
        if ($user_type !== 'staff') {
            return 'Only your assessor can confirm closure';
        }

        if ($poam_status === 'Closed') {
            return 'This item is already closed';
        }

        if (trim($note) === '') {
            return 'Enter a closure note';
        }

        if ($closure_evidence === 0 && trim($reason) === '') {
            return 'Link closure evidence, or state why there is none';
        }

        if ($control_result !== 'Implemented' && !$override) {
            return 'The assessment does not record this control as Implemented ('.($control_result !== '' ? $control_result : 'no result').'). Acknowledge the override to close it anyway.';
        }

        return '';
    }

    /* ----------------------------------------------------------- presentation */

    /**
     * A record with everything the register and the report show worked out:
     * effective weakness, action and priority, the explicit "Not assigned" /
     * "Not set" markers, overdue, CMMC closeout and FedRAMP derived deadline.
     */
    public static function present(array $record, array $rules, ?string $clock_date, string $today): array
    {
        $priority_set = (string) ($record['priority_override'] ?? '') !== '';
        $priority = $priority_set ? (string) $record['priority_override'] : (string) $record['source_priority'];
        $scheduled = $record['scheduled_completion_date'] ?? null;
        $closed = $record['poam_status'] === 'Closed';
        $deadline = null;

        if ($rules['profile'] === 'fedramp' && (string) ($record['risk_rating'] ?? '') !== '' && !empty($record['detection_date'])) {
            $days = $rules['days_by_risk'][strtolower((string) $record['risk_rating'])] ?? null;
            if ($days !== null) {
                $deadline = date('Y-m-d', strtotime($record['detection_date'].' +'.(int) $days.' days'));
            }
        }

        $closeout = null;
        if ($rules['profile'] === 'conditional' && $clock_date !== null && $clock_date !== '' && $rules['closeout_days'] !== null) {
            $closeout = date('Y-m-d', strtotime($clock_date.' +'.(int) $rules['closeout_days'].' days'));
        }

        $open_objectives = json_decode((string) ($record['source_open_objectives'] ?? ''), true);

        return array_merge($record, array(
            'weakness' => (string) ($record['weakness_override'] ?? '') !== '' ? (string) $record['weakness_override'] : (string) $record['source_weakness'],
            'weakness_overridden' => (string) ($record['weakness_override'] ?? '') !== '',
            'action' => (string) ($record['action_override'] ?? '') !== '' ? (string) $record['action_override'] : (string) $record['source_action'],
            'action_overridden' => (string) ($record['action_override'] ?? '') !== '',
            'priority' => $priority,
            'priority_label' => $priority === '' ? self::NOT_SET : $priority.($priority_set ? ' (set by management)' : ' (derived)'),
            'owner_label' => (string) ($record['owner_name'] ?? '') !== '' ? (string) $record['owner_name'] : self::NOT_ASSIGNED,
            'resources_label' => (string) ($record['resources'] ?? '') !== '' ? (string) $record['resources'] : self::NOT_SET,
            'comments_label' => (string) ($record['comments'] ?? '') !== '' ? (string) $record['comments'] : self::NONE_RECORDED,
            'detection_label' => self::date_label($record['detection_date'] ?? null),
            'scheduled_label' => self::date_label($scheduled),
            'completion_label' => self::date_label($record['completion_date'] ?? null),
            'overdue' => !$closed && $scheduled !== null && $scheduled !== '' && $scheduled < $today,
            'open_objectives' => is_array($open_objectives) ? $open_objectives : array(),
            'resolved_label' => (int) $record['source_resolved'] === 1 && !$closed ? 'Assessment records '.$record['source_result'].', closure not yet confirmed' : '',
            'eligibility_label' => self::eligibility_label((string) $record['source_eligibility']),
            'fedramp_deadline' => $deadline,
            'closeout_date' => $closeout
        ));
    }

    public static function eligibility_label(string $eligibility): string
    {
        $labels = array(
            'eligible' => 'Eligible for the POA&M',
            'not_eligible' => 'Not eligible: must be MET at the assessment',
            'not_permitted' => 'POA&M not permitted (internal tracking only)'
        );

        return $labels[$eligibility] ?? '';
    }

    /**
     * Counts for the overview, from the records and milestones only: open,
     * overdue (only where a scheduled date exists), without a scheduled date,
     * unassigned, upcoming and overdue milestones, and open items by priority
     * with how many of them have every milestone completed.
     */
    public static function overview(array $presented, array $milestones, string $today): array
    {
        $open = array_values(array_filter($presented, function ($record) {
            return $record['poam_status'] !== 'Closed';
        }));
        $open_ids = array_flip(array_map('intval', array_column($open, 'id')));
        $horizon = date('Y-m-d', strtotime($today.' +'.self::UPCOMING_DAYS.' days'));
        $upcoming = array();
        $late = array();
        $by_item = array();

        foreach ($milestones as $milestone) {
            if (!isset($open_ids[(int) $milestone['poam_item_id']])) {
                continue;
            }
            $by_item[(int) $milestone['poam_item_id']][] = $milestone;
            if ($milestone['milestone_status'] === 'Completed' || empty($milestone['due_date'])) {
                continue;
            }
            if ($milestone['due_date'] < $today) {
                $late[] = $milestone;
            } elseif ($milestone['due_date'] <= $horizon) {
                $upcoming[] = $milestone;
            }
        }

        $priorities = array();
        foreach (array_merge(self::PRIORITIES, array(
            ''
        )) as $priority) {
            $priorities[$priority] = array(
                'total' => 0,
                'in_progress' => 0,
                'ready' => 0,
                'with_milestones' => 0,
                'milestones_done' => 0,
                'milestones_total' => 0
            );
        }

        foreach ($open as $record) {
            $key = in_array($record['priority'], self::PRIORITIES, true) ? $record['priority'] : '';
            $priorities[$key]['total']++;
            if ($record['poam_status'] === 'In Progress') {
                $priorities[$key]['in_progress']++;
            }
            if ($record['poam_status'] === 'Ready for Validation') {
                $priorities[$key]['ready']++;
            }
            $list = $by_item[(int) $record['id']] ?? array();
            if (count($list) > 0) {
                $priorities[$key]['with_milestones']++;
            }
            $priorities[$key]['milestones_total'] += count($list);
            $priorities[$key]['milestones_done'] += count(array_filter($list, function ($milestone) {
                return $milestone['milestone_status'] === 'Completed';
            }));
        }

        return array(
            'total' => count($presented),
            'open' => count($open),
            'closed' => count($presented) - count($open),
            'overdue' => count(array_filter($open, function ($record) {
                return $record['overdue'];
            })),
            'scheduled' => count(array_filter($open, function ($record) {
                return !empty($record['scheduled_completion_date']);
            })),
            'unassigned' => count(array_filter($open, function ($record) {
                return (string) ($record['owner_name'] ?? '') === '';
            })),
            'resolved' => count(array_filter($open, function ($record) {
                return (int) $record['source_resolved'] === 1;
            })),
            'ready' => count(array_filter($open, function ($record) {
                return $record['poam_status'] === 'Ready for Validation';
            })),
            'upcoming_milestones' => $upcoming,
            'overdue_milestones' => $late,
            'priorities' => $priorities
        );
    }

    /* ---------------------------------------------------------------- helpers */

    public static function label(string $field): string
    {
        $labels = array(
            'owner_name' => 'owner',
            'resources' => 'resources',
            'detection_date' => 'detection date',
            'scheduled_completion_date' => 'scheduled completion date',
            'completion_date' => 'completion date',
            'poam_status' => 'status',
            'comments' => 'comments',
            'weakness_override' => 'weakness',
            'action_override' => 'corrective action',
            'priority_override' => 'priority',
            'risk_rating' => 'risk rating',
            'vendor_dependency' => 'vendor dependency',
            'vendor_product' => 'vendor product',
            'vendor_checkin_date' => 'last vendor check-in date',
            'deviation_type' => 'deviation type',
            'deviation_rationale' => 'deviation rationale',
            'poam_clock_date' => 'Conditional status date'
        );

        return $labels[$field] ?? str_replace('_', ' ', $field);
    }

    public static function valid_date(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    public static function date_label($value): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return self::NOT_SET;
        }

        $time = strtotime((string) $value);

        return $time === false ? (string) $value : date('M j, Y', $time);
    }

    private static function cut(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) : $text;
    }
}
