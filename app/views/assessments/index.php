<div class="page page--list">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title">Assessments</h1>
        </div>
        <div class="headline__actions">
            <button type="button" class="btn btn--primary btn--add do_assessment_add" aria-label="New assessment" title="New assessment"><i class="fa-regular fa-plus"></i></button>
        </div>
    </header>

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="assessment_search" class="input roster__search" placeholder="Search assessments" autocomplete="off" spellcheck="false" aria-label="Search assessments">
            <div class="roster__filters">
                <div class="roster__filter">
                    <select id="client_filter" class="form-select" data-live-search="true" aria-label="Filter by client">
                        <option value="">All clients</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="panel__body">
            <div class="tiles" id="assessments_list"></div>
            <p class="tiles__empty" id="assessments_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading assessments&hellip;</p>
        </div>
    </div>
</div>

<?php require Main::app_path().'/app/views/partials/assessment_modal.php'; ?>

<script>
$(document).ready(function () {

    var assessments = [];

    function status_tone(value) {

        if (value === 'Complete') {
            return 'badge--active';
        }

        if (value === 'In Progress') {
            return 'badge--onboarding';
        }

        return 'badge--inactive';
    }

    function render() {

        var term = $('#assessment_search').val().trim().toLowerCase();
        var client = $('#client_filter').val() || '';
        var html = '';
        var shown = 0;

        for (var i = 0; i < assessments.length; i++) {

            var row = assessments[i];
            var total = parseInt(row.item_count, 10) || 0;
            var done = parseInt(row.assessed_count, 10) || 0;

            if (client !== '' && String(row.client_id) !== client) {
                continue;
            }

            if (term !== '' && (row.assessment_name + ' ' + (row.client_name || '') + ' ' + (row.short_code || '')).toLowerCase().indexOf(term) === -1) {
                continue;
            }

            shown++;

            html += '<a class="tile" href="/assessments/detail/id/' + row.id + '">'
                + '<span class="tile__top">'
                + '<span class="tile__title">' + esc(row.assessment_name) + '</span>'
                + '<span class="badge ' + status_tone(row.assessment_status) + '">' + esc(row.assessment_status) + '</span>'
                + '</span>'
                + (row.client_name ? '<span class="tile__meta">' + esc(row.client_name) + '</span>' : '')
                + '<span class="tile__figure">' + (total > 0 ? Math.round(done / total * 100) : 0) + '%<span class="tile__figure-label">complete</span></span>'
                + '</a>';
        }

        $('#assessments_list').html(html).prop('hidden', shown === 0);
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
