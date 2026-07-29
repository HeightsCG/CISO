<div class="page">
    <a class="page__back" href="/standards"><i class="fa-regular fa-arrow-left"></i> Back to Standards</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo htmlspecialchars($this->standard['standard_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="client__meta">
                <span class="badge <?php echo ($this->standard['standard_status'] === 'Archived' ? 'badge--inactive' : 'badge--active'); ?>"><?php echo htmlspecialchars($this->standard['standard_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo ($this->standard['short_code'] === '' ? 'No code' : htmlspecialchars($this->standard['short_code'], ENT_QUOTES, 'UTF-8')); ?></span>
                <span class="client__segment"><?php echo ($this->standard['version'] === '' ? 'No version' : htmlspecialchars($this->standard['version'], ENT_QUOTES, 'UTF-8')); ?></span>
                <span class="client__segment" id="control_count"><?php echo (int) $this->standard['control_count']; ?> controls</span>
            </div>
        </div>
        <div class="page__actions">
            <a class="btn btn--primary" href="/standards/form/id/<?php echo (int) $this->standard['id']; ?>"><i class="fa-regular fa-pen"></i> Edit</a>
            <button type="button" class="btn btn--secondary" id="do_duplicate_open"><i class="fa-regular fa-copy"></i> Duplicate</button>
            <button type="button" class="btn btn--destructive" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete</button>
        </div>
    </div>

    <?php echo ($this->standard['description'] === '' ? '' : '<div class="panel record__panel"><div class="panel__body"><p class="standard__description">'.nl2br(htmlspecialchars($this->standard['description'], ENT_QUOTES, 'UTF-8')).'</p></div></div>'); ?>

    <div class="panel record__panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="control_search" class="input roster__search" placeholder="Search by identifier or title..." autocomplete="off" spellcheck="false">
            <div class="roster__filters">
                <button type="button" class="btn btn--secondary" id="do_import_open"><i class="fa-regular fa-file-arrow-up"></i> Import CSV</button>
                <button type="button" class="btn btn--primary" id="do_control_add"><i class="fa-regular fa-plus"></i> Add Control</button>
            </div>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data controls__table">
                    <thead>
                        <tr>
                            <th scope="col">Identifier</th>
                            <th scope="col">Control</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody id="controls_body"></tbody>
                </table>
            </div>
            <p class="controls__empty" id="controls_empty">Loading controls&hellip;</p>
        </div>
    </div>
</div>

<div class="modal fade" id="control_modal" tabindex="-1" aria-labelledby="control_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="control_modal_title">Add Control</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="field">
                        <label for="control_identifier">Identifier <abbr title="required">*</abbr></label>
                        <input type="text" id="control_identifier" placeholder="AC.L2-3.1.1" autocapitalize="characters" spellcheck="false">
                    </div>
                    <div class="field">
                        <label for="family">Family</label>
                        <input type="text" id="family" placeholder="Access Control" list="family_options">
                        <datalist id="family_options"></datalist>
                    </div>
                </div>
                <div class="field">
                    <label for="control_title">Title <abbr title="required">*</abbr></label>
                    <input type="text" id="control_title" placeholder="Limit system access to authorized users">
                </div>
                <div class="field">
                    <label for="control_description">Description</label>
                    <textarea id="control_description" rows="5" placeholder="The control text as published."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_control_save" class="btn btn--primary">Save Control</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="import_modal" tabindex="-1" aria-labelledby="import_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="import_modal_title">Import Controls</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="import__hint">Upload a CSV with the columns <strong>identifier, title, description, family</strong>, in that order. A header row is optional. Controls load in file order, and identifiers already in this standard are skipped.</p>
                <div class="field">
                    <label for="csv_file">CSV File</label>
                    <input type="file" id="csv_file" class="import__file" accept=".csv,text/csv">
                </div>
                <div class="import__result" id="import_result" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_import" class="btn btn--primary">Import Controls</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="duplicate_modal" tabindex="-1" aria-labelledby="duplicate_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="duplicate_modal_title">Duplicate Standard</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>The copy starts active and carries every control from <strong><?php echo htmlspecialchars($this->standard['standard_name'], ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
                <div class="field">
                    <label for="duplicate_name">Name <abbr title="required">*</abbr></label>
                    <input type="text" id="duplicate_name" value="<?php echo htmlspecialchars($this->standard['standard_name'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="duplicate_code">Short Code</label>
                        <input type="text" id="duplicate_code" autocapitalize="characters" spellcheck="false" value="<?php echo htmlspecialchars($this->standard['short_code'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="field">
                        <label for="duplicate_version">Version</label>
                        <input type="text" id="duplicate_version" placeholder="Rev 3" value="<?php echo htmlspecialchars($this->standard['version'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_duplicate" class="btn btn--primary">Duplicate Standard</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="delete_control_modal" tabindex="-1" aria-labelledby="delete_control_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_control_modal_title">Delete Control</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong id="delete_control_name"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_control_delete" class="btn btn--destructive">Delete Control</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete Standard</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong><?php echo htmlspecialchars($this->standard['standard_name'], ENT_QUOTES, 'UTF-8'); ?></strong> and every control it holds? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete Standard</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var standard_id = <?php echo (int) $this->standard['id']; ?>;
    var editing_control_id = 0;
    var deleting_control_id = 0;

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

    function control_row(control) {

        var body = control.description === ''
            ? '<span class="controls__title">' + esc(control.control_title) + '</span>'
            : '<details class="controls__detail"><summary>' + esc(control.control_title) + '</summary>'
                + '<p class="controls__desc">' + esc(control.description) + '</p></details>';

        return '<tr class="controls__row" data-id="' + control.id + '">'
            + '<td class="controls__id">' + esc(control.control_identifier) + '</td>'
            + '<td>' + body + '</td>'
            + '<td class="controls__actions">'
            + '<button type="button" class="btn btn--tertiary btn--sm" data-action="edit" aria-label="Edit ' + esc(control.control_identifier) + '"><i class="fa-regular fa-pen"></i></button>'
            + '<button type="button" class="btn btn--tertiary btn--sm" data-action="delete" aria-label="Delete ' + esc(control.control_identifier) + '"><i class="fa-regular fa-trash"></i></button>'
            + '</td></tr>';
    }

    function render(controls) {

        var html = '';
        var families = [];
        var current = null;

        for (var i = 0; i < controls.length; i++) {

            var family = controls[i].family === '' ? 'Ungrouped' : controls[i].family;

            if (family !== current) {
                current = family;
                html += '<tr class="controls__family"><td colspan="3">' + esc(family) + '</td></tr>';
                if (controls[i].family !== '' && families.indexOf(controls[i].family) === -1) {
                    families.push(controls[i].family);
                }
            }

            html += control_row(controls[i]);
        }

        $('#controls_body').html(html);
        $('#control_count').text(controls.length + (controls.length === 1 ? ' control' : ' controls'));

        var options = '';
        for (var f = 0; f < families.length; f++) {
            options += '<option value="' + esc(families[f]) + '"></option>';
        }
        $('#family_options').html(options);

    }

    /**
     * Match state is recomputed from the row text every pass rather than read back
     * off the DOM: while the table itself is hidden nothing inside it counts as
     * visible, so a cleared search could never restore the group headers.
     */
    function filter() {

        var term = $('#control_search').val().trim().toLowerCase();
        var visible = 0;

        $('#controls_body .controls__family').each(function () {

            var matched = 0;

            $(this).nextUntil('.controls__family', '.controls__row').each(function () {

                var text = $(this).find('.controls__id').text().toLowerCase()
                    + ' ' + $(this).find('summary, .controls__title').text().toLowerCase();

                var hit = term === '' || text.indexOf(term) !== -1;

                $(this).toggle(hit);

                if (hit) {
                    matched++;
                }
            });

            $(this).toggle(matched > 0);
            visible += matched;
        });

        $('.controls__table').toggle(visible > 0);
        $('#controls_empty').toggle(visible === 0);

        if (visible === 0) {
            $('#controls_empty').text(term === ''
                ? 'No controls yet. Add one, or import a CSV to load a whole framework at once.'
                : 'No controls match your search');
        }
    }

    function load() {
        $.post('/standards/load_controls', { standard_id: standard_id }, function (data) {
            render(JSON.parse(data));
            filter();
        });
    }

    $('#control_search').on('input', filter);

    $('#do_control_add').click(function () {
        editing_control_id = 0;
        $('#control_modal_title').text('Add Control');
        $('#do_control_save').text('Save Control');
        $('#control_identifier, #control_title, #family, #control_description').val('').removeClass('is-invalid');
        modal('control_modal').show();
    });

    $('#controls_body').on('click', '[data-action="edit"]', function () {

        var row = $(this).closest('.controls__row');

        editing_control_id = parseInt(row.attr('data-id'), 10);

        $('#control_modal_title').text('Edit Control');
        $('#do_control_save').text('Update Control');
        $('#control_identifier').val(row.find('.controls__id').text()).removeClass('is-invalid');
        $('#control_title').val(row.find('summary, .controls__title').text()).removeClass('is-invalid');
        $('#control_description').val(row.find('.controls__desc').text());
        $('#family').val(row.prevAll('.controls__family').first().text() === 'Ungrouped' ? '' : row.prevAll('.controls__family').first().text());

        modal('control_modal').show();
    });

    $('#controls_body').on('click', '[data-action="delete"]', function () {

        var row = $(this).closest('.controls__row');

        deleting_control_id = parseInt(row.attr('data-id'), 10);

        $('#delete_control_name').text(row.find('.controls__id').text());
        modal('delete_control_modal').show();
    });

    $('#do_control_save').click(function () {

        $('#control_identifier, #control_title').removeClass('is-invalid');

        var values = {
            standard_id: standard_id,
            control_id: editing_control_id,
            control_identifier: $('#control_identifier').val().trim(),
            control_title: $('#control_title').val().trim(),
            description: $('#control_description').val().trim(),
            family: $('#family').val().trim()
        };

        var errors = 0;

        if (values.control_identifier === '') {
            $('#control_identifier').addClass('is-invalid');
            errors++;
        }

        if (values.control_title === '') {
            $('#control_title').addClass('is-invalid');
            errors++;
        }

        if (errors > 0) {
            $('#control_modal .is-invalid').first().focus();
            toastr.error('Please fix the errors before continuing.');
            return;
        }

        set_loading('#do_control_save', true);

        $.post('/standards/save_control', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_control_save', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            modal('control_modal').hide();
            toastr.success(obj.message);
            load();
        });
    });

    $('#do_control_delete').click(function () {

        set_loading('#do_control_delete', true);

        $.post('/standards/delete_control', { control_id: deleting_control_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_control_delete', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            modal('delete_control_modal').hide();
            toastr.success(obj.message);
            load();
        });
    });

    $('#do_import_open').click(function () {
        $('#csv_file').val('');
        $('#import_result').attr('hidden', true).empty();
        modal('import_modal').show();
    });

    $('#do_import').click(function () {

        var file = document.getElementById('csv_file').files[0];

        if (!file) {
            toastr.error('Choose a CSV file to import.');
            return;
        }

        var form_data = new FormData();

        form_data.append('csv_file', file);
        form_data.append('standard_id', standard_id);
        form_data.append('csrf_token', $('meta[name="csrf-token"]').attr('content'));

        $('#import_result').attr('hidden', true).empty();
        set_loading('#do_import', true);

        $.ajax({
            url: '/standards/import_controls',
            type: 'POST',
            data: form_data,
            processData: false,
            contentType: false,
            success: function (data) {

                var obj = JSON.parse(data);

                set_loading('#do_import', false);

                if (!obj.success) {

                    var html = '<p class="import__failed">' + esc(obj.message) + '</p>';

                    if (obj.errors && obj.errors.length > 0) {
                        html += '<ul class="import__errors">';
                        for (var i = 0; i < obj.errors.length; i++) {
                            html += '<li><span class="import__row">Row ' + obj.errors[i].row + '</span> '
                                + esc(obj.errors[i].reason) + '</li>';
                        }
                        html += '</ul>';
                    }

                    $('#import_result').html(html).removeAttr('hidden');
                    return;
                }

                modal('import_modal').hide();
                toastr.success(obj.message);
                load();
            },
            error: function () {
                set_loading('#do_import', false);
                toastr.error('That file could not be imported.');
            }
        });
    });

    $('#do_duplicate_open').click(function () {
        modal('duplicate_modal').show();
    });

    $('#do_duplicate').click(function () {

        var values = {
            standard_id: standard_id,
            standard_name: $('#duplicate_name').val().trim(),
            short_code: $('#duplicate_code').val().trim(),
            version: $('#duplicate_version').val().trim()
        };

        if (values.standard_name === '') {
            $('#duplicate_name').addClass('is-invalid').focus();
            toastr.error('Please fix the errors before continuing.');
            return;
        }

        set_loading('#do_duplicate', true);

        $.post('/standards/duplicate_standard', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_duplicate', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            window.location.href = '/standards/detail/id/' + obj.standard_id;
        });
    });

    $('#do_delete_open').click(function () {
        modal('delete_modal').show();
    });

    $('#do_delete').click(function () {

        set_loading('#do_delete', true);

        $.post('/standards/delete_standard', { standard_id: standard_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            window.location.href = '/standards';
        });
    });

    load();

});
</script>
