<div class="page">
    <a class="page__back" href="<?php echo (Main::get_param('from') === 'assessment' && Main::get_param('ref') ? '/projects/assessment/id/'.((int) Main::get_param('ref')) : '/projects/detail/id/'.((int) $this->project['id'])); ?>"><i class="fa-regular fa-arrow-left"></i> Back to <?php echo (Main::get_param('from') === 'assessment' && Main::get_param('ref') ? 'Assessment' : htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8')); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo ($this->project['client_name'] === null ? htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($this->project['client_name'], ENT_QUOTES, 'UTF-8')); ?> &middot; Evidence Vault</h1>
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
                <button type="button" class="btn btn--tertiary btn--sm" id="do_folder_add" aria-label="New folder"><i class="fa-regular fa-folder-plus"></i></button>
            </div>
            <ul class="vault__list" id="folder_tree"></ul>
        </aside>

        <section class="vault__files" id="drop_zone">
            <div class="vault__toolbar">
                <input type="search" id="evidence_search" class="input vault__search" placeholder="Search title or file name..." autocomplete="off" spellcheck="false">
                <select id="type_filter" class="form-select vault__filter">
                    <option value="">All types</option>
                    <option value="document">Documents</option>
                    <option value="image">Images</option>
                    <option value="archive">Archives</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <table class="data data--light" id="evidence_table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col" class="vault__col-size">Size</th>
                        <th scope="col" class="vault__col-date">Uploaded</th>
                        <th scope="col" class="vault__col-links">Controls</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody id="evidence_body"></tbody>
            </table>
            <p class="vault__empty" id="evidence_empty" hidden></p>
            <div class="vault__drop" id="drop_hint" hidden><i class="fa-regular fa-arrow-up-from-bracket"></i> Drop to upload into <strong id="drop_folder"></strong></div>
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

<div class="modal fade" data-bs-backdrop="static" id="folder_modal" tabindex="-1" aria-labelledby="folder_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="folder_modal_title">New Folder</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="folder_id" value="0">
                <input type="hidden" id="folder_parent_id" value="0">
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="folder_name">Folder Name <abbr title="required">*</abbr></label>
                        <input type="text" class="form-control" id="folder_name" placeholder="Policies">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_folder_save" class="btn btn--primary">Save Folder</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="folder_delete_modal" tabindex="-1" aria-labelledby="folder_delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="folder_delete_modal_title">Delete Folder</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="folder_delete_id" value="0">
                <p>Delete <strong id="folder_delete_name"></strong>? This cannot be undone.</p>
                <div class="notice notice--warn" id="folder_delete_warning" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_folder_delete" class="btn btn--destructive">Delete Folder</button>
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
                <input type="hidden" id="edit_id" value="0">
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="edit_title">Title <abbr title="required">*</abbr></label>
                        <input type="text" class="form-control" id="edit_title">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_edit_save" class="btn btn--primary">Save Evidence</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="move_modal" tabindex="-1" aria-labelledby="move_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="move_modal_title">Move to Folder</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="move_id" value="0">
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="move_folder">Move <strong id="move_title"></strong> to</label>
                        <select class="form-control" id="move_folder"></select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_move" class="btn btn--primary">Move Evidence</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="delete_id" value="0">
                <p>Delete <strong id="delete_title"></strong>? This cannot be undone.</p>
                <div class="notice notice--warn" id="delete_warning" hidden></div>
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

    var project_id = <?php echo (int) $this->project['id']; ?>;
    var evidence = [];
    var folders = [];
    var selected_folder = 0;

    var DOCUMENT_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods'];
    var IMAGE_TYPES = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic'];
    var ARCHIVE_TYPES = ['zip', 'gz', 'tgz', '7z'];

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

    function extension_of(name) {
        var at = String(name || '').lastIndexOf('.');
        return at === -1 ? '' : String(name).slice(at + 1).toLowerCase();
    }

    function type_of(name) {
        var ext = extension_of(name);
        if (DOCUMENT_TYPES.indexOf(ext) !== -1) { return 'document'; }
        if (IMAGE_TYPES.indexOf(ext) !== -1) { return 'image'; }
        if (ARCHIVE_TYPES.indexOf(ext) !== -1) { return 'archive'; }
        return 'other';
    }

    function find(id) {
        for (var i = 0; i < evidence.length; i++) {
            if (parseInt(evidence[i].id, 10) === parseInt(id, 10)) {
                return evidence[i];
            }
        }
        return null;
    }

    function folder_by_id(id) {
        for (var i = 0; i < folders.length; i++) {
            if (parseInt(folders[i].id, 10) === parseInt(id, 10)) {
                return folders[i];
            }
        }
        return null;
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

    function subtree_of(folder_id) {

        var ids = [parseInt(folder_id, 10)];
        var frontier = [parseInt(folder_id, 10)];

        while (frontier.length > 0) {

            var next = [];

            for (var i = 0; i < folders.length; i++) {
                if (frontier.indexOf(parseInt(folders[i].parent_id, 10)) !== -1 && ids.indexOf(parseInt(folders[i].id, 10)) === -1) {
                    ids.push(parseInt(folders[i].id, 10));
                    next.push(parseInt(folders[i].id, 10));
                }
            }

            frontier = next;
        }

        return ids;
    }

    function file_count_of(id) {

        var total = 0;

        for (var i = 0; i < evidence.length; i++) {
            if (parseInt(id, 10) === 0 || parseInt(evidence[i].folder_id, 10) === parseInt(id, 10)) {
                total++;
            }
        }

        return '<span class="vault__count' + (total === 0 ? ' vault__count--empty' : '') + '">' + total + '</span>';
    }

    function folder_name_of(id) {
        var f = folder_by_id(id);
        return f === null ? 'All Evidence' : f.folder_name;
    }

    function folder_menu(folder_id) {
        return '<span class="dropdown vault__menu">'
            + '<button type="button" class="btn btn--tertiary btn--sm" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Folder actions"><i class="fa-regular fa-ellipsis"></i></button>'
            + '<ul class="dropdown-menu dropdown-menu-end">'
            + '<li><button type="button" class="dropdown-item" data-action="folder_add" data-id="' + folder_id + '">New subfolder</button></li>'
            + '<li><button type="button" class="dropdown-item" data-action="folder_edit" data-id="' + folder_id + '">Rename</button></li>'
            + '<li><button type="button" class="dropdown-item dropdown-item--danger" data-action="folder_delete" data-id="' + folder_id + '">Delete</button></li>'
            + '</ul></span>';
    }

    function tree_branch(parent_id, depth) {

        var html = '';
        var kids = children_of(parent_id);

        for (var i = 0; i < kids.length; i++) {

            var f = kids[i];

            html += '<li class="vault__item' + (parseInt(selected_folder, 10) === parseInt(f.id, 10) ? ' is-on' : '') + '" style="--tree-depth:' + depth + '">'
                + '<button type="button" class="vault__link" data-id="' + f.id + '">'
                + '<i class="fa-regular fa-folder"></i><span class="vault__name">' + esc(f.folder_name) + '</span>'
                + file_count_of(f.id) + '</button>'
                + folder_menu(f.id) + '</li>'
                + tree_branch(f.id, depth + 1);
        }

        return html;
    }

    function render_tree() {
        $('#folder_tree').html('<li class="vault__item' + (parseInt(selected_folder, 10) === 0 ? ' is-on' : '') + '" style="--tree-depth:0">'
            + '<button type="button" class="vault__link" data-id="0">'
            + '<i class="fa-regular fa-inbox"></i><span class="vault__name">All Evidence</span>'
            + file_count_of(0) + '</button></li>'
            + tree_branch(0, 0));
    }

    function visible_evidence() {

        var term = $('#evidence_search').val().trim().toLowerCase();
        var type = $('#type_filter').val();
        var scope = parseInt(selected_folder, 10);
        var out = [];

        for (var i = 0; i < evidence.length; i++) {

            var e = evidence[i];

            if (scope !== 0 && parseInt(e.folder_id, 10) !== scope) {
                continue;
            }

            if (type !== '' && type_of(e.file_name) !== type) {
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
                + '<span class="vault__file" title="' + esc(e.file_name) + '">' + esc(e.file_name) + (ext === '' ? '' : ' &middot; ' + esc(ext.toUpperCase())) + '</span>'
                + '</span></td>'
                + '<td class="vault__col-size">' + size_label(e.file_size) + '</td>'
                + '<td class="vault__col-date">' + esc(e.date_created_display) + '</td>'
                + '<td class="vault__col-links">' + (links > 0
                    ? '<span class="dropdown"><button type="button" class="chip" data-bs-toggle="dropdown" aria-expanded="false" data-action="links" data-id="' + e.id + '">' + links + '</button>'
                        + '<div class="dropdown-menu dropdown-menu-end links__menu"><p class="links__note">Loading&hellip;</p></div></span>'
                    : '<span class="chip chip--empty">0</span>') + '</td>'
                + '<td class="controls__actions"><span class="dropdown vault__menu">'
                + '<button type="button" class="btn btn--tertiary btn--sm" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Evidence actions"><i class="fa-regular fa-ellipsis"></i></button>'
                + '<ul class="dropdown-menu dropdown-menu-end">'
                + '<li><button type="button" class="dropdown-item" data-action="edit" data-id="' + e.id + '"><i class="fa-regular fa-pencil"></i> Edit</button></li>'
                + '<li><button type="button" class="dropdown-item dropdown-item--danger" data-action="delete" data-id="' + e.id + '"><i class="fa-regular fa-trash-can"></i> Delete</button></li>'
                + '</ul></span></td></tr>';
        }

        $('#evidence_body').html(html);
        $('#evidence_table').toggle(rows.length > 0);
        $('#evidence_empty').prop('hidden', rows.length > 0);

        if (rows.length === 0) {
            $('#evidence_empty').text(
                $('#evidence_search').val().trim() !== '' || $('#type_filter').val() !== ''
                    ? 'Nothing matches the current search or filter.'
                    : folder_name_of(selected_folder) + ' has no evidence yet. Use Upload Evidence to add a file.');
        }

        $('#vault_count').text(folders.length + (folders.length === 1 ? ' folder' : ' folders')
            + ' · ' + evidence.length + (evidence.length === 1 ? ' file' : ' files'));
    }

    function folder_options(target, selected) {

        var html = '<option value="0">All Evidence</option>';

        function walk(parent_id, prefix) {

            var kids = children_of(parent_id);

            for (var i = 0; i < kids.length; i++) {
                html += '<option value="' + kids[i].id + '"'
                    + (parseInt(selected, 10) === parseInt(kids[i].id, 10) ? ' selected' : '') + '>'
                    + prefix + esc(kids[i].folder_name) + '</option>';
                walk(kids[i].id, prefix + '— ');
            }
        }

        walk(0, '');
        $(target).html(html);
    }

    function load() {
        ApiDataSvc.apiCall('post', 'load_evidence', { project_id: project_id }, function (data) {
            evidence = JSON.parse(data);
            render_tree();
            render();
        });
    }

    function load_folders() {
        ApiDataSvc.apiCall('post', 'load_folders', { project_id: project_id }, function (data) {

            folders = JSON.parse(data);

            if (selected_folder !== 0 && folder_by_id(selected_folder) === null) {
                selected_folder = 0;
            }

            render_tree();
            render();
        });
    }

    $('#folder_tree').on('click', '.vault__link', function () {
        selected_folder = parseInt($(this).attr('data-id'), 10);
        render_tree();
        render();
    });

    $('#evidence_search').on('input', render);
    $('#type_filter').on('change', render);

    $('#do_folder_add').click(function () {
        $('#folder_modal_title').text('New Folder');
        $('#folder_id').val(0);
        $('#folder_parent_id').val(selected_folder);
        $('#folder_name').val('').removeClass('is-invalid');
        modal('folder_modal').show();
    });

    $('#folder_tree').on('click', '[data-action="folder_add"]', function () {
        $('#folder_modal_title').text('New Subfolder');
        $('#folder_id').val(0);
        $('#folder_parent_id').val($(this).attr('data-id'));
        $('#folder_name').val('').removeClass('is-invalid');
        modal('folder_modal').show();
    });

    $('#folder_tree').on('click', '[data-action="folder_edit"]', function () {

        var f = folder_by_id($(this).attr('data-id'));

        if (f === null) {
            return;
        }

        $('#folder_modal_title').text('Rename Folder');
        $('#folder_id').val(f.id);
        $('#folder_parent_id').val(f.parent_id);
        $('#folder_name').val(f.folder_name).removeClass('is-invalid');
        modal('folder_modal').show();
    });

    $('#folder_tree').on('click', '[data-action="folder_delete"]', function () {

        var f = folder_by_id($(this).attr('data-id'));

        if (f === null) {
            return;
        }

        var ids = subtree_of(f.id);
        var files = 0;

        for (var i = 0; i < evidence.length; i++) {
            if (ids.indexOf(parseInt(evidence[i].folder_id, 10)) !== -1) {
                files++;
            }
        }

        $('#folder_delete_id').val(f.id);
        $('#folder_delete_name').text(f.folder_name);

        var subfolders = ids.length - 1;
        var parts = [];

        if (subfolders > 0) {
            parts.push('<strong>' + subfolders + (subfolders === 1 ? ' subfolder</strong> is' : ' subfolders</strong> are') + ' deleted with it.');
        }

        if (files > 0) {
            parts.push('<strong>' + files + (files === 1 ? ' file</strong> moves' : ' files</strong> move') + ' to ' + esc(folder_name_of(f.parent_id)) + '.');
        }

        $('#folder_delete_warning').prop('hidden', parts.length === 0).html(parts.join(' '));

        modal('folder_delete_modal').show();
    });

    $('#do_folder_save').click(function () {

        if ($('#folder_name').val().trim() === '') {
            $('#folder_name').addClass('is-invalid').focus();
            toastr.error('Folder name is required');
            return;
        }

        var values = {
            folder_id: $('#folder_id').val(),
            project_id: project_id,
            parent_id: $('#folder_parent_id').val(),
            folder_name: $('#folder_name').val().trim()
        };

        set_loading('#do_folder_save', true);

        ApiDataSvc.apiCall('post', 'save_folder', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_folder_save', false);

            if (obj.success) {
                toastr.success(obj.message);
                modal('folder_modal').hide();
                load_folders();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_folder_delete').click(function () {

        set_loading('#do_folder_delete', true);

        ApiDataSvc.apiCall('post', 'delete_folder', { folder_id: $('#folder_delete_id').val() }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_folder_delete', false);

            if (obj.success) {
                toastr.success(obj.message);
                modal('folder_delete_modal').hide();
                selected_folder = 0;
                load();
                load_folders();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#evidence_body').on('click', '.evidence__open', function (e) {

        e.preventDefault();

        ApiDataSvc.apiCall('post', 'evidence_url', { evidence_id: $(this).attr('data-id') }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                window.open(obj.url, '_blank', 'noopener');
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#evidence_body').on('click', '[data-action="links"]', function () {

        var menu = $(this).next('.links__menu');

        ApiDataSvc.apiCall('post', 'load_evidence_links', { evidence_id: $(this).attr('data-id') }, function (data) {

            var list = JSON.parse(data);
            var html = '';

            for (var i = 0; i < list.length; i++) {
                html += '<a class="links__item" href="/projects/assessment/id/' + list[i].assessment_id + '">'
                    + '<span class="links__id">' + esc(list[i].control_identifier) + '</span>'
                    + '<span class="links__title">' + esc(list[i].control_title) + '</span>'
                    + '<span class="links__meta">' + esc(list[i].assessment_name) + ' · ' + esc(list[i].short_code) + '</span></a>';
            }

            menu.html(html === '' ? '<p class="links__note">Not attached to any control.</p>' : html);
        });
    });

    $('#evidence_body').on('click', '[data-action="edit"]', function () {

        var e = find($(this).attr('data-id'));

        if (e === null) {
            return;
        }

        $('#edit_id').val(e.id);
        $('#edit_title').val(e.evidence_title).removeClass('is-invalid');
        $('#edit_description').val(e.description);
        modal('edit_modal').show();
    });

    $('#do_edit_save').click(function () {

        if ($('#edit_title').val().trim() === '') {
            $('#edit_title').addClass('is-invalid').focus();
            toastr.error('Evidence title is required');
            return;
        }

        var values = {
            evidence_id: $('#edit_id').val(),
            evidence_title: $('#edit_title').val().trim(),
            description: $('#edit_description').val().trim()
        };

        set_loading('#do_edit_save', true);

        ApiDataSvc.apiCall('post', 'save_evidence', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_edit_save', false);

            if (obj.success) {
                toastr.success(obj.message);
                modal('edit_modal').hide();
                load();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#evidence_body').on('click', '[data-action="move"]', function () {

        var e = find($(this).attr('data-id'));

        if (e === null) {
            return;
        }

        $('#move_id').val(e.id);
        $('#move_title').text(e.evidence_title);
        folder_options('#move_folder', e.folder_id);
        modal('move_modal').show();
    });

    $('#do_move').click(function () {

        set_loading('#do_move', true);

        ApiDataSvc.apiCall('post', 'move_evidence', { evidence_id: $('#move_id').val(), folder_id: $('#move_folder').val() }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_move', false);

            if (obj.success) {
                toastr.success(obj.message);
                modal('move_modal').hide();
                load();
                load_folders();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#evidence_body').on('click', '[data-action="delete"]', function () {

        var e = find($(this).attr('data-id'));

        if (e === null) {
            return;
        }

        var links = parseInt(e.link_count, 10);

        $('#delete_id').val(e.id);
        $('#delete_title').text(e.evidence_title);
        $('#delete_warning').prop('hidden', links === 0).html('<strong>Attached to ' + links
            + (links === 1 ? ' control' : ' controls') + '.</strong>');

        modal('delete_modal').show();
    });

    $('#do_delete').click(function () {

        set_loading('#do_delete', true);

        ApiDataSvc.apiCall('post', 'delete_evidence', { evidence_id: $('#delete_id').val() }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (obj.success) {
                toastr.success(obj.message);
                modal('delete_modal').hide();
                load();
                load_folders();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    /* Pointer-based rather than the HTML5 drag API: a table row is an awkward
       native drag source and the browser will silently decline to start one,
       which is why this never actually worked. mousedown/mousemove/mouseup
       behaves the same everywhere and can be driven by a real mouse. */
    var drag = null;

    function drag_stop() {

        $('#drag_ghost').remove();
        $('.vault__link.is-drop-target').removeClass('is-drop-target');
        $('body').removeClass('is-dragging');
        drag = null;
    }

    function drop_target_at(x, y) {
        return $(document.elementFromPoint(x, y)).closest('.vault__link');
    }

    $('#evidence_body').on('mousedown', '.vault__row', function (e) {

        if (e.button !== 0 || $(e.target).closest('a, button, input, select, textarea').length > 0) {
            return;
        }

        e.preventDefault();

        var row = $(this);

        drag = {
            id: row.attr('data-id'),
            title: row.find('.vault__title').text(),
            x: e.pageX,
            y: e.pageY,
            active: false
        };
    });

    $(document).on('mousemove', function (e) {

        if (drag === null) {
            return;
        }

        if (!drag.active) {

            if (Math.abs(e.pageX - drag.x) + Math.abs(e.pageY - drag.y) < 5) {
                return;
            }

            drag.active = true;
            $('body').addClass('is-dragging');
            $('<div>').attr('id', 'drag_ghost').addClass('drag__ghost').text(drag.title).appendTo('body');
        }

        e.preventDefault();

        $('#drag_ghost').css({ left: e.clientX + 12, top: e.clientY + 12 });
        $('.vault__link.is-drop-target').removeClass('is-drop-target');
        drop_target_at(e.clientX, e.clientY).addClass('is-drop-target');
    });

    $(document).on('mouseup', function (e) {

        if (drag === null) {
            return;
        }

        if (!drag.active) {
            drag = null;
            return;
        }

        var target = drop_target_at(e.clientX, e.clientY);
        var evidence_id = drag.id;

        drag_stop();

        if (target.length === 0) {
            return;
        }

        ApiDataSvc.apiCall('post', 'move_evidence', { evidence_id: evidence_id, folder_id: target.attr('data-id') }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                toastr.success(obj.message);
                load();
                load_folders();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && drag !== null) {
            drag_stop();
        }
    });

    function open_upload() {
        $('#upload_folder').text(folder_name_of(selected_folder));
        $('#evidence_title, #evidence_description').val('').removeClass('is-invalid');
        $('#evidence_file').val('').removeClass('is-invalid');
        modal('upload_modal').show();
    }

    $('#do_upload_open').click(open_upload);

    function upload(file, title, description) {

        var form_data = new FormData();

        form_data.append('project_id', project_id);
        form_data.append('folder_id', selected_folder);
        form_data.append('evidence_title', title);
        form_data.append('description', description);
        form_data.append('evidence_file', file);
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

                set_loading('#do_upload', false);

                if (obj.success) {
                    toastr.success(obj.message);
                    modal('upload_modal').hide();
                    load();
                    load_folders();
                } else {
                    toastr.error(obj.message);
                }
            },
            error: function () {
                set_loading('#do_upload', false);
                toastr.error('That file could not be uploaded.');
            }
        });
    }

    $('#do_upload').click(function () {

        if ($('#evidence_title').val().trim() === '') {
            $('#evidence_title').addClass('is-invalid').focus();
            toastr.error('Evidence title is required');
            return;
        }

        if (!$('#evidence_file')[0].files[0]) {
            $('#evidence_file').addClass('is-invalid');
            toastr.error('Choose a file to upload');
            return;
        }

        upload($('#evidence_file')[0].files[0], $('#evidence_title').val().trim(), $('#evidence_description').val().trim());
    });

    var drag_depth = 0;

    function end_drag() {
        drag_depth = 0;
        $('#drop_hint').prop('hidden', true);
        $('#drop_zone').removeClass('is-drop-target');
    }

    $('#drop_zone').on('dragenter dragover', function (e) {

        if ($.inArray('Files', e.originalEvent.dataTransfer.types) === -1) {
            return;
        }

        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'copy';

        if (e.type === 'dragenter') {
            drag_depth++;
        }

        $('#drop_folder').text(folder_name_of(selected_folder));
        $('#drop_hint').prop('hidden', false);
        $('#drop_zone').addClass('is-drop-target');
    });

    $('#drop_zone').on('dragleave', function () {

        drag_depth--;

        if (drag_depth <= 0) {
            end_drag();
        }
    });

    $('#drop_zone').on('drop', function (e) {

        e.preventDefault();
        end_drag();

        var file = e.originalEvent.dataTransfer.files[0];

        if (file) {
            upload(file, file.name, '');
        }
    });

    load();
    load_folders();

});
</script>
