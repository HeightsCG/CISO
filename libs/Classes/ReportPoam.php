<?php
/**
 * The Plan of Action and Milestones: the assessment's POA&M register as a
 * living "as of" document. Building it runs the POA&M sync first, so the
 * register matches the recorded results, then reports the stored records:
 * an executive overview counted from the records only, the framework's POA&M
 * rules with their citations, the open register, one card per open item,
 * the closed items and the change history. Owners, dates, resources and
 * milestones appear only as recorded; anything unset says so ("Not
 * assigned", "Not set", "None recorded"). Priority is the derived
 * remediation priority (or one set by management), never a risk rating.
 *
 * The document is not part of the Final approval: it carries no approval
 * rows and its status reads "Living document" with the date it was built.
 */
class ReportPoam extends ReportBuilder
{
    private $data = null;

    protected function title(): string
    {
        return 'Plan of Action and Milestones';
    }

    protected function doc_options(): array
    {
        return array(
            'toc' => true,
            'numbered' => true
        );
    }

    protected function report_status_text(): string
    {
        return 'Living document, as of '.date('F j, Y', strtotime((string) $this->options['generated']));
    }

    protected function document_control(): array
    {
        $control = parent::document_control();
        $control['report_status'] = 'Living document';
        $control['changed_since_final'] = false;
        $control['approved_by'] = '';
        $control['approved_date'] = null;

        return $control;
    }

    /**
     * Loads the register before anything else is built, so the cover, the
     * document control and every section show the same records and the same
     * "as of" moment.
     */
    public function build(): array
    {
        if ($this->a !== null) {
            $this->data();
        }

        return parent::build();
    }

    /**
     * The synced register, loaded once per build. After the sync commits,
     * every record, milestone, evidence link and history row is read in one
     * read-only consistent snapshot, and the report's "as of" time is the
     * moment that snapshot was taken; edits saved while the report builds
     * are not half included.
     */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $assessment_id = (int) $this->a['assessment']['id'];
        $model = new PoamModel();
        $sync = Poam::sync($assessment_id, $this->company_id, 0, $this->a);
        $rules = Poam::framework_rules((string) $this->a['assessment']['framework_key'], $this->a['definition'] ?? null);

        $model->db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');

        try {
            $this->options['generated'] = date('Y-m-d H:i:s');
            $clock = $model->clock_date($assessment_id, $this->company_id);
            $rows = $model->load_items($assessment_id, $this->company_id);
            $milestones = $model->load_milestones(0, $assessment_id, $this->company_id);
            $evidence = $model->load_evidence(0, $assessment_id, $this->company_id, true);
            $history = $model->load_history(0, $assessment_id, $this->company_id);
            $model->db->exec('COMMIT');
        } catch (Throwable $e) {
            $model->db->exec('ROLLBACK');
            throw $e;
        }

        $today = date('Y-m-d', strtotime((string) $this->options['generated']));
        $items = array();

        foreach ($rows as $row) {
            $items[] = Poam::present($row, $rules, $clock, $today);
        }

        $by_item = array();
        $evidence_by_item = array();

        foreach ($milestones as $milestone) {
            $by_item[(int) $milestone['poam_item_id']][] = $milestone;
        }

        foreach ($evidence as $link) {
            $evidence_by_item[(int) $link['poam_item_id']][] = $link;
        }

        $refs = array();
        $successor = array();
        foreach ($items as $item) {
            $refs[(int) $item['id']] = $item['poam_ref'];
            if (!empty($item['reopened_from_id'])) {
                $successor[(int) $item['reopened_from_id']] = $item['poam_ref'];
            }
        }

        if (count($items) > 200 && PHP_SAPI !== 'cli') {
            set_time_limit(180 + count($items));
        }

        $this->data = array(
            'sync' => $sync,
            'rules' => $rules,
            'clock' => $clock,
            'today' => $today,
            'items' => $items,
            'open' => array_values(array_filter($items, function ($item) {
                return $item['poam_status'] !== 'Closed';
            })),
            'closed' => array_values(array_filter($items, function ($item) {
                return $item['poam_status'] === 'Closed';
            })),
            'milestones' => $milestones,
            'milestones_of' => $by_item,
            'evidence_of' => $evidence_by_item,
            'successor' => $successor,
            'history' => $history,
            'overview' => Poam::overview($items, $milestones, $today)
        );

        return $this->data;
    }

    protected function blocks(): array
    {
        $d = $this->data();

        if (isset($d['sync']['error'])) {
            return array(
                self::h1('Executive overview'),
                self::text('The POA&M register could not be brought up to date: '.$d['sync']['error'].'.')
            );
        }

        $blocks = array_merge(
            $this->overview(),
            $this->framework_section(),
            $this->register()
        );

        if ($this->detail() !== 'executive') {
            $blocks = array_merge($blocks, $this->cards());
        }

        $blocks = array_merge($blocks, $this->closed_section());

        if (!empty($this->options['appendices'])) {
            $blocks = array_merge($blocks, $this->history_section());
        }

        return $blocks;
    }

    /* ----------------------------------------------------------- sections */

    private function overview(): array
    {
        $d = $this->data();
        $o = $d['overview'];
        $rules = $d['rules'];
        $blocks = array(
            self::h1('Executive overview')
        );

        if ($rules['banner'] !== null) {
            $blocks[] = array(
                'kind' => 'panel',
                'value' => array(
                    'tone' => 'critical',
                    'title' => 'Internal tracking only',
                    'body' => $rules['banner']
                )
            );
        }

        $subject = $this->a['client'] !== '' ? $this->a['client'] : 'the organization';
        $text = 'This Plan of Action and Milestones tracks the remediation of the '.$this->term('controls').' found not fully implemented in '.$this->a['narratives']['subject'].'. It is a living register: it shows the position as of '.date('F j, Y', strtotime($d['today'])).' and is kept in step with the recorded results, but the owners, dates, resources and milestones in it are entered by '.$subject.' and its assessor and are shown only as recorded.';

        if ($o['total'] === 0) {
            $text .= "\n\n".'No '.$this->term('control').' is currently recorded as not implemented or partially implemented, so the register holds no items.';
            if ($this->a['counts']['Not Assessed'] > 0) {
                $text .= ' '.$this->a['counts']['Not Assessed'].' of '.$this->a['total'].' '.$this->term('controls').' have not been assessed yet and may add items once they are.';
            }
            $blocks[] = self::text($text);
            return $blocks;
        }

        $lines = array();
        $lines[] = $o['open'].' open '.($o['open'] === 1 ? 'item' : 'items').' and '.$o['closed'].' closed.';
        $lines[] = $o['scheduled'] === 0
            ? 'No open item has a scheduled completion date, so none can be counted as overdue.'
            : $o['overdue'].' of the '.$o['scheduled'].' open '.($o['scheduled'] === 1 ? 'item' : 'items').' with a scheduled completion date '.($o['overdue'] === 1 ? 'is' : 'are').' overdue; '.($o['open'] - $o['scheduled']).' '.($o['open'] - $o['scheduled'] === 1 ? 'has' : 'have').' no date set.';
        $lines[] = $o['unassigned'].' open '.($o['unassigned'] === 1 ? 'item has' : 'items have').' no owner assigned.';
        $lines[] = count($o['upcoming_milestones']).' '.(count($o['upcoming_milestones']) === 1 ? 'milestone is' : 'milestones are').' due in the next '.Poam::UPCOMING_DAYS.' days and '.count($o['overdue_milestones']).' '.(count($o['overdue_milestones']) === 1 ? 'is' : 'are').' past due.';
        if ($o['resolved'] > 0) {
            $lines[] = $o['resolved'].' open '.($o['resolved'] === 1 ? 'item is' : 'items are').' now recorded as no longer deficient in the assessment, but closure has not been confirmed yet.';
        }
        if ($o['ready'] > 0) {
            $lines[] = $o['ready'].' '.($o['ready'] === 1 ? 'item is' : 'items are').' ready for validation.';
        }

        $blocks[] = self::text($text."\n\n".self::bullets($lines));
        $blocks[] = array(
            'kind' => 'metrics',
            'value' => array(
                'items' => array(
                    array(
                        'value' => (string) $o['open'],
                        'label' => 'Open items',
                        'sub' => $o['closed'].' closed'
                    ),
                    array(
                        'value' => $o['scheduled'] === 0 ? 'None dated' : (string) $o['overdue'],
                        'label' => 'Overdue items',
                        'sub' => $o['scheduled'].' of '.$o['open'].' with a scheduled date',
                        'tone' => $o['overdue'] > 0 ? 'critical' : 'neutral'
                    ),
                    array(
                        'value' => (string) count($o['upcoming_milestones']),
                        'label' => 'Milestones due in '.Poam::UPCOMING_DAYS.' days',
                        'sub' => count($o['overdue_milestones']).' past due'
                    ),
                    array(
                        'value' => (string) $o['unassigned'],
                        'label' => 'Open items without an owner',
                        'sub' => ($o['open'] - $o['unassigned']).' assigned',
                        'tone' => $o['unassigned'] > 0 ? 'warning' : 'positive'
                    )
                )
            )
        );

        $rows = array();
        foreach ($o['priorities'] as $priority => $counts) {
            if ($counts['total'] === 0) {
                continue;
            }
            $rows[] = array(
                $priority !== '' ? $priority : Poam::NOT_SET,
                (string) $counts['total'],
                (string) ($counts['total'] - $counts['in_progress'] - $counts['ready']),
                (string) $counts['in_progress'],
                (string) $counts['ready'],
                $counts['milestones_total'] > 0 ? $counts['milestones_done'].' of '.$counts['milestones_total'] : Poam::NONE_RECORDED
            );
        }

        $blocks[] = self::h2('Progress by remediation priority');
        $blocks[] = self::text('Open items by remediation priority. The priority is derived from the recorded results (or set by management where marked on the item); it is a planning aid, not a risk rating.');
        $blocks[] = self::table(array(
            'Remediation priority (derived)',
            'Open',
            'Open, not started',
            'In progress',
            'Ready for validation',
            'Milestones completed'
        ), array(
            22,
            10,
            16,
            14,
            18,
            20
        ), $rows);

        $upcoming = array_merge($o['overdue_milestones'], $o['upcoming_milestones']);
        if (count($upcoming) > 0) {
            usort($upcoming, function ($x, $y) {
                return strcmp((string) $x['due_date'], (string) $y['due_date']);
            });
            $rows = array();
            foreach ($upcoming as $milestone) {
                $rows[] = array(
                    Poam::date_label($milestone['due_date']),
                    $milestone['poam_ref'],
                    $milestone['source_ref'],
                    (string) $milestone['milestone_text'],
                    $milestone['due_date'] < $d['today'] ? 'Past due' : $milestone['milestone_status']
                );
            }
            $blocks[] = self::h2('Milestones past due or due in the next '.Poam::UPCOMING_DAYS.' days');
            $blocks[] = self::table(array(
                'Due',
                'POA&M ID',
                ucfirst($this->term('control')),
                'Milestone',
                'Status'
            ), array(
                13,
                16,
                14,
                43,
                14
            ), $rows);
        }

        return $blocks;
    }

    private function framework_section(): array
    {
        $d = $this->data();
        $rules = $d['rules'];
        $blocks = array(
            self::h1('Framework rules and eligibility'),
            self::text($rules['statement'])
        );

        if (count($rules['rules']) > 0) {
            $rows = array();
            foreach ($rules['rules'] as $rule) {
                $rows[] = array(
                    $rule['summary'],
                    $rule['cite'].($rule['source'] !== '' ? "\n".$rule['source'] : ''),
                    $rule['quote'] !== '' ? '"'.$rule['quote'].'"' : ''
                );
            }
            $blocks[] = self::table(array(
                'Rule',
                'Citation',
                'Source text'
            ), array(
                40,
                25,
                35
            ), $rows);
        }

        if ($rules['profile'] === 'conditional') {
            $eligible = count(array_filter($d['open'], function ($item) {
                return $item['source_eligibility'] === 'eligible';
            }));
            $text = '';
            if ($rules['closeout_days'] !== null) {
                $text = ($d['clock'] !== null
                    ? 'Conditional status date: '.Poam::date_label($d['clock']).'. Every open item must be closed and confirmed by a POA&M closeout assessment by '.Poam::date_label(date('Y-m-d', strtotime($d['clock'].' +'.(int) $rules['closeout_days'].' days'))).'.'
                    : 'Conditional status date: not recorded. The '.(int) $rules['closeout_days'].'-day closeout deadline cannot be calculated until it is recorded.')."\n\n";
            }
            $text .= $eligible.' of '.count($d['open']).' open '.(count($d['open']) === 1 ? 'item is' : 'items are').' eligible for the POA&M; '.(count($d['open']) - $eligible).' must be MET at the assessment for Conditional status.';
            $blocks[] = self::text($text);
            $rows = array();
            foreach ($d['open'] as $item) {
                $rows[] = array(
                    $item['poam_ref'],
                    $item['source_ref'],
                    $item['source_points'] !== null ? (string) $item['source_points'] : '',
                    $item['eligibility_label'],
                    $item['source_eligibility_note']
                );
            }
            if (count($rows) > 0) {
                $blocks[] = self::h2('Eligibility of each open item');
                $blocks[] = self::table(array(
                    'POA&M ID',
                    ucfirst($this->term('control')),
                    'Points',
                    'Eligibility',
                    'Basis'
                ), array(
                    15,
                    16,
                    8,
                    22,
                    39
                ), $rows);
            }
        }

        if ($rules['profile'] === 'sprs') {
            $points = 0;
            foreach ($d['open'] as $item) {
                $points += (int) ($item['source_points'] ?? 0);
            }
            $blocks[] = self::text('The open items account for '.$points.' '.($points === 1 ? 'point' : 'points').' of SPRS deductions. Each item card shows its own deduction.');
        }

        if ($rules['profile'] === 'fedramp') {
            $rows = array();
            foreach ($rules['days_by_risk'] as $risk => $days) {
                $rows[] = array(
                    ucfirst((string) $risk),
                    $days.' days from discovery'
                );
            }
            if (count($rows) > 0) {
                $blocks[] = self::table(array(
                    'Risk rating',
                    'Remediation timeframe (FedRAMP, legacy POA&M guidance)'
                ), array(
                    40,
                    60
                ), $rows);
            }
            $rated = count(array_filter($d['open'], function ($item) {
                return $item['fedramp_deadline'] !== null;
            }));
            $blocks[] = self::text($rated.' of '.count($d['open']).' open items have both a risk rating and a detection date recorded, so a derived deadline can be shown for them. The remediation priority in this register is an internal recommendation, not a FedRAMP risk rating, and sets no FedRAMP deadline.');
        }

        if ($rules['profile'] === 'corrective_action') {
            $blocks[] = self::text('Period agreed with the competent authority: not recorded.');
        }

        return $blocks;
    }

    private function register(): array
    {
        $d = $this->data();
        $blocks = array(
            self::h1('Open register')
        );

        if (count($d['open']) === 0) {
            $blocks[] = self::text('There are no open items.');
            return $blocks;
        }

        $rows = array();
        $anchors = array();
        foreach ($d['open'] as $item) {
            $rows[] = array(
                $item['poam_ref'],
                $item['source_ref'].' '.$item['source_title'],
                $item['priority_label'],
                $item['owner_label'],
                $item['scheduled_label'].($item['overdue'] ? ' (overdue)' : ''),
                $item['poam_status'].($item['resolved_label'] !== '' ? ' (assessment records '.$item['source_result'].')' : '')
            );
            $anchors[] = 'poam-'.$item['poam_ref'];
        }

        $table = self::table(array(
            'POA&M ID',
            ucfirst($this->term('control')),
            'Remediation priority',
            'Responsible owner',
            'Scheduled completion',
            'Status'
        ), array(
            14,
            34,
            14,
            13,
            12,
            13
        ), $rows);

        if ($this->detail() !== 'executive') {
            $table['kind'] = 'register';
            $table['value']['anchors'] = $anchors;
        }

        $blocks[] = self::text('Every open item, in the order it was recorded. Remediation priority is derived from the recorded results unless marked as set by management.'.($this->detail() !== 'executive' ? ' Select a POA&M ID to go to the item.' : ''));
        $blocks[] = $table;

        return $blocks;
    }

    private function cards(): array
    {
        $d = $this->data();

        if (count($d['open']) === 0) {
            return array();
        }

        $blocks = array(
            self::h1('Open items'),
            self::text('One card per open item. An item is closed only when the assessor confirms closure with a closure note and closure evidence, or a stated reason why there is none; closing an item whose '.$this->term('control').' is not recorded Implemented in the assessment also needs an explicit override, which is shown with the closed item.')
        );

        foreach ($d['open'] as $item) {
            $blocks[] = $this->card($item);
        }

        return $blocks;
    }

    private function card(array $item): array
    {
        $d = $this->data();
        $rules = $d['rules'];
        $rows = array(
            array(
                'Assessment result',
                ($item['resolved_label'] !== '' ? $item['resolved_label'] : $item['source_result']).($item['source_family'] !== '' ? "\n".ucfirst($this->term('family')).': '.$item['source_family'] : '')
            ),
            array(
                'Weakness'.($item['weakness_overridden'] ? ' (as edited)' : ''),
                $item['weakness']
            ),
            array(
                'Corrective action'.($item['action_overridden'] ? ' (as edited)' : ''),
                $item['action']
            ),
            array(
                'Remediation priority',
                $item['priority_label'].((string) ($item['priority_override'] ?? '') === '' && $item['source_priority_basis'] !== '' ? "\n".$item['source_priority_basis'] : '')
            )
        );

        if ($item['source_initiative_ref'] !== '') {
            $rows[] = array(
                'Remediation initiative',
                $item['source_initiative_ref'].' '.$item['source_initiative_title']
            );
        }

        if ($item['eligibility_label'] !== '' || $item['source_eligibility_note'] !== '') {
            $rows[] = array(
                'Framework eligibility',
                trim($item['eligibility_label']."\n".$item['source_eligibility_note'])
            );
        }

        if ($rules['profile'] === 'conditional') {
            $rows[] = array(
                'Closeout deadline',
                $item['closeout_date'] !== null ? Poam::date_label($item['closeout_date']) : 'Not recorded (no Conditional status date)'
            );
        }

        if ($rules['profile'] === 'fedramp') {
            $rows[] = array(
                'Risk rating',
                (string) $item['risk_rating'] !== '' ? $item['risk_rating'] : Poam::NOT_SET
            );
            $rows[] = array(
                'Derived deadline',
                $item['fedramp_deadline'] !== null ? Poam::date_label($item['fedramp_deadline']) : 'Not set (needs a risk rating and a detection date)'
            );
            $rows[] = array(
                'Vendor dependency',
                (int) $item['vendor_dependency'] === 1 ? 'Yes'.((string) $item['vendor_product'] !== '' ? ': '.$item['vendor_product'] : '').'; last check-in '.Poam::date_label($item['vendor_checkin_date']) : 'None recorded'
            );
            $rows[] = array(
                'Deviation request',
                (string) $item['deviation_type'] !== '' ? $item['deviation_type'].((string) $item['deviation_rationale'] !== '' ? ': '.$item['deviation_rationale'] : '') : 'None recorded'
            );
        }

        $rows[] = array(
            'Owner and resources',
            'Responsible owner: '.$item['owner_label']."\nResources required: ".$item['resources_label']
        );
        $rows[] = array(
            'Dates',
            'Detected: '.$item['detection_label']."\nScheduled completion: ".$item['scheduled_label'].($item['overdue'] ? ' (overdue)' : '')."\nCompleted: ".$item['completion_label']
        );

        $milestones = array();
        foreach ($d['milestones_of'][(int) $item['id']] ?? array() as $milestone) {
            $milestones[] = $milestone['milestone_text'].' (due '.Poam::date_label($milestone['due_date']).'; '.($milestone['milestone_status'] === 'Completed' ? 'completed '.Poam::date_label($milestone['completed_date']) : strtolower($milestone['milestone_status'])).')';
        }

        $evidence = array();
        foreach ($d['evidence_of'][(int) $item['id']] ?? array() as $link) {
            $evidence[] = $link['evidence_role'].': '.ReportAnalysis::plain($link['evidence_title']);
        }

        $progress = 'Milestones: '.(count($milestones) > 0 ? '' : Poam::NONE_RECORDED);
        if (count($milestones) > 0) {
            $progress .= "\n".self::bullets($milestones);
        }
        $progress .= "\n".'Evidence: '.(count($evidence) > 0 ? '' : Poam::NONE_RECORDED);
        if (count($evidence) > 0) {
            $progress .= "\n".self::bullets($evidence);
        }
        $progress .= "\n".'Comments: '.$item['comments_label'];
        if (!empty($item['reopened_from_ref'])) {
            $progress .= "\n".'Reopened from '.$item['reopened_from_ref'];
        }

        $sections = array(
            array(
                'Milestones, evidence and comments',
                $progress
            )
        );

        return array(
            'kind' => 'finding',
            'anchor' => 'poam-'.$item['poam_ref'],
            'value' => array(
                'ref' => $item['poam_ref'],
                'heading' => $item['source_ref'].' '.$item['source_title'],
                'severity' => $item['priority'],
                'rating' => $item['priority'] !== '' ? $item['priority'].' remediation priority '.((string) ($item['priority_override'] ?? '') !== '' ? '(set by management)' : '(derived)') : '',
                'anchor' => 'poam-'.$item['poam_ref'],
                'meta' => array(
                    array(
                        'Status',
                        $item['poam_status']
                    ),
                    array(
                        'Responsible owner',
                        $item['owner_label']
                    ),
                    array(
                        'Scheduled',
                        $item['scheduled_label']
                    )
                ),
                'rows' => $rows,
                'sections' => $sections
            )
        );
    }

    private function closed_section(): array
    {
        $d = $this->data();
        $blocks = array(
            self::h1('Closed items')
        );

        if (count($d['closed']) === 0) {
            $blocks[] = self::text('No item has been closed yet.');
            return $blocks;
        }

        $rows = array();
        foreach ($d['closed'] as $item) {
            $basis = (string) ($item['closure_note'] ?? '');
            if ((string) ($item['closure_reason'] ?? '') !== '') {
                $basis .= "\nNo closure evidence: ".$item['closure_reason'];
            }
            if ((int) $item['closure_override'] === 1) {
                $basis .= "\nClosed with override: the assessment recorded ".$item['closure_result'].' at closure.';
            }
            $rows[] = array(
                $item['poam_ref'],
                $item['source_ref'].' '.$item['source_title'],
                Poam::date_label($item['date_closed']),
                $item['closed_by_name'] !== '' ? $item['closed_by_name'] : Poam::NOT_SET,
                $basis,
                $d['successor'][(int) $item['id']] ?? ''
            );
        }

        $blocks[] = self::text('Items whose closure has been confirmed, with the closure note. An item whose '.$this->term('control').' was later recorded as deficient again continues under a new POA&M ID.');
        $blocks[] = self::table(array(
            'POA&M ID',
            ucfirst($this->term('control')),
            'Closed',
            'Confirmed by',
            'Closure basis',
            'Reopened as'
        ), array(
            14,
            26,
            11,
            13,
            24,
            12
        ), $rows);

        return $blocks;
    }

    private function history_section(): array
    {
        $d = $this->data();
        $rows = array();

        foreach ($d['history'] as $entry) {
            $rows[] = array(
                date('M j, Y H:i', strtotime((string) $entry['date_created'])),
                (string) ($entry['poam_ref'] ?? '') !== '' ? $entry['poam_ref'] : 'Register',
                $entry['change_source'],
                self::change_text($entry),
                (int) $entry['changed_by'] === 0 ? 'System (sync)' : ((string) $entry['changed_by_name'] !== '' ? $entry['changed_by_name'] : 'User #'.$entry['changed_by'])
            );
        }

        return array(
            self::h1('Appendix: change history'),
            self::text('Every recorded change, oldest first: items created and refreshed by the sync with the assessment, edits, closures and reopenings. The Excel workbook\'s Change Log sheet holds the full old and new values.'),
            self::table(array(
                'When (UTC)',
                'POA&M ID',
                'Source',
                'Change',
                'By'
            ), array(
                15,
                15,
                9,
                47,
                14
            ), $rows)
        );
    }

    /** One history row in words; long snapshot text is summarised, the workbook holds it in full. */
    private static function change_text(array $entry): string
    {
        $field = (string) $entry['field_name'];
        $note = (string) $entry['change_note'];

        if ($field === 'poam_ref') {
            return 'Created '.$entry['new_value'].($note !== '' ? '. '.$note : '');
        }

        if ($field === 'reopened_as') {
            return 'Continued as '.$entry['new_value'].($note !== '' ? '. '.$note : '');
        }

        $long = in_array($field, array(
            'source_open_objectives',
            'source_weakness',
            'source_action',
            'source_priority_basis',
            'source_initiative_title',
            'source_eligibility_note',
            'weakness_override',
            'action_override',
            'resources',
            'comments',
            'closure_note',
            'closure_reason',
            'deviation_rationale'
        ), true);

        $label = ucfirst(str_replace('source ', 'assessment ', Poam::label($field)));

        if ($long) {
            return $label.' updated'.($note !== '' && $note !== 'Refreshed from the assessment.' ? '. '.$note : '');
        }

        if ($field === 'closed_by') {
            return 'Closure confirmed by '.((string) $entry['changed_by_name'] !== '' ? $entry['changed_by_name'] : 'user #'.$entry['new_value']);
        }

        $old = (string) ($entry['old_value'] ?? '');
        $new = (string) ($entry['new_value'] ?? '');

        if (in_array($field, array(
            'source_resolved',
            'closure_override',
            'vendor_dependency'
        ), true)) {
            $old = $old === '1' ? 'yes' : 'no';
            $new = $new === '1' ? 'yes' : 'no';
        }

        return $label.': '.($old !== '' ? $old : 'blank').' to '.($new !== '' ? $new : 'blank').($note !== '' ? '. '.$note : '');
    }

    /* -------------------------------------------------------------- excel */

    protected function sheets(): array
    {
        $d = $this->data();

        if (isset($d['sync']['error'])) {
            return array();
        }

        $rules = $d['rules'];
        $fedramp = $rules['profile'] === 'fedramp';
        $eligibility = in_array($rules['profile'], array(
            'conditional',
            'not_permitted',
            'sprs'
        ), true);

        $head = array(
            'POA&M ID',
            ucfirst($this->term('control')),
            ucfirst($this->term('control')).' title',
            ucfirst($this->term('family')),
            'Weakness',
            'Corrective action',
            'Remediation priority (derived unless marked)',
            'Priority basis',
            'Remediation initiative'
        );
        $widths = array(
            16,
            14,
            40,
            24,
            60,
            60,
            18,
            40,
            30
        );

        if ($eligibility) {
            $head[] = 'Framework eligibility';
            $head[] = 'Points';
            $widths[] = 40;
            $widths[] = 8;
        }

        if ($rules['profile'] === 'conditional') {
            $head[] = 'Closeout deadline';
            $widths[] = 16;
        }

        $head = array_merge($head, array(
            'Responsible owner',
            'Resources required',
            'Detection date',
            'Scheduled completion',
            'Completion date',
            'Status',
            'Overdue',
            'Milestones completed',
            'Evidence linked',
            'Assessment result',
            'Comments',
            'First recorded',
            'Last updated'
        ));
        $widths = array_merge($widths, array(
            20,
            30,
            14,
            14,
            14,
            18,
            10,
            14,
            12,
            30,
            40,
            14,
            18
        ));

        if ($fedramp) {
            $head = array_merge($head, array(
                'Original risk rating',
                'Derived deadline',
                'Vendor dependency',
                'Vendor product',
                'Last vendor check-in',
                'Deviation request',
                'Deviation rationale'
            ));
            $widths = array_merge($widths, array(
                14,
                14,
                12,
                24,
                16,
                22,
                40
            ));
        }

        $open_rows = array();
        foreach ($d['open'] as $item) {
            $open_rows[] = $this->item_row($item, $eligibility, $rules['profile'] === 'conditional', $fedramp);
        }

        $closed_rows = array();
        foreach ($d['closed'] as $item) {
            $closed_rows[] = array(
                $item['poam_ref'],
                $item['source_ref'],
                $item['source_title'],
                $item['weakness'],
                $item['owner_label'],
                $item['completion_label'],
                Poam::date_label($item['date_closed']),
                $item['closed_by_name'] !== '' ? $item['closed_by_name'] : Poam::NOT_SET,
                (string) ($item['closure_note'] ?? ''),
                (string) ($item['closure_reason'] ?? '') !== '' ? $item['closure_reason'] : Poam::NONE_RECORDED,
                (string) $item['closure_result'],
                (int) $item['closure_override'] === 1 ? 'Yes' : 'No',
                $d['successor'][(int) $item['id']] ?? ''
            );
        }

        $milestone_rows = array();
        foreach ($d['milestones'] as $milestone) {
            $milestone_rows[] = array(
                $milestone['poam_ref'],
                $milestone['source_ref'],
                (string) $milestone['milestone_text'],
                Poam::date_label($milestone['due_date']),
                Poam::date_label($milestone['completed_date']),
                $milestone['milestone_status'],
                $milestone['milestone_status'] !== 'Completed' && !empty($milestone['due_date']) && $milestone['due_date'] < $d['today'] ? 'Yes' : 'No'
            );
        }

        $log_rows = array();
        foreach ($d['history'] as $entry) {
            $log_rows[] = array(
                (string) $entry['date_created'],
                (string) ($entry['poam_ref'] ?? '') !== '' ? $entry['poam_ref'] : 'Register',
                $entry['change_source'],
                Poam::label((string) $entry['field_name']),
                (string) ($entry['old_value'] ?? ''),
                (string) ($entry['new_value'] ?? ''),
                (string) $entry['change_note'],
                (int) $entry['changed_by'] === 0 ? 'System (sync)' : ((string) $entry['changed_by_name'] !== '' ? $entry['changed_by_name'] : 'User #'.$entry['changed_by'])
            );
        }

        return array(
            array(
                'name' => 'Open Items',
                'head' => $head,
                'widths' => $widths,
                'rows' => $open_rows
            ),
            array(
                'name' => 'Closed Items',
                'head' => array(
                    'POA&M ID',
                    ucfirst($this->term('control')),
                    ucfirst($this->term('control')).' title',
                    'Weakness',
                    'Responsible owner',
                    'Completion date',
                    'Closed',
                    'Confirmed by',
                    'Closure note',
                    'Reason without closure evidence',
                    'Assessment result at closure',
                    'Closed with override',
                    'Reopened as'
                ),
                'widths' => array(
                    16,
                    14,
                    40,
                    60,
                    20,
                    14,
                    14,
                    20,
                    50,
                    40,
                    22,
                    12,
                    16
                ),
                'rows' => $closed_rows
            ),
            array(
                'name' => 'Milestones',
                'head' => array(
                    'POA&M ID',
                    ucfirst($this->term('control')),
                    'Milestone',
                    'Due',
                    'Completed',
                    'Status',
                    'Past due'
                ),
                'widths' => array(
                    16,
                    14,
                    60,
                    14,
                    14,
                    14,
                    10
                ),
                'rows' => $milestone_rows
            ),
            array(
                'name' => 'Change Log',
                'head' => array(
                    'When (UTC)',
                    'POA&M ID',
                    'Source',
                    'Field',
                    'Old value',
                    'New value',
                    'Note',
                    'By'
                ),
                'widths' => array(
                    20,
                    16,
                    10,
                    24,
                    50,
                    50,
                    40,
                    18
                ),
                'rows' => $log_rows
            )
        );
    }

    private function item_row(array $item, bool $eligibility, bool $conditional, bool $fedramp): array
    {
        $d = $this->data();
        $milestones = $d['milestones_of'][(int) $item['id']] ?? array();
        $done = count(array_filter($milestones, function ($milestone) {
            return $milestone['milestone_status'] === 'Completed';
        }));

        $row = array(
            $item['poam_ref'],
            $item['source_ref'],
            $item['source_title'],
            $item['source_family'],
            $item['weakness'],
            $item['action'],
            $item['priority_label'],
            (string) $item['source_priority_basis'],
            trim($item['source_initiative_ref'].' '.$item['source_initiative_title'])
        );

        if ($eligibility) {
            $row[] = trim($item['eligibility_label'].' '.$item['source_eligibility_note']);
            $row[] = $item['source_points'] !== null ? (int) $item['source_points'] : '';
        }

        if ($conditional) {
            $row[] = $item['closeout_date'] !== null ? Poam::date_label($item['closeout_date']) : 'Not recorded';
        }

        $row = array_merge($row, array(
            $item['owner_label'],
            $item['resources_label'],
            $item['detection_label'],
            $item['scheduled_label'],
            $item['completion_label'],
            $item['poam_status'],
            $item['overdue'] ? 'Yes' : 'No',
            count($milestones) > 0 ? $done.' of '.count($milestones) : Poam::NONE_RECORDED,
            count($d['evidence_of'][(int) $item['id']] ?? array()),
            $item['resolved_label'] !== '' ? $item['resolved_label'] : $item['source_result'],
            $item['comments_label'],
            Poam::date_label($item['date_created']),
            (string) $item['date_updated']
        ));

        if ($fedramp) {
            $row = array_merge($row, array(
                (string) $item['risk_rating'] !== '' ? $item['risk_rating'] : Poam::NOT_SET,
                $item['fedramp_deadline'] !== null ? Poam::date_label($item['fedramp_deadline']) : Poam::NOT_SET,
                (int) $item['vendor_dependency'] === 1 ? 'Yes' : 'No',
                (string) $item['vendor_product'] !== '' ? $item['vendor_product'] : Poam::NONE_RECORDED,
                Poam::date_label($item['vendor_checkin_date']),
                (string) $item['deviation_type'] !== '' ? $item['deviation_type'] : Poam::NONE_RECORDED,
                (string) ($item['deviation_rationale'] ?? '') !== '' ? $item['deviation_rationale'] : Poam::NONE_RECORDED
            ));
        }

        return $row;
    }
}
