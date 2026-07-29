<div class="page">
    <a class="page__back" href="/projects/detail/id/<?php echo (int) $this->assessment['project_id']; ?>"><i class="fa-regular fa-arrow-left"></i> Back to <?php echo htmlspecialchars($this->assessment['project_name'], ENT_QUOTES, 'UTF-8'); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="client__meta">
                <span class="badge" id="status_badge"><?php echo htmlspecialchars($this->assessment['assessment_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo htmlspecialchars($this->assessment['standard_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo htmlspecialchars($this->assessment['short_code'], ENT_QUOTES, 'UTF-8'); ?><?php echo ($this->assessment['version'] === '' ? '' : ' &middot; '.htmlspecialchars($this->assessment['version'], ENT_QUOTES, 'UTF-8')); ?></span>
                <span class="client__segment"><?php echo (int) $this->assessment['item_count']; ?> items</span>
            </div>
        </div>
        <div class="page__actions">
            <?php echo ((int) $this->assessment['client_id'] === 0 ? '' : '<a class="btn btn--secondary" href="/clients/evidence/id/'.((int) $this->assessment['client_id']).'"><i class="fa-regular fa-folder-open"></i> Evidence Vault</a>'); ?>
            <button type="button" class="btn btn--secondary" id="do_settings_open"><i class="fa-regular fa-pen"></i> Edit</button>
            <button type="button" class="btn btn--destructive" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete</button>
        </div>
    </div>

    <div class="panel record__panel">
        <div class="panel__body">
            <div class="rollup" id="rollup"></div>
            <div class="progress progress--lg"><div class="progress__bar" id="rollup_bar" style="width:0%"></div></div>
            <p class="progress__label" id="rollup_label">&nbsp;</p>
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
                <div class="roster__filter">
                    <select id="evidence_filter" class="form-select">
                        <option value="">Any</option>
                        <option value="yes">Has evidence</option>
                        <option value="no">No evidence</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
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
            <p class="controls__empty" id="items_empty">Loading items&hellip;</p>
        </div>
    </div>
</div>

<div class="modal fade" id="item_modal" tabindex="-1" aria-labelledby="item_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="item_modal_title">Assess Control</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body assess">
                <div class="assess__pane assess__pane--form">
                    <div class="field">
                        <label for="item_result">Result</label>
                        <select id="item_result" class="form-select">
                            <option value="Not Assessed">Not Assessed</option>
                            <option value="Implemented">Implemented</option>
                            <option value="Partially Implemented">Partially Implemented</option>
                            <option value="Not Implemented">Not Implemented</option>
                            <option value="Not Applicable">Not Applicable</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="item_notes">Notes and observations</label>
                        <textarea id="item_notes" rows="6" placeholder="What was examined, who was interviewed, what was observed."></textarea>
                    </div>
                    <div class="assess__evidence">
                        <h3 class="record__group-title">Evidence</h3>
                        <ul class="evidence__list" id="item_evidence"></ul>
                        <div class="assess__actions">
                            <button type="button" class="btn btn--secondary btn--sm" id="do_attach_open"><i class="fa-regular fa-link"></i> Attach from Vault</button>
                            <button type="button" class="btn btn--secondary btn--sm" id="do_upload_open"><i class="fa-regular fa-arrow-up-from-bracket"></i> Upload New</button>
                        </div>
                    </div>
                </div>
                <div class="assess__pane assess__pane--control">
                    <p class="assess__eyebrow"><span class="assess__id" id="item_identifier"></span> <span class="assess__sep" aria-hidden="true">&middot;</span> <span class="assess__family" id="item_family"></span></p>
                    <h3 class="assess__title" id="item_title"></h3>
                    <p class="assess__text" id="item_description"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="do_item_save" class="btn btn--primary">Save Item</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="attach_modal" tabindex="-1" aria-labelledby="attach_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="attach_modal_title">Attach from Vault</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="search" id="vault_search" class="input" placeholder="Search the client's evidence..." autocomplete="off">
                <ul class="evidence__list evidence__list--pick" id="vault_list"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="upload_modal" tabindex="-1" aria-labelledby="upload_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="upload_modal_title">Upload Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="import__hint">The file is stored in this client&rsquo;s vault and attached to this control. It can be reused on any other control without uploading it again.</p>
                <div class="field">
                    <label for="evidence_title">Title <abbr title="required">*</abbr></label>
                    <input type="text" id="evidence_title" placeholder="Information security policy, signed">
                </div>
                <div class="field">
                    <label for="evidence_description">Description</label>
                    <textarea id="evidence_description" rows="3" placeholder="What this shows and where it came from."></textarea>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="evidence_expiry">Valid Until</label>
                        <input type="date" id="evidence_expiry">
                    </div>
                    <div class="field">
                        <label for="evidence_file">File <abbr title="required">*</abbr></label>
                        <input type="file" id="evidence_file" class="import__file">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_upload" class="btn btn--primary">Upload and Attach</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="settings_modal" tabindex="-1" aria-labelledby="settings_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="settings_modal_title">Edit Assessment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="field">
                    <label for="assessment_name">Name <abbr title="required">*</abbr></label>
                    <input type="text" id="assessment_name" value="<?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="field">
                    <label for="assessment_status">Status</label>
                    <select id="assessment_status" class="form-select">
                        <option value="Planned"<?php echo ($this->assessment['assessment_status'] === 'Planned' ? ' selected' : ''); ?>>Planned</option>
                        <option value="In Progress"<?php echo ($this->assessment['assessment_status'] === 'In Progress' ? ' selected' : ''); ?>>In Progress</option>
                        <option value="Complete"<?php echo ($this->assessment['assessment_status'] === 'Complete' ? ' selected' : ''); ?>>Complete</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_settings_save" class="btn btn--primary">Save Assessment</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete Assessment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong><?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></strong> and all <?php echo (int) $this->assessment['item_count']; ?> of its items, including results and notes? Evidence itself stays in the client&rsquo;s vault. This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete Assessment</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var assessment_id = <?php echo (int) $this->assessment['id']; ?>;
    var client_id = <?php echo (int) $this->assessment['client_id']; ?>;
    var items = [];
    var current_item = null;

    var RESULTS = ['Not Assessed', 'Implemented', 'Partially Implemented', 'Not Implemented', 'Not Applicable'];
    var RESULT_CLASS = {
        'Not Assessed': 'badge--inactive',
        'Implemented': 'badge--active',
        'Partially Implemented': 'badge--onboarding',
        'Not Implemented': 'badge--critical',
        'Not Applicable': 'badge--prospect'
    };

    function set_loading(target, loading) {
        if (loading) {
            $(target).addClass("is-loading").prop("disabled", true);
        } else {
            $(target).removeClass("is-loading").prop("disabled", false);
        }
    }

    function esc(value) {
        return $('<div>').text(value === null ? '' : value).html();
    }

    function modal(id) {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
    }

    function result_badge(value) {
        return '<span class="badge ' + (RESULT_CLASS[value] || 'badge--inactive') + '">' + esc(value) + '</span>';
    }

    function size_label(bytes) {
        var n = parseInt(bytes, 10) || 0;
        if (n < 1024) { return n + ' B'; }
        if (n < 1048576) { return Math.round(n / 1024) + ' KB'; }
        return (n / 1048576).toFixed(1) + ' MB';
    }

    /* --------------------------------------------------------------- roll-up */

    function rollup() {

        var counts = {};
        var evidenced = 0;

        for (var i = 0; i < RESULTS.length; i++) { counts[RESULTS[i]] = 0; }

        for (var j = 0; j < items.length; j++) {
            counts[items[j].item_result] = (counts[items[j].item_result] || 0) + 1;
            if (parseInt(items[j].evidence_count, 10) > 0) { evidenced++; }
        }

        var total = items.length;
        var assessed = total - counts['Not Assessed'];
        var pct = total > 0 ? Math.round(assessed / total * 100) : 0;
        var html = '';

        for (var k = 0; k < RESULTS.length; k++) {
            var n = counts[RESULTS[k]];
            html += '<div class="rollup__stat"><span class="rollup__n">' + n + '</span>'
                + '<span class="rollup__label">' + esc(RESULTS[k]) + '</span>'
                + '<span class="rollup__pct">' + (total > 0 ? Math.round(n / total * 100) : 0) + '%</span></div>';
        }

        html += '<div class="rollup__stat"><span class="rollup__n">' + evidenced + '</span>'
            + '<span class="rollup__label">With evidence</span>'
            + '<span class="rollup__pct">' + (total > 0 ? Math.round(evidenced / total * 100) : 0) + '%</span></div>';

        $('#rollup').html(html);
        $('#rollup_bar').css('width', pct + '%');
        $('#rollup_label').text(assessed + ' of ' + total + ' items assessed · ' + pct + '%');
    }

    /* ----------------------------------------------------------- item table */

    function render() {

        var html = '';
        var current = null;
        var families = [];

        for (var i = 0; i < items.length; i++) {

            var it = items[i];
            var family = it.family === '' ? 'Ungrouped' : it.family;

            if (family !== current) {
                current = family;
                families.push(family);
                html += '<tr class="controls__family" data-family="' + esc(family) + '"><td colspan="4">' + esc(family) + '</td></tr>';
            }

            html += '<tr class="item__row" data-id="' + it.id + '" data-family="' + esc(family) + '"'
                + ' data-result="' + esc(it.item_result) + '"'
                + ' data-evidence="' + (parseInt(it.evidence_count, 10) > 0 ? 'yes' : 'no') + '" tabindex="0">'
                + '<td class="controls__id">' + esc(it.control_identifier) + '</td>'
                + '<td><span class="controls__title">' + esc(it.control_title) + '</span></td>'
                + '<td>' + result_badge(it.item_result) + '</td>'
                + '<td class="item__evidence">' + (parseInt(it.evidence_count, 10) > 0
                    ? '<span class="badge badge--active">' + it.evidence_count + '</span>'
                    : '<span class="roster__none">&mdash;</span>') + '</td>'
                + '</tr>';
        }

        $('#items_body').html(html);

        var options = '<option value="">All families</option>';
        for (var f = 0; f < families.length; f++) {
            options += '<option value="' + esc(families[f]) + '">' + esc(families[f]) + '</option>';
        }
        $('#family_filter').html(options);

        rollup();
    }

    /**
     * Match state is recomputed from the row data every pass rather than read back
     * off the DOM: while the table itself is hidden nothing inside it counts as
     * visible, so a cleared filter could never restore the group headers.
     */
    function filter() {

        var term = $('#item_search').val().trim().toLowerCase();
        var family = $('#family_filter').val();
        var result = $('#result_filter').val();
        var evidence = $('#evidence_filter').val();
        var visible = 0;

        $('#items_body .controls__family').each(function () {

            var matched = 0;

            $(this).nextUntil('.controls__family', '.item__row').each(function () {

                var row = $(this);
                var text = row.find('.controls__id').text().toLowerCase()
                    + ' ' + row.find('.controls__title').text().toLowerCase();

                var hit = (term === '' || text.indexOf(term) !== -1)
                    && (family === '' || row.attr('data-family') === family)
                    && (result === '' || row.attr('data-result') === result)
                    && (evidence === '' || row.attr('data-evidence') === evidence);

                row.toggle(hit);

                if (hit) {
                    matched++;
                }
            });

            $(this).toggle(matched > 0);
            visible += matched;
        });

        $('#items_table').toggle(visible > 0);
        $('#items_empty').toggle(visible === 0);

        if (visible === 0) {
            $('#items_empty').text(items.length === 0
                ? 'This assessment has no items.'
                : 'No items match the current filters');
        }
    }

    function load(keep_open) {
        $.post('/projects/load_items', { assessment_id: assessment_id }, function (data) {
            items = JSON.parse(data);
            render();
            filter();
            if (keep_open && current_item) {
                for (var i = 0; i < items.length; i++) {
                    if (parseInt(items[i].id, 10) === parseInt(current_item.id, 10)) {
                        current_item = items[i];
                        break;
                    }
                }
            }
        });
    }

    $('#item_search').on('input', filter);
    $('#family_filter, #result_filter, #evidence_filter').on('change', filter);

    /* ---------------------------------------------------------- item editing */

    function open_item(id) {

        for (var i = 0; i < items.length; i++) {
            if (parseInt(items[i].id, 10) === parseInt(id, 10)) {
                current_item = items[i];
                break;
            }
        }

        if (!current_item) {
            return;
        }

        $('#item_identifier').text(current_item.control_identifier);
        $('#item_title').text(current_item.control_title);
        $('#item_family').text(current_item.family === '' ? 'Ungrouped' : current_item.family);
        $('#item_modal_title').text('Assess ' + current_item.control_identifier);
        $('#item_description').text(current_item.description === '' ? 'No control text was supplied for this item.' : current_item.description);
        $('#item_result').val(current_item.item_result);
        $('#item_notes').val(current_item.notes);

        load_item_evidence();
        modal('item_modal').show();
    }

    $('#items_body').on('click', '.item__row', function () {
        open_item($(this).attr('data-id'));
    });

    $('#items_body').on('keydown', '.item__row', function (e) {
        if (e.key === 'Enter') {
            open_item($(this).attr('data-id'));
        }
    });

    $('#do_item_save').click(function () {

        if (!current_item) {
            return;
        }

        set_loading('#do_item_save', true);

        var values = {
            item_id: current_item.id,
            item_result: $('#item_result').val(),
            notes: $('#item_notes').val().trim()
        };

        $.post('/projects/save_item', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_item_save', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            modal('item_modal').hide();
            toastr.success(obj.message);
            load();
        });
    });

    /* ------------------------------------------------------------- evidence */

    function load_item_evidence() {

        if (!current_item) {
            return;
        }

        $.post('/projects/load_item_evidence', { item_id: current_item.id }, function (data) {

            var list = JSON.parse(data);
            var html = '';

            for (var i = 0; i < list.length; i++) {
                html += '<li class="evidence__item" data-id="' + list[i].id + '">'
                    + '<div><a href="#" class="evidence__open" data-id="' + list[i].id + '">' + esc(list[i].evidence_title) + '</a>'
                    + '<span class="evidence__meta">' + esc(list[i].file_name) + ' · ' + size_label(list[i].file_size)
                    + (list[i].expiry_date_display ? ' · valid until ' + esc(list[i].expiry_date_display) : '')
                    + (parseInt(list[i].expired, 10) === 1 ? ' <span class="badge badge--critical">Expired</span>' : '')
                    + '</span></div>'
                    + '<button type="button" class="btn btn--tertiary btn--sm" data-action="detach" data-id="' + list[i].id + '" aria-label="Detach ' + esc(list[i].evidence_title) + '"><i class="fa-regular fa-link-slash"></i></button>'
                    + '</li>';
            }

            $('#item_evidence').html(html === '' ? '<li class="evidence__none">No evidence attached to this control yet.</li>' : html);
        });
    }

    $('#item_evidence').on('click', '[data-action="detach"]', function () {

        var evidence_id = $(this).attr('data-id');

        $.post('/projects/unlink_evidence', { item_id: current_item.id, evidence_id: evidence_id }, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            toastr.success(obj.message);
            load_item_evidence();
            load(true);
        });
    });

    $('#item_evidence, #vault_list').on('click', '.evidence__open', function (e) {

        e.preventDefault();

        $.post('/clients/evidence_url', { evidence_id: $(this).attr('data-id') }, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            window.open(obj.url, '_blank', 'noopener');
        });
    });

    $('#do_attach_open').click(function () {

        if (client_id === 0) {
            toastr.error('Assign a client to this project before attaching evidence.');
            return;
        }

        $('#vault_search').val('');
        load_vault();
        modal('attach_modal').show();
    });

    function load_vault() {

        $.post('/clients/load_evidence', { client_id: client_id }, function (data) {

            var list = JSON.parse(data);
            var term = $('#vault_search').val().trim().toLowerCase();
            var html = '';

            for (var i = 0; i < list.length; i++) {

                var hay = (list[i].evidence_title + ' ' + list[i].file_name).toLowerCase();

                if (term !== '' && hay.indexOf(term) === -1) {
                    continue;
                }

                html += '<li class="evidence__item">'
                    + '<div><a href="#" class="evidence__open" data-id="' + list[i].id + '">' + esc(list[i].evidence_title) + '</a>'
                    + '<span class="evidence__meta">' + esc(list[i].file_name) + ' · ' + size_label(list[i].file_size)
                    + ' · used on ' + list[i].link_count + ' control' + (parseInt(list[i].link_count, 10) === 1 ? '' : 's')
                    + (parseInt(list[i].expired, 10) === 1 ? ' <span class="badge badge--critical">Expired</span>' : '')
                    + '</span></div>'
                    + '<button type="button" class="btn btn--secondary btn--sm" data-action="attach" data-id="' + list[i].id + '">Attach</button>'
                    + '</li>';
            }

            $('#vault_list').html(html === '' ? '<li class="evidence__none">Nothing in this client\'s vault matches.</li>' : html);
        });
    }

    $('#vault_search').on('input', load_vault);

    $('#vault_list').on('click', '[data-action="attach"]', function () {

        var button = this;

        set_loading(button, true);

        $.post('/projects/link_evidence', { item_id: current_item.id, evidence_id: $(this).attr('data-id') }, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            toastr.success(obj.message);
            load_item_evidence();
            load(true);
        });
    });

    $('#do_upload_open').click(function () {

        if (client_id === 0) {
            toastr.error('Assign a client to this project before uploading evidence.');
            return;
        }

        $('#evidence_title, #evidence_description, #evidence_expiry').val('').removeClass('is-invalid');
        $('#evidence_file').val('');
        modal('upload_modal').show();
    });

    $('#do_upload').click(function () {

        var file = document.getElementById('evidence_file').files[0];
        var title = $('#evidence_title').val().trim();

        if (title === '') {
            $('#evidence_title').addClass('is-invalid').focus();
            toastr.error('Give the evidence a title.');
            return;
        }

        if (!file) {
            toastr.error('Choose a file to upload.');
            return;
        }

        var form_data = new FormData();

        form_data.append('evidence_file', file);
        form_data.append('client_id', client_id);
        form_data.append('evidence_title', title);
        form_data.append('description', $('#evidence_description').val().trim());
        form_data.append('expiry_date', $('#evidence_expiry').val());
        form_data.append('csrf_token', $('meta[name="csrf-token"]').attr('content'));

        set_loading('#do_upload', true);

        $.ajax({
            url: '/clients/upload_evidence',
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

                $.post('/projects/link_evidence', { item_id: current_item.id, evidence_id: obj.evidence_id }, function (linked) {

                    var res = JSON.parse(linked);

                    set_loading('#do_upload', false);
                    modal('upload_modal').hide();

                    if (!res.success) {
                        toastr.error(res.message);
                        return;
                    }

                    toastr.success('Evidence uploaded and attached');
                    load_item_evidence();
                    load(true);
                });
            },
            error: function () {
                set_loading('#do_upload', false);
                toastr.error('That file could not be uploaded.');
            }
        });
    });

    /* ------------------------------------------------------ assessment admin */

    $('#do_settings_open').click(function () {
        modal('settings_modal').show();
    });

    $('#do_settings_save').click(function () {

        var values = {
            assessment_id: assessment_id,
            assessment_name: $('#assessment_name').val().trim(),
            assessment_status: $('#assessment_status').val()
        };

        if (values.assessment_name === '') {
            $('#assessment_name').addClass('is-invalid').focus();
            toastr.error('Please fix the errors before continuing.');
            return;
        }

        set_loading('#do_settings_save', true);

        $.post('/projects/save_assessment', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_settings_save', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            window.location.reload();
        });
    });

    $('#do_delete_open').click(function () {
        modal('delete_modal').show();
    });

    $('#do_delete').click(function () {

        set_loading('#do_delete', true);

        $.post('/projects/delete_assessment', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            window.location.href = '/projects/detail/id/<?php echo (int) $this->assessment['project_id']; ?>';
        });
    });

    $('#status_badge').addClass(RESULT_CLASS[$('#status_badge').text().trim()] || 'badge--prospect');

    load();

});
</script>
