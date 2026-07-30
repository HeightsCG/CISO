<div class="page">
    <a class="page__back" href="/portal/project/id/<?php echo (int) $this->project['id']; ?>"><i class="fa-regular fa-arrow-left"></i> Back to <?php echo htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8'); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title">Evidence</h1>
            <div class="client__meta">
                <span class="client__segment"><?php echo htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment" id="vault_count">&nbsp;</span>
            </div>
        </div>
        <div class="page__actions">
            <button type="button" class="btn btn--primary" id="do_upload_open"><i class="fa-regular fa-arrow-up-from-bracket"></i> Upload Evidence</button>
        </div>
    </div>

    <div class="vault">
        <aside class="vault__tree">
            <div class="vault__tree-head">
                <span class="vault__tree-title">Folders</span>
            </div>
            <ul class="vault__list" id="folder_tree"></ul>
        </aside>

        <section class="vault__files">
            <div class="vault__toolbar">
                <input type="search" id="evidence_search" class="input vault__search" placeholder="Search title or file name..." autocomplete="off" spellcheck="false">
            </div>
            <table class="data data--light" id="evidence_table">
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
                <tbody id="evidence_body"></tbody>
            </table>
            <p class="vault__empty" id="evidence_empty" hidden></p>
        </section>
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
                <p class="import__hint">Saved into <strong id="upload_folder"></strong>. Documents, images and archives up to 25MB.</p>
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

<div class="modal fade" data-bs-backdrop="static" id="remove_modal" tabindex="-1" aria-labelledby="remove_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="remove_modal_title">Remove Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="remove_id" value="0">
                <p>Remove <strong id="remove_title"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_remove" class="btn btn--destructive">Remove Evidence</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var project_id = <?php echo (int) $this->project['id']; ?>;
    var my_user_id = <?php echo (int) Session::get('user_id'); ?>;
    var folders = [];
    var evidence = [];
    var selected_folder = 0;

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

    function size_label(bytes) {

        var n = parseInt(bytes, 10);

        if (isNaN(n) || n <= 0) {
            return '<span class="roster__none">&mdash;</span>';
        }
        if (n < 1024) {
            return n + ' B';
        }
        if (n < 1048576) {
            return Math.round(n / 1024) + ' KB';
        }
        return (n / 1048576).toFixed(1) + ' MB';
    }

    function extension_of(name) {
        var at = String(name || '').lastIndexOf('.');
        return at === -1 ? '' : String(name).slice(at + 1).toUpperCase().slice(0, 5);
    }

    function children_of(parent_id) {

        var out = [];

        for (var i = 0; i < folders.length; i++) {
            if (parseInt(folders[i].parent_id, 10) === parseInt(parent_id, 10)) {
                out.push(folders[i]);
            }
        }

        return out;
    }

    function count_of(id) {

        var total = 0;

        for (var i = 0; i < evidence.length; i++) {
            if (parseInt(id, 10) === 0 || parseInt(evidence[i].folder_id, 10) === parseInt(id, 10)) {
                total++;
            }
        }

        return '<span class="vault__count' + (total === 0 ? ' vault__count--empty' : '') + '">' + total + '</span>';
    }

    function folder_name_of(id) {

        for (var i = 0; i < folders.length; i++) {
            if (parseInt(folders[i].id, 10) === parseInt(id, 10)) {
                return folders[i].folder_name;
            }
        }

        return 'This project';
    }

    function tree_branch(parent_id, depth) {

        var html = '';
        var kids = children_of(parent_id);

        for (var i = 0; i < kids.length; i++) {
            html += '<li class="vault__item' + (selected_folder === parseInt(kids[i].id, 10) ? ' is-on' : '') + '" style="--tree-depth:' + depth + '">'
                + '<button type="button" class="vault__link" data-id="' + kids[i].id + '">'
                + '<i class="fa-regular fa-folder"></i><span class="vault__name">' + esc(kids[i].folder_name) + '</span>'
                + count_of(kids[i].id) + '</button></li>'
                + tree_branch(kids[i].id, depth + 1);
        }

        return html;
    }

    function render_tree() {
        $('#folder_tree').html('<li class="vault__item' + (selected_folder === 0 ? ' is-on' : '') + '" style="--tree-depth:0">'
            + '<button type="button" class="vault__link" data-id="0">'
            + '<i class="fa-regular fa-inbox"></i><span class="vault__name">All Evidence</span>'
            + count_of(0) + '</button></li>'
            + tree_branch(0, 0));
    }

    function visible_evidence() {

        var term = $('#evidence_search').val().trim().toLowerCase();
        var out = [];

        for (var i = 0; i < evidence.length; i++) {

            var e = evidence[i];

            if (selected_folder !== 0 && parseInt(e.folder_id, 10) !== selected_folder) {
                continue;
            }

            if (term !== '' && (e.evidence_title + ' ' + e.file_name).toLowerCase().indexOf(term) === -1) {
                continue;
            }

            out.push(e);
        }

        return out;
    }

    function render() {

        var rows = visible_evidence();
        var html = '';

        for (var i = 0; i < rows.length; i++) {

            var e = rows[i];
            var links = parseInt(e.link_count, 10);
            var ext = extension_of(e.file_name);

            html += '<tr class="vault__row" data-id="' + e.id + '">'
                + '<td class="vault__cell-name"><span class="vault__line">'
                + '<a href="#" class="vault__title evidence__open" data-id="' + e.id + '" title="' + esc(e.evidence_title) + '">' + esc(e.evidence_title) + '</a>'
                + '<span class="vault__file" title="' + esc(e.file_name) + '">' + esc(e.file_name) + (ext === '' ? '' : ' &middot; ' + esc(ext)) + '</span>'
                + '</span></td>'
                + '<td class="vault__col-size">' + size_label(e.file_size) + '</td>'
                + '<td class="vault__col-date">' + esc(e.date_created_display) + '</td>'
                + '<td class="vault__col-links">' + (links > 0
                    ? '<span class="chip">' + links + '</span>'
                    : '<span class="chip chip--empty">0</span>') + '</td>'
                + '<td>' + esc(e.uploaded_by_name === null ? '' : e.uploaded_by_name) + '</td>'
                + '<td class="vault__col-actions">' + (parseInt(e.uploaded_by, 10) === my_user_id && links === 0
                    ? '<button type="button" class="btn btn--tertiary btn--sm" data-action="remove" data-id="' + e.id + '" data-title="' + esc(e.evidence_title) + '" title="Remove" aria-label="Remove ' + esc(e.evidence_title) + '"><i class="fa-regular fa-trash-can"></i></button>'
                    : '') + '</td>'
                + '</tr>';
        }

        $('#evidence_body').html(html);
        $('#evidence_table').toggle(rows.length > 0);
        $('#evidence_empty').prop('hidden', rows.length > 0).text(evidence.length === 0
            ? 'No evidence has been collected for this project yet.'
            : ($('#evidence_search').val().trim() === ''
                ? folder_name_of(selected_folder) + ' has no evidence yet.'
                : 'No files match your search.'));

        $('#vault_count').text(folders.length + (folders.length === 1 ? ' folder' : ' folders')
            + ' · ' + evidence.length + (evidence.length === 1 ? ' file' : ' files'));
    }

    $('#folder_tree').on('click', '.vault__link', function () {
        selected_folder = parseInt($(this).attr('data-id'), 10);
        render_tree();
        render();
    });

    $('#evidence_search').on('input', render);

    $('#evidence_body').on('click', '.evidence__open', function (e) {

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

    $('#evidence_body').on('click', '[data-action="remove"]', function () {
        $('#remove_id').val($(this).attr('data-id'));
        $('#remove_title').text($(this).attr('data-title'));
        modal('remove_modal').show();
    });

    $('#do_remove').click(function () {

        set_loading('#do_remove', true);

        ApiDataSvc.apiCall('post', 'portal_delete_evidence', { evidence_id: $('#remove_id').val() }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_remove', false);

            if (obj.success) {
                modal('remove_modal').hide();
                toastr.success(obj.message);
                load();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    function modal(id) {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
    }

    $('#do_upload_open').click(function () {
        $('#evidence_title').val('').removeClass('is-invalid');
        $('#evidence_description').val('');
        $('#evidence_file').val('').removeClass('is-invalid');
        $('#upload_folder').text(folder_name_of(selected_folder));
        modal('upload_modal').show();
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
        form_data.append('folder_id', selected_folder);
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

                set_loading('#do_upload', false);

                if (obj.success) {
                    modal('upload_modal').hide();
                    toastr.success(obj.message);
                    load();
                } else {
                    toastr.error(obj.message);
                }
            },
            error: function () {
                set_loading('#do_upload', false);
                toastr.error('That file could not be uploaded');
            }
        });
    });

    function load(){

        ApiDataSvc.apiCall('post', 'portal_folders', { project_id: project_id }, function (data) {
            folders = JSON.parse(data);
            render_tree();
            render();
        });

        ApiDataSvc.apiCall('post', 'portal_evidence', { project_id: project_id }, function (data) {
            evidence = JSON.parse(data);
            render_tree();
            render();
        });
    }

    load();

});
</script>
