<?php
/**
 * The document state of an assessment's reports and the Final gate.
 *
 * Draft and In Review documents use current narratives whatever their
 * review status (rejected ones excluded). Final is allowed only when every
 * required narrative section is current and approved; it then records a
 * digest of the narrative input hashes, their text and the recorded
 * results. When the assessment or a narrative later changes, or a section
 * is no longer approved, the state reports changed_since_final and
 * documents render as Draft until re-approved and finalized again.
 *
 * Versions: no row is Draft 0.1. Each change into Draft or In Review bumps
 * the minor number (0.2, 0.3, after Final 1.1); Final bumps the major (1.0, 2.0).
 */
class ReportStates
{
    const STATUSES = array(
        'Draft',
        'In Review',
        'Final'
    );

    const INITIAL_VERSION = '0.1';

    /** Why Final is not allowed yet, one line per section; empty when it is. */
    public static function gate(array $states): array
    {
        $blockers = array();

        if (count($states) === 0) {
            $blockers[] = 'No narrative sections apply to this assessment yet';
        }

        foreach ($states as $state) {
            $label = $state['label'] ?? $state['section_key'];
            if ($state['state'] === 'missing') {
                $blockers[] = $label.': not generated';
                continue;
            }
            if ($state['state'] === 'stale') {
                $blockers[] = $label.': out of date with the assessment, regenerate and approve it';
                continue;
            }
            if ($state['narrative_status'] !== 'Approved') {
                $blockers[] = $label.': '.($state['narrative_status'] === 'Rejected' ? 'rejected' : 'not approved');
            }
        }

        return $blockers;
    }

    /** The version after a change into $report_status. */
    public static function next_version(?string $version, string $report_status): string
    {
        $parts = explode('.', (string) ($version ?? self::INITIAL_VERSION));
        $major = (int) ($parts[0] ?? 0);
        $minor = (int) ($parts[1] ?? 1);

        if ($report_status === 'Final') {
            return ($major + 1).'.0';
        }

        return $major.'.'.($minor + 1);
    }

    /** Digest of every section's input hash and text and the recorded results, for change detection. */
    public static function digest(array $states, array $analysis): string
    {
        $hashes = array();
        foreach ($states as $key => $state) {
            $hashes[$key] = array(
                'input' => $state['hash'],
                'content' => hash('sha256', ReportAI::canonical($state['content']))
            );
        }

        return hash('sha256', ReportAI::canonical(array(
            'sections' => $hashes,
            'facts' => self::facts($analysis)
        )));
    }

    /** The recorded results a Final document stands on. */
    public static function facts(array $analysis): array
    {
        $controls = array();

        foreach ($analysis['controls'] ?? array() as $control) {
            $objectives = array();
            foreach ($control['objectives'] ?? array() as $objective) {
                $objectives[] = array(
                    'ref' => $objective['ref'] ?? '',
                    'result' => $objective['result'] ?? '',
                    'evidence_count' => $objective['evidence_count'] ?? 0
                );
            }
            $controls[] = array(
                'ref' => $control['ref'],
                'result' => $control['result'],
                'applicability' => $control['applicability'],
                'justification' => $control['justification'],
                'assessor' => $control['assessor'],
                'risk_rating' => $control['risk_rating'],
                'evidence' => $control['evidence'],
                'objectives' => $objectives
            );
        }

        return array(
            'name' => $analysis['name'] ?? '',
            'client' => $analysis['client'] ?? '',
            'standard' => $analysis['standard'] ?? '',
            'period' => $analysis['period'] ?? '',
            'assessor' => $analysis['assessor'] ?? array(),
            'controls' => $controls
        );
    }

    /** The stored row, or the implicit Draft 0.1. */
    public static function stored(int $assessment_id, int $company_id): array
    {
        $rows = (new ReportStatesModel())->get_state($assessment_id, $company_id);

        if (count($rows) === 1) {
            return $rows[0];
        }

        return array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id,
            'report_status' => 'Draft',
            'report_version' => self::INITIAL_VERSION,
            'final_hash' => null,
            'finalized_by' => null,
            'finalized_date' => null,
            'finalized_by_name' => ''
        );
    }

    /** Narrative states for the current analysis (generation order). */
    public static function narrative_states(array $analysis, int $company_id): array
    {
        $rows = (new ReportNarrativesModel())->load_narratives((int) $analysis['assessment']['id'], $company_id);

        return ReportNarratives::states($analysis, $rows);
    }

    /**
     * The state block for the Reports tab: report_status, report_version,
     * changed_since_final, finalized_by_name, finalized_date, can_finalize,
     * finalize_blockers.
     */
    public static function summary(array $analysis, int $company_id, ?array $states = null): array
    {
        $states = $states ?? self::narrative_states($analysis, $company_id);
        $row = self::stored((int) $analysis['assessment']['id'], $company_id);
        $blockers = self::gate($states);
        $changed = $row['report_status'] === 'Final' && ($row['final_hash'] !== self::digest($states, $analysis) || count($blockers) > 0);

        return array(
            'report_status' => $row['report_status'],
            'report_version' => $row['report_version'],
            'changed_since_final' => $changed,
            'finalized_by_name' => $row['report_status'] === 'Final' ? (string) $row['finalized_by_name'] : '',
            'finalized_date' => $row['report_status'] === 'Final' ? $row['finalized_date'] : null,
            'can_finalize' => count($blockers) === 0,
            'finalize_blockers' => $blockers
        );
    }

    /** Move the reports to Draft, In Review or Final. Final is refused while any section blocks it. */
    public static function set_status(int $assessment_id, int $company_id, int $user_id, string $report_status): array
    {
        if (!in_array($report_status, self::STATUSES, true)) {
            return array(
                'success' => false,
                'message' => 'Choose Draft, In Review or Final'
            );
        }

        $analysis = (new ReportAnalysis($assessment_id, $company_id))->build();

        if ($analysis === null) {
            return array(
                'success' => false,
                'message' => 'That assessment could not be found'
            );
        }

        $states = self::narrative_states($analysis, $company_id);
        $current = self::summary($analysis, $company_id, $states);
        $row = self::stored($assessment_id, $company_id);

        if ($report_status === 'Final') {
            if (!$current['can_finalize']) {
                return array(
                    'success' => false,
                    'message' => 'Every narrative section must be current and approved before the reports can be Final',
                    'blockers' => $current['finalize_blockers'],
                    'state' => $current
                );
            }
            if ($row['report_status'] === 'Final' && !$current['changed_since_final']) {
                return array(
                    'success' => true,
                    'message' => 'The reports are already Final',
                    'state' => $current
                );
            }
            (new ReportStatesModel())->save_state(
                $assessment_id,
                $company_id,
                'Final',
                self::next_version($row['report_version'], 'Final'),
                self::digest($states, $analysis),
                $user_id,
                date('Y-m-d H:i:s'),
                $user_id
            );
        } elseif ($row['report_status'] !== $report_status) {
            (new ReportStatesModel())->save_state(
                $assessment_id,
                $company_id,
                $report_status,
                self::next_version($row['report_version'], $report_status),
                $row['final_hash'],
                $row['finalized_by'],
                $row['finalized_date'],
                $user_id
            );
        }

        return array(
            'success' => true,
            'message' => 'The reports are now '.$report_status,
            'state' => self::summary($analysis, $company_id, $states)
        );
    }

    /**
     * Options for ReadyReports::render: report_status, report_version,
     * changed_since_final, approved_by, approved_date, generated and the
     * narrative mode. A Final that no longer matches renders as Draft.
     */
    public static function document_options(int $assessment_id, int $company_id): array
    {
        $analysis = (new ReportAnalysis($assessment_id, $company_id))->build();

        if ($analysis === null) {
            return array();
        }

        $summary = self::summary($analysis, $company_id);
        $final = $summary['report_status'] === 'Final' && !$summary['changed_since_final'];

        return array(
            'report_status' => $summary['changed_since_final'] ? 'Draft' : $summary['report_status'],
            'report_version' => $summary['report_version'],
            'changed_since_final' => $summary['changed_since_final'],
            'approved_by' => $final ? $summary['finalized_by_name'] : '',
            'approved_date' => $final ? $summary['finalized_date'] : null,
            'generated' => date('Y-m-d H:i:s'),
            'narrative_mode' => $final ? 'final' : 'draft'
        );
    }
}
