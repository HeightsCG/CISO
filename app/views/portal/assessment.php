<div class="page">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title"><?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>
        <div class="headline__actions">
            <a class="btn btn--secondary btn--pill" href="/portal"><i class="fa-regular fa-chevron-left"></i> Back</a>
            <button type="button" class="btn btn--secondary btn--pill" id="do_vault_open"><i class="fa-regular fa-folder-open"></i> Evidence</button>
            <button type="button" class="btn btn--secondary btn--pill" id="do_poam_open"><i class="fa-regular fa-list-check"></i> POA&amp;M</button>
        </div>
    </header>

    <div class="panel record__panel">
        <div class="panel__body">
            <div class="rollup" id="rollup"></div>
            <div class="rollup__progress">
                <div class="progress progress--lg"><div class="progress__bar" id="rollup_bar" style="width:0%"></div></div>
                <span class="progress__label" id="rollup_label">&nbsp;</span>
            </div>
        </div>
    </div>

    <div class="panel record__panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="item_search" class="input roster__search" placeholder="Search by identifier or title..." autocomplete="off" spellcheck="false">
            <div class="roster__filters">
                <div class="roster__filter">
                    <select id="family_filter" class="form-select">
                        <option value="">All families</option>
                    </select>
                </div>
                <div class="roster__filter">
                    <select id="result_filter" class="form-select">
                        <option value="">All results</option>
                        <option value="Not Assessed">Not Assessed</option>
                        <option value="Implemented">Implemented</option>
                        <option value="Partially Implemented">Partially Implemented</option>
                        <option value="Not Implemented">Not Implemented</option>
                        <option value="Not Applicable">Not Applicable</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap controls__scroll">
                <table class="data controls__table" id="items_table">
                    <thead>
                        <tr>
                            <th scope="col">Identifier</th>
                            <th scope="col">Control</th>
                            <th scope="col">Result</th>
                            <th scope="col">Evidence</th>
                        </tr>
                    </thead>
                    <tbody id="items_body"></tbody>
                </table>
            </div>
            <p class="controls__empty" id="items_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading controls&hellip;</p>
        </div>
    </div>
</div>

<div class="modal fade" id="portal_item_modal" tabindex="-1" aria-labelledby="portal_item_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="ctl__heading">
                    <p class="assess__eyebrow ctl__eyebrow"><span class="assess__family" id="item_family"></span></p>
                    <h2 class="modal-title" id="portal_item_modal_title"><span id="item_heading"></span><span class="badge ctl__badge" id="item_result"></span></h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="ctl__desc" id="item_description"></p>
                <div class="poam__section">
                    <div class="poam__section-head">
                        <h3 class="record__group-title">Evidence</h3>
                        <span class="poam__ev-actions">
                            <button type="button" class="btn btn--secondary btn--sm" id="do_attach_open"><i class="fa-regular fa-link"></i> Attach from Vault</button>
                            <button type="button" class="btn btn--secondary btn--sm" id="do_item_upload_open"><i class="fa-regular fa-arrow-up-from-bracket"></i> Upload New</button>
                            <input type="file" id="item_upload_file" multiple hidden>
                        </span>
                    </div>
                    <ul class="evidence__list" id="item_evidence" hidden></ul>
                    <p class="ctl__none" id="item_evidence_empty" hidden>No evidence attached to this control yet.</p>
                </div>
                <div class="poam__section">
                    <div class="poam__section-head">
                        <h3 class="record__group-title">Comments</h3>
                    </div>
                    <ul class="evidence__list comments__list" id="item_comments" hidden></ul>
                    <p class="ctl__none comments__none" id="item_comments_empty" hidden>No comments yet.</p>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="visually-hidden" for="item_comment_text">Comment</label>
                            <textarea class="form-control" id="item_comment_text" rows="2" maxlength="4000"></textarea>
                        </div>
                    </div>
                    <div class="comments__add">
                        <button type="button" class="btn btn--primary btn--sm" id="do_item_comment_add"><i class="fa-regular fa-comment"></i> Add Comment</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="portal_attach_modal" tabindex="-1" aria-labelledby="portal_attach_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="portal_attach_modal_title">Attach Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="pick__label">Files in <?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?>&rsquo;s evidence vault <span class="pick__count" id="pick_count"></span></p>
                <div class="vault vault--pick">
                    <aside class="vault__tree">
                        <div class="panel__head">
                            <h2 class="panel__title">Folders</h2>
                        </div>
                        <ul class="vault__list" id="pick_tree"></ul>
                    </aside>

                    <section class="vault__files">
                        <div class="vault__toolbar">
                            <input type="search" id="pick_search" class="input vault__search" placeholder="Search title or file name..." autocomplete="off" spellcheck="false">
                        </div>
                        <div class="pick__scroll">
                            <table class="data data--light" id="pick_table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="pick__sort is-sortable" data-sort="evidence_title">Name</th>
                                        <th scope="col" class="vault__col-size pick__sort is-sortable" data-sort="file_size">Size</th>
                                        <th scope="col" class="vault__col-date pick__sort is-sortable" data-sort="date_created">Uploaded</th>
                                        <th scope="col" class="vault__col-links pick__sort is-sortable" data-sort="link_count">Controls</th>
                                        <th scope="col" class="pick__col-attach"><span class="visually-hidden">Attach</span></th>
                                    </tr>
                                </thead>
                                <tbody id="pick_list"></tbody>
                            </table>
                            <p class="vault__empty" id="pick_empty" hidden></p>
                        </div>
                    </section>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="poam_modal" tabindex="-1" aria-labelledby="poam_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="poam_modal_title">POA&amp;M Register</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="poam__toolbar">
                    <input type="search" id="poam_search" class="input poam__search" placeholder="Search by ID, control, weakness or owner..." autocomplete="off" spellcheck="false">
                    <div class="poam__filter">
                        <select id="poam_status_filter" class="form-select" data-style="form-control" aria-label="Status">
                            <option value="">All statuses</option>
                            <option value="Open">Open</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Ready for Validation">Ready for Validation</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                </div>
                <div class="poam__scroll">
                    <table class="data poam__table" id="poam_table" hidden>
                        <thead>
                            <tr>
                                <th scope="col" class="poam__col-id">ID</th>
                                <th scope="col" class="poam__col-control">Control</th>
                                <th scope="col">Weakness</th>
                                <th scope="col" class="poam__col-owner">Responsible Owner</th>
                                <th scope="col" class="poam__col-date">Scheduled</th>
                                <th scope="col" class="poam__col-status">Status</th>
                            </tr>
                        </thead>
                        <tbody id="poam_list"></tbody>
                    </table>
                    <p class="poam__empty" id="poam_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading items&hellip;</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="poam_item_modal" tabindex="-1" aria-labelledby="poam_item_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="poam_item_modal_title">POA&amp;M Item</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="poam__head">
                    <p class="assess__eyebrow"><span class="assess__id" id="poam_item_control"></span></p>
                    <h3 class="assess__title" id="poam_item_title"></h3>
                    <p class="poam__meta">
                        <span id="poam_item_status"></span>
                        <span class="poam__meta-item" id="poam_item_priority"></span>
                    </p>
                </div>

                <div class="poam__section" id="poam_item_fields">
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label>Weakness</label>
                            <p class="assess__text" id="poam_weakness"></p>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label>Corrective Action</label>
                            <p class="assess__text" id="poam_action"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="poam_owner">Responsible Owner</label>
                            <input type="text" class="form-control" id="poam_owner" maxlength="200">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="poam_status">Status</label>
                            <select class="form-control" id="poam_status" data-style="form-control">
                                <option value="Open">Open</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Ready for Validation">Ready for Validation</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group mb-3">
                            <label for="poam_resources">Resources Required</label>
                            <textarea class="form-control poam__text" id="poam_resources" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label for="poam_comments">Comments</label>
                            <textarea class="form-control poam__text" id="poam_comments" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="poam__section">
                    <div class="poam__section-head">
                        <h3 class="record__group-title">Milestones</h3>
                        <button type="button" class="btn btn--secondary btn--sm" id="do_poam_milestone_add"><i class="fa-regular fa-plus"></i> Add Milestone</button>
                    </div>
                    <table class="data data--light poam__milestones" id="poam_milestones_table" hidden>
                        <thead>
                            <tr>
                                <th scope="col">Milestone</th>
                                <th scope="col" class="poam__ms-date">Due</th>
                                <th scope="col" class="poam__ms-date">Completed</th>
                                <th scope="col" class="poam__ms-status">Status</th>
                                <th scope="col" class="poam__ms-actions"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody id="poam_milestones"></tbody>
                    </table>
                    <p class="poam__none" id="poam_milestones_empty">No milestones.</p>
                </div>

                <div class="poam__section">
                    <div class="poam__section-head">
                        <h3 class="record__group-title">Evidence</h3>
                        <span class="poam__ev-actions" id="poam_ev_actions">
                            <button type="button" class="btn btn--secondary btn--sm" id="do_poam_ev_attach_open"><i class="fa-regular fa-link"></i> Attach from Vault</button>
                            <button type="button" class="btn btn--primary btn--sm" id="do_poam_ev_upload_open"><i class="fa-regular fa-arrow-up-from-bracket"></i> Upload New</button>
                            <input type="file" id="poam_ev_upload_file" multiple hidden>
                        </span>
                    </div>
                    <ul class="evidence__list poam__evidence" id="poam_ev_list" hidden></ul>
                    <p class="poam__none" id="poam_ev_empty">No evidence linked.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" id="poam_item_dismiss" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_poam_save">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="vault_modal" tabindex="-1" aria-labelledby="vault_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="vault_modal_title">Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="vault vault--pick">
                    <aside class="vault__tree">
                        <div class="panel__head">
                            <h2 class="panel__title">Folders</h2>
                        </div>
                        <ul class="vault__list" id="vault_folder_tree"></ul>
                    </aside>

                    <section class="vault__files">
                        <div class="vault__toolbar">
                            <input type="search" id="vault_search" class="input vault__search" placeholder="Search title or file name..." autocomplete="off" spellcheck="false">
                            <button type="button" class="btn btn--primary" id="do_vault_upload_open"><i class="fa-regular fa-arrow-up-from-bracket"></i> Upload Evidence</button>
                        </div>
                        <div class="pick__scroll">
                            <table class="data data--light" id="vault_table">
                                <thead>
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col" class="vault__col-size">Size</th>
                                        <th scope="col" class="vault__col-date">Uploaded</th>
                                        <th scope="col" class="vault__col-links">Controls</th>
                                        <th scope="col" class="vault__col-by">Uploaded by</th>
                                        <th scope="col" class="vault__col-actions"><span class="visually-hidden">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody id="vault_body"></tbody>
                            </table>
                            <p class="vault__empty" id="vault_empty" hidden></p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="vault_upload_modal" tabindex="-1" aria-labelledby="vault_upload_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="vault_upload_modal_title">Upload Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="import__hint">Saved into <strong id="vault_upload_folder"></strong>. Documents, images and archives up to 25MB.</p>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="vault_evidence_title">Title <abbr title="required">*</abbr></label>
                        <input type="text" class="form-control" id="vault_evidence_title" placeholder="Information security policy, signed">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="vault_evidence_description">Description</label>
                        <textarea class="form-control" id="vault_evidence_description" rows="3" placeholder="What this shows and where it came from."></textarea>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="vault_evidence_file">File <abbr title="required">*</abbr></label>
                        <input type="file" class="form-control" id="vault_evidence_file">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_vault_upload" class="btn btn--primary">Upload Evidence</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="vault_remove_modal" tabindex="-1" aria-labelledby="vault_remove_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="vault_remove_modal_title">Remove Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="vault_remove_id" value="0">
                <p>Remove <strong id="vault_remove_title"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_vault_remove" class="btn btn--destructive">Remove Evidence</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var assessment_id = <?php echo (int) $this->assessment['id']; ?>;
    var items = [];
    var current_item = null;

    function size_label(bytes) {

        var n = parseInt(bytes, 10);

        if (isNaN(n) || n <= 0) {
            return '';
        }
        if (n < 1024) {
            return n + ' B';
        }
        if (n < 1048576) {
            return Math.round(n / 1024) + ' KB';
        }
        return (n / 1048576).toFixed(1) + ' MB';
    }

    var RESULTS = [
        { name: 'Implemented', dot: 'is-ok', badge: 'badge--active' },
        { name: 'Partially Implemented', dot: 'is-partial', badge: 'badge--onboarding' },
        { name: 'Not Implemented', dot: 'is-gap', badge: 'badge--critical' },
        { name: 'Not Applicable', dot: 'is-na', badge: 'badge--prospect' },
        { name: 'Not Assessed', dot: 'is-none', badge: 'badge--inactive' }
    ];

    function description_body(title, description) {

        var body = String(description === null ? '' : description).trim();
        var lead = String(title === null ? '' : title).trim().replace(/\.$/, '');

        if (lead !== '' && body.indexOf(lead) === 0) {
            body = body.slice(lead.length).replace(/^[.\s]+/, '');
        }

        return body;
    }

    function badge_for(result) {
        for (var i = 0; i < RESULTS.length; i++) {
            if (RESULTS[i].name === result) {
                return RESULTS[i].badge;
            }
        }
        return 'badge--inactive';
    }

    function counts_of() {

        var counts = {};

        for (var i = 0; i < RESULTS.length; i++) {
            counts[RESULTS[i].name] = 0;
        }

        for (var j = 0; j < items.length; j++) {
            counts[items[j].item_result] = (counts[items[j].item_result] || 0) + 1;
        }

        return counts;
    }

    function render_rollup() {

        var counts = counts_of();
        var total = items.length;
        var assessed = total - counts['Not Assessed'];
        var pct = total > 0 ? Math.round(assessed / total * 100) : 0;
        var html = '';

        for (var i = 0; i < RESULTS.length; i++) {
            html += '<span class="rollup__stat">'
                + '<span class="rollup__dot ' + RESULTS[i].dot + '"></span>'
                + '<span class="rollup__n">' + counts[RESULTS[i].name] + '</span>'
                + '<span class="rollup__label">' + esc(RESULTS[i].name) + '</span></span>';
        }

        $('#rollup').html(html);
        $('#rollup_bar').css('width', pct + '%');
        $('#rollup_label').text(total === 0
            ? 'No controls in this assessment'
            : assessed + ' of ' + total + ' controls assessed \u00b7 ' + pct + '%');
    }

    function render_families() {

        var seen = [];
        var html = '<option value="">All families</option>';

        for (var i = 0; i < items.length; i++) {
            if (items[i].family !== '' && seen.indexOf(items[i].family) === -1) {
                seen.push(items[i].family);
            }
        }

        seen.sort();

        for (var f = 0; f < seen.length; f++) {
            html += '<option value="' + esc(seen[f]) + '">' + esc(seen[f]) + '</option>';
        }

        $('#family_filter').html(html);
    }

    function visible_items() {

        var term = $('#item_search').val().trim().toLowerCase();
        var family = $('#family_filter').val();
        var result = $('#result_filter').val();
        var out = [];

        for (var i = 0; i < items.length; i++) {

            var it = items[i];

            if (family !== '' && it.family !== family) {
                continue;
            }

            if (result !== '' && it.item_result !== result) {
                continue;
            }

            if (term !== '' && (it.control_identifier + ' ' + it.control_title).toLowerCase().indexOf(term) === -1) {
                continue;
            }

            out.push(it);
        }

        return out;
    }

    function render() {

        var rows = visible_items();
        var html = '';
        var family = null;

        for (var i = 0; i < rows.length; i++) {

            var it = rows[i];
            var used = parseInt(it.evidence_count, 10);

            if (it.family !== family) {
                family = it.family;
                html += '<tr class="controls__group"><td colspan="4">' + esc(family === '' ? 'Ungrouped' : family) + '</td></tr>';
            }

            html += '<tr class="item__row" data-id="' + it.id + '">'
                + '<td class="controls__id">' + esc(it.control_identifier) + '</td>'
                + '<td>' + esc(it.control_title) + '</td>'
                + '<td><span class="badge ' + badge_for(it.item_result) + '">' + esc(it.item_result) + '</span></td>'
                + '<td>' + (used > 0
                    ? '<span class="chip">' + used + '</span>'
                    : '<span class="chip chip--empty">0</span>') + '</td>'
                + '</tr>';
        }

        $('#items_body').html(html);
        $('#items_table').toggle(rows.length > 0);
        $('#items_empty').prop('hidden', rows.length > 0).text(items.length === 0
            ? 'No controls in this assessment yet.'
            : 'No controls match your filters.');
    }

    $('#item_search').on('input', render);
    $('#family_filter').on('change', render);
    $('#result_filter').on('change', render);

    $('#items_body').on('click', '.item__row', function () {

        var id = $(this).attr('data-id');
        var found = null;

        for (var i = 0; i < items.length; i++) {
            if (String(items[i].id) === String(id)) {
                found = items[i];
            }
        }

        if (found === null) {
            return;
        }

        current_item = found;

        var summary = description_body(current_item.control_title, current_item.description);

        $('#item_family').text(current_item.family === '' ? 'Ungrouped' : current_item.family);
        $('#item_heading').text(current_item.control_identifier + ' ' + current_item.control_title);
        $('#item_description').text(summary).attr('title', summary).prop('hidden', summary === '');
        $('#item_evidence').prop('hidden', true).empty();
        $('#item_evidence_empty').prop('hidden', true);
        $('#item_result').text(current_item.item_result)
            .removeClass('badge--active badge--onboarding badge--critical badge--prospect badge--inactive')
            .addClass(badge_for(current_item.item_result));

        $('#item_comments').prop('hidden', true).empty();
        $('#item_comments_empty').prop('hidden', true);
        $('#item_comment_text').val('').removeClass('is-invalid');

        load_item_evidence();
        load_item_comments();
        modal('portal_item_modal').show();
    });

    function load_item_evidence() {

        var target = current_item;

        ApiDataSvc.apiCall('post', 'portal_item_evidence', { item_id: target.id }, function (data) {

            var rows = JSON.parse(data);
            var html = '';

            for (var i = 0; i < rows.length; i++) {
                html += '<li class="evidence__item" data-id="' + rows[i].id + '">'
                    + '<div><a href="#" class="evidence__open" data-id="' + rows[i].id + '">' + esc(rows[i].evidence_title) + '</a>'
                    + '<span class="evidence__meta">' + esc(rows[i].file_name) + ' &middot; ' + size_label(rows[i].file_size) + '</span></div>'
                    + '<button type="button" class="btn btn--tertiary btn--sm" data-action="unlink" data-id="' + rows[i].id + '" aria-label="Detach ' + esc(rows[i].evidence_title) + '"><i class="fa-regular fa-link-slash"></i></button>'
                    + '</li>';
            }

            if (current_item !== target) {
                return;
            }

            $('#item_evidence').html(html).prop('hidden', html === '');
            $('#item_evidence_empty').prop('hidden', html !== '');
        });
    }

    function comment_html(row) {

        var html = '<li class="comments__item" data-id="' + row.id + '">'
            + '<div class="comments__head">'
            + '<span class="comments__author">' + (parseInt(row.is_own, 10) === 1 ? 'You' : esc(row.author_name)) + '</span>'
            + '<span class="comments__date">' + esc(row.date_created_display) + '</span>';

        if (parseInt(row.is_own, 10) === 1) {
            html += '<span class="comments__tools">'
                + '<button type="button" class="btn btn--tertiary btn--sm" data-action="comment-delete">Delete</button>'
                + '<span class="comments__confirm" hidden>'
                + '<button type="button" class="btn btn--destructive btn--sm" data-action="comment-delete-yes">Delete</button>'
                + '<button type="button" class="btn btn--tertiary btn--sm" data-action="comment-delete-no">Cancel</button>'
                + '</span>'
                + '</span>';
        }

        return html + '</div><p class="comments__text">' + esc(row.comment_text) + '</p></li>';
    }

    function load_item_comments() {

        var target = current_item;
        var obj = {
            item_id: target.id
        };

        ApiDataSvc.apiCall('post', 'portal_item_comments_load', obj, function (data) {

            var response = JSON.parse(data);
            var html = '';

            if (current_item !== target) {
                return;
            }

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            for (var i = 0; i < response.comments.length; i++) {
                html += comment_html(response.comments[i]);
            }

            $('#item_comments').html(html).prop('hidden', html === '');
            $('#item_comments_empty').prop('hidden', html !== '');

            if (html !== '') {
                $('#item_comments').scrollTop($('#item_comments')[0].scrollHeight);
            }
        });
    }

    $('#portal_item_modal').on('shown.bs.modal', function () {
        $('#item_comments').scrollTop($('#item_comments')[0].scrollHeight);
    });

    $('#do_item_comment_add').click(function () {

        var button = this;
        var obj = {
            item_id: current_item.id,
            comment_text: String($('#item_comment_text').val() || '').trim()
        };

        $('#item_comment_text').removeClass('is-invalid');

        if (obj.comment_text === '') {
            $('#item_comment_text').addClass('is-invalid');
            toastr.error('Enter a comment');
            return;
        }

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'portal_item_comment_add', obj, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (response.success) {
                $('#item_comment_text').val('');
                load_item_comments();
            } else {
                $('#item_comment_text').addClass('is-invalid');
                toastr.error(response.message);
            }
        }, function () {
            set_loading(button, false);
        });
    });

    $('#item_comment_text').on('input', function () {
        $(this).removeClass('is-invalid');
    });

    $('#item_comments').on('click', '[data-action="comment-delete"]', function () {

        var tools = $(this).closest('.comments__tools');

        $(this).prop('hidden', true);
        tools.find('.comments__confirm').prop('hidden', false);
    });

    $('#item_comments').on('click', '[data-action="comment-delete-no"]', function () {

        var tools = $(this).closest('.comments__tools');

        tools.find('.comments__confirm').prop('hidden', true);
        tools.find('[data-action="comment-delete"]').prop('hidden', false);
    });

    $('#item_comments').on('click', '[data-action="comment-delete-yes"]', function () {

        var button = this;
        var obj = {
            comment_id: $(this).closest('.comments__item').attr('data-id')
        };

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'portal_item_comment_delete', obj, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (response.success) {
                load_item_comments();
            } else {
                toastr.error(response.message);
            }
        }, function () {
            set_loading(button, false);
        });
    });

    function open_evidence_file(link, evidence_id) {

        set_loading(link, true);

        ApiDataSvc.apiCall('post', 'portal_evidence_url', { evidence_id: evidence_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading(link, false);

            if (obj.success) {
                window.open(obj.url, '_blank', 'noopener');
            } else {
                toastr.error(obj.message);
            }
        });
    }

    $('#item_evidence, #pick_list').on('click', '.evidence__open', function (e) {
        e.preventDefault();
        open_evidence_file(this, $(this).attr('data-id'));
    });

    $('#item_evidence').on('click', '[data-action="unlink"]', function () {

        var button = this;

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'portal_unlink_evidence', {
            item_id: current_item.id,
            evidence_id: $(this).attr('data-id')
        }, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {
                toastr.success(obj.message);
                load_item_evidence();
                load();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    var pick = {
        mode: 'control',
        folders: [],
        evidence: [],
        folder: 0,
        sort: 'date_created',
        dir: 'desc'
    };

    function extension_of(name) {

        var at = String(name || '').lastIndexOf('.');

        return at === -1 ? 'FILE' : String(name).slice(at + 1).toUpperCase().slice(0, 4);
    }

    function attached_ids() {

        if (pick.mode === 'poam') {
            return poam.payload.evidence.map(function (link) {
                return String(link.evidence_id);
            });
        }

        return $('#item_evidence .evidence__item').map(function () {
            return String($(this).attr('data-id'));
        }).get();
    }

    function pick_children(parent_id) {

        var out = [];

        for (var i = 0; i < pick.folders.length; i++) {
            if (parseInt(pick.folders[i].parent_id, 10) === parseInt(parent_id, 10)) {
                out.push(pick.folders[i]);
            }
        }

        return out;
    }

    function pick_count_of(id) {

        var total = 0;

        for (var i = 0; i < pick.evidence.length; i++) {
            if (parseInt(id, 10) === 0 || parseInt(pick.evidence[i].folder_id, 10) === parseInt(id, 10)) {
                total++;
            }
        }

        return '<span class="vault__count' + (total === 0 ? ' vault__count--empty' : '') + '">' + total + '</span>';
    }

    function pick_branch(parent_id, depth) {

        var html = '';
        var kids = pick_children(parent_id);

        for (var i = 0; i < kids.length; i++) {
            html += '<li class="vault__item' + (pick.folder === parseInt(kids[i].id, 10) ? ' is-on' : '') + '" style="--tree-depth:' + depth + '">'
                + '<button type="button" class="vault__link" data-id="' + kids[i].id + '">'
                + '<i class="fa-regular fa-folder"></i><span class="vault__name">' + esc(kids[i].folder_name) + '</span>'
                + pick_count_of(kids[i].id) + '</button></li>'
                + pick_branch(kids[i].id, depth + 1);
        }

        return html;
    }

    function pick_render_tree() {
        $('#pick_tree').html('<li class="vault__item' + (pick.folder === 0 ? ' is-on' : '') + '" style="--tree-depth:0">'
            + '<button type="button" class="vault__link" data-id="0">'
            + '<i class="fa-regular fa-inbox"></i><span class="vault__name">All Evidence</span>'
            + pick_count_of(0) + '</button></li>'
            + pick_branch(0, 0));
    }

    function pick_key(row) {

        if (pick.sort === 'file_size' || pick.sort === 'link_count') {
            return parseInt(row[pick.sort], 10) || 0;
        }

        if (pick.sort === 'date_created') {
            return parseInt(row.id, 10) || 0;
        }

        return String(row.evidence_title || row.file_name).toLowerCase();
    }

    function pick_visible() {

        var term = $('#pick_search').val().trim().toLowerCase();
        var out = [];

        for (var i = 0; i < pick.evidence.length; i++) {

            var e = pick.evidence[i];

            if (pick.folder !== 0 && parseInt(e.folder_id, 10) !== pick.folder) {
                continue;
            }

            if (term !== '' && (e.evidence_title + ' ' + e.file_name).toLowerCase().indexOf(term) === -1) {
                continue;
            }

            out.push(e);
        }

        out.sort(function (a, b) {

            var x = pick_key(a);
            var y = pick_key(b);

            return (x < y ? -1 : (x > y ? 1 : 0)) * (pick.dir === 'asc' ? 1 : -1);
        });

        return out;
    }

    function pick_empty_text() {

        var term = $('#pick_search').val().trim();

        if (pick.evidence.length === 0) {
            return 'No evidence has been collected yet.';
        }

        if (term !== '') {
            return 'No files match “' + term + '”.';
        }

        return 'This folder is empty.';
    }

    function pick_row(row, attached) {

        var already = attached.indexOf(String(row.id)) !== -1;
        var used = parseInt(row.link_count, 10);

        return '<tr class="vault__row">'
            + '<td class="vault__cell-name"><span class="vault__line">'
            + '<a href="#" class="vault__title evidence__open" data-id="' + row.id + '" title="' + esc(row.evidence_title) + '">' + esc(row.evidence_title) + '</a>'
            + '<span class="vault__file" title="' + esc(row.file_name) + '">' + esc(row.file_name) + ' &middot; ' + esc(extension_of(row.file_name)) + '</span>'
            + '</span></td>'
            + '<td class="vault__col-size">' + size_label(row.file_size) + '</td>'
            + '<td class="vault__col-date">' + esc(row.date_created_display) + '</td>'
            + '<td class="vault__col-links"><span class="chip' + (used > 0 ? '' : ' chip--empty') + '">' + used + '</span></td>'
            + '<td class="pick__col-attach">' + (already
                ? '<span class="badge badge--active">Attached</span>'
                : '<button type="button" class="btn btn--secondary btn--sm" data-action="attach" data-id="' + row.id + '">Attach</button>')
            + '</td></tr>';
    }

    function mark_sort() {
        $('#portal_attach_modal .pick__sort').removeClass('is-asc is-desc');
        $('#portal_attach_modal .pick__sort[data-sort="' + pick.sort + '"]').addClass(pick.dir === 'asc' ? 'is-asc' : 'is-desc');
    }

    function pick_render() {

        var rows = pick_visible();
        var attached = attached_ids();
        var html = '';

        for (var i = 0; i < rows.length; i++) {
            html += pick_row(rows[i], attached);
        }

        $('#pick_list').html(html);
        $('#pick_table').toggle(rows.length > 0);
        $('#pick_empty').prop('hidden', rows.length > 0).text(pick_empty_text());
        $('#pick_count').text(pick.evidence.length + (pick.evidence.length === 1 ? ' file' : ' files'));
        mark_sort();
    }

    function pick_open(mode, button) {

        pick.mode = mode;
        $('#portal_attach_modal .modal-dialog').toggleClass('pick--wide', mode === 'poam');
        pick.folders = [];
        pick.evidence = [];
        pick.folder = 0;
        pick.sort = 'date_created';
        pick.dir = 'desc';

        $('#pick_search').val('');
        $('#pick_list').empty();
        pick_render_tree();
        pick_render();
        set_loading(button, true);

        var pending = 2;

        function finish() {

            pending--;

            if (pending > 0) {
                return;
            }

            set_loading(button, false);
            pick_render_tree();
            pick_render();
            modal('portal_attach_modal').show();
        }

        ApiDataSvc.apiCall('post', 'portal_folders', {}, function (data) {
            pick.folders = JSON.parse(data);
            finish();
        }, function () {
            set_loading(button, false);
        });

        ApiDataSvc.apiCall('post', 'portal_evidence', {}, function (data) {
            pick.evidence = JSON.parse(data);
            finish();
        }, function () {
            set_loading(button, false);
        });
    }

    $('#do_attach_open').click(function () {
        pick_open('control', this);
    });

    $('#pick_tree').on('click', '.vault__link', function () {
        pick.folder = parseInt($(this).attr('data-id'), 10);
        pick_render_tree();
        pick_render();
    });

    $('#pick_search').on('input', pick_render);

    $(document).on('click', '#portal_attach_modal .is-sortable', function () {

        var column = $(this).attr('data-sort');

        if (pick.sort === column) {
            pick.dir = (pick.dir === 'asc') ? 'desc' : 'asc';
        } else {
            pick.sort = column;
            pick.dir = (column === 'evidence_title') ? 'asc' : 'desc';
        }

        pick_render();
    });

    function link_target(evidence_id, done) {

        if (pick.mode === 'poam') {
            ApiDataSvc.apiCall('post', 'portal_poam_evidence_link', {
                item_id: poam.payload.item.id,
                evidence_id: evidence_id
            }, done);
            return;
        }

        ApiDataSvc.apiCall('post', 'portal_link_evidence', {
            item_id: current_item.id,
            evidence_id: evidence_id
        }, done);
    }

    function refresh_target() {

        if (pick.mode === 'poam') {
            poam.changed = true;
            poam_reload_item();
            return;
        }

        load_item_evidence();
        load();
    }

    $('#pick_list').on('click', '[data-action="attach"]', function () {

        var button = this;

        set_loading(button, true);

        link_target($(this).attr('data-id'), function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {
                modal('portal_attach_modal').hide();
                toastr.success(obj.message);
                refresh_target();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    function file_title(name) {

        var at = String(name).lastIndexOf('.');

        return (at > 0 ? String(name).slice(0, at) : String(name)).replace(/[_-]+/g, ' ').trim();
    }

    var uploaded = 0;

    function upload_next(files, index, done) {

        if (index >= files.length) {
            done();
            return;
        }

        var form_data = new FormData();

        form_data.append('folder_id', 0);
        form_data.append('evidence_file', files[index]);
        form_data.append('evidence_title', file_title(files[index].name));
        form_data.append('description', '');
        form_data.append('csrf_token', $('meta[name="csrf-token"]').attr('content'));

        $.ajax({
            url: '/api/portal_upload_evidence',
            type: 'POST',
            data: form_data,
            processData: false,
            contentType: false,
            success: function (data) {

                var obj = JSON.parse(data);

                if (!obj.success) {
                    toastr.error(files[index].name + ': ' + obj.message);
                    upload_next(files, index + 1, done);
                    return;
                }

                link_target(obj.evidence_id, function (linked) {

                    var res = JSON.parse(linked);

                    if (res.success) {
                        uploaded++;
                    } else {
                        toastr.error(files[index].name + ': ' + res.message);
                    }

                    upload_next(files, index + 1, done);
                });
            },
            error: function () {
                toastr.error(files[index].name + ' could not be uploaded.');
                upload_next(files, index + 1, done);
            }
        });
    }

    function upload_files(mode, input, button) {

        var files = Array.prototype.slice.call(input.files || []);

        if (files.length === 0) {
            return;
        }

        pick.mode = mode;
        uploaded = 0;
        set_loading(button, true);

        upload_next(files, 0, function () {

            set_loading(button, false);

            if (uploaded > 0) {
                toastr.success(uploaded === 1 ? 'Evidence uploaded' : uploaded + ' files uploaded');
            }

            refresh_target();
        });
    }

    $('#do_item_upload_open').click(function () {
        $('#item_upload_file').val('').trigger('click');
    });

    $('#item_upload_file').on('change', function () {
        upload_files('control', this, '#do_item_upload_open');
    });

    var poam = {
        items: [],
        payload: null,
        changed: false
    };
    var POAM_STATUS_CLASS = {
        'Open': 'badge--prospect',
        'In Progress': 'badge--onboarding',
        'Ready for Validation': 'badge--admin',
        'Closed': 'badge--active'
    };
    var POAM_MILESTONE_CLASS = {
        'Not Started': 'badge--inactive',
        'In Progress': 'badge--onboarding',
        'Completed': 'badge--active'
    };

    function poam_badge(value, classes) {
        return '<span class="badge ' + (classes[value] || 'badge--inactive') + '">' + esc(value) + '</span>';
    }

    function poam_date(value) {

        if (!value || String(value).indexOf('0000') === 0) {
            return '';
        }

        var parts = String(value).slice(0, 10).split('-');
        var date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));

        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function poam_set_empty(text, loading) {
        $('#poam_empty').html(loading ? '<i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> ' + esc(text) : esc(text)).prop('hidden', false);
    }

    function poam_load() {

        poam_set_empty('Loading items…', true);
        $('#poam_table').prop('hidden', true);

        ApiDataSvc.apiCall('post', 'portal_poam_list', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                poam_set_empty(obj.message, false);
                toastr.error(obj.message);
                return;
            }

            poam.items = obj.items;
            poam_render();
        }, function () {
            poam_set_empty('The register could not be loaded.', false);
        });
    }

    function poam_visible() {

        var term = $('#poam_search').val().trim().toLowerCase();
        var status = $('#poam_status_filter').val() || '';
        var out = [];

        for (var i = 0; i < poam.items.length; i++) {

            var item = poam.items[i];
            var haystack = [
                item.poam_ref,
                item.source_ref,
                item.source_title,
                item.weakness,
                item.owner_name
            ].join(' ').toLowerCase();

            if (status !== '' && item.poam_status !== status) {
                continue;
            }

            if (term !== '' && haystack.indexOf(term) === -1) {
                continue;
            }

            out.push(item);
        }

        return out;
    }

    function poam_row(item) {

        var owner = item.owner_name ? esc(item.owner_name) : '<span class="poam__muted">Not assigned</span>';
        var scheduled = item.scheduled_completion_date ? '<span class="' + (item.overdue ? 'poam__overdue' : '') + '">' + esc(poam_date(item.scheduled_completion_date)) + '</span>' : '';

        return '<tr class="rowlink poam__row" tabindex="0" data-id="' + item.id + '">'
            + '<td class="poam__col-id">' + esc(item.poam_ref) + '</td>'
            + '<td class="poam__col-control" title="' + esc(item.source_title) + '">' + esc(item.source_ref) + '</td>'
            + '<td class="poam__clip" title="' + esc(item.weakness) + '">' + esc(item.weakness) + '</td>'
            + '<td class="poam__col-owner poam__clip">' + owner + '</td>'
            + '<td class="poam__col-date">' + scheduled + '</td>'
            + '<td class="poam__col-status">' + poam_badge(item.poam_status, POAM_STATUS_CLASS) + '</td>'
            + '</tr>';
    }

    function poam_render() {

        var rows = poam_visible();
        var html = '';

        for (var i = 0; i < rows.length; i++) {
            html += poam_row(rows[i]);
        }

        $('#poam_list').html(html);
        $('#poam_table').prop('hidden', rows.length === 0);

        if (rows.length > 0) {
            $('#poam_empty').prop('hidden', true);
            return;
        }

        poam_set_empty(poam.items.length === 0 ? 'No POA&M items' : 'No items match.', false);
    }

    $('#do_poam_open').click(function () {
        $('#poam_search').val('');
        $('#poam_status_filter').val('');
        modal('poam_modal').show();
        poam_load();
    });

    $('#poam_search').on('input', function () {
        poam_render();
    });

    $('#poam_status_filter').on('change', function () {
        poam_render();
    });

    $('#poam_list').on('click', '.poam__row', function () {
        poam_open_item($(this).attr('data-id'));
    });

    $('#poam_list').on('keydown', '.poam__row', function (event) {

        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        poam_open_item($(this).attr('data-id'));
    });

    $('#poam_item_modal').on('hidden.bs.modal', function () {

        if (!poam.changed) {
            return;
        }

        poam.changed = false;
        poam_load();
    });

    function poam_open_item(item_id) {

        var row = $('#poam_list .poam__row[data-id="' + item_id + '"]');

        row.addClass('is-loading');

        ApiDataSvc.apiCall('post', 'portal_poam_item_load', { item_id: item_id }, function (data) {

            var obj = JSON.parse(data);

            row.removeClass('is-loading');

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            if (!$('#poam_item_modal').hasClass('show')) {
                modal('poam_item_modal').show();
            }

            poam_fill(obj);
        }, function () {
            row.removeClass('is-loading');
        });
    }

    function poam_reload_item() {

        ApiDataSvc.apiCall('post', 'portal_poam_item_load', { item_id: poam.payload.item.id }, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            poam_fill(obj);
        });
    }

    function poam_is_closed() {
        return poam.payload.item.poam_status === 'Closed';
    }

    function poam_fill(obj) {

        var item = obj.item;

        poam.payload = obj;

        $('#poam_item_modal_title').text(item.poam_ref);
        $('#poam_item_control').text(item.source_ref);
        $('#poam_item_title').text(item.source_title);
        $('#poam_item_status').html(poam_badge(item.poam_status, POAM_STATUS_CLASS));
        $('#poam_item_priority').text(item.priority !== '' ? 'Remediation Priority: ' + item.priority : '').prop('hidden', item.priority === '');

        $('#poam_weakness').text(item.weakness).closest('.form-group').prop('hidden', item.weakness === '');
        $('#poam_action').text(item.action).closest('.form-group').prop('hidden', item.action === '');
        $('#poam_owner').val(item.owner_name || '');
        $('#poam_resources').val(item.resources || '');
        $('#poam_status').val(poam_is_closed() ? 'Open' : item.poam_status);
        $('#poam_comments').val(item.comments || '');

        $('#poam_item_fields .is-invalid').removeClass('is-invalid');
        $('#poam_item_fields').find('input, textarea, select').prop('disabled', poam_is_closed());
        $('#poam_status').closest('.form-group').prop('hidden', poam_is_closed());

        $('#do_poam_save').prop('hidden', poam_is_closed());
        $('#poam_item_dismiss').text(poam_is_closed() ? 'Close' : 'Cancel');
        $('#do_poam_milestone_add').prop('hidden', poam_is_closed());
        $('#poam_ev_actions').prop('hidden', poam_is_closed());

        poam_render_milestones();
        poam_render_evidence();
    }

    function poam_render_evidence() {

        var list = poam.payload.evidence;
        var html = '';

        for (var i = 0; i < list.length; i++) {
            html += '<li class="evidence__item">'
                + '<div class="poam__ev"><span class="poam__ev-title">' + esc(list[i].evidence_title) + '</span><span class="poam__ev-file">' + esc(poam_date(list[i].date_created)) + '</span></div>'
                + '<span class="badge ' + (list[i].evidence_role === 'Closure' ? 'badge--active' : 'badge--prospect') + '">' + esc(list[i].evidence_role) + '</span>'
                + (list[i].can_unlink && !poam_is_closed() ? '<button type="button" class="btn btn--secondary btn--sm" data-action="poam_ev_unlink" data-id="' + list[i].id + '">Unlink</button>' : '')
                + '</li>';
        }

        $('#poam_ev_list').html(html).prop('hidden', list.length === 0);
        $('#poam_ev_empty').prop('hidden', list.length > 0);
    }

    $('#do_poam_ev_attach_open').click(function () {
        pick_open('poam', this);
    });

    $('#do_poam_ev_upload_open').click(function () {
        $('#poam_ev_upload_file').val('').trigger('click');
    });

    $('#poam_ev_upload_file').on('change', function () {
        upload_files('poam', this, '#do_poam_ev_upload_open');
    });

    $('#poam_ev_list').on('click', '[data-action="poam_ev_unlink"]', function () {

        var button = this;

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'portal_poam_evidence_unlink', { link_id: $(this).attr('data-id') }, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            poam.changed = true;
            poam_reload_item();
        }, function () {
            set_loading(button, false);
        });
    });

    function poam_value(selector) {
        return String($(selector).val() || '').trim();
    }

    $('#do_poam_save').click(function () {

        var button = this;
        var obj = {
            item_id: poam.payload.item.id,
            owner_name: poam_value('#poam_owner'),
            resources: poam_value('#poam_resources'),
            poam_status: poam_value('#poam_status'),
            comments: poam_value('#poam_comments')
        };

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'portal_poam_item_save', obj, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            poam.changed = true;
            poam_reload_item();
        }, function () {
            set_loading(button, false);
        });
    });

    function poam_milestone_view(milestone) {

        var actions = poam_is_closed() ? '' : '<button type="button" class="btn btn--icon btn--sm" data-action="ms_edit" data-id="' + milestone.id + '" aria-label="Edit milestone" title="Edit"><i class="fa-regular fa-pen"></i></button>'
            + '<button type="button" class="btn btn--icon btn--sm" data-action="ms_delete" data-id="' + milestone.id + '" aria-label="Delete milestone" title="Delete"><i class="fa-regular fa-trash"></i></button>';

        return '<tr class="poam__ms" data-id="' + milestone.id + '">'
            + '<td class="poam__ms-text">' + esc(milestone.milestone_text) + '</td>'
            + '<td class="poam__ms-date">' + esc(poam_date(milestone.due_date)) + '</td>'
            + '<td class="poam__ms-date">' + esc(poam_date(milestone.completed_date)) + '</td>'
            + '<td class="poam__ms-status">' + poam_badge(milestone.milestone_status, POAM_MILESTONE_CLASS) + '</td>'
            + '<td class="poam__ms-actions">' + actions + '</td>'
            + '</tr>';
    }

    function poam_milestone_form(milestone) {

        var statuses = poam.payload.milestone_statuses;
        var options = '';

        for (var i = 0; i < statuses.length; i++) {
            options += '<option value="' + esc(statuses[i]) + '"' + (statuses[i] === milestone.milestone_status ? ' selected' : '') + '>' + esc(statuses[i]) + '</option>';
        }

        return '<tr class="poam__ms poam__ms--edit" data-id="' + milestone.id + '">'
            + '<td class="poam__ms-text"><input type="text" class="form-control" data-field="milestone_text" aria-label="Milestone" maxlength="2000" value="' + esc(milestone.milestone_text) + '"></td>'
            + '<td class="poam__ms-date"><input type="date" class="form-control" data-field="due_date" aria-label="Due date" placeholder="Due" value="' + esc(milestone.due_date || '') + '"></td>'
            + '<td class="poam__ms-date"><input type="date" class="form-control" data-field="completed_date" aria-label="Completed date" placeholder="Completed" max="' + esc(new Date().toISOString().slice(0, 10)) + '" value="' + esc(milestone.completed_date || '') + '"></td>'
            + '<td class="poam__ms-status"><select class="form-control" data-field="milestone_status" data-style="form-control" aria-label="Status">' + options + '</select></td>'
            + '<td class="poam__ms-actions">'
            + '<button type="button" class="btn btn--icon btn--sm" data-action="ms_save" data-id="' + milestone.id + '" aria-label="Save milestone" title="Save"><i class="fa-regular fa-check"></i></button>'
            + '<button type="button" class="btn btn--icon btn--sm" data-action="ms_cancel" aria-label="Cancel" title="Cancel"><i class="fa-regular fa-xmark"></i></button>'
            + '</td>'
            + '</tr>';
    }

    function poam_render_milestones() {

        var list = poam.payload.milestones;
        var html = '';

        for (var i = 0; i < list.length; i++) {
            html += poam_milestone_view(list[i]);
        }

        $('#poam_milestones').html(html);
        $('#poam_milestones_table').prop('hidden', list.length === 0);
        $('#poam_milestones_empty').prop('hidden', list.length > 0);
    }

    function poam_find_milestone(milestone_id) {

        var list = poam.payload.milestones;

        for (var i = 0; i < list.length; i++) {
            if (parseInt(list[i].id, 10) === parseInt(milestone_id, 10)) {
                return list[i];
            }
        }

        return null;
    }

    function poam_edit_row(row) {
        date_pickers(row);
        row.find('[data-field="milestone_text"]').trigger('focus');
    }

    $('#do_poam_milestone_add').click(function () {

        poam_render_milestones();

        var row = $(poam_milestone_form({
            id: 0,
            milestone_text: '',
            due_date: '',
            completed_date: '',
            milestone_status: 'Not Started'
        }));

        $('#poam_milestones').append(row);
        $('#poam_milestones_table').prop('hidden', false);
        $('#poam_milestones_empty').prop('hidden', true);
        poam_edit_row(row);
    });

    $('#poam_milestones').on('click', '[data-action="ms_edit"]', function () {

        poam_render_milestones();

        var milestone = poam_find_milestone($(this).attr('data-id'));

        if (milestone === null) {
            return;
        }

        var row = $(poam_milestone_form(milestone));

        $('#poam_milestones .poam__ms[data-id="' + milestone.id + '"]').replaceWith(row);
        poam_edit_row(row);
    });

    $('#poam_milestones').on('click', '[data-action="ms_cancel"]', function () {
        poam_render_milestones();
    });

    $('#poam_milestones').on('click', '[data-action="ms_save"]', function () {

        var button = this;
        var row = $(this).closest('.poam__ms');
        var obj = {
            item_id: poam.payload.item.id,
            milestone_id: $(this).attr('data-id'),
            milestone_text: String(row.find('[data-field="milestone_text"]').val() || '').trim(),
            due_date: String(row.find('[data-field="due_date"]').val() || '').trim(),
            completed_date: String(row.find('[data-field="completed_date"]').val() || '').trim(),
            milestone_status: String(row.find('select[data-field="milestone_status"]').val() || '').trim()
        };

        row.find('[data-field="milestone_text"]').removeClass('is-invalid');

        if (obj.milestone_text === '') {
            row.find('[data-field="milestone_text"]').addClass('is-invalid');
            toastr.error('Describe the milestone');
            return;
        }

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'portal_poam_milestone_save', obj, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            poam.changed = true;
            poam_reload_item();
        }, function () {
            set_loading(button, false);
        });
    });

    $('#poam_milestones').on('click', '[data-action="ms_delete"]', function () {

        var button = this;

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'portal_poam_milestone_delete', { milestone_id: $(this).attr('data-id') }, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            poam.changed = true;
            poam_reload_item();
        }, function () {
            set_loading(button, false);
        });
    });

    function load(){
        ApiDataSvc.apiCall('post', 'portal_items', { assessment_id: assessment_id }, function (data) {
            items = JSON.parse(data);
            render_families();
            render_rollup();
            render();
        });
    }

    load();

});
</script>

<script>
$(document).ready(function () {

    var vault_user_id = <?php echo (int) Session::get('user_id'); ?>;
    var vault_folders = [];
    var vault_evidence = [];
    var vault_folder = 0;

    function vault_size_label(bytes) {

        var n = parseInt(bytes, 10);

        if (isNaN(n) || n <= 0) {
            return '';
        }
        if (n < 1024) {
            return n + ' B';
        }
        if (n < 1048576) {
            return Math.round(n / 1024) + ' KB';
        }
        return (n / 1048576).toFixed(1) + ' MB';
    }

    function vault_extension_of(name) {

        var at = String(name || '').lastIndexOf('.');

        return at === -1 ? '' : String(name).slice(at + 1).toUpperCase().slice(0, 5);
    }

    function vault_children_of(parent_id) {

        var out = [];

        for (var i = 0; i < vault_folders.length; i++) {
            if (parseInt(vault_folders[i].parent_id, 10) === parseInt(parent_id, 10)) {
                out.push(vault_folders[i]);
            }
        }

        return out;
    }

    function vault_count_of(id) {

        var total = 0;

        for (var i = 0; i < vault_evidence.length; i++) {
            if (parseInt(id, 10) === 0 || parseInt(vault_evidence[i].folder_id, 10) === parseInt(id, 10)) {
                total++;
            }
        }

        return '<span class="vault__count' + (total === 0 ? ' vault__count--empty' : '') + '">' + total + '</span>';
    }

    function vault_folder_name_of(id) {

        for (var i = 0; i < vault_folders.length; i++) {
            if (parseInt(vault_folders[i].id, 10) === parseInt(id, 10)) {
                return vault_folders[i].folder_name;
            }
        }

        return 'All Evidence';
    }

    function vault_tree_branch(parent_id, depth) {

        var html = '';
        var kids = vault_children_of(parent_id);

        for (var i = 0; i < kids.length; i++) {
            html += '<li class="vault__item' + (vault_folder === parseInt(kids[i].id, 10) ? ' is-on' : '') + '" style="--tree-depth:' + depth + '">'
                + '<button type="button" class="vault__link" data-id="' + kids[i].id + '">'
                + '<i class="fa-regular fa-folder"></i><span class="vault__name">' + esc(kids[i].folder_name) + '</span>'
                + vault_count_of(kids[i].id) + '</button></li>'
                + vault_tree_branch(kids[i].id, depth + 1);
        }

        return html;
    }

    function vault_render_tree() {
        $('#vault_folder_tree').html('<li class="vault__item' + (vault_folder === 0 ? ' is-on' : '') + '" style="--tree-depth:0">'
            + '<button type="button" class="vault__link" data-id="0">'
            + '<i class="fa-regular fa-inbox"></i><span class="vault__name">All Evidence</span>'
            + vault_count_of(0) + '</button></li>'
            + vault_tree_branch(0, 0));
    }

    function vault_visible() {

        var term = $('#vault_search').val().trim().toLowerCase();
        var out = [];

        for (var i = 0; i < vault_evidence.length; i++) {

            var e = vault_evidence[i];

            if (vault_folder !== 0 && parseInt(e.folder_id, 10) !== vault_folder) {
                continue;
            }

            if (term !== '' && (e.evidence_title + ' ' + e.file_name).toLowerCase().indexOf(term) === -1) {
                continue;
            }

            out.push(e);
        }

        return out;
    }

    function vault_render() {

        var rows = vault_visible();
        var html = '';

        for (var i = 0; i < rows.length; i++) {

            var e = rows[i];
            var links = parseInt(e.link_count, 10);
            var ext = vault_extension_of(e.file_name);

            html += '<tr class="vault__row" data-id="' + e.id + '">'
                + '<td class="vault__cell-name"><span class="vault__line">'
                + '<a href="#" class="vault__title evidence__open" data-id="' + e.id + '" title="' + esc(e.evidence_title) + '">' + esc(e.evidence_title) + '</a>'
                + '<span class="vault__file" title="' + esc(e.file_name) + '">' + esc(e.file_name) + (ext === '' ? '' : ' &middot; ' + esc(ext)) + '</span>'
                + '</span></td>'
                + '<td class="vault__col-size">' + vault_size_label(e.file_size) + '</td>'
                + '<td class="vault__col-date">' + esc(e.date_created_display) + '</td>'
                + '<td class="vault__col-links">' + (links > 0
                    ? '<span class="chip">' + links + '</span>'
                    : '<span class="chip chip--empty">0</span>') + '</td>'
                + '<td>' + esc(e.uploaded_by_name === null ? '' : e.uploaded_by_name) + '</td>'
                + '<td class="vault__col-actions">' + (parseInt(e.uploaded_by, 10) === vault_user_id && links === 0
                    ? '<button type="button" class="btn btn--tertiary btn--sm" data-action="remove" data-id="' + e.id + '" data-title="' + esc(e.evidence_title) + '" title="Remove" aria-label="Remove ' + esc(e.evidence_title) + '"><i class="fa-regular fa-trash-can"></i></button>'
                    : '') + '</td>'
                + '</tr>';
        }

        $('#vault_body').html(html);
        $('#vault_table').toggle(rows.length > 0);
        $('#vault_empty').prop('hidden', rows.length > 0).text(vault_evidence.length === 0
            ? 'No evidence has been collected yet.'
            : ($('#vault_search').val().trim() === ''
                ? vault_folder_name_of(vault_folder) + ' has no evidence yet.'
                : 'No files match your search.'));
    }

    function vault_load(done) {

        var pending = 2;

        function finish() {

            pending--;

            if (pending > 0) {
                return;
            }

            vault_render_tree();
            vault_render();

            if (done) {
                done();
            }
        }

        ApiDataSvc.apiCall('post', 'portal_folders', {}, function (data) {
            vault_folders = JSON.parse(data);
            finish();
        });

        ApiDataSvc.apiCall('post', 'portal_evidence', {}, function (data) {
            vault_evidence = JSON.parse(data);
            finish();
        });
    }

    $('#do_vault_open').click(function () {

        vault_folder = 0;
        $('#vault_search').val('');
        set_loading('#do_vault_open', true);

        vault_load(function () {
            set_loading('#do_vault_open', false);
            modal('vault_modal').show();
        });
    });

    $('#vault_folder_tree').on('click', '.vault__link', function () {
        vault_folder = parseInt($(this).attr('data-id'), 10);
        vault_render_tree();
        vault_render();
    });

    $('#vault_search').on('input', vault_render);

    $('#vault_body').on('click', '.evidence__open', function (e) {

        e.preventDefault();

        var link = this;

        set_loading(link, true);

        ApiDataSvc.apiCall('post', 'portal_evidence_url', { evidence_id: $(this).attr('data-id') }, function (data) {

            var obj = JSON.parse(data);

            set_loading(link, false);

            if (obj.success) {
                window.open(obj.url, '_blank', 'noopener');
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#vault_body').on('click', '[data-action="remove"]', function () {
        $('#vault_remove_id').val($(this).attr('data-id'));
        $('#vault_remove_title').text($(this).attr('data-title'));
        modal('vault_remove_modal').show();
    });

    $('#do_vault_remove').click(function () {

        set_loading('#do_vault_remove', true);

        ApiDataSvc.apiCall('post', 'portal_delete_evidence', { evidence_id: $('#vault_remove_id').val() }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                vault_load(function () {
                    set_loading('#do_vault_remove', false);
                    modal('vault_remove_modal').hide();
                    toastr.success(obj.message);
                });
            } else {
                set_loading('#do_vault_remove', false);
                toastr.error(obj.message);
            }
        });
    });

    $('#do_vault_upload_open').click(function () {
        $('#vault_evidence_title').val('').removeClass('is-invalid');
        $('#vault_evidence_description').val('');
        $('#vault_evidence_file').val('').removeClass('is-invalid');
        $('#vault_upload_folder').text(vault_folder_name_of(vault_folder));
        modal('vault_upload_modal').show();
    });

    $('#do_vault_upload').click(function () {

        if ($('#vault_evidence_title').val().trim() === '') {
            $('#vault_evidence_title').addClass('is-invalid');
            toastr.error('Evidence title is required');
            return;
        }

        if ($('#vault_evidence_file')[0].files.length === 0) {
            $('#vault_evidence_file').addClass('is-invalid');
            toastr.error('Choose a file to upload');
            return;
        }

        var form_data = new FormData();

        form_data.append('folder_id', vault_folder);
        form_data.append('evidence_title', $('#vault_evidence_title').val().trim());
        form_data.append('description', $('#vault_evidence_description').val().trim());
        form_data.append('evidence_file', $('#vault_evidence_file')[0].files[0]);

        var csrf = document.querySelector('meta[name="csrf-token"]');

        if (csrf) {
            form_data.append('csrf_token', csrf.getAttribute('content'));
        }

        set_loading('#do_vault_upload', true);

        $.ajax({
            url: '/api/portal_upload_evidence',
            type: 'POST',
            data: form_data,
            processData: false,
            contentType: false,
            success: function (data) {

                var obj = JSON.parse(data);

                if (!obj.success) {
                    set_loading('#do_vault_upload', false);
                    toastr.error(obj.message);
                    return;
                }

                vault_load(function () {
                    set_loading('#do_vault_upload', false);
                    modal('vault_upload_modal').hide();
                    toastr.success(obj.message);
                });
            },
            error: function () {
                set_loading('#do_vault_upload', false);
                toastr.error('That file could not be uploaded');
            }
        });
    });

});
</script>
