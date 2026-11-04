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

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="assessment_search" class="input roster__search" placeholder="Search assessments" autocomplete="off" spellcheck="false" aria-label="Search assessments">
        </div>
        <div class="panel__body">
            <div class="tiles" id="assessments_list"></div>
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

    function status_tone(value) {

        if (value === 'Complete') {
            return 'badge--active';
        }

        if (value === 'In Progress') {
            return 'badge--onboarding';
        }

        return 'badge--inactive';
    }

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

            html += '<a class="tile" href="/portal/assessment/id/' + row.id + '">'
                + '<span class="tile__top">'
                + '<span class="tile__title">' + esc(row.assessment_name) + '</span>'
                + '<span class="badge ' + status_tone(row.assessment_status) + '">' + esc(row.assessment_status) + '</span>'
                + '</span>'
                + '<span class="tile__meta">' + esc(standard_of(row)) + '</span>'
                + '<span class="tile__figure">' + percent(parseInt(row.assessed_count, 10), parseInt(row.item_count, 10)) + '%<span class="tile__figure-label">complete</span></span>'
                + '<span class="tile__foot"><span>' + esc(dates_of(row)) + '</span></span>'
                + '</a>';
        }

        $('#assessments_list').html(html).prop('hidden', shown === 0);
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
