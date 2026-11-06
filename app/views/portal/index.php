<div class="page page--list">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title">Your Assessments</h1>
        </div>
    </header>

    <div class="panel record__panel">
        <div class="panel__body">
            <div class="rollup" id="rollup"></div>
            <div class="rollup__progress">
                <div class="progress progress--lg"><div class="progress__bar" id="rollup_bar" style="width:0%"></div></div>
                <span class="progress__label" id="rollup_label">&nbsp;</span>
            </div>
        </div>
    </div>

    <div class="panel listpage">
        <div class="panel__head roster__toolbar">
            <input type="search" id="assessment_search" class="input roster__search" placeholder="Search assessments" autocomplete="off" spellcheck="false" aria-label="Search assessments">
        </div>
        <div class="panel__body panel__body--flush listpage__body">
            <table class="data listpage__table" id="assessments_table" hidden>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Progress</th>
                        <th scope="col">Dates</th>
                    </tr>
                </thead>
                <tbody id="assessments_list"></tbody>
            </table>
            <p class="tiles__empty" id="assessments_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading assessments&hellip;</p>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var assessments = [];

    function percent(done, total) {
        return total > 0 ? Math.round(done / total * 100) : 0;
    }

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

    function standard_of(row) {
        return row.short_code + (row.version === '' ? '' : ' · ' + row.version);
    }

    function dates_of(row) {

        if (!row.end_date_display) {
            return 'Ongoing';
        }

        return row.start_date_display + ' – ' + row.end_date_display;
    }

    function stat(count, label, dot) {
        return '<span class="rollup__stat">'
            + (dot === '' ? '' : '<span class="rollup__dot ' + dot + '"></span>')
            + '<span class="rollup__n">' + count + '</span>'
            + '<span class="rollup__label">' + label + '</span></span>';
    }

    function render_rollup() {

        var count = assessments.length;
        var items = 0;
        var assessed = 0;
        var open = 0;

        for (var i = 0; i < assessments.length; i++) {
            items += parseInt(assessments[i].item_count, 10);
            assessed += parseInt(assessments[i].assessed_count, 10);
            if (assessments[i].assessment_status !== 'Complete') {
                open++;
            }
        }

        $('#rollup').html(
            stat(count, count === 1 ? 'assessment' : 'assessments', '')
            + stat(open, 'in progress', 'is-partial')
            + stat(items - assessed, 'controls outstanding', 'is-none')
            + stat(assessed, 'controls assessed', 'is-ok')
        );

        $('#rollup_bar').css('width', percent(assessed, items) + '%');
        $('#rollup_label').text(items === 0
            ? 'No controls yet'
            : assessed + ' of ' + items + ' controls assessed · ' + percent(assessed, items) + '%');
    }

    function render() {

        var term = $('#assessment_search').val().trim().toLowerCase();
        var html = '';
        var shown = 0;

        for (var i = 0; i < assessments.length; i++) {

            var row = assessments[i];
            var text = (row.assessment_name + ' ' + standard_of(row) + ' ' + row.assessment_status).toLowerCase();

            if (term !== '' && text.indexOf(term) === -1) {
                continue;
            }

            shown++;

            var pct = percent(parseInt(row.assessed_count, 10), parseInt(row.item_count, 10));
            var state = STATUS[row.assessment_status] || STATUS['Planned'];

            html += '<tr class="rowlink">'
                + '<td><a class="rowlink__a listpage__name" href="/portal/assessment/id/' + row.id + '">' + esc(row.assessment_name) + '</a>'
                + '<span class="listpage__sub">' + esc(standard_of(row)) + '</span></td>'
                + '<td><span class="listpage__status" title="' + state.label + '"><span class="rollup__dot ' + state.tone + '"></span><span class="visually-hidden">' + state.label + '</span></span></td>'
                + '<td><span class="listpage__progress"><span class="progress"><span class="progress__bar" style="width:' + pct + '%"></span></span><span class="listpage__pct">' + pct + '%</span></span></td>'
                + '<td>' + esc(dates_of(row)) + '</td>'
                + '</tr>';
        }

        $('#assessments_list').html(html);
        $('#assessments_table').prop('hidden', shown === 0);
        $('#assessments_empty').prop('hidden', shown > 0).text(assessments.length === 0
            ? 'No assessments yet.'
            : 'No assessments match your search.');
    }

    $('#assessment_search').on('input', render);

    ApiDataSvc.apiCall('post', 'portal_assessments', {}, function (data) {

        assessments = JSON.parse(data);
        assessments.sort(function (a, b) {
            return a.assessment_name.localeCompare(b.assessment_name);
        });

        render_rollup();
        render();
    });

});
</script>
