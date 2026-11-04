<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<div class="page">
    <a class="page__back" href="<?php echo ($this->project === null ? '/projects' : '/projects/detail/id/'.((int) $this->project['id'])); ?>"><i class="fa-regular fa-arrow-left"></i> <?php echo ($this->project === null ? 'Back to Projects' : 'Back to Project'); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo ($this->project === null ? 'Add Project' : 'Edit Project'); ?></h1>
        </div>
    </div>

    <div class="panel">
        <div class="panel__body">
            <div class="record__group">
                <h2 class="record__group-title">Project</h2>
                <div class="row g-3">
                    <div class="col-md-6 form-group">
                        <label for="project_name">Project Name</label>
                        <input type="text" class="form-control" id="project_name" placeholder="Enter project name" value="<?php echo ($this->project === null ? '' : htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8')); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="client_id">Client</label>
                        <select id="client_id" class="selectpicker" data-style="form-control" data-width="100%" data-live-search="true" data-size="10">
                            <option value="0">No client</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="schedule">Schedule</label>
                        <select id="schedule" class="selectpicker" data-style="form-control" data-width="100%">
                            <option value="Fixed dates">Fixed dates</option>
                            <option value="Ongoing"<?php echo ($this->project !== null && empty($this->project['end_date']) ? ' selected' : ''); ?>>Ongoing</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group project-dates">
                        <label for="start_date">Start Date</label>
                        <input type="date" class="form-control" id="start_date" value="<?php echo ($this->project === null ? '' : htmlspecialchars((string) $this->project['start_date'], ENT_QUOTES, 'UTF-8')); ?>">
                    </div>
                    <div class="col-md-6 form-group project-dates">
                        <label for="end_date">End Date</label>
                        <input type="date" class="form-control" id="end_date" value="<?php echo ($this->project === null ? '' : htmlspecialchars((string) $this->project['end_date'], ENT_QUOTES, 'UTF-8')); ?>">
                    </div>
                    <div class="col-md-12 form-group">
                        <label for="description_editor">Description</label>
                        <div id="description_editor" class="rich-editor"></div>
                    </div>
                </div>
            </div>
            <div class="panel__actions">
                <a class="btn btn--secondary" href="<?php echo ($this->project === null ? '/projects' : '/projects/detail/id/'.((int) $this->project['id'])); ?>">Cancel</a>
                <button type="button" id="do_save" class="btn btn--primary">Save Project</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var project_id = <?php echo ($this->project === null ? 0 : (int) $this->project['id']); ?>;
    var current_client_id = <?php echo ($this->project === null ? 0 : (int) $this->project['client_id']); ?>;

    var editor = new Quill('#description_editor', {
        theme: 'snow',
        placeholder: 'What this engagement covers.',
        formats: ['bold', 'italic', 'underline', 'list', 'link'],
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ list: 'bullet' }, { list: 'ordered' }],
                ['link']
            ]
        }
    });

    editor.clipboard.dangerouslyPasteHTML(<?php echo json_encode(RichText::editable($this->project === null ? '' : $this->project['description']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

    function description() {

        if (editor.getText().trim() === '') {
            return '';
        }

        return editor.getSemanticHTML().replace(/&nbsp;/g, ' ');
    }

    function show_dates() {
        $('.project-dates').prop('hidden', $('#schedule').val() === 'Ongoing');
    }

    $('#schedule').on('changed.bs.select change', show_dates);
    show_dates();

    function load_clients() {
        ApiDataSvc.apiCall('post', 'load_clients', {}, function (data) {

            var list = JSON.parse(data);
            var options = '<option value="0">No client</option>';

            for (var i = 0; i < list.length; i++) {
                options += '<option value="' + list[i].id + '"'
                    + (parseInt(list[i].id, 10) === current_client_id ? ' selected' : '') + '>'
                    + esc(list[i].company_name) + '</option>';
            }

            $('#client_id').selectpicker('destroy');
            $('#client_id').html(options).selectpicker();
        });
    }

    $('#do_save').click(function () {

        $('.form-control').removeClass('is-invalid');

        var values = {
            project_id: project_id,
            client_id: $('#client_id').val(),
            project_name: $('#project_name').val().trim(),
            description: description(),
            schedule: $('#schedule').val(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val()
        };

        var ongoing = (values.schedule === 'Ongoing');

        if (ongoing) {
            values.start_date = '';
            values.end_date = '';
        }

        var errors = 0;

        if (values.project_name === '') {
            $('#project_name').addClass('is-invalid');
            errors++;
        }

        if (!ongoing && values.start_date === '') {
            $('#start_date').addClass('is-invalid');
            errors++;
        }

        if (!ongoing && values.end_date === '') {
            $('#end_date').addClass('is-invalid');
            errors++;
        }

        if (values.start_date !== '' && values.end_date !== '' && values.end_date < values.start_date) {
            $('#end_date').addClass('is-invalid');
            toastr.error('The end date cannot fall before the start date.');
            return;
        }

        if (errors > 0) {
            $('.form-control.is-invalid').first().focus();
            toastr.error('Please fix the errors before continuing.');
            return;
        }

        set_loading('#do_save', true);

        ApiDataSvc.apiCall('post', 'save_project', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_save', false);

            if (obj.success) {

                window.location.href = '/projects/detail/id/' + obj.project_id;
            } else {
                toastr.error(obj.message);
            }
        });
    });

    load_clients();

});
</script>
