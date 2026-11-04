<div class="modal fade" data-bs-backdrop="static" id="assessment_modal" tabindex="-1" aria-labelledby="assessment_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="assessment_modal_title">New Assessment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <?php if (empty($this->client)) { ?>
                    <div class="col-md-12 form-group">
                        <label for="assessment_client_id">Client</label>
                        <select id="assessment_client_id" class="selectpicker" data-style="form-control" data-width="100%" data-live-search="true" data-size="8" data-container="body" title="Choose a client"></select>
                    </div>
                    <?php } ?>
                    <div class="col-md-12 form-group">
                        <label for="assessment_standard_id">Standard</label>
                        <select id="assessment_standard_id" class="selectpicker" data-style="form-control" data-width="100%" data-live-search="true" data-size="8" data-container="body" title="Choose a standard"></select>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for="assessment_name">Assessment Name</label>
                        <input type="text" class="form-control" id="assessment_name">
                    </div>
                    <div class="col-md-12 form-group">
                        <label for="assessment_schedule">Schedule</label>
                        <select id="assessment_schedule" class="selectpicker" data-style="form-control" data-width="100%" data-container="body">
                            <option value="Fixed dates">Fixed dates</option>
                            <option value="Ongoing">Ongoing</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group assessment-dates">
                        <label for="assessment_start">Start Date</label>
                        <input type="date" class="form-control" id="assessment_start">
                    </div>
                    <div class="col-md-6 form-group assessment-dates">
                        <label for="assessment_end">End Date</label>
                        <input type="date" class="form-control" id="assessment_end">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_assessment_save" class="btn btn--primary">Create Assessment</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var am_client_id = <?php echo (int) ($this->client['id'] ?? 0); ?>;

    function am_fill(id, options) {
        $('#' + id).selectpicker('destroy');
        $('#' + id).html(options);
        $('#' + id).selectpicker();
        $('#' + id).selectpicker('val', '');
    }

    function am_dates() {
        $('.assessment-dates').prop('hidden', $('#assessment_schedule').val() === 'Ongoing');
    }

    function am_open(standards, clients) {

        var options = '';

        for (var i = 0; i < standards.length; i++) {
            options += '<option value="' + standards[i].id + '">' + esc(standard_display(standards[i].standard_name, standards[i].version)) + '</option>';
        }

        am_fill('assessment_standard_id', options);

        if (clients !== null) {

            options = '';

            for (var j = 0; j < clients.length; j++) {
                options += '<option value="' + clients[j].id + '">' + esc(clients[j].company_name) + '</option>';
            }

            am_fill('assessment_client_id', options);
        }

        $('#assessment_modal .form-control').removeClass('is-invalid');
        $('#assessment_name').val('').removeData('default_name');
        $('#assessment_schedule').selectpicker('val', 'Fixed dates');
        $('#assessment_start').val('');
        $('#assessment_end').val('');
        am_dates();
        modal('assessment_modal').show();
    }

    $('#assessment_schedule').on('change', am_dates);

    $(document).on('click', '.do_assessment_add', function () {

        var button = this;

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'load_active_standards', {}, function (data) {

            var standards = JSON.parse(data);

            if (am_client_id > 0) {
                set_loading(button, false);
                am_open(standards, null);
                return;
            }

            ApiDataSvc.apiCall('post', 'load_clients', {}, function (client_data) {

                var clients = JSON.parse(client_data);

                clients.sort(function (a, b) {
                    return a.company_name.localeCompare(b.company_name);
                });

                set_loading(button, false);
                am_open(standards, clients);
            });
        });
    });

    $(document).on('change', '#assessment_standard_id', function () {

        var name = $('#assessment_name');
        var current = name.val().trim();
        var suggested = ($(this).val() ? $(this).find('option:selected').text() + ' ' + new Date().getFullYear() : '');

        if (current === '' || current === name.data('default_name')) {
            name.val(suggested).removeClass('is-invalid');
            name.data('default_name', suggested);
        }
    });

    $('#do_assessment_save').click(function () {

        var ongoing = ($('#assessment_schedule').val() === 'Ongoing');
        var values = {
            client_id: (am_client_id > 0 ? am_client_id : ($('#assessment_client_id').val() || '')),
            standard_id: $('#assessment_standard_id').val() || '',
            assessment_name: $('#assessment_name').val().trim(),
            schedule: $('#assessment_schedule').val(),
            start_date: (ongoing ? '' : $('#assessment_start').val()),
            end_date: (ongoing ? '' : $('#assessment_end').val())
        };
        var problem = '';

        $('#assessment_modal .form-control').removeClass('is-invalid');

        if (values.client_id === '') {
            problem = 'Choose a client.';
        } else if (values.standard_id === '') {
            problem = 'Choose a standard to assess against.';
        } else if (values.assessment_name === '') {
            $('#assessment_name').addClass('is-invalid');
            problem = 'Enter an assessment name.';
        } else if (!ongoing && values.start_date === '') {
            $('#assessment_start').addClass('is-invalid');
            problem = 'Choose a start date.';
        } else if (!ongoing && values.end_date === '') {
            $('#assessment_end').addClass('is-invalid');
            problem = 'Choose an end date.';
        } else if (!ongoing && values.end_date < values.start_date) {
            $('#assessment_end').addClass('is-invalid');
            problem = 'The end date cannot fall before the start date.';
        }

        if (problem !== '') {
            $('#assessment_modal .is-invalid').first().focus();
            toastr.error(problem);
            return;
        }

        set_loading('#do_assessment_save', true);

        ApiDataSvc.apiCall('post', 'create_assessment', values, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                window.location.href = '/assessments/detail/id/' + obj.assessment_id;
                return;
            }

            set_loading('#do_assessment_save', false);
            toastr.error(obj.message);
        });
    });

});
</script>
