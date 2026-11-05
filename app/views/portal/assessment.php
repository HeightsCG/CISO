<div class="page">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title"><?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>
        <div class="headline__actions">
            <a class="btn btn--secondary btn--pill" href="/portal"><i class="fa-regular fa-chevron-left"></i> Back</a>
            <a class="btn btn--secondary btn--pill" href="/portal/evidence"><i class="fa-regular fa-folder-open"></i> Evidence</a>
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

<div class="modal fade" data-bs-backdrop="static" id="portal_item_modal" tabindex="-1" aria-labelledby="portal_item_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="portal_item_modal_title">Control</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="assess__eyebrow"><span class="assess__id" id="item_identifier"></span> <span class="assess__sep" aria-hidden="true">&middot;</span> <span class="assess__family" id="item_family"></span></p>
                <h3 class="assess__title" id="item_title"></h3>
                <p class="assess__text" id="item_description"></p>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label>Result</label>
                        <div><span class="badge" id="item_result"></span></div>
                    </div>
                </div>
                <h3 class="record__group-title">Evidence</h3>
                <ul class="evidence__list" id="item_evidence"></ul>
                <div class="assess__actions">
                    <button type="button" class="btn btn--secondary btn--sm" id="do_attach_open"><i class="fa-regular fa-link"></i> Attach from Evidence</button>
                    <button type="button" class="btn btn--secondary btn--sm" id="do_item_upload_open"><i class="fa-regular fa-arrow-up-from-bracket"></i> Upload New</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="portal_attach_modal" tabindex="-1" aria-labelledby="portal_attach_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="portal_attach_modal_title">Attach Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="search" id="attach_search" class="input pick__search mb-4" placeholder="Search title or file name..." autocomplete="off" spellcheck="false">
                <table class="data data--light" id="attach_table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col" class="vault__col-date">Uploaded</th>
                            <th scope="col"><span class="visually-hidden">Attach</span></th>
                        </tr>
                    </thead>
                    <tbody id="attach_body"></tbody>
                </table>
                <p class="vault__empty" id="attach_empty" hidden></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="portal_upload_modal" tabindex="-1" aria-labelledby="portal_upload_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="portal_upload_modal_title">Upload Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="import__hint">Saved to your evidence and attached to <strong id="upload_for"></strong>. Documents, images and archives up to 25MB.</p>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="evidence_title">Title <abbr title="required">*</abbr></label>
                        <input type="text" class="form-control" id="evidence_title" placeholder="Information security policy, signed">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="evidence_description">Description</label>
                        <textarea class="form-control" id="evidence_description" rows="3" placeholder="What this shows and where it came from."></textarea>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="evidence_file">File <abbr title="required">*</abbr></label>
                        <input type="file" class="form-control" id="evidence_file">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_upload" class="btn btn--primary">Upload Evidence</button>
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
                                <th scope="col" class="poam__col-owner">Owner</th>
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
                        <div class="col-md-4 form-group mb-3">
                            <label for="poam_owner">Owner</label>
                            <input type="text" class="form-control" id="poam_owner" maxlength="200">
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label for="poam_resources">Resources</label>
                            <input type="text" class="form-control" id="poam_resources">
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label for="poam_status">Status</label>
                            <select class="form-control" id="poam_status" data-style="form-control">
                                <option value="Open">Open</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Ready for Validation">Ready for Validation</option>
                            </select>
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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" id="poam_item_dismiss" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_poam_save">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var assessment_id = <?php echo (int) $this->assessment['id']; ?>;
    var items = [];
    var vault = [];
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

        $('#item_identifier').text(current_item.control_identifier);
        $('#item_family').text(current_item.family === '' ? 'Ungrouped' : current_item.family);
        $('#item_title').text(current_item.control_title);
        $('#item_description').text(description_body(current_item.control_title, current_item.description));
        $('#item_result').text(current_item.item_result)
            .removeClass('badge--active badge--onboarding badge--critical badge--prospect badge--inactive')
            .addClass(badge_for(current_item.item_result));

        load_item_evidence();
        modal('portal_item_modal').show();
    });

    function load_item_evidence() {
        ApiDataSvc.apiCall('post', 'portal_item_evidence', { item_id: current_item.id }, function (data) {

            var rows = JSON.parse(data);
            var html = '';

            for (var i = 0; i < rows.length; i++) {
                html += '<li class="evidence__item" data-id="' + rows[i].id + '">'
                    + '<div><a href="#" class="evidence__open" data-id="' + rows[i].id + '">' + esc(rows[i].evidence_title) + '</a>'
                    + '<span class="evidence__meta">' + esc(rows[i].file_name) + ' &middot; ' + size_label(rows[i].file_size) + '</span></div>'
                    + '<button type="button" class="btn btn--tertiary btn--sm" data-action="unlink" data-id="' + rows[i].id + '">Detach</button>'
                    + '</li>';
            }

            $('#item_evidence').html(html === ''
                ? '<li class="evidence__none">Nothing attached to this control yet.</li>'
                : html);
        });
    }

    $('#item_evidence').on('click', '.evidence__open', function (e) {

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

    function attached_ids() {
        return $('#item_evidence .evidence__item').map(function () {
            return String($(this).attr('data-id'));
        }).get();
    }

    function render_attach() {

        var term = $('#attach_search').val().trim().toLowerCase();
        var attached = attached_ids();
        var html = '';
        var shown = 0;

        for (var i = 0; i < vault.length; i++) {

            var e = vault[i];

            if (term !== '' && (e.evidence_title + ' ' + e.file_name).toLowerCase().indexOf(term) === -1) {
                continue;
            }

            shown++;

            html += '<tr class="vault__row">'
                + '<td class="vault__cell-name"><span class="vault__line">'
                + '<span class="vault__title">' + esc(e.evidence_title) + '</span>'
                + '<span class="vault__file">' + esc(e.file_name) + '</span></span></td>'
                + '<td class="vault__col-date">' + esc(e.date_created_display) + '</td>'
                + '<td class="pick__col-attach">' + (attached.indexOf(String(e.id)) !== -1
                    ? '<span class="badge badge--active">Attached</span>'
                    : '<button type="button" class="btn btn--secondary btn--sm" data-action="attach" data-id="' + e.id + '">Attach</button>')
                + '</td></tr>';
        }

        $('#attach_body').html(html);
        $('#attach_table').toggle(shown > 0);
        $('#attach_empty').prop('hidden', shown > 0).text(vault.length === 0
            ? 'You have no evidence uploaded yet. Use Upload New.'
            : 'No files match your search.');
    }

    $('#do_attach_open').click(function () {
        $('#attach_search').val('');
        ApiDataSvc.apiCall('post', 'portal_evidence', {}, function (data) {
            vault = JSON.parse(data);
            render_attach();
            modal('portal_attach_modal').show();
        });
    });

    $('#attach_search').on('input', render_attach);

    $('#attach_body').on('click', '[data-action="attach"]', function () {

        var button = this;

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'portal_link_evidence', {
            item_id: current_item.id,
            evidence_id: $(this).attr('data-id')
        }, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {
                toastr.success(obj.message);
                load_item_evidence();
                load();
                modal('portal_attach_modal').hide();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_item_upload_open').click(function () {
        $('#evidence_title').val('').removeClass('is-invalid');
        $('#evidence_description').val('');
        $('#evidence_file').val('').removeClass('is-invalid');
        $('#upload_for').text(current_item.control_identifier);
        modal('portal_upload_modal').show();
    });

    $('#do_upload').click(function () {

        if ($('#evidence_title').val().trim() === '') {
            $('#evidence_title').addClass('is-invalid');
            toastr.error('Evidence title is required');
            return;
        }

        if ($('#evidence_file')[0].files.length === 0) {
            $('#evidence_file').addClass('is-invalid');
            toastr.error('Choose a file to upload');
            return;
        }

        var form_data = new FormData();

        form_data.append('folder_id', 0);
        form_data.append('evidence_title', $('#evidence_title').val().trim());
        form_data.append('description', $('#evidence_description').val().trim());
        form_data.append('evidence_file', $('#evidence_file')[0].files[0]);

        var csrf = document.querySelector('meta[name="csrf-token"]');

        if (csrf) {
            form_data.append('csrf_token', csrf.getAttribute('content'));
        }

        set_loading('#do_upload', true);

        $.ajax({
            url: '/api/portal_upload_evidence',
            type: 'POST',
            data: form_data,
            processData: false,
            contentType: false,
            success: function (data) {

                var obj = JSON.parse(data);

                if (!obj.success) {
                    set_loading('#do_upload', false);
                    toastr.error(obj.message);
                    return;
                }

                ApiDataSvc.apiCall('post', 'portal_link_evidence', {
                    item_id: current_item.id,
                    evidence_id: obj.evidence_id
                }, function (linked) {

                    var link_obj = JSON.parse(linked);

                    set_loading('#do_upload', false);
                    modal('portal_upload_modal').hide();

                    if (link_obj.success) {
                        toastr.success('Evidence uploaded and attached');
                    } else {
                        toastr.error(link_obj.message);
                    }

                    load_item_evidence();
                    load();
                });
            },
            error: function () {
                set_loading('#do_upload', false);
                toastr.error('That file could not be uploaded');
            }
        });
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

        poam_render_milestones();
    }

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
