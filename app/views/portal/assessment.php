<div class="page">
    <a class="page__back" href="/portal/project/id/<?php echo (int) $this->assessment['project_id']; ?>"><i class="fa-regular fa-arrow-left"></i> Back to <?php echo htmlspecialchars($this->assessment['project_name'], ENT_QUOTES, 'UTF-8'); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="client__meta">
                <span class="badge <?php echo ($this->assessment['assessment_status'] === 'Complete' ? 'badge--active' : ($this->assessment['assessment_status'] === 'In Progress' ? 'badge--onboarding' : 'badge--inactive')); ?>"><?php echo htmlspecialchars($this->assessment['assessment_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo htmlspecialchars($this->assessment['standard_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo htmlspecialchars($this->assessment['short_code'], ENT_QUOTES, 'UTF-8'); ?><?php echo ($this->assessment['version'] === '' ? '' : ' &middot; '.htmlspecialchars($this->assessment['version'], ENT_QUOTES, 'UTF-8')); ?></span>
            </div>
        </div>
        <div class="page__actions">
            <a class="btn btn--secondary" href="/portal/evidence/id/<?php echo (int) $this->assessment['project_id']; ?>"><i class="fa-regular fa-folder-open"></i> Evidence</a>
        </div>
    </div>

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
            <p class="controls__empty" id="items_empty">Loading controls&hellip;</p>
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
                <p class="import__hint">Saved to your project evidence and attached to <strong id="upload_for"></strong>. Documents, images and archives up to 25MB.</p>
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

<script>
$(document).ready(function () {

    var assessment_id = <?php echo (int) $this->assessment['id']; ?>;
    var project_id = <?php echo (int) $this->assessment['project_id']; ?>;
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

    /**
     * The imported control text opens by restating the title, so the modal would
     * print the same sentence twice. Only a leading match is removed - the rest of
     * the description is left exactly as supplied.
     */
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
        ApiDataSvc.apiCall('post', 'portal_evidence', { project_id: project_id }, function (data) {
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

        form_data.append('project_id', project_id);
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
