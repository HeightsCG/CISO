<div class="page page--list">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title">Assessments</h1>
        </div>
        <div class="headline__actions">
            <button type="button" class="btn btn--primary btn--add do_assessment_add" aria-label="New assessment" title="New assessment"><i class="fa-regular fa-plus"></i></button>
        </div>
    </header>

    <div class="panel listpage">
        <div class="panel__head roster__toolbar">
            <input type="search" id="assessment_search" class="input roster__search" placeholder="Search assessments" autocomplete="off" spellcheck="false" aria-label="Search assessments">
            <div class="roster__filters">
                <div class="roster__filter">
                    <select id="client_filter" class="form-select" data-live-search="true" aria-label="Filter by client">
                        <option value="">All clients</option>
                    </select>
                </div>
                <div class="roster__filter">
                    <select id="status_filter" class="form-select" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <option value="Planned">Not Started</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Complete">Complete</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="panel__body panel__body--flush listpage__body">
            <table class="data listpage__table" id="assessments_table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Framework</th>
                        <th scope="col">Status</th>
                        <th scope="col">Progress</th>
                    </tr>
                </thead>
                <tbody id="assessments_list"></tbody>
            </table>
            <p class="tiles__empty" id="assessments_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading assessments&hellip;</p>
        </div>
    </div>
</div>

<?php require Main::app_path().'/app/views/partials/assessment_modal.php'; ?>

<script>
$(document).ready(function () {

    var assessments = [];

    var STATUS = {
        'Planned': {
            label: 'Not Started',
            tone: 'is-none'
        },
        'In Progress': {
            label: 'In Progress',
            tone: 'is-partial'
        },
        'Complete': {
            label: 'Complete',
            tone: 'is-ok'
        }
    };

    function render() {

        var term = $('#assessment_search').val().trim().toLowerCase();
        var client = $('#client_filter').val() || '';
        var status = $('#status_filter').val() || '';
        var html = '';
        var shown = 0;

        for (var i = 0; i < assessments.length; i++) {

            var row = assessments[i];
            var total = parseInt(row.item_count, 10) || 0;
            var done = parseInt(row.assessed_count, 10) || 0;
            var pct = total > 0 ? Math.round(done / total * 100) : 0;
            var state = STATUS[row.assessment_status] || STATUS['Planned'];

            if (client !== '' && String(row.client_id) !== client) {
                continue;
            }

            if (status !== '' && row.assessment_status !== status) {
                continue;
            }

            if (term !== '' && (row.assessment_name + ' ' + (row.client_name || '') + ' ' + (row.short_code || '')).toLowerCase().indexOf(term) === -1) {
                continue;
            }

            shown++;

            html += '<tr class="rowlink">'
                + '<td><a class="rowlink__a listpage__name" href="/assessments/detail/id/' + row.id + '">' + esc(row.assessment_name) + '</a>'
                + (row.client_name ? '<span class="listpage__sub">' + esc(row.client_name) + '</span>' : '') + '</td>'
                + '<td>' + esc(row.short_code || '') + '</td>'
                + '<td><span class="listpage__status" title="' + state.label + '"><span class="rollup__dot ' + state.tone + '"></span><span class="visually-hidden">' + state.label + '</span></span></td>'
                + '<td><span class="listpage__progress"><span class="progress"><span class="progress__bar" style="width:' + pct + '%"></span></span><span class="listpage__pct">' + pct + '%</span></span></td>'
                + '</tr>';
        }

        $('#assessments_list').html(html);
        $('#assessments_table').prop('hidden', shown === 0);
        $('#assessments_empty').prop('hidden', shown > 0).text(assessments.length === 0
            ? 'No assessments yet.'
            : 'No assessments match your search.');
    }

    function fill_clients() {

        var seen = {};
        var list = [];
        var options = '<option value="">All clients</option>';

        for (var i = 0; i < assessments.length; i++) {
            if (assessments[i].client_name && !seen[assessments[i].client_id]) {
                seen[assessments[i].client_id] = true;
                list.push(assessments[i]);
            }
        }

        list.sort(function (a, b) {
            return a.client_name.localeCompare(b.client_name);
        });

        for (var j = 0; j < list.length; j++) {
            options += '<option value="' + list[j].client_id + '">' + esc(list[j].client_name) + '</option>';
        }

        $('#client_filter').html(options);
    }

    $('#assessment_search').on('input', render);
    $('#client_filter').on('change', render);
    $('#status_filter').on('change', render);

    ApiDataSvc.apiCall('post', 'load_all_assessments', {}, function (data) {

        assessments = JSON.parse(data);
        assessments.sort(function (a, b) {
            return a.assessment_name.localeCompare(b.assessment_name);
        });

        fill_clients();
        render();
    });

});
</script>
