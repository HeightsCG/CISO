<div class="page">
    <a class="page__back" href="/projects"><i class="fa-regular fa-arrow-left"></i> Back to Projects</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="client__meta">
                <span class="badge <?php echo ($this->project['project_status'] === 'Complete' ? 'badge--inactive' : 'badge--active'); ?>"><?php echo htmlspecialchars($this->project['project_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo ($this->project['client_name'] === null ? 'No client assigned' : htmlspecialchars($this->project['client_name'], ENT_QUOTES, 'UTF-8')); ?></span>
                <span class="client__segment"><?php echo htmlspecialchars($this->project['start_date_display'], ENT_QUOTES, 'UTF-8'); ?> &ndash; <?php echo htmlspecialchars($this->project['end_date_display'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="page__actions">
            <?php echo ((int) $this->project['client_id'] === 0 ? '' : '<a class="btn btn--secondary" href="/clients/evidence/id/'.((int) $this->project['client_id']).'/from/project/ref/'.((int) $this->project['id']).'"><i class="fa-regular fa-folder-open"></i> Evidence Vault</a>'); ?>
            <a class="btn btn--secondary" href="/projects/form/id/<?php echo (int) $this->project['id']; ?>"><i class="fa-regular fa-pen"></i> Edit</a>
            <button type="button" class="btn btn--primary" id="do_assessment_add"><i class="fa-regular fa-plus"></i> Assessment</button>
            <button type="button" class="btn btn--destructive" id="do_delete_open"><i class="fa-regular fa-trash"></i></button>
        </div>
    </div>

    <?php echo ((int) $this->project['client_id'] === 0 ? '<div class="notice notice--warn"><strong>No client assigned.</strong> Evidence is collected into a client&rsquo;s vault, so assessments on this project cannot attach evidence until a client is set. <a href="/projects/form/id/'.((int) $this->project['id']).'">Edit the project</a> to assign one.</div>' : ''); ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel record__panel">
                <div class="panel__head roster__toolbar">
                    <input type="search" id="assessment_search" class="input roster__search" placeholder="Search assessments..." autocomplete="off" spellcheck="false">
                </div>
                <div class="panel__body panel__body--flush">
                    <div class="table-wrap">
                        <table class="data" id="assessments_table">
                            <thead>
                                <tr>
                                    <th scope="col">Assessment</th>
                                    <th scope="col">Standard</th>
                                    <th scope="col">Progress</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <p class="controls__empty" id="assessments_empty">Loading assessments&hellip;</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Project</h2>
                </div>
                <div class="panel__body">
                    <dl class="datalist datalist--record">
                        <dt>Client</dt>
                        <dd><?php echo ($this->project['client_name'] === null ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->project['client_name'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Status</dt>
                        <dd><?php echo htmlspecialchars($this->project['project_status'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>Start</dt>
                        <dd><?php echo htmlspecialchars($this->project['start_date_display'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>End</dt>
                        <dd><?php echo htmlspecialchars($this->project['end_date_display'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>Description</dt>
                        <dd><?php echo ($this->project['description'] === '' ? '<span class="roster__none">&mdash;</span>' : nl2br(htmlspecialchars($this->project['description'], ENT_QUOTES, 'UTF-8'))); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete Project</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong><?php echo htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8'); ?></strong>? This cannot be undone.</p>
                <div class="notice notice--warn" id="delete_warning" hidden></div>
                <p class="import__hint">Evidence stays in the client&rsquo;s vault &mdash; only the attachments to this project&rsquo;s controls are removed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete Project</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="assessment_modal" tabindex="-1" aria-labelledby="assessment_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="assessment_modal_title">New Assessment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="import__hint">The standard&rsquo;s controls are copied into the assessment when it is created, so later edits to the library will not change an assessment already under way.</p>
                <div class="field">
                    <label for="assessment_name">Assessment Name <abbr title="required">*</abbr></label>
                    <input type="text" id="assessment_name" placeholder="2026 annual CMMC Level 2 assessment">
                </div>
                <div class="field">
                    <label for="standard_id">Standard <abbr title="required">*</abbr></label>
                    <select id="standard_id" class="form-select">
                        <option value="0">Choose a standard&hellip;</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_assessment_save" class="btn btn--primary">Create</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var project_id = <?php echo (int) $this->project['id']; ?>;

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

    function status_badge(value) {
        if (value === 'Complete') {
            return '<span class="badge badge--active">Complete</span>';
        }
        if (value === 'In Progress') {
            return '<span class="badge badge--onboarding">In Progress</span>';
        }
        return '<span class="badge badge--prospect">Planned</span>';
    }

    function progress_bar(done, total) {
        var pct = total > 0 ? Math.round(done / total * 100) : 0;
        return '<div class="progress"><div class="progress__bar" style="width:' + pct + '%"></div></div>'
            + '<span class="progress__label">' + done + ' of ' + total + ' &middot; ' + pct + '%</span>';
    }

    function render(list) {

        var html = '';

        for (var i = 0; i < list.length; i++) {
            html += '<tr data-id="' + list[i].id + '" tabindex="0">'
                + '<td>' + esc(list[i].assessment_name) + '</td>'
                + '<td><span class="roster__name">' + esc(list[i].standard_name) + '</span>'
                + '<span class="roster__sub">' + esc(list[i].short_code)
                + (list[i].version ? ' &middot; ' + esc(list[i].version) : '') + '</span></td>'
                + '<td>' + progress_bar(parseInt(list[i].assessed_count || 0, 10), parseInt(list[i].item_count || 0, 10)) + '</td>'
                + '<td>' + status_badge(list[i].assessment_status) + '</td>'
                + '</tr>';
        }

        $('#assessments_table tbody').html(html);
    }

    function filter() {

        var term = $('#assessment_search').val().trim().toLowerCase();
        var rows = $('#assessments_table tbody tr');
        var visible = 0;

        rows.each(function () {
            var hit = term === '' || $(this).text().toLowerCase().indexOf(term) !== -1;
            $(this).toggle(hit);
            if (hit) {
                visible++;
            }
        });

        $('#assessments_table').toggle(visible > 0);
        $('#assessments_empty').toggle(visible === 0);

        if (visible === 0) {
            $('#assessments_empty').text(rows.length === 0
                ? 'No assessments yet. Create one against a standard from the library.'
                : 'No assessments match your search');
        }
    }

    function load() {
        ApiDataSvc.apiCall('post', 'load_assessments', { project_id: project_id }, function (data) {
            render(JSON.parse(data));
            filter();
        });
    }

    $('#assessment_search').on('input', filter);

    $('#assessments_table tbody').on('click', 'tr', function () {
        window.location.href = '/projects/assessment/id/' + $(this).attr('data-id');
    });

    $('#do_assessment_add').click(function () {

        $('#assessment_name').val('').removeClass('is-invalid');

        ApiDataSvc.apiCall('post', 'load_active_standards', {}, function (data) {

            var list = JSON.parse(data);
            var options = '<option value="0">Choose a standard&hellip;</option>';

            for (var i = 0; i < list.length; i++) {
                options += '<option value="' + list[i].id + '">' + esc(list[i].standard_name)
                    + (list[i].version ? ' (' + esc(list[i].version) + ')' : '') + '</option>';
            }

            $('#standard_id').html(options);
            modal('assessment_modal').show();
        });
    });

    $('#do_assessment_save').click(function () {

        var values = {
            project_id: project_id,
            assessment_name: $('#assessment_name').val().trim(),
            standard_id: $('#standard_id').val()
        };

        if (values.assessment_name === '') {
            $('#assessment_name').addClass('is-invalid').focus();
            toastr.error('Please fix the errors before continuing.');
            return;
        }

        if (values.standard_id === '0') {
            toastr.error('Choose a standard to assess against.');
            return;
        }

        set_loading('#do_assessment_save', true);

        ApiDataSvc.apiCall('post', 'create_assessment', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_assessment_save', false);

            if (obj.success) {

                window.location.href = '/projects/assessment/id/' + obj.assessment_id;
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_delete_open').click(function () {

        ApiDataSvc.apiCall('post', 'load_assessments', { project_id: project_id }, function (data) {

            var list = JSON.parse(data);
            var items = 0;

            for (var i = 0; i < list.length; i++) {
                items += parseInt(list[i].item_count, 10) || 0;
            }

            if (list.length === 0) {
                $('#delete_warning').attr('hidden', true);
            } else {
                $('#delete_warning').removeAttr('hidden').html('<strong>'
                    + list.length + ' ' + (list.length === 1 ? 'assessment' : 'assessments')
                    + ' and ' + items + ' assessed ' + (items === 1 ? 'item' : 'items')
                    + '</strong> will be deleted with it, including every result and note.');
            }

            modal('delete_modal').show();
        });
    });

    $('#do_delete').click(function () {

        set_loading('#do_delete', true);

        ApiDataSvc.apiCall('post', 'delete_project', { project_id: project_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (obj.success) {

                window.location.href = '/projects';
            } else {
                toastr.error(obj.message);
            }
        });
    });

    load();

});
</script>
