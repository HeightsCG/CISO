<?php
/**
 * Client portal POA&M authorization, without the network or the database:
 * record ownership against a simulated session, the field allow-list and
 * workflow limits for portal users, what a portal user is sent (no closure,
 * deviation or risk decisions, private evidence or staff-only history), and
 * that every POA&M server action carries its own guard behind the app-wide
 * CSRF check and the portal action prefix.
 */
require_once ROOT.'/libs/Classes/Session.php';
require_once ROOT.'/libs/Classes/Model.php';
require_once ROOT.'/libs/Classes/FrameworkLibrary.php';
require_once ROOT.'/libs/Classes/Poam.php';
require_once ROOT.'/app/models/PoamModel.php';

$poam_api = function () {
    $api = (string) file_get_contents(ROOT.'/app/controllers/ApiController.php');
    $start = strpos($api, '/* ------------------------------------------------------------------ poam */');
    $end = strpos($api, '/* ------------------------------------------------------------ assistant */');
    Assert::true($start !== false && $end !== false && $end > $start, 'the POA&M section of ApiController can be found');
    return substr($api, $start, $end - $start);
};

$actions = function (string $section) {
    preg_match_all('/public function (\w+Action)\(\)\{(.*?)\n    \}\n/s', $section, $matches, PREG_SET_ORDER);
    $out = array();
    foreach ($matches as $match) {
        $out[$match[1]] = $match[2];
    }
    return $out;
};

$session = function (array $values) {
    $_SESSION = $values;
};

$item = array(
    'id' => 60,
    'company_id' => 1,
    'assessment_id' => 915,
    'client_id' => 3,
    'poam_ref' => 'POAM-915-001',
    'source_ref' => 'GV.OC-01',
    'source_title' => 'Mission understood',
    'weakness' => 'Not implemented',
    'action' => 'Implement it',
    'priority' => 'High',
    'owner_name' => 'J. Smith',
    'resources' => 'Two days',
    'comments' => 'Started',
    'poam_status' => 'Open',
    'scheduled_completion_date' => '2026-12-01',
    'overdue' => false,
    'closure_note' => 'Assessor note',
    'closure_reason' => 'Verified on site',
    'closure_override' => 1,
    'closure_result' => 'Partially Implemented',
    'closed_by' => 1,
    'closed_by_name' => 'Staff User',
    'deviation_type' => 'Risk Adjustment',
    'deviation_rationale' => 'Accepted internally',
    'risk_rating' => 'High',
    'vendor_product' => 'Vendor X',
    'weakness_override' => 'Internal wording',
    'action_override' => 'Internal action',
    'priority_override' => 'High',
    'evidence_count' => 3,
    'framework_key' => 'nist-csf-2',
    'poam_clock_date' => null,
    'created_by' => 1,
    'updated_by' => 1
);

return array(

    'a record is reachable only by a portal user of its own client' => function () use ($session, $item) {
        $session(array(
            'user_type' => 'portal',
            'client_id' => 3,
            'company_id' => 1
        ));
        Assert::true(PoamModel::portal_owns($item, Session::get('user_type'), Session::get('client_id')), 'own client');

        $session(array(
            'user_type' => 'portal',
            'client_id' => 901,
            'company_id' => 1
        ));
        Assert::true(!PoamModel::portal_owns($item, Session::get('user_type'), Session::get('client_id')), 'another client of the same company');

        $session(array(
            'user_type' => 'portal',
            'client_id' => 0,
            'company_id' => 1
        ));
        Assert::true(!PoamModel::portal_owns(array_merge($item, array(
            'client_id' => 0
        )), Session::get('user_type'), Session::get('client_id')), 'no client on either side is not a match');

        $session(array(
            'user_type' => 'staff',
            'client_id' => 3,
            'company_id' => 1
        ));
        Assert::true(!PoamModel::portal_owns($item, Session::get('user_type'), Session::get('client_id')), 'a staff session never passes the portal guard');

        $session(array());
        Assert::true(!PoamModel::portal_owns($item, Session::get('user_type'), Session::get('client_id')), 'no session');
        $_SESSION = array();
    },

    'portal users edit only owner, resources, comments and a non-closed status' => function () {
        Assert::same(array(
            'owner_name',
            'resources',
            'poam_status',
            'comments'
        ), Poam::PORTAL_FIELDS, 'the portal field allow-list');

        foreach (array_keys(Poam::STAFF_FIELDS) as $field) {
            $clean = Poam::clean_field($field, '', '2026-11-05', true);
            if (in_array($field, Poam::PORTAL_FIELDS, true)) {
                continue;
            }
            Assert::true(isset($clean['error']), $field.' is refused for portal users');
        }

        foreach (array(
            'Open',
            'In Progress',
            'Ready for Validation'
        ) as $status) {
            Assert::same(array(
                'value' => $status
            ), Poam::clean_field('poam_status', $status, '2026-11-05', true), $status.' is open to portal users');
        }

        foreach (array(
            'Closed',
            'closed',
            'Bogus',
            ''
        ) as $status) {
            Assert::true(isset(Poam::clean_field('poam_status', $status, '2026-11-05', true)['error']), '"'.$status.'" is refused');
        }

        foreach (array(
            'client_id',
            'company_id',
            'closure_note',
            'closed_by',
            'source_weakness',
            'assessment_id'
        ) as $field) {
            Assert::true(isset(Poam::clean_field($field, '1', '2026-11-05', false)['error']), $field.' is not an editable field for anyone');
        }

        Assert::true(isset(Poam::clean_field('owner_name', str_repeat('a', 201), '2026-11-05', true)['error']), 'owner length is capped');
        Assert::true(isset(Poam::clean_field('comments', str_repeat('a', 10001), '2026-11-05', true)['error']), 'comment length is capped');
        Assert::same('Only your assessor can confirm closure', Poam::closure_error('portal', 'Ready for Validation', 'Done', 1, '', true, 'Implemented'), 'closure is staff only');
    },

    'a portal user is sent no closure, deviation, risk or internal columns' => function () use ($item) {
        $portal = PoamModel::portal_item($item);

        foreach (array(
            'closure_note',
            'closure_reason',
            'closure_override',
            'closure_result',
            'closed_by',
            'closed_by_name',
            'deviation_type',
            'deviation_rationale',
            'risk_rating',
            'vendor_product',
            'weakness_override',
            'action_override',
            'priority_override',
            'evidence_count',
            'company_id',
            'client_id',
            'framework_key',
            'created_by',
            'updated_by'
        ) as $field) {
            Assert::true(!array_key_exists($field, $portal), $field.' is not sent to the portal');
        }

        foreach (array(
            'id',
            'poam_ref',
            'source_ref',
            'source_title',
            'weakness',
            'action',
            'priority',
            'owner_name',
            'resources',
            'comments',
            'poam_status',
            'scheduled_completion_date',
            'overdue'
        ) as $field) {
            Assert::true(array_key_exists($field, $portal), $field.' is still sent, the portal screen reads it');
        }
    },

    'portal history keeps their fields and status changes, nothing staff-side' => function () {
        $row = function (string $field, string $source, string $new_value, string $note) {
            return array(
                'id' => 1,
                'poam_item_id' => 60,
                'poam_milestone_id' => null,
                'poam_ref' => 'POAM-915-001',
                'change_source' => $source,
                'field_name' => $field,
                'field_label' => $field,
                'old_value' => null,
                'new_value' => $new_value,
                'change_note' => $note,
                'changed_by' => 1,
                'changed_by_name' => 'Staff User',
                'date_created' => '2026-11-05 10:00:00'
            );
        };

        $rows = PoamModel::portal_history(array(
            $row('owner_name', 'User', 'J. Smith', PoamModel::PORTAL_NOTE),
            $row('poam_status', 'Closure', 'Closed', 'Closed with override: the assessment records Partially Implemented.'),
            $row('closure_note', 'Closure', 'Assessor note', 'Closure confirmed.'),
            $row('closure_reason', 'Closure', 'Verified', 'Closure confirmed.'),
            $row('closure_override', 'Closure', '1', 'Closure confirmed.'),
            $row('evidence_linked', 'User', 'Closure: private-file.pdf', 'Closure evidence linked.'),
            $row('deviation_rationale', 'User', 'Accepted', ''),
            $row('priority_override', 'User', 'High', ''),
            $row('source_result', 'Sync', 'Implemented', ''),
            $row('milestone_added', 'User', 'Patch servers', 'Milestone added.'),
            $row('reopened_as', 'Reopen', 'POAM-915-003', 'Reopened from POAM-915-001: internal reason')
        ));

        $fields = array();
        foreach ($rows as $kept) {
            $fields[] = $kept['field_name'];
            Assert::true(!array_key_exists('change_note', $kept), 'no change note reaches the portal');
            Assert::true(!array_key_exists('changed_by', $kept), 'no user id reaches the portal');
        }

        Assert::same(array(
            'owner_name',
            'poam_status',
            'milestone_added',
            'reopened_as'
        ), $fields, 'only portal fields, status, milestones and numbering');
    },

    'private evidence never reaches the portal' => function () {
        $rows = PoamModel::portal_evidence(array(
            array(
                'id' => 1,
                'poam_item_id' => 60,
                'evidence_id' => 902,
                'evidence_role' => 'Progress',
                'evidence_title' => 'Shared file',
                'file_name' => 'internal/path.pdf',
                'evidence_private' => 0,
                'linked_by_name' => 'Staff User',
                'date_created' => '2026-11-05 10:00:00'
            ),
            array(
                'id' => 2,
                'poam_item_id' => 60,
                'evidence_id' => 903,
                'evidence_role' => 'Closure',
                'evidence_title' => 'Private file',
                'file_name' => 'private.pdf',
                'evidence_private' => 1,
                'linked_by_name' => 'Staff User',
                'date_created' => '2026-11-05 10:00:00'
            ),
            array(
                'id' => 3,
                'poam_item_id' => 60,
                'evidence_id' => 904,
                'evidence_role' => 'Closure',
                'evidence_title' => 'Unknown flag',
                'file_name' => 'x.pdf',
                'date_created' => '2026-11-05 10:00:00'
            )
        ));

        Assert::same(1, count($rows), 'only the non-private link');
        Assert::same(902, $rows[0]['evidence_id'], 'the shared file');
        Assert::true(!array_key_exists('file_name', $rows[0]), 'no storage path');
    },

    'every portal POA&M action checks the portal user and their client first' => function () use ($poam_api, $actions) {
        $portal = array_filter($actions($poam_api()), function ($name) {
            return strpos($name, 'portal_poam_') === 0;
        }, ARRAY_FILTER_USE_KEY);

        Assert::same(array(
            'portal_poam_listAction',
            'portal_poam_item_loadAction',
            'portal_poam_item_saveAction',
            'portal_poam_milestone_saveAction',
            'portal_poam_milestone_deleteAction'
        ), array_keys($portal), 'the portal POA&M actions; adding one means adding its guard and a test here');

        foreach ($portal as $name => $body) {
            Assert::true(strpos($body, '$this->poam_portal_item(') !== false || strpos($body, '$this->refuse_unless_poam_portal(') !== false, $name.' is guarded');
            Assert::true(preg_match('/poam_(save|milestone_save|milestone_delete|item_payload|register)\([^;]*\bfalse\)/', $body) === 0, $name.' never takes the staff path');
            Assert::true(preg_match('/close|reopen|link_evidence|clock/', $body) === 0, $name.' cannot close, reopen, link evidence or set the clock');
        }
    },

    'every staff POA&M action refuses anyone but staff of the company' => function () use ($poam_api, $actions) {
        $staff = array_filter($actions($poam_api()), function ($name) {
            return strpos($name, 'poam_') === 0;
        }, ARRAY_FILTER_USE_KEY);

        Assert::true(count($staff) >= 12, 'the staff POA&M actions are found');

        foreach ($staff as $name => $body) {
            Assert::true(strpos($body, '$this->poam_staff_item(') !== false || strpos($body, '$this->refuse_unless_report_staff(') !== false, $name.' is guarded');
        }

        $section = $poam_api();
        Assert::true(strpos($section, "Session::get('user_type') !== 'staff' || count(\$rows) !== 1") !== false, 'poam_staff_item checks the user type');
        Assert::true(preg_match('/post\[\'(client_id|company_id|user_id|user_type)\'\]|input\(\'(client_id|company_id|user_id|user_type)\'\)/', $section) === 0, 'scope never comes from the request');
    },

    'portal answers go through the allow-lists and portal writes are marked in history' => function () use ($poam_api) {
        $section = $poam_api();
        Assert::true(strpos($section, "'item' => PoamModel::portal_item(\$presented)") !== false, 'portal item allow-list');
        Assert::true(strpos($section, 'PoamModel::portal_history(') !== false, 'portal history filter');
        Assert::true(strpos($section, 'PoamModel::portal_evidence(') !== false, 'portal evidence filter');
        Assert::true(strpos($section, "'portal_item'") !== false, 'portal register allow-list');
        Assert::true(substr_count($section, "(int) Session::get('user_id'),\n            \$portal\n        );") === 2, 'item and milestone saves pass the portal flag');
        Assert::true(strpos($section, "'User', (int) Session::get('user_id'), \$portal)") !== false, 'milestone delete passes the portal flag');
    },

    'CSRF is checked for every POST and portal users reach only portal_ API actions' => function () {
        $bootstrap = (string) file_get_contents(ROOT.'/app/Bootstrap.php');
        $api = (string) file_get_contents(ROOT.'/app/controllers/ApiController.php');
        $data = (string) file_get_contents(ROOT.'/public/js/api.data.js');

        Assert::true(strpos($bootstrap, "if (\$this->is_post() && !\$this->is_csrf_exempt(\$co, \$m) && !CSRF::validate()) {") !== false, 'every POST is CSRF-checked before the action runs');
        Assert::true(strpos($api, 'csrfExempt') === false, 'no API action is CSRF-exempt');
        Assert::true(strpos($bootstrap, "if (\$name === 'apicontroller' && strpos(\$method, 'portal_') === 0) {") !== false, 'portal users are held to portal_ actions');
        Assert::true(strpos($data, "xhr.setRequestHeader('X-CSRF-TOKEN'") !== false && strpos($data, 'body.csrf_token') !== false, 'ApiDataSvc sends the token');
    }

);
