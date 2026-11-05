<div class="page" id="dash">
    <h1 class="visually-hidden">Dashboard</h1>

    <div class="panel record__panel summary">
        <dl class="kpi" id="kpis"></dl>
    </div>

    <div class="panel record__panel dash__results">
        <div class="panel__head">
            <h2 class="panel__title">Control Results</h2>
            <span class="panel__note" id="results_total"></span>
        </div>
        <div class="panel__body">
            <div class="dash__bar" id="results_bar"></div>
            <ul class="dash__legend" id="results_legend"></ul>
        </div>
    </div>

    <div class="row gx-4 dash__lists">
        <div class="col-lg-6 dash__col">
            <div class="panel record__panel dash__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Needs Attention</h2>
                </div>
                <div class="panel__body dash__scroll">
                    <ul class="metriclist" id="attention"></ul>
                    <p class="controls__empty" id="attention_empty" hidden>No assessments are late or behind.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6 dash__col">
            <div class="panel record__panel dash__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Milestones, Next 30 Days</h2>
                </div>
                <div class="panel__body dash__scroll">
                    <ul class="metriclist dash__milestones" id="milestones"></ul>
                    <p class="controls__empty" id="milestones_empty" hidden>No POA&amp;M milestones are due in the next 30 days.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var RESULTS = [
        {
            name: 'Implemented',
            label: 'Implemented',
            tone: 'is-ok'
        },
        {
            name: 'Partially Implemented',
            label: 'Partial',
            tone: 'is-partial'
        },
        {
            name: 'Not Implemented',
            label: 'Not Implemented',
            tone: 'is-gap'
        },
        {
            name: 'Not Applicable',
            label: 'N/A',
            tone: 'is-na'
        },
        {
            name: 'Not Assessed',
            label: 'Not Assessed',
            tone: 'is-none'
        }
    ];

    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    var data = {};

    function num(value) {
        return parseInt(value, 10) || 0;
    }

    function group(value) {
        return value.toLocaleString('en-US');
    }

    function short_date(value) {
        var parts = String(value).split('-');

        return MONTHS[num(parts[1]) - 1] + ' ' + num(parts[2]);
    }

    function kpi(label, value, sub, tone) {
        return '<div class="kpi__cell">'
            + '<dt class="kpi__label">' + label + '</dt>'
            + '<dd class="kpi__value' + (tone === '' ? '' : ' ' + tone) + '">' + value + '</dd>'
            + '<dd class="kpi__sub">' + sub + '</dd>'
            + '</div>';
    }

    function render_kpis() {

        var p = data.poam.length > 0 ? data.poam[0] : {};
        var statuses = counts_by_status();
        var counts = counts_by_result();
        var assessed = counts['Implemented'] + counts['Partially Implemented'] + counts['Not Implemented'];
        var implemented = assessed > 0 ? Math.round(counts['Implemented'] / assessed * 100) : 0;
        var open_items = num(p.open_count);
        var overdue = num(p.overdue_count);

        $('#kpis').html(
            kpi(
                'Not Started',
                group(statuses['Planned']),
                statuses['Planned'] === 1 ? 'assessment planned' : 'assessments planned',
                ''
            )
            + kpi(
                'In Progress',
                group(statuses['In Progress']),
                statuses['In Progress'] === 1 ? 'assessment underway' : 'assessments underway',
                ''
            )
            + kpi(
                'Completed',
                group(statuses['Complete']),
                statuses['Complete'] === 1 ? 'assessment complete' : 'assessments complete',
                ''
            )
            + kpi(
                'Implementation',
                implemented + '%',
                'of assessed controls fully implemented',
                ''
            )
            + kpi(
                'Open POA&amp;M Items',
                group(open_items),
                overdue > 0 ? '<span class="kpi__alert"><i class="fa-regular fa-clock"></i> ' + group(overdue) + ' overdue</span>' : 'none overdue',
                ''
            )
        );
    }

    function counts_by_status() {

        var counts = {
            'Planned': 0,
            'In Progress': 0,
            'Complete': 0
        };

        for (var i = 0; i < data.assessment_status.length; i++) {
            counts[data.assessment_status[i].assessment_status] = num(data.assessment_status[i].assessment_count);
        }

        return counts;
    }

    function counts_by_result() {

        var counts = {};

        for (var i = 0; i < RESULTS.length; i++) {
            counts[RESULTS[i].name] = 0;
        }

        for (var j = 0; j < data.results.length; j++) {
            counts[data.results[j].item_result] = num(data.results[j].item_count);
        }

        return counts;
    }

    function render_results() {

        var counts = counts_by_result();
        var total = 0;
        var bar = '';
        var legend = '';

        for (var i = 0; i < RESULTS.length; i++) {
            total += counts[RESULTS[i].name];
        }

        for (var j = 0; j < RESULTS.length; j++) {
            var count = counts[RESULTS[j].name];
            var share = total > 0 ? count / total * 100 : 0;

            if (count > 0) {
                bar += '<span class="dash__bar-part ' + RESULTS[j].tone + '" style="width:' + share + '%" title="' + group(count) + ' ' + RESULTS[j].label + '"></span>';
            }

            legend += '<li class="dash__legend-item">'
                + '<span class="rollup__dot ' + RESULTS[j].tone + '"></span>'
                + '<span class="dash__legend-label">' + RESULTS[j].label + '</span>'
                + '<span class="dash__legend-count">' + group(count) + '</span>'
                + '<span class="dash__legend-share">' + (total > 0 ? Math.round(share) : 0) + '%</span>'
                + '</li>';
        }

        $('#results_total').text(group(total) + (total === 1 ? ' control' : ' controls'));
        $('#results_bar').html(bar);
        $('#results_legend').html(legend);
    }

    function render_attention() {

        var html = '';

        for (var i = 0; i < data.attention.length; i++) {

            var a = data.attention[i];
            var days = parseInt(a.days_left, 10);
            var tone = days < 0 ? 'badge--critical' : 'badge--onboarding';
            var when = days < 0
                ? Math.abs(days) + (Math.abs(days) === 1 ? ' day overdue' : ' days overdue')
                : (days === 0 ? 'due today' : 'due in ' + days + (days === 1 ? ' day' : ' days'));
            var why = num(a.assessed_pct) + '% assessed' + (days < 0 ? '' : ', ' + num(a.elapsed_pct) + '% of time used');

            html += '<li class="metric rowlink">'
                + '<span class="metric__label"><a class="rowlink__a" href="/assessments/detail/id/' + a.id + '">' + esc(a.assessment_name) + '</a>'
                + '<span class="metric__sub">' + (a.client_name ? esc(a.client_name) + ' · ' : '') + why + '</span></span>'
                + '<span class="badge ' + tone + '">' + when + '</span>'
                + '<i class="fa-regular fa-chevron-right rowlink__go" aria-hidden="true"></i>'
                + '</li>';
        }

        $('#attention').html(html);
        $('#attention_empty').prop('hidden', data.attention.length > 0);
    }

    function render_milestones() {

        var html = '';

        for (var i = 0; i < data.milestones.length; i++) {

            var m = data.milestones[i];

            html += '<li class="metric rowlink">'
                + '<span class="metric__label"><a class="rowlink__a" href="/assessments/detail/id/' + m.assessment_id + '">' + esc(m.milestone_text) + '</a>'
                + '<span class="metric__sub">' + esc(m.poam_ref) + ' · ' + esc(m.assessment_name) + '</span></span>'
                + '<span class="badge badge--onboarding">' + short_date(m.due_date) + '</span>'
                + '<i class="fa-regular fa-chevron-right rowlink__go" aria-hidden="true"></i>'
                + '</li>';
        }

        $('#milestones').html(html);
        $('#milestones_empty').prop('hidden', data.milestones.length > 0);
    }

    function load() {
        ApiDataSvc.apiCall('post', 'load_dashboard', {}, function (payload) {

            data = JSON.parse(payload);

            render_kpis();
            render_results();
            render_attention();
            render_milestones();
        });
    }

    load();

});
</script>
