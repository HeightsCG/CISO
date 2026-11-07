<?php
/**
 * Control comments authorization, without the network or the database: which
 * actions exist and the guard each one runs before anything else, that the
 * portal guard scopes to the session's own client and company, that a comment
 * can only be removed by its author, and the text rules (trimmed, required, at
 * most 4000 characters).
 */
require_once ROOT.'/libs/Classes/Session.php';
require_once ROOT.'/libs/Classes/Model.php';
require_once ROOT.'/app/models/ItemCommentsModel.php';

$api_source = function () {
    return (string) file_get_contents(ROOT.'/app/controllers/ApiController.php');
};

$comments_api = function () use ($api_source) {
    $api = $api_source();
    $start = strpos($api, '/* ------------------------------------------------------- control comments */');
    $end = strpos($api, '/* ------------------------------------------------------------ assistant */');
    Assert::true($start !== false && $end !== false && $end > $start, 'the control comments section of ApiController can be found');
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

$private_method = function (string $source, string $name) {
    $start = strpos($source, 'private function '.$name.'(');
    Assert::true($start !== false, $name.' exists');
    $end = strpos($source, "\n    }\n", $start);
    return substr($source, $start, $end - $start);
};

return array(

    'the comment actions are exactly these, portal ones carrying the portal prefix' => function () use ($comments_api, $actions) {
        Assert::same(array(
            'portal_item_comments_loadAction',
            'portal_item_comment_addAction',
            'portal_item_comment_deleteAction',
            'item_comments_loadAction',
            'item_comment_addAction',
            'item_comment_deleteAction'
        ), array_keys($actions($comments_api())), 'the comment actions; adding one means adding its guard and a test here');
    },

    'every portal action in the controller is listed, so a new one cannot slip in unguarded' => function () use ($api_source) {
        preg_match_all('/public function (portal_\w+Action)\(\)/', $api_source(), $matches);

        Assert::same(array(
            'portal_assessmentsAction',
            'portal_foldersAction',
            'portal_evidenceAction',
            'portal_evidence_urlAction',
            'portal_item_evidenceAction',
            'portal_link_evidenceAction',
            'portal_unlink_evidenceAction',
            'portal_delete_evidenceAction',
            'portal_upload_evidenceAction',
            'portal_itemsAction',
            'portal_invoicesAction',
            'portal_invoice_urlAction',
            'portal_poam_listAction',
            'portal_poam_item_loadAction',
            'portal_poam_item_saveAction',
            'portal_poam_milestone_saveAction',
            'portal_poam_milestone_deleteAction',
            'portal_poam_evidence_linkAction',
            'portal_poam_evidence_unlinkAction',
            'portal_item_comments_loadAction',
            'portal_item_comment_addAction',
            'portal_item_comment_deleteAction'
        ), $matches[1], 'the portal actions');
    },

    'portal comment actions run the portal guard first and never the staff one' => function () use ($comments_api, $actions) {
        foreach ($actions($comments_api()) as $name => $body) {
            $portal = strpos($name, 'portal_') === 0;
            $first = $portal ? '$this->comment_portal_item(' : '$this->comment_staff_item(';
            $other = $portal ? '$this->comment_staff_item(' : '$this->comment_portal_item(';

            Assert::true(strpos($body, $first) !== false, $name.' is guarded');
            Assert::true(strpos($body, $other) === false, $name.' takes only its own path');
            Assert::true(preg_match("/post\\['(client_id|company_id|user_id|created_by|assessment_id)'\\]/", $body) === 0, $name.' reads no scope from the request');

            if (strpos($name, 'delete') !== false) {
                Assert::true(strpos($body, '$this->comment_row(') < strpos($body, $first), $name.' checks the comment\'s own control');
                Assert::true(strpos($body, "(int) \$comment['assessment_item_id']") !== false, $name.' guards the control the comment is on, not a posted one');
            }
        }
    },

    'the portal guard scopes to the session client and refuses non-portal users' => function () use ($api_source, $private_method, $comments_api) {
        $source = $api_source();
        $guard = $private_method($source, 'comment_portal_item');
        $row = $private_method($source, 'portal_item_row');

        Assert::true(strpos($guard, '$this->portal_item_row($item_id, $read)') !== false, 'goes through the shared portal control check');
        Assert::true(strpos($guard, 'bool $read = false') !== false, 'the guard is write-strict unless a read asks otherwise');
        Assert::true(strpos($row, '$client_id = $this->portal_client_id($read);') !== false, 'the client comes from the portal client check');
        Assert::true(strpos($row, "get_item(\$item_id, Session::get('company_id'))") !== false, 'company from the session');
        Assert::true(strpos($row, "(int) \$item[0]['client_id'] !== \$client_id") !== false, 'the control must belong to that client');

        $client = $private_method($source, 'portal_client_id');
        Assert::true(strpos($client, "if (Session::get('user_type') === 'portal') {\n            return (int) Session::get('client_id');") !== false, 'a client account always gets its own client from the session');
        Assert::true(strpos($client, "if (!\$read || Session::get('user_type') !== 'staff' || \$preview_id <= 0) {\n            return 0;") !== false, 'staff get a client only for reads while previewing');
        Assert::true(strpos($client, "get_client(\$preview_id, Session::get('company_id'))") !== false, 'the previewed client must belong to the session company');

        $comments = $comments_api();
        Assert::true(strpos($comments, "\$this->comment_portal_item(\$this->poam_post_id('item_id'), true)") !== false, 'loading comments is the one read that allows a preview');
        Assert::true(substr_count($comments, ', true)') === 1, 'no comment write allows a preview');

        $staff = $private_method($source, 'comment_staff_item');
        Assert::true(strpos($staff, "Session::get('user_type') !== 'staff'") !== false, 'staff actions refuse portal users');
        Assert::true(strpos($staff, "get_item(\$item_id, Session::get('company_id'))") !== false, 'staff actions are company scoped');

        $row = $private_method($source, 'comment_row');
        Assert::true(strpos($row, "get_comment(\$comment_id, Session::get('company_id'))") !== false, 'a comment is found within the session company');
    },

    'only the portal prefix gets past the portal scope' => function () {
        $bootstrap = (string) file_get_contents(ROOT.'/app/Bootstrap.php');

        Assert::true(strpos($bootstrap, "\$name === 'apicontroller' && strpos(\$method, 'portal_') === 0") !== false, 'portal_ actions are let through');

        foreach (array('item_comments_loadAction', 'item_comment_addAction', 'item_comment_deleteAction') as $name) {
            Assert::true(strpos($name, 'portal_') !== 0 && strpos($name, 'ai_') !== 0 && $name !== 'change_passwordAction', $name.' is refused to portal users');
        }
    },

    'adding stores the session user, trimmed text and the control\'s own assessment' => function () use ($api_source, $private_method) {
        $add = $private_method($api_source(), 'comment_add');

        Assert::true(strpos($add, "\$this->input('comment_text')") !== false, 'text is trimmed');
        Assert::true(strpos($add, 'ItemCommentsModel::text_error($comment_text)') !== false, 'text is validated');
        Assert::true(strpos($add, "(int) \$item['assessment_id']") !== false, 'assessment from the control');
        Assert::true(strpos($add, "(int) Session::get('user_id')") !== false, 'author from the session');
    },

    'a comment can only be removed by its author' => function () use ($api_source, $private_method) {
        Assert::true(ItemCommentsModel::may_delete(array(
            'created_by' => 12
        ), 12), 'own comment');
        Assert::true(!ItemCommentsModel::may_delete(array(
            'created_by' => 12
        ), 1), 'another user\'s comment');
        Assert::true(!ItemCommentsModel::may_delete(array(
            'created_by' => 0
        ), 0), 'no user');
        Assert::true(!ItemCommentsModel::may_delete(array(
            'created_by' => '12'
        ), null), 'no session');

        $delete = $private_method($api_source(), 'comment_delete');
        Assert::true(strpos($delete, "ItemCommentsModel::may_delete(\$comment, (int) Session::get('user_id'))") !== false, 'the action checks the author');

        $model = (string) file_get_contents(ROOT.'/app/models/ItemCommentsModel.php');
        Assert::true(strpos($model, "'id = :id and company_id = :company_id and created_by = :created_by and deleted = 0'") !== false, 'the update itself is limited to the author');
        Assert::true(preg_match('/parent::delete(_all)?\(/', $model) === 0, 'comments are soft-deleted');
    },

    'comment text is required and at most 4000 characters' => function () {
        Assert::same('Enter a comment', ItemCommentsModel::text_error(''), 'empty');
        Assert::same('', ItemCommentsModel::text_error('Looks good'), 'ordinary text');
        Assert::same('', ItemCommentsModel::text_error(str_repeat('a', 4000)), '4000 characters');
        Assert::same('Comments can be up to 4000 characters', ItemCommentsModel::text_error(str_repeat('a', 4001)), '4001 characters');
        Assert::same('', ItemCommentsModel::text_error(str_repeat('é', 4000)), '4000 multibyte characters count as 4000');
        Assert::same('Comments can be up to 4000 characters', ItemCommentsModel::text_error(str_repeat('é', 4001)), '4001 multibyte characters');
    },

    'the list says whose comment it is without exposing user ids' => function () {
        $model = (string) file_get_contents(ROOT.'/app/models/ItemCommentsModel.php');
        $start = strpos($model, 'public function load_comments(');
        $end = strpos($model, 'public function get_comment(');
        $load = substr($model, $start, $end - $start);

        Assert::true(strpos($load, "IF(u.user_type = 'portal', 1, 0) AS is_client") !== false, 'client comments are marked');
        Assert::true(strpos($load, 'IF(c.created_by = :user_id, 1, 0) AS is_own') !== false, 'the caller\'s own comments are marked');
        Assert::true(strpos($load, 'c.company_id = :company_id') !== false, 'company scoped');
        Assert::true(strpos($load, 'c.deleted = 0') !== false, 'removed comments are hidden');
        Assert::true(preg_match('/^\s*c\.created_by,?\s*$/m', $load) === 0, 'created_by is not returned');
    }

);
