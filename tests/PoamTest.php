<?php
/**
 * The POA&M's pure logic, without the database: the sync plan is idempotent,
 * keeps management fields, never reuses a reference and never closes an
 * item by itself; the closure gate; portal limits; and every framework
 * definition maps to a POA&M rule profile backed by its own rule data.
 */
require_once ROOT.'/libs/Classes/FrameworkLibrary.php';
require_once ROOT.'/libs/Classes/Poam.php';

$snapshot = function (string $ref, string $result, array $fields = array()) {
    return array_merge(array(
        'source_item_id' => 100,
        'source_ref' => $ref,
        'source_title' => 'Limit system access',
        'source_family' => 'Access Control',
        'source_result' => $result,
        'source_open_objectives' => '[]',
        'source_weakness' => 'Control '.$ref.' is recorded as '.strtolower($result).'.',
        'source_action' => 'Bring to the expected condition.',
        'source_priority' => 'Medium',
        'source_priority_basis' => 'Derived from the recorded result.',
        'source_initiative_ref' => 'R-01',
        'source_initiative_title' => 'Access reviews',
        'source_eligibility' => '',
        'source_eligibility_note' => '',
        'source_points' => null,
        'updated' => '2026-11-01 10:00:00'
    ), $fields);
};

/** Applies plan_sync operations to an in-memory record list, the way PoamModel::sync_items does. */
$apply = function (array $records, array $ops) {
    $seq = 0;
    foreach ($records as $record) {
        $seq = max($seq, (int) $record['poam_seq']);
    }
    foreach ($ops as $op) {
        if ($op['op'] === 'insert') {
            $seq++;
            $record = array(
                'id' => 1000 + $seq,
                'poam_seq' => $seq,
                'poam_ref' => Poam::ref(7, $seq),
                'poam_status' => 'Open',
                'source_resolved' => 0,
                'owner_name' => '',
                'scheduled_completion_date' => null,
                'date_closed' => null,
                'reopened_from_id' => $op['reopened_from'] === null ? null : $op['reopened_from']['id']
            );
            foreach (Poam::SNAPSHOT_FIELDS as $field) {
                $record[$field] = $op['snapshot'][$field];
            }
            $records[] = $record;
            continue;
        }
        foreach ($records as $index => $record) {
            if ($record['id'] === $op['record']['id']) {
                $records[$index] = array_merge($record, $op['set']);
            }
        }
    }
    return $records;
};

return array(
    'sync is idempotent: a second plan over the result of the first does nothing' => function () use ($snapshot, $apply) {
        $snapshots = array(
            'AC-1' => $snapshot('AC-1', 'Not Implemented'),
            'AC-2' => $snapshot('AC-2', 'Partially Implemented')
        );
        $results = array(
            'AC-1' => 'Not Implemented',
            'AC-2' => 'Partially Implemented'
        );
        $first = Poam::plan_sync($snapshots, array(), $results);
        Assert::same(2, count($first), 'two new records');
        $records = $apply(array(), $first);
        Assert::same(array(), Poam::plan_sync($snapshots, $records, $results), 'nothing to do the second time');
        Assert::same(array(
            'POAM-7-001',
            'POAM-7-002'
        ), array_column($records, 'poam_ref'), 'references are POAM-{assessment}-{seq:03}');
    },

    'management edits survive a re-sync; only the source snapshot is refreshed' => function () use ($snapshot, $apply) {
        $records = $apply(array(), Poam::plan_sync(array(
            'AC-1' => $snapshot('AC-1', 'Not Implemented')
        ), array(), array()));
        $records[0]['owner_name'] = 'J. Smith';
        $records[0]['scheduled_completion_date'] = '2026-12-31';
        $records[0]['poam_status'] = 'In Progress';
        $changed = $snapshot('AC-1', 'Partially Implemented', array(
            'source_priority' => 'Low'
        ));
        $ops = Poam::plan_sync(array(
            'AC-1' => $changed
        ), $records, array());
        Assert::same(1, count($ops), 'one refresh');
        foreach (array_keys($ops[0]['set']) as $field) {
            Assert::true(strpos($field, 'source_') === 0, $field.' is a source field');
        }
        $after = $apply($records, $ops)[0];
        Assert::same('J. Smith', $after['owner_name'], 'owner kept');
        Assert::same('2026-12-31', $after['scheduled_completion_date'], 'date kept');
        Assert::same('In Progress', $after['poam_status'], 'status kept');
        Assert::same('Partially Implemented', $after['source_result'], 'snapshot refreshed');
    },

    'a resolved control is flagged, never closed or deleted' => function () use ($snapshot, $apply) {
        $records = $apply(array(), Poam::plan_sync(array(
            'AC-1' => $snapshot('AC-1', 'Not Implemented')
        ), array(), array()));
        $ops = Poam::plan_sync(array(), $records, array(
            'AC-1' => 'Implemented'
        ));
        Assert::same(1, count($ops), 'one flag');
        Assert::same('update', $ops[0]['op'], 'an update, not a delete');
        Assert::true(!isset($ops[0]['set']['poam_status']), 'status untouched');
        Assert::same(1, $ops[0]['set']['source_resolved'], 'flagged as resolved');
        $records = $apply($records, $ops);
        Assert::same('Open', $records[0]['poam_status'], 'still open');
        Assert::same(array(), Poam::plan_sync(array(), $records, array(
            'AC-1' => 'Implemented'
        )), 'flagging is idempotent');
    },

    'references are never reused: a control deficient again after closure gets a new id' => function () use ($snapshot, $apply) {
        $records = $apply(array(), Poam::plan_sync(array(
            'AC-1' => $snapshot('AC-1', 'Not Implemented'),
            'AC-2' => $snapshot('AC-2', 'Not Implemented')
        ), array(), array()));
        $records[0]['poam_status'] = 'Closed';
        $records[0]['date_closed'] = '2026-11-02 09:00:00';

        Assert::same(array(), Poam::plan_sync(array(
            'AC-1' => $snapshot('AC-1', 'Not Implemented'),
            'AC-2' => $snapshot('AC-2', 'Not Implemented')
        ), $records, array()), 'a closure with override is not undone while the control is unchanged');

        $ops = Poam::plan_sync(array(
            'AC-1' => $snapshot('AC-1', 'Partially Implemented', array(
                'updated' => '2026-11-03 09:00:00'
            )),
            'AC-2' => $snapshot('AC-2', 'Not Implemented')
        ), $records, array());
        Assert::same(1, count($ops), 'one reopen');
        Assert::same('Reopen', $ops[0]['change_source'], 'logged as a reopen');
        $records = $apply($records, $ops);
        Assert::same('POAM-7-003', $records[2]['poam_ref'], 'next number, not the closed one');
        Assert::same($records[0]['id'], $records[2]['reopened_from_id'], 'linked to the closed record');
        Assert::same('Closed', $records[0]['poam_status'], 'closed record kept');

        $model = (string) file_get_contents(ROOT.'/app/models/PoamModel.php');
        Assert::true(strpos($model, 'SELECT COALESCE(MAX(poam_seq), 0) + 1 AS next_seq FROM poam_items WHERE assessment_id = :assessment_id")') !== false, 'the next number counts every row ever written, deleted or not');
        Assert::true(strpos($model, 'FOR UPDATE') !== false && strpos($model, 'beginTransaction') !== false, 'numbers are allocated under a lock in a transaction');
        Assert::true(preg_match('/(UPDATE|DELETE FROM)\s+poam_history|update\(\'poam_history\'|delete(_all)?\(\'poam_history\'/', $model) === 0, 'history is append-only');
        Assert::true(preg_match('/delete(_all)?\(\'poam_items\'|DELETE FROM poam_items/', $model) === 0, 'records are never deleted');
    },

    'closure needs a note, evidence or a reason, and an override when not Implemented' => function () {
        Assert::same('Enter a closure note', Poam::closure_error('staff', 'Ready for Validation', '', 1, '', false, 'Implemented'), 'note required');
        Assert::same('Link closure evidence, or state why there is none', Poam::closure_error('staff', 'Open', 'Fixed', 0, '', false, 'Implemented'), 'evidence or reason required');
        Assert::same('', Poam::closure_error('staff', 'Open', 'Fixed', 1, '', false, 'Implemented'), 'note and evidence close an Implemented control');
        Assert::same('', Poam::closure_error('staff', 'Open', 'Fixed', 0, 'Verified on site', false, 'Implemented'), 'a stated reason stands in for evidence');
        Assert::true(strpos(Poam::closure_error('staff', 'Open', 'Fixed', 1, '', false, 'Partially Implemented'), 'Acknowledge the override') !== false, 'override required when not Implemented');
        Assert::true(Poam::closure_error('staff', 'Open', 'Fixed', 1, '', false, 'Not Applicable') !== '', 'Not Applicable is not Implemented either');
        Assert::same('', Poam::closure_error('staff', 'Open', 'Fixed', 1, '', true, 'Partially Implemented'), 'override acknowledged');
        Assert::same('This item is already closed', Poam::closure_error('staff', 'Closed', 'Fixed', 1, '', true, 'Implemented'), 'no double closure');
    },

    'no edit can set Closed' => function () {
        Assert::true(isset(Poam::clean_field('poam_status', 'Closed', '2026-11-05', false)['error']), 'staff cannot pick Closed');
        Assert::true(isset(Poam::clean_field('poam_status', 'Closed', '2026-11-05', true)['error']), 'portal cannot pick Closed');
        Assert::same(array(
            'value' => 'Ready for Validation'
        ), Poam::clean_field('poam_status', 'Ready for Validation', '2026-11-05', false), 'other statuses are fine');
        Assert::true(!in_array('Closed', Poam::EDITABLE_STATUSES, true), 'Closed is not an editable status');
        Assert::true(isset(Poam::clean_field('completion_date', '2026-12-01', '2026-11-05', false)['error']), 'a completion date cannot be in the future');
        Assert::true(Poam::date_order_error('2026-11-10', '2026-11-01') !== '', 'scheduled completion cannot precede detection');
    },

    'portal users cannot close or reopen, and edit only their fields' => function () {
        Assert::same('Only your assessor can confirm closure', Poam::closure_error('portal', 'Open', 'Fixed', 1, '', true, 'Implemented'), 'closure refused for portal');
        foreach (Poam::PORTAL_FIELDS as $field) {
            Assert::true(!isset(Poam::clean_field($field, '', '2026-11-05', true)['error']) || $field === 'poam_status', $field.' is open to portal users');
        }
        foreach (array(
            'weakness_override',
            'priority_override',
            'scheduled_completion_date',
            'risk_rating',
            'deviation_type'
        ) as $field) {
            Assert::true(isset(Poam::clean_field($field, '', '2026-11-05', true)['error']), $field.' is refused for portal users');
        }

        $api = (string) file_get_contents(ROOT.'/app/controllers/ApiController.php');
        foreach (array(
            'poam_item_closeAction',
            'poam_item_reopenAction'
        ) as $action) {
            $start = strpos($api, 'public function '.$action.'(');
            Assert::true($start !== false, $action.' exists');
            $body = substr($api, $start, strpos($api, 'public function', $start + 20) - $start);
            Assert::true(strpos($body, '$this->poam_staff_item(') !== false, $action.' is staff only');
        }
        $guard = substr($api, strpos($api, 'private function poam_staff_item('), 400);
        Assert::true(strpos($guard, "Session::get('user_type') !== 'staff'") !== false, 'the staff guard checks the session user type');
        Assert::true(preg_match('/function portal_poam_\w*(close|reopen)/', $api) === 0, 'there is no portal close or reopen');
        Assert::true(strpos($api, '$_GET') === false, 'no $_GET');
    },

    'every framework definition maps to a POA&M rule profile backed by its rule data' => function () {
        $definitions = FrameworkLibrary::all();
        Assert::true(count($definitions) >= 19, 'the framework definitions load');

        foreach ($definitions as $key => $definition) {
            Assert::true(isset(Poam::PROFILES[$key]), $key.' has a profile');
            $rules = Poam::framework_rules($key, $definition);
            $types = array_count_values(array_column($rules['rules'], 'type'));
            $profile = $rules['profile'];
            Assert::true($rules['statement'] !== '', $key.' has a statement');

            if ($profile === 'not_permitted') {
                Assert::true(($rules['rules'][0]['raw']['permitted'] ?? true) === false, $key.' deferral forbids a POA&M');
                Assert::true($rules['banner'] !== null && $rules['internal_only'], $key.' shows the internal-tracking banner');
            }
            if ($profile === 'conditional') {
                Assert::true(isset($types['deferral']) && $rules['closeout_days'] === 180, $key.' has a deferral rule and a 180-day closeout');
            }
            if ($profile === 'sprs') {
                Assert::true(isset($types['plan_handling']), $key.' has its plan handling rule');
            }
            if ($profile === 'fedramp') {
                Assert::same(30, (int) $rules['days_by_risk']['high'], $key.' high risks within 30 days');
                Assert::true(isset($types['plan_handling']), $key.' operational requirement rule');
            }
            if ($profile === 'corrective_action') {
                Assert::true(isset($types['plan_handling'], $types['deadline']), $key.' has a corrective action plan and an agreed period');
            }
            if ($profile === 'plan_handling') {
                Assert::true(isset($types['plan_handling']), $key.' cites its plan handling rule');
            }
            if ($profile === 'none') {
                Assert::true(!isset($types['deferral']) && !isset($types['plan_handling']), $key.' has no POA&M rule, so no mandated format');
            }
            foreach ($rules['rules'] as $rule) {
                Assert::true($rule['cite'] !== '', $key.' '.$rule['id'].' carries a citation');
            }
        }

        foreach (array_keys(Poam::PROFILES) as $key) {
            Assert::true(isset($definitions[$key]), $key.' is a real framework key');
        }
        Assert::same('none', Poam::profile(''), 'a custom standard gets the generic tracker');
    },

    'presentation marks unset fields and never calls priority a risk rating' => function () {
        $rules = Poam::framework_rules('soc2', FrameworkLibrary::get('soc2'));
        $item = Poam::present(array(
            'id' => 1,
            'poam_status' => 'Open',
            'source_priority' => 'High',
            'priority_override' => '',
            'source_weakness' => 'W',
            'source_action' => 'A',
            'source_result' => 'Not Implemented',
            'source_resolved' => 0,
            'source_eligibility' => '',
            'source_open_objectives' => '[]',
            'owner_name' => '',
            'resources' => null,
            'comments' => null,
            'detection_date' => null,
            'scheduled_completion_date' => null,
            'completion_date' => null
        ), $rules, null, '2026-11-05');
        Assert::same('Not assigned', $item['owner_label'], 'owner marker');
        Assert::same('Not set', $item['scheduled_label'], 'date marker');
        Assert::same('None recorded', $item['comments_label'], 'comments marker');
        Assert::same('High (derived)', $item['priority_label'], 'derived priority');
        Assert::same(false, $item['overdue'], 'no date, never overdue');
        $source = (string) file_get_contents(ROOT.'/libs/Classes/ReportPoam.php');
        Assert::true(strpos($source, 'remediation priority (derived)') !== false || strpos($source, "' remediation priority '") !== false, 'cards say remediation priority (derived)');
        Assert::true(stripos($source, 'more\'') === false && stripos($source, 'and others') === false, 'no truncated reference lists');
    },

    'unchanged findings never duplicate across repeated syncs' => function () use ($snapshot, $apply) {
        $snapshots = array(
            'AC-1' => $snapshot('AC-1', 'Not Implemented'),
            'AC-2' => $snapshot('AC-2', 'Partially Implemented')
        );
        $records = $apply(array(), Poam::plan_sync($snapshots, array(), array()));
        for ($run = 0; $run < 5; $run++) {
            $ops = Poam::plan_sync($snapshots, $records, array());
            Assert::same(array(), $ops, 'run '.$run.' changes nothing');
            $records = $apply($records, $ops);
        }
        Assert::same(2, count($records), 'still one record per finding');
        $records[0]['source_resolved'] = 1;
        $ops = Poam::plan_sync($snapshots, $records, array());
        Assert::same(1, count($ops), 'a finding deficient again clears the flag on its open record');
        Assert::same('update', $ops[0]['op'], 'no new open item for a finding that already has one');
    },

    'a recurring finding gets one linked new item and the closed item is left untouched' => function () use ($snapshot, $apply) {
        $records = $apply(array(), Poam::plan_sync(array(
            'AC-1' => $snapshot('AC-1', 'Not Implemented')
        ), array(), array()));
        $records[0]['poam_status'] = 'Closed';
        $records[0]['date_closed'] = '2026-11-02 09:00:00';
        $records[0]['closure_note'] = 'Fixed and verified';
        $closed = $records[0];

        Assert::same(array(), Poam::plan_sync(array(
            'AC-1' => $snapshot('AC-1', 'Partially Implemented', array(
                'updated' => '2026-11-01 08:00:00'
            ))
        ), $records, array()), 'a result recorded before the closure does not touch the closed item');

        $recurring = array(
            'AC-1' => $snapshot('AC-1', 'Not Implemented', array(
                'updated' => '2026-11-04 09:00:00'
            ))
        );
        $ops = Poam::plan_sync($recurring, $records, array());
        Assert::same(1, count($ops), 'one new item');
        Assert::same('insert', $ops[0]['op'], 'inserted, not reopened in place');
        $records = $apply($records, $ops);
        Assert::same($closed, $records[0], 'closure note, date and status kept exactly');
        Assert::same($closed['id'], $records[1]['reopened_from_id'], 'linked to the closed item');

        Assert::same(array(), Poam::plan_sync($recurring, $records, array()), 'the next sync adds nothing: the new item is the open one');
        foreach (Poam::plan_sync(array(), $records, array(
            'AC-1' => 'Implemented'
        )) as $op) {
            Assert::true($op['record']['id'] !== $closed['id'], 'a closed item is never rewritten');
            Assert::true(!isset($op['set']['poam_status']), 'never closed automatically');
        }
    },

    'a sync never writes a management field or deletes anything' => function () use ($snapshot, $apply) {
        $records = $apply(array(), Poam::plan_sync(array(
            'AC-1' => $snapshot('AC-1', 'Not Implemented'),
            'AC-2' => $snapshot('AC-2', 'Not Implemented')
        ), array(), array()));
        $plans = array(
            Poam::plan_sync(array(
                'AC-1' => $snapshot('AC-1', 'Partially Implemented', array(
                    'source_priority' => 'Low',
                    'source_weakness' => 'Changed'
                ))
            ), $records, array(
                'AC-2' => 'Implemented'
            )),
            Poam::plan_sync(array(), $records, array())
        );
        $allowed = array_merge(Poam::SNAPSHOT_FIELDS, array(
            'source_resolved',
            'source_hash',
            'source_date_updated'
        ));
        foreach ($plans as $ops) {
            foreach ($ops as $op) {
                Assert::true(in_array($op['op'], array(
                    'insert',
                    'update'
                ), true), 'only inserts and updates');
                foreach (array_keys($op['set'] ?? array()) as $field) {
                    Assert::true(in_array($field, $allowed, true), $field.' is owned by the sync');
                    Assert::true(!isset(Poam::STAFF_FIELDS[$field]), $field.' is not a management field');
                }
            }
        }
    },

    'concurrent syncs queue on the assessment lock and the schema refuses a reused id' => function () {
        $model = (string) file_get_contents(ROOT.'/app/models/PoamModel.php');
        $sync = substr($model, strpos($model, 'public function sync_items('), 4000);
        Assert::true(strpos($sync, 'beginTransaction') < strpos($sync, 'lock_assessment(') && strpos($sync, 'lock_assessment(') < strpos($sync, 'all_records(') && strpos($sync, 'all_records(') < strpos($sync, '$planner($records)'), 'the lock is taken before the records are read and planned');
        Assert::true(strpos($sync, 'rollBack') !== false && strpos($sync, 'commit') !== false, 'all or nothing');
        $schema = (string) file_get_contents(ROOT.'/sql/2026-11-26-poam.sql');
        Assert::true(strpos($schema, 'UNIQUE KEY uq_poam_items_seq (assessment_id, poam_seq)') !== false, 'one sequence number per assessment');
        Assert::true(strpos($schema, 'UNIQUE KEY uq_poam_items_ref (poam_ref)') !== false, 'one reference ever');
    },

    'the report reads the register once, in one snapshot, and is dated at that snapshot' => function () {
        $source = (string) file_get_contents(ROOT.'/libs/Classes/ReportPoam.php');
        $data = substr($source, strpos($source, 'private function data()'), 3000);
        $start = strpos($data, 'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        Assert::true($start !== false && strpos($data, 'Poam::sync(') < $start, 'the snapshot starts after the sync commits');
        foreach (array(
            "\$this->options['generated'] = date(",
            'load_items(',
            'load_milestones(',
            'load_evidence(',
            'load_history(',
            "exec('COMMIT')"
        ) as $step) {
            Assert::true(strpos($data, $step) > $start, $step.' happens inside the snapshot');
        }
        Assert::true(strpos($source, 'if ($this->data !== null)') !== false, 'loaded once per build, so the PDF, Word and Excel output of a build share the records');
        $build = substr($source, strpos($source, 'public function build()'), 300);
        Assert::true(strpos($build, '$this->data()') < strpos($build, 'parent::build()'), 'loaded before the cover and document control read the date');
    },

    'framework rules come from the definition and no deadline is universal' => function () {
        $l2 = Poam::framework_rules('cmmc-l2', FrameworkLibrary::get('cmmc-l2'));
        Assert::same(180, $l2['closeout_days'], 'CMMC closeout days from 32 CFR 170.21(b)');
        Assert::true(strpos($l2['statement'], '80%') !== false, 'the 80% minimum score is stated');
        Assert::true(in_array('threshold', array_column($l2['rules'], 'type'), true), 'the Conditional score threshold is listed with its citation');
        foreach (array(
            'cmmc-l1',
            'nist-800-171-r2',
            'fedramp-moderate',
            'easa-part-is-i',
            'nist-800-53-r5',
            'soc2'
        ) as $key) {
            $rules = Poam::framework_rules($key, FrameworkLibrary::get($key));
            Assert::same(null, $rules['closeout_days'], $key.' has no closeout deadline');
            Assert::true(strpos($rules['statement'], '180') === false, $key.' states no 180-day deadline');
        }
        $fedramp = Poam::framework_rules('fedramp-moderate', FrameworkLibrary::get('fedramp-moderate'));
        Assert::same(array(
            'critical' => 30,
            'high' => 30,
            'moderate' => 90,
            'low' => 180
        ), $fedramp['days_by_risk'], 'FedRAMP timeframes by risk rating');
        Assert::true(strpos($fedramp['statement'], 'internal tracking fields, not FedRAMP requirements') !== false, 'internal fields are not presented as FedRAMP requirements');
        Assert::true(in_array('vdr_supersedes_poam', array_column($fedramp['rules'], 'id'), true), 'the legacy status of the POA&M timeframes is stated');
        $l3 = Poam::framework_rules('cmmc-l3', FrameworkLibrary::get('cmmc-l3'));
        Assert::true(in_array('poam_level2_prerequisite', array_column($l3['rules'], 'id'), true), 'Level 3 states the Final Level 2 (C3PAO) prerequisite');
    },

    'eligibility follows 32 CFR 170.21 and the SPRS note never shows a zero deduction' => function () {
        $l2 = Poam::framework_rules('cmmc-l2', FrameworkLibrary::get('cmmc-l2'));
        $scoring = array(
            'plan' => array(
                'deferrable' => array(
                    'SC.L2-3.13.11',
                    'AC.L2-3.1.8'
                )
            )
        );
        $note = Poam::eligibility($l2, $scoring, array(
            'ref' => 'SC.L2-3.13.11'
        ), 3);
        Assert::same('eligible', $note['eligibility'], 'partial FIPS encryption is eligible');
        Assert::true(strpos($note['note'], 'not FIPS-validated') !== false, 'the exception names its condition');
        Assert::same('not_eligible', Poam::eligibility($l2, $scoring, array(
            'ref' => 'CA.L2-3.12.4'
        ), 0)['eligibility'], 'the SSP can never be on a POA&M');
        Assert::same('not_eligible', Poam::eligibility($l2, $scoring, array(
            'ref' => 'AC.L2-3.1.1'
        ), 5)['eligibility'], 'a 5-point requirement is not eligible');
        Assert::same('not_permitted', Poam::eligibility(Poam::framework_rules('cmmc-l1', FrameworkLibrary::get('cmmc-l1')), $scoring, array(
            'ref' => 'AC.L1-b.1.i'
        ), null)['eligibility'], 'no POA&M at Level 1');
        $sprs = Poam::framework_rules('nist-800-171-r2', FrameworkLibrary::get('nist-800-171-r2'));
        $zero = Poam::eligibility($sprs, null, array(
            'ref' => '3.12.4'
        ), 0)['note'];
        Assert::true(strpos($zero, 'Deducts 0') === false, 'no zero-point deduction');
        Assert::true(strpos(Poam::eligibility($sprs, null, array(
            'ref' => '3.1.1'
        ), 5)['note'], 'Deducts 5 points') !== false, 'the deduction is shown');
    }
);
