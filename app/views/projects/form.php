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
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="project_name">Project Name</label>
                        <input type="text" class="form-control" id="project_name" placeholder="2026 CMMC readiness" value="<?php echo ($this->project === null ? '' : htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8')); ?>">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label for="date_range">Project Dates</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="start_date" value="<?php echo ($this->project === null ? '' : htmlspecialchars($this->project['start_date'], ENT_QUOTES, 'UTF-8')); ?>">
                            <input type="date" class="form-control" id="end_date" value="<?php echo ($this->project === null ? '' : htmlspecialchars($this->project['end_date'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="client_id">Client</label>
                        <select class="form-control" id="client_id">
                            <option value="0">No client</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" rows="4" placeholder="What this engagement covers."><?php echo ($this->project === null ? '' : htmlspecialchars($this->project['description'], ENT_QUOTES, 'UTF-8')); ?></textarea>
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

    function load_clients() {
        $.post('/api/load_clients', {}, function (data) {

            var list = JSON.parse(data);
            var options = '<option value="0">No client</option>';

            for (var i = 0; i < list.length; i++) {
                options += '<option value="' + list[i].id + '"'
                    + (parseInt(list[i].id, 10) === current_client_id ? ' selected' : '') + '>'
                    + esc(list[i].company_name) + '</option>';
            }

            $('#client_id').html(options);
        });
    }

    $('#do_save').click(function () {

        $('.form-control').removeClass('is-invalid');

        var values = {
            project_id: project_id,
            client_id: $('#client_id').val(),
            project_name: $('#project_name').val().trim(),
            description: $('#description').val().trim(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val()
        };

        var errors = 0;

        if (values.project_name === '') {
            $('#project_name').addClass('is-invalid');
            errors++;
        }

        if (values.start_date === '') {
            $('#start_date').addClass('is-invalid');
            errors++;
        }

        if (values.end_date === '') {
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
