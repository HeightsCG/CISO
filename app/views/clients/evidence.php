<div class="page">
    <a class="page__back" href="<?php echo (Main::get_param('from') === 'assessment' && Main::get_param('ref') ? '/projects/assessment/id/'.((int) Main::get_param('ref')) : (Main::get_param('from') === 'project' && Main::get_param('ref') ? '/projects/detail/id/'.((int) Main::get_param('ref')) : '/clients/detail/id/'.((int) $this->client['id']))); ?>"><i class="fa-regular fa-arrow-left"></i> Back to <?php echo (Main::get_param('from') === 'assessment' && Main::get_param('ref') ? 'Assessment' : (Main::get_param('from') === 'project' && Main::get_param('ref') ? 'Project' : htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'))); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title">Evidence Vault</h1>
            <div class="client__meta">
                <span class="client__segment"><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment" id="evidence_count">&nbsp;</span>
            </div>
        </div>
        <div class="page__actions">
            <button type="button" class="btn btn--primary" id="do_upload_open"><i class="fa-regular fa-arrow-up-from-bracket"></i> Upload Evidence</button>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="evidence_search" class="input roster__search" placeholder="Search by title or file name..." autocomplete="off" spellcheck="false">
            <div class="roster__filters">
                <div class="roster__filter">
                    <label for="usage_filter">Usage</label>
                    <select id="usage_filter" class="form-select">
                        <option value="">All evidence</option>
                        <option value="linked">Linked to controls</option>
                        <option value="unused">Not yet used</option>
                        </select>
                </div>
            </div>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data" id="evidence_table">
                    <thead>
                        <tr>
                            <th scope="col">Evidence</th>
                            <th scope="col">File</th>
                            <th scope="col">Uploaded</th>
                            <th scope="col">Used On</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody id="evidence_body"></tbody>
                </table>
            </div>
            <p class="controls__empty" id="evidence_empty">Loading evidence&hellip;</p>
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
                <p class="import__hint">Evidence lives in this client&rsquo;s vault and can be attached to any control in any assessment. Documents, images and archives up to 25MB.</p>
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
                <button type="button" id="do_upload" class="btn btn--primary">Upload Evidence</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="edit_modal" tabindex="-1" aria-labelledby="edit_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="edit_modal_title">Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="field">
                    <label for="edit_title">Title <abbr title="required">*</abbr></label>
                    <input type="text" id="edit_title">
                </div>
                <div class="field">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" rows="3"></textarea>
                </div>
                <div class="record__group">
                    <h2 class="record__group-title">Linked Controls</h2>
                    <ul class="evidence__list" id="edit_links"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_edit_save" class="btn btn--primary">Save Evidence</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong id="delete_title"></strong>? The file is removed from storage and cannot be recovered.</p>
                <div id="delete_links_wrap" hidden>
                    <div class="notice notice--warn" id="delete_warning"></div>
                    <ul class="evidence__list" id="delete_links"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete Evidence</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var client_id = <?php echo (int) $this->client['id']; ?>;
    var evidence = [];
    var current = null;

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

    function size_label(bytes) {
        var n = parseInt(bytes, 10) || 0;
        if (n < 1024) { return n + ' B'; }
        if (n < 1048576) { return Math.round(n / 1024) + ' KB'; }
        return (n / 1048576).toFixed(1) + ' MB';
    }

    function find(id) {
        for (var i = 0; i < evidence.length; i++) {
            if (parseInt(evidence[i].id, 10) === parseInt(id, 10)) {
                return evidence[i];
            }
        }
        return null;
    }

    function render() {

        var html = '';


        for (var i = 0; i < evidence.length; i++) {

            var e = evidence[i];
            var links = parseInt(e.link_count, 10);

            html += '<tr class="evidence__row" data-id="' + e.id + '"'
                + ' data-usage="' + (links > 0 ? 'linked' : 'unused') + '">'
                + '<td><span class="roster__name">' + esc(e.evidence_title) + '</span>'
                + (e.description ? '<span class="roster__sub">' + esc(e.description) + '</span>' : '') + '</td>'
                + '<td><a href="#" class="evidence__open" data-id="' + e.id + '">' + esc(e.file_name) + '</a>'
                + '<span class="roster__sub">' + size_label(e.file_size) + '</span></td>'
                + '<td>' + esc(e.date_created_display) + '<span class="roster__sub">' + esc(e.uploaded_by_name || '') + '</span></td>'
                + '<td>' + (links > 0
                    ? '<span class="badge badge--active">' + links + '</span>'
                    : '<span class="roster__none">Not used</span>') + '</td>'
                + '<td class="controls__actions">'
                + '<button type="button" class="btn btn--tertiary btn--sm" data-action="edit" data-id="' + e.id + '" aria-label="Edit ' + esc(e.evidence_title) + '"><i class="fa-regular fa-pen"></i></button>'
                + '<button type="button" class="btn btn--tertiary btn--sm" data-action="delete" data-id="' + e.id + '" aria-label="Delete ' + esc(e.evidence_title) + '"><i class="fa-regular fa-trash"></i></button>'
                + '</td></tr>';
        }

        $('#evidence_body').html(html);
        $('#evidence_count').text(evidence.length + (evidence.length === 1 ? ' item' : ' items'));
    }

    function filter() {

        var term = $('#evidence_search').val().trim().toLowerCase();
        var usage = $('#usage_filter').val();
        var visible = 0;

        $('#evidence_body .evidence__row').each(function () {

            var row = $(this);
            var hit = (term === '' || row.text().toLowerCase().indexOf(term) !== -1)
                && (usage === '' || row.attr('data-usage') === usage);

            row.toggle(hit);

            if (hit) {
                visible++;
            }
        });

        $('#evidence_table').toggle(visible > 0);
        $('#evidence_empty').toggle(visible === 0);

        if (visible === 0) {
            $('#evidence_empty').text(evidence.length === 0
                ? 'No evidence yet. Upload a file to start this client\'s vault.'
                : 'No evidence matches the current filters');
        }
    }

    function load() {
        $.post('/clients/load_evidence', { client_id: client_id }, function (data) {
            evidence = JSON.parse(data);
            render();
            filter();
        });
    }

    $('#evidence_search').on('input', filter);
    $('#usage_filter').on('change', filter);

    $('#evidence_body, #edit_links').on('click', '.evidence__open', function (e) {

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

    function link_rows(list) {

        var html = '';

        for (var i = 0; i < list.length; i++) {
            html += '<li class="evidence__item">'
                + '<div><a href="/projects/assessment/id/' + list[i].assessment_id + '">'
                + esc(list[i].control_identifier) + ' &middot; ' + esc(list[i].control_title) + '</a>'
                + '<span class="evidence__meta">' + esc(list[i].project_name) + ' &middot; '
                + esc(list[i].assessment_name) + ' &middot; ' + esc(list[i].standard_name) + '</span></div>'
                + '<span class="badge badge--inactive">' + esc(list[i].item_result) + '</span>'
                + '</li>';
        }

        return html === '' ? '<li class="evidence__none">Not attached to any control yet.</li>' : html;
    }

    $('#evidence_body').on('click', '[data-action="edit"]', function () {

        current = find($(this).attr('data-id'));

        if (!current) {
            return;
        }

        $('#edit_modal_title').text(current.evidence_title);
        $('#edit_title').val(current.evidence_title).removeClass('is-invalid');
        $('#edit_description').val(current.description);
        $('#edit_links').html('<li class="evidence__none">Loading&hellip;</li>');

        $.post('/clients/load_evidence_links', { evidence_id: current.id }, function (data) {
            $('#edit_links').html(link_rows(JSON.parse(data)));
        });

        modal('edit_modal').show();
    });

    $('#do_edit_save').click(function () {

        var title = $('#edit_title').val().trim();

        if (title === '') {
            $('#edit_title').addClass('is-invalid').focus();
            toastr.error('Give the evidence a title.');
            return;
        }

        set_loading('#do_edit_save', true);

        var values = {
            evidence_id: current.id,
            evidence_title: title,
            description: $('#edit_description').val().trim()
        };

        $.post('/clients/save_evidence', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_edit_save', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            modal('edit_modal').hide();
            toastr.success(obj.message);
            load();
        });
    });

    $('#evidence_body').on('click', '[data-action="delete"]', function () {

        current = find($(this).attr('data-id'));

        if (!current) {
            return;
        }

        $('#delete_title').text(current.evidence_title);
        $('#delete_links_wrap').attr('hidden', true);

        // The confirm names every control that will lose this evidence, so the
        // consequence is on screen before the button is pressed.
        $.post('/clients/load_evidence_links', { evidence_id: current.id }, function (data) {

            var list = JSON.parse(data);

            if (list.length === 0) {
                return;
            }

            $('#delete_warning').html('<strong>' + list.length + ' control'
                + (list.length === 1 ? '' : 's') + ' will lose this evidence.</strong> The attachments below are removed; the controls themselves keep their result and notes.');
            $('#delete_links').html(link_rows(list));
            $('#delete_links_wrap').removeAttr('hidden');
        });

        modal('delete_modal').show();
    });

    $('#do_delete').click(function () {

        set_loading('#do_delete', true);

        $.post('/clients/delete_evidence', { evidence_id: current.id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            modal('delete_modal').hide();
            toastr.success(obj.message);
            load();
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
        form_data.append('client_id', client_id);
        form_data.append('evidence_title', title);
        form_data.append('description', $('#evidence_description').val().trim());
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

                set_loading('#do_upload', false);

                if (!obj.success) {
                    toastr.error(obj.message);
                    return;
                }

                modal('upload_modal').hide();
                toastr.success(obj.message);
                load();
            },
            error: function () {
                set_loading('#do_upload', false);
                toastr.error('That file could not be uploaded.');
            }
        });
    });

    load();

});
</script>
