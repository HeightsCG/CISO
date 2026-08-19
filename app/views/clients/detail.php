<div class="page">
    <a class="page__back" href="/clients"><i class="fa-regular fa-arrow-left"></i> Back to Clients</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>
        <div class="page__actions">
            <a class="btn btn--primary" href="/clients/form/id/<?php echo (int) $this->client['id']; ?>/from/detail"><i class="fa-regular fa-pen"></i> Edit</a>
            <button type="button" class="btn btn--destructive" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete</button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Projects</h2>
                </div>
                <div class="panel__body">
                    <ul class="projlist" id="client_projects_list"></ul>
                    <p class="controls__empty" id="client_projects_empty">Loading projects&hellip;</p>
                </div>
            </div>
            <?php if ((int) Session::get('role_id') === 1) { ?>
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Invoices</h2>
                    <a class="btn btn--secondary btn--sm" href="/billing/form/client/<?php echo (int) $this->client['id']; ?>"><i class="fa-regular fa-plus"></i> New Invoice</a>
                </div>
                <div class="panel__body panel__body--flush">
                    <div class="table-wrap">
                        <table class="data" id="client_invoices_table">
                            <thead>
                                <tr>
                                    <th scope="col">Invoice</th>
                                    <th scope="col">Created</th>
                                    <th scope="col">Due</th>
                                    <th scope="col" class="num">Amount</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody id="client_invoices_body"></tbody>
                        </table>
                    </div>
                    <p class="controls__empty" id="client_invoices_empty">Loading invoices&hellip;</p>
                </div>
            </div>
            <?php } ?>
        </div>
        <div class="col-lg-4">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Primary Contact</h2>
                </div>
                <div class="panel__body">
                    <dl class="datalist datalist--record">
                        <dt>Full Name</dt>
                        <dd><?php echo ($this->client['contact_name'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['contact_name'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Job Title</dt>
                        <dd><?php echo ($this->client['contact_title'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['contact_title'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Email Address</dt>
                        <dd><?php echo ($this->client['contact_email'] === '' ? '<span class="roster__none">&mdash;</span>' : '<a href="mailto:'.htmlspecialchars($this->client['contact_email'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($this->client['contact_email'], ENT_QUOTES, 'UTF-8').'</a>'); ?></dd>
                        <dt>Phone</dt>
                        <dd><?php echo ($this->client['contact_phone'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['contact_phone'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Website</dt>
                        <dd><?php echo ($this->client['website'] === '' ? '<span class="roster__none">&mdash;</span>' : '<a href="'.htmlspecialchars($this->client['website'], ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer">'.htmlspecialchars($this->client['website'], ENT_QUOTES, 'UTF-8').'</a>'); ?></dd>
                    </dl>
                </div>
            </div>
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Address</h2>
                </div>
                <div class="panel__body">
                    <dl class="datalist datalist--record">
                        <dt>Street</dt>
                        <dd><?php echo ($this->client['address_1'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['address_1'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Suite, Floor</dt>
                        <dd><?php echo ($this->client['address_2'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['address_2'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>City</dt>
                        <dd><?php echo ($this->client['city'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['city'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>State</dt>
                        <dd><?php echo ($this->client['state'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['state'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Postal Code</dt>
                        <dd><?php echo ($this->client['postal_code'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['postal_code'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Country</dt>
                        <dd><?php echo ($this->client['country'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['country'], ENT_QUOTES, 'UTF-8')); ?></dd>
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
                <h2 class="modal-title" id="delete_modal_title">Delete Client</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete Client</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var client_id = <?php echo (int) $this->client['id']; ?>;

    function size_of(done, total) {
        return total > 0 ? Math.round(done / total * 100) : 0;
    }

    function status_tone(value) {

        if (value === 'Complete') {
            return 'badge--active';
        }

        if (value === 'In Progress') {
            return 'badge--onboarding';
        }

        return 'badge--inactive';
    }

    function assessment_rows(assessments, project_id) {

        var html = '';

        for (var i = 0; i < assessments.length; i++) {

            if (parseInt(assessments[i].project_id, 10) !== parseInt(project_id, 10)) {
                continue;
            }

            var total = parseInt(assessments[i].item_count, 10);
            var done = parseInt(assessments[i].assessed_count, 10);
            var pct = size_of(done, total);

            html += '<li class="asmt">'
                + '<span class="asmt__label">'
                + '<a class="asmt__name" href="/projects/assessment/id/' + assessments[i].id + '">'
                + esc(assessments[i].assessment_name) + '</a>'
                + '<span class="asmt__standard">' + esc(assessments[i].short_code)
                + (assessments[i].version === '' ? '' : ' \u00b7 ' + esc(assessments[i].version)) + '</span>'
                + '</span>'
                + '<span class="asmt__progress">'
                + (total === 0
                    ? '<span class="roster__none">No controls yet</span>'
                    : '<span class="progress"><span class="progress__bar" style="width:' + pct + '%"></span></span>'
                      + '<span class="asmt__count">' + done + ' of ' + total + ' controls</span>')
                + '</span>'
                + '<span class="badge ' + status_tone(assessments[i].assessment_status) + '">'
                + esc(assessments[i].assessment_status) + '</span>'
                + '</li>';
        }

        return html === ''
            ? '<p class="proj__none">No assessments yet.</p>'
            : '<ul class="asmts">' + html + '</ul>';
    }

    function render_projects(projects, assessments) {

        var html = '';

        for (var i = 0; i < projects.length; i++) {

            var files = parseInt(projects[i].evidence_count, 10);
            var meta = [];

            meta.push(files + (files === 1 ? ' evidence file' : ' evidence files'));

            if (projects[i].end_date_display !== '') {
                meta.push('ends ' + esc(projects[i].end_date_display));
            }

            html += '<li class="proj">'
                + '<div class="proj__head">'
                + '<a class="proj__name" href="/projects/detail/id/' + projects[i].id + '">' + esc(projects[i].project_name) + '</a>'
                + '<span class="proj__meta">' + meta.join(' \u00b7 ') + '</span>'
                + '<span class="badge ' + (projects[i].project_status === 'Complete' ? 'badge--inactive' : 'badge--active') + '">'
                + esc(projects[i].project_status) + '</span>'
                + '</div>'
                + assessment_rows(assessments, projects[i].id)
                + '</li>';
        }

        $('#client_projects_list').html(html).prop('hidden', projects.length === 0);
        $('#client_projects_empty').prop('hidden', projects.length > 0).text('No projects for this client yet.');
    }

    function load_client_projects(){
        ApiDataSvc.apiCall('post', 'load_client_assessments', { client_id: client_id }, function (assessment_data) {

            var assessments = JSON.parse(assessment_data);

            ApiDataSvc.apiCall('post', 'load_client_projects', { client_id: client_id }, function (project_data) {
                render_projects(JSON.parse(project_data), assessments);
            });
        });
    }

    function invoice_tone(value) {
        if (value === 'Paid') {
            return 'badge--active';
        }
        if (value === 'Overdue' || value === 'Payment Failed') {
            return 'badge--critical';
        }
        if (value === 'Payment Processing' || value === 'Sending' || value === 'Action Needed') {
            return 'badge--admin';
        }
        if (value === 'Awaiting Payment') {
            return 'badge--onboarding';
        }
        if (value === 'Void' || value === 'Written Off') {
            return 'badge--inactive';
        }
        return 'badge--prospect';
    }

    function load_client_invoices(){

        if ($('#client_invoices_table').length === 0) {
            return;
        }

        ApiDataSvc.apiCall('post', 'load_client_invoices', { client_id: client_id }, function (data) {

            var list = JSON.parse(data);
            var html = '';

            for (var i = 0; i < list.length; i++) {
                html += '<tr data-id="' + list[i].id + '" tabindex="0">'
                    + '<td>' + esc(list[i].invoice_number ? list[i].invoice_number : 'Draft') + '</td>'
                    + '<td>' + esc(list[i].date_created_display) + '</td>'
                    + '<td>' + (list[i].due_date === null ? '<span class="roster__none">&mdash;</span>' : esc(list[i].due_date_display)) + '</td>'
                    + '<td class="num">' + esc(list[i].total_display) + '</td>'
                    + '<td><span class="badge ' + invoice_tone(list[i].invoice_status_display) + '">' + esc(list[i].invoice_status_display) + '</span></td>'
                    + '</tr>';
            }

            $('#client_invoices_body').html(html);
            $('#client_invoices_empty').prop('hidden', list.length > 0).text('No invoices for this client yet.');
        });
    }

    $('#client_invoices_table').on('click', 'tr', function () {
        window.location.href = '/billing/invoice/id/' + $(this).attr('data-id');
    });

    load_client_projects();
    load_client_invoices();

    var client_id = <?php echo (int) $this->client['id']; ?>;

    $('#do_delete_open').click(function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('delete_modal')).show();
    });

    $('#do_delete').click(function () {

        set_loading('#do_delete', true);

        ApiDataSvc.apiCall('post', 'delete_client', { client_id: client_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (obj.success) {

                window.location.href = '/clients';
            } else {
                toastr.error(obj.message);
            }
        });
    });

});
</script>
