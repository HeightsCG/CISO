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
                <span class="client__segment">Evidence vault: <?php echo htmlspecialchars($this->assessment['project_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="page__actions">
            <a class="btn btn--secondary" href="/projects/evidence/id/<?php echo (int) $this->assessment['project_id']; ?>/from/assessment/ref/<?php echo (int) $this->assessment['id']; ?>"><i class="fa-regular fa-folder-open"></i> Evidence Vault</a>
            <button type="button" class="btn btn--primary" id="do_complete"><i class="fa-regular fa-circle-check"></i> Mark Complete</button>
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
            <p class="controls__empty" id="items_empty">Loading items&hellip;</p>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="item_modal" tabindex="-1" aria-labelledby="item_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="item_modal_title">Assess Control</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body assess">
                <div class="assess__pane assess__pane--form">
                    <div class="field">
                        <label id="item_result_label">Result</label>
                        <div class="segmented segmented--wrap" id="item_result" role="group" aria-labelledby="item_result_label">
                            <button type="button" class="segmented__btn" data-value="Not Assessed" aria-pressed="false">Not Assessed</button>
                            <button type="button" class="segmented__btn" data-value="Implemented" aria-pressed="false">Implemented</button>
                            <button type="button" class="segmented__btn" data-value="Partially Implemented" aria-pressed="false">Partially Implemented</button>
                            <button type="button" class="segmented__btn" data-value="Not Implemented" aria-pressed="false">Not Implemented</button>
                            <button type="button" class="segmented__btn" data-value="Not Applicable" aria-pressed="false">Not Applicable</button>
                        </div>
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
                    <div class="assess__control-head">
                        <p class="assess__eyebrow"><span class="assess__id" id="item_identifier"></span> <span class="assess__sep" aria-hidden="true">&middot;</span> <span class="assess__family" id="item_family"></span></p>
                        <h3 class="assess__title" id="item_title"></h3>
                        <p class="assess__statement" id="item_statement"></p>
                    </div>
                    <div class="assess__scroll">
                        <p class="assess__text" id="item_description"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="do_item_save" class="btn btn--primary">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="attach_modal" tabindex="-1" aria-labelledby="attach_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="attach_modal_title">Attach Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="pick__head">
                    <p class="pick__label">Files in <span id="pick_project"></span>&rsquo;s evidence vault <span class="pick__count" id="pick_count"></span></p>
                    <div class="pick__search-wrap">
                        <input type="search" id="vault_search" class="input pick__search" placeholder="Search file name or description..." autocomplete="off" spellcheck="false">
                    </div>
                </div>
                <div class="pick__scroll">
                    <table class="data pick__table">
                        <thead>
                            <tr>
                                <th scope="col" class="pick__sort is-sortable" data-sort="file_name">File</th>
                                <th scope="col" class="pick__num pick__sort is-sortable" data-sort="file_size">Size</th>
                                <th scope="col" class="pick__num pick__sort is-sortable" data-sort="date_created">Uploaded</th>
                                <th scope="col" class="pick__mid pick__sort is-sortable" data-sort="link_count">Used</th>
                                <th scope="col"><span class="visually-hidden">Attach</span></th>
                            </tr>
                        </thead>
                        <tbody id="vault_list"></tbody>
                    </table>
                    <p class="controls__empty" id="vault_empty" hidden></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="upload_modal" tabindex="-1" aria-labelledby="upload_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="upload_modal_title">Upload Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="import__hint">The file is stored in this project&rsquo;s vault and attached to this control. It can be reused on any other control without uploading it again.</p>
                <div class="field">
                    <label for="evidence_title">Title <abbr title="required">*</abbr></label>
                    <input type="text" id="evidence_title" placeholder="Information security policy, signed">
                </div>
                <div class="field">
                    <label for="evidence_description">Description</label>
                    <textarea id="evidence_description" rows="3" placeholder="What this shows and where it came from."></textarea>
                </div>
                <div class="field">
                    <label for="evidence_file">File <abbr title="required">*</abbr></label>
                    <input type="file" id="evidence_file" class="import__file">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_upload" class="btn btn--primary">Upload</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete Assessment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong><?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></strong> and all <?php echo (int) $this->assessment['item_count']; ?> of its items, including results and notes? Evidence itself stays in the project&rsquo;s vault. This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var assessment_id = <?php echo (int) $this->assessment['id']; ?>;
    var project_name = "<?php echo htmlspecialchars($this->assessment['project_name'], ENT_QUOTES, 'UTF-8'); ?>";
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

    function set_result(item_result) {
        $('#item_result .segmented__btn').removeClass('is-on').attr('aria-pressed', 'false');
        $('#item_result .segmented__btn[data-value="' + item_result + '"]').addClass('is-on').attr('aria-pressed', 'true');
    }

    $('#item_result').on('click', '.segmented__btn', function () {
        set_result($(this).attr('data-value'));
    });

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

        sync_complete(counts['Not Assessed']);
    }

    function sync_complete(remaining) {

        if ($('#status_badge').text().trim() === 'Complete') {
            $('#do_complete').prop('disabled', true).attr('title', 'This assessment is complete');
            return;
        }

        $('#do_complete').prop('disabled', remaining > 0).attr('title', remaining > 0
            ? remaining + ' control' + (remaining === 1 ? '' : 's') + ' still unanswered'
            : '');
    }

    function set_status(assessment_status) {
        $('#status_badge').text(assessment_status)
            .removeClass('badge--active badge--onboarding badge--prospect')
            .addClass(RESULT_CLASS[assessment_status] || 'badge--prospect');
    }

    $('#do_complete').click(function () {

        set_loading('#do_complete', true);

        ApiDataSvc.apiCall('post', 'complete_assessment', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_complete', false);

            if (obj.success) {
                toastr.success(obj.message);
                set_status(obj.assessment_status);
                load();
            } else {
                toastr.error(obj.message);
            }
        });
    });

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
        ApiDataSvc.apiCall('post', 'load_items', { assessment_id: assessment_id }, function (data) {
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

    function set_control_text(item) {

        var text = item.description === '' ? 'No control text was supplied for this item.' : item.description;
        var at = text.search(/(^|\n)\s*Discussion:/);
        var statement = at > 0 ? text.slice(0, at).trim() : '';
        var body = at > 0 ? text.slice(at).trim() : text;

        if (statement === item.control_title.trim()) {
            statement = '';
        }

        $('#item_statement').text(statement).toggle(statement !== '');
        $('#item_description').text(body);
        $('.assess__scroll').scrollTop(0);
    }

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
        set_control_text(current_item);
        set_result(current_item.item_result);
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
            item_result: $('#item_result .segmented__btn.is-on').attr('data-value'),
            notes: $('#item_notes').val().trim()
        };

        ApiDataSvc.apiCall('post', 'save_item', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_item_save', false);

            if (obj.success) {

                modal('item_modal').hide();
                set_status(obj.assessment_status);
                load();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    function load_item_evidence() {

        if (!current_item) {
            return;
        }

        ApiDataSvc.apiCall('post', 'load_item_evidence', { item_id: current_item.id }, function (data) {

            var list = JSON.parse(data);
            var html = '';

            for (var i = 0; i < list.length; i++) {
                html += '<li class="evidence__item" data-id="' + list[i].id + '">'
                    + '<div><a href="#" class="evidence__open" data-id="' + list[i].id + '">' + esc(list[i].evidence_title) + '</a>'
                    + '<span class="evidence__meta">' + esc(list[i].file_name) + ' · ' + size_label(list[i].file_size)
                    + '</span></div>'
                    + '<button type="button" class="btn btn--tertiary btn--sm" data-action="detach" data-id="' + list[i].id + '" aria-label="Detach ' + esc(list[i].evidence_title) + '"><i class="fa-regular fa-link-slash"></i></button>'
                    + '</li>';
            }

            $('#item_evidence').html(html === '' ? '<li class="evidence__none">No evidence attached to this control yet.</li>' : html);
        });
    }

    $('#item_evidence').on('click', '[data-action="detach"]', function () {

        var evidence_id = $(this).attr('data-id');

        ApiDataSvc.apiCall('post', 'unlink_evidence', { item_id: current_item.id, evidence_id: evidence_id }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {

                toastr.success(obj.message);
                load_item_evidence();
                load(true);
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#item_evidence, #vault_list').on('click', '.evidence__open', function (e) {
        ApiDataSvc.apiCall('post', 'evidence_url', { evidence_id: $(this).attr('data-id') }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {

                window.open(obj.url, '_blank', 'noopener');
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_attach_open').click(function () {

        $('#vault_search').val('');
        $('#pick_project').text(project_name);
        mark_sort();
        load_vault(true);
        modal('attach_modal').show();
    });

    function extension_of(name) {
        var at = String(name || '').lastIndexOf('.');
        return at === -1 ? 'FILE' : String(name).slice(at + 1).toUpperCase().slice(0, 4);
    }

    var PAGE_SIZE = 50;
    var vault = { offset: 0, total: 0, term: '', loading: false, done: false, token: 0,
                  sort: 'date_created', dir: 'desc' };

    function vault_row(row, attached) {

        var used = parseInt(row.link_count, 10);
        var already = attached.indexOf(String(row.id)) !== -1;

        return '<tr class="pick__row">'
            + '<td class="pick__file"><span class="pick__name">' + esc(row.file_name) + '</span>'
            + (row.evidence_title ? '<span class="pick__desc">' + esc(row.evidence_title) + '</span>' : '')
            + '</td>'
            + '<td class="pick__num">' + size_label(row.file_size) + '</td>'
            + '<td class="pick__num">' + esc(row.date_created_display) + '</td>'
            + '<td class="pick__mid">' + (used === 0 ? '<span class="pick__zero">0</span>' : used) + '</td>'
            + '<td class="pick__act">' + (already
                ? '<span class="badge badge--active">Attached</span>'
                : '<button type="button" class="btn btn--secondary btn--sm" data-action="attach" data-id="' + row.id + '">Attach</button>')
            + '</td></tr>';
    }

    function load_vault(reset) {
        if (!reset && (vault.loading || vault.done)) {
            return;
        }

        if (reset) {
            vault.offset = 0;
            vault.done = false;
            $('#vault_list').empty();
        }

        var token = ++vault.token;

        vault.loading = true;
        vault.term = $('#vault_search').val().trim();

        var attached = $('#item_evidence .evidence__item').map(function () {
            return String($(this).attr('data-id'));
        }).get();

        ApiDataSvc.apiCall('post', 'search_evidence', {
            project_id: project_id,
            search: vault.term,
            limit: PAGE_SIZE,
            offset: vault.offset,
            sort: vault.sort,
            dir: vault.dir
        }, function (data) {

            if (token !== vault.token) {
                vault.loading = false;
                return;
            }

            var obj = JSON.parse(data);
            var html = '';

            for (var i = 0; i < obj.rows.length; i++) {
                html += vault_row(obj.rows[i], attached);
            }

            $('#vault_list').append(html);

            if (obj.total !== undefined) {
                vault.total = parseInt(obj.total, 10);
            }
            vault.offset += obj.rows.length;
            vault.done = obj.rows.length < PAGE_SIZE || vault.offset >= vault.total;
            vault.loading = false;

            var shown = $('#vault_list tr').length;

            $('#pick_count').text(shown >= vault.total
                ? vault.total + (vault.total === 1 ? ' file' : ' files')
                : shown + ' of ' + vault.total);

            $('.pick__table').toggle(shown > 0);
            $('#vault_empty').prop('hidden', shown > 0).text(vault.term === ''
                ? esc(project_name) + ' has no evidence yet. Close this and choose Upload New \u2014 the file is saved to this project\'s vault and attached here in one step.'
                : 'No files match \u201c' + vault.term + '\u201d.');
        });
    }

    function mark_sort() {
        $('.pick__sort').removeClass('is-asc is-desc');
        $('.pick__sort[data-sort="' + vault.sort + '"]').addClass(vault.dir === 'asc' ? 'is-asc' : 'is-desc');
    }

    $(document).on('click', '#attach_modal .is-sortable', function () {

        var column = $(this).attr('data-sort');

        if (vault.sort === column) {
            vault.dir = (vault.dir === 'asc') ? 'desc' : 'asc';
        } else {
            vault.sort = column;
            vault.dir = (column === 'file_name') ? 'asc' : 'desc';
        }

        mark_sort();
        load_vault(true);
    });

    var vault_debounce = null;

    $('#vault_search').on('input', function () {
        window.clearTimeout(vault_debounce);
        vault_debounce = window.setTimeout(function () { load_vault(true); }, 250);
    });

    $('.pick__scroll').on('scroll', function () {
        if (this.scrollTop + this.clientHeight >= this.scrollHeight - 120) {
            load_vault(false);
        }
    });


    $('#vault_list').on('click', '[data-action="attach"]', function () {

        var button = this;

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'link_evidence', { item_id: current_item.id, evidence_id: $(this).attr('data-id') }, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {

                modal('attach_modal').hide();
                toastr.success(obj.message);
                load_item_evidence();
                load(true);
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_upload_open').click(function () {

        $('#evidence_title, #evidence_description').val('').removeClass('is-invalid');
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
        form_data.append('project_id', project_id);
        form_data.append('evidence_title', title);
        form_data.append('description', $('#evidence_description').val().trim());
        form_data.append('csrf_token', $('meta[name="csrf-token"]').attr('content'));

        set_loading('#do_upload', true);

        $.ajax({
            url: '/api/upload_evidence',
            type: 'POST',
            data: form_data,
            processData: false,
            contentType: false,
            success: function (data) {

                var obj = JSON.parse(data);

                if (obj.success) {

                    ApiDataSvc.apiCall('post', 'link_evidence', { item_id: current_item.id, evidence_id: obj.evidence_id }, function (linked) {

                        var res = JSON.parse(linked);

                        set_loading('#do_upload', false);

                        if (res.success) {
                            modal('upload_modal').hide();
                            toastr.success(res.message);
                            load_item_evidence();
                            load(true);
                        } else {
                            toastr.error(res.message);
                        }
                    });
                } else {
                    set_loading('#do_upload', false);
                    toastr.error(obj.message);
                }
            },
            error: function () {
                set_loading('#do_upload', false);
                toastr.error('That file could not be uploaded.');
            }
        });
    });

    $('#do_settings_save').click(function () {

        var values = {
            assessment_id: assessment_id,
            assessment_name: $('#assessment_name').val().trim(),
        };

        if (values.assessment_name === '') {
            $('#assessment_name').addClass('is-invalid').focus();
            toastr.error('Please fix the errors before continuing.');
            return;
        }

        set_loading('#do_settings_save', true);

        ApiDataSvc.apiCall('post', 'save_assessment', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_settings_save', false);

            if (obj.success) {

                window.location.reload();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_delete_open').click(function () {
        modal('delete_modal').show();
    });

    $('#do_delete').click(function () {

        set_loading('#do_delete', true);

        ApiDataSvc.apiCall('post', 'delete_assessment', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (obj.success) {

                window.location.href = '/projects/detail/id/<?php echo (int) $this->assessment['project_id']; ?>';
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#status_badge').addClass(RESULT_CLASS[$('#status_badge').text().trim()] || 'badge--prospect');

    load();

});
</script>
