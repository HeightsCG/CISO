<div class="page" id="dash">
    <div class="page__head">
        <div>
            <h1 class="page__title">Dashboard</h1>
            <div class="client__meta">
                <span class="client__segment">Signed in as <?php echo htmlspecialchars(Session::get('first_name').' '.Session::get('last_name'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="page__actions">
            <a class="btn btn--secondary" href="/clients"><i class="fa-regular fa-shield-halved"></i> Clients</a>
            <a class="btn btn--primary" href="/projects"><i class="fa-regular fa-project-diagram"></i> Projects</a>
        </div>
    </div>

    <div class="panel record__panel summary">
        <dl class="kpi" id="kpis"></dl>
        <div class="panel__body">
            <div class="posture__head">
                <h2 class="panel__title">Compliance posture</h2>
                <span class="panel__note" id="posture_label">&nbsp;</span>
            </div>
            <div class="stack" id="posture" role="img" aria-labelledby="posture_label"></div>
            <ul class="stackkey" id="posture_key"></ul>
        </div>
    </div>

    <div class="panel record__panel attention" id="attention_panel" hidden>
        <div class="panel__head">
            <h2 class="panel__title"><i class="fa-regular fa-triangle-exclamation"></i> Needs attention</h2>
            <span class="panel__note" id="attention_note"></span>
        </div>
        <div class="panel__body attention__body">
            <ul class="metriclist" id="attention"></ul>
        </div>
    </div>

    <div class="row g-4 dashgrid">
        <div class="col-lg-4">
            <div class="panel record__panel">
                <div class="panel__head roster__toolbar">
                    <h2 class="panel__title">Projects</h2>
                    <div class="roster__filters">
                        <div class="roster__filter">
                            <select id="status_filter" class="form-select form-select-sm" aria-label="Filter projects by status">
                                <option value="open">Open</option>
                                <option value="">All</option>
                                <option value="Complete">Complete</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="panel__body dashscroll">
                    <ul class="projlist" id="project_list"></ul>
                    <p class="controls__empty" id="project_empty">Loading&hellip;</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Clients</h2>
                    <span class="panel__note" id="clients_note">&nbsp;</span>
                </div>
                <div class="panel__body dashscroll">
                    <ul class="metriclist" id="clients"></ul>
                    <p class="controls__empty" id="clients_empty" hidden></p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Assessment progress</h2>
                    <span class="panel__note" id="assessments_note">&nbsp;</span>
                </div>
                <div class="panel__body dashscroll">
                    <ul class="metriclist" id="assessments"></ul>
                    <p class="controls__empty" id="assessments_empty" hidden></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var RESULTS = [
        { name: 'Implemented', tone: 'is-ok' },
        { name: 'Partially Implemented', tone: 'is-partial' },
        { name: 'Not Implemented', tone: 'is-gap' },
        { name: 'Not Applicable', tone: 'is-na' },
        { name: 'Not Assessed', tone: 'is-none' }
    ];

    var data = {};
    var progress = {};

    function esc(value) {
        return $('<div>').text(value === null ? '' : value).html();
    }

    function pct_of(done, total) {
        return total > 0 ? Math.round(done / total * 100) : 0;
    }

    function num(value) {
        return parseInt(value, 10) || 0;
    }

    function kpi(label, value, sub, tone) {
        return '<div class="kpi__cell">'
            + '<dt class="kpi__label">' + label + '</dt>'
            + '<dd class="kpi__value' + (tone === '' ? '' : ' ' + tone) + '">' + value + '</dd>'
            + '<dd class="kpi__sub">' + sub + '</dd>'
            + '</div>';
    }

    function bar(pct) {
        return '<span class="progress"><span class="progress__bar" style="width:' + pct + '%"></span></span>';
    }

    function metric(label, sub, pct, value) {
        return '<li class="metric">'
            + '<span class="metric__label">' + label + (sub === '' ? '' : '<span class="metric__sub">' + sub + '</span>') + '</span>'
            + '<span class="metric__bar">' + (pct === null ? '' : bar(pct)) + '</span>'
            + '<span class="metric__value">' + value + '</span>'
            + '</li>';
    }

    /**
     * The five figures a consultant acts on. Two of them - gaps and overdue work -
     * are exceptions, so they carry a tone and an icon once they are non-zero and
     * stay quiet at zero.
     */
    function render_kpis() {

        var t = data.totals;
        var counts = counts_by_result();
        var statuses = counts_by_status();
        var controls = 0;
        var clients = num(t.client_count);
        var projects = num(t.project_count);
        var assessments = num(t.assessment_count);
        var due = data.attention.length;
        var assessed = 0;
        var gaps = counts['Not Implemented'];
        var pipeline = [];

        for (var i = 0; i < RESULTS.length; i++) {
            controls += counts[RESULTS[i].name];
        }

        assessed = controls - counts['Not Assessed'];

        if (statuses['In Progress'] > 0) {
            pipeline.push(statuses['In Progress'] + ' in progress');
        }

        if (statuses['Complete'] > 0) {
            pipeline.push(statuses['Complete'] + ' complete');
        }

        if (statuses['Planned'] > 0) {
            pipeline.push(statuses['Planned'] + ' planned');
        }

        $('#kpis').html(
            kpi(
                'Controls assessed',
                controls === 0 ? '&mdash;' : pct_of(assessed, controls) + '%',
                controls === 0 ? 'No controls imported yet' : assessed + ' of ' + controls + ' controls',
                ''
            )
            + kpi(
                'Gaps',
                (gaps > 0 ? '<i class="fa-regular fa-circle-exclamation"></i>' : '') + gaps,
                gaps === 1 ? 'control not implemented' : 'controls not implemented',
                gaps > 0 ? 'is-gap' : ''
            )
            + kpi(
                'Needs attention',
                (due > 0 ? '<i class="fa-regular fa-triangle-exclamation"></i>' : '') + due,
                due === 1 ? 'project overdue or due soon' : 'projects overdue or due soon',
                due > 0 ? 'is-due' : ''
            )
            + kpi(
                'Open projects',
                num(t.open_project_count),
                'of ' + projects + ' · ' + clients + (clients === 1 ? ' client' : ' clients'),
                ''
            )
            + kpi(
                'Assessments',
                assessments,
                pipeline.length === 0 ? 'None started' : pipeline.join(' · '),
                ''
            )
        );
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

    function render_posture() {

        var counts = counts_by_result();
        var total = 0;
        var segments = '';
        var key = '';

        for (var i = 0; i < RESULTS.length; i++) {
            total += counts[RESULTS[i].name];
        }

        for (var k = 0; k < RESULTS.length; k++) {

            var n = counts[RESULTS[k].name];
            var share = total > 0 ? (n / total * 100) : 0;

            if (share > 0) {
                segments += '<span class="stack__seg ' + RESULTS[k].tone + '" style="width:' + share.toFixed(3) + '%"'
                    + ' title="' + esc(RESULTS[k].name) + ': ' + n + '"></span>';
            }

            key += '<li class="stackkey__item">'
                + '<span class="rollup__dot ' + RESULTS[k].tone + '"></span>'
                + '<span class="stackkey__n">' + n + '</span>'
                + '<span class="stackkey__label">' + esc(RESULTS[k].name) + '</span>'
                + '<span class="stackkey__pct">' + Math.round(share) + '%</span>'
                + '</li>';
        }

        $('#posture').html(segments);
        $('#posture_key').html(key);
        $('#posture_label').text(total === 0
            ? 'No controls assessed yet'
            : (total - counts['Not Assessed']) + ' of ' + total + ' controls assessed');
    }

    function render_assessments() {

        var html = '';
        var open = 0;

        for (var i = 0; i < data.assessments.length; i++) {

            var a = data.assessments[i];
            var total = num(a.item_count);
            var done = num(a.assessed_count);
            var sub = [];

            if (a.short_code !== null && a.short_code !== '') {
                sub.push(esc(a.short_code));
            }

            if (a.client_name !== null) {
                sub.push(esc(a.client_name));
            }

            if (num(a.gap_count) > 0) {
                sub.push(num(a.gap_count) + ' not implemented');
            }

            if (a.assessment_status !== 'Complete') {
                open++;
            }

            html += metric(
                '<a href="/projects/assessment/id/' + a.id + '">' + esc(a.assessment_name) + '</a>',
                sub.join(' · '),
                total === 0 ? null : pct_of(done, total),
                total === 0 ? '<span class="roster__none">no controls</span>' : done + ' of ' + total
            );
        }

        $('#assessments').html(html);
        $('#assessments_note').text(data.assessments.length === 0 ? '' : open + ' in flight');
        $('#assessments_empty').prop('hidden', data.assessments.length > 0)
            .text('No assessments yet. Open a project and start one against a standard.');
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

    function render_clients() {

        var html = '';

        for (var i = 0; i < data.clients.length; i++) {

            var c = data.clients[i];
            var total = num(c.item_count);
            var done = num(c.assessed_count);
            var sub = [];

            sub.push(num(c.project_count) + (num(c.project_count) === 1 ? ' project' : ' projects'));
            sub.push(num(c.assessment_count) + (num(c.assessment_count) === 1 ? ' assessment' : ' assessments'));
            sub.push(num(c.evidence_count) + ' files');

            html += metric(
                '<a href="/clients/detail/id/' + c.id + '">' + esc(c.company_name) + '</a>',
                sub.join(' · '),
                total === 0 ? null : pct_of(done, total),
                total === 0 ? '<span class="roster__none">&mdash;</span>' : done + ' of ' + total
            );
        }

        $('#clients').html(html);
        $('#clients_note').text(num(data.totals.evidence_count) + ' evidence files');
        $('#clients_empty').prop('hidden', data.clients.length > 0)
            .text('No clients yet. Add one to start a project against it.');
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

            html += '<li class="metric">'
                + '<span class="metric__label"><a href="/projects/detail/id/' + a.id + '">' + esc(a.project_name) + '</a>'
                + '<span class="metric__sub">' + (a.client_name === null ? 'No client assigned' : esc(a.client_name)) + '</span></span>'
                + '<span class="metric__value">' + esc(a.end_date_display) + '</span>'
                + '<span class="badge ' + tone + '">' + when + '</span>'
                + '</li>';
        }

        $('#attention').html(html);
        $('#attention_note').text(data.attention.length + (data.attention.length === 1 ? ' project' : ' projects'));
        $('#attention_panel').prop('hidden', data.attention.length === 0);
        $('#dash').toggleClass('has-attention', data.attention.length > 0);
    }

    function render_projects() {

        var filter = $('#status_filter').val();
        var html = '';
        var shown = 0;

        for (var i = 0; i < data.projects.length; i++) {

            var project = data.projects[i];

            if (filter === 'open' && project.project_status === 'Complete') {
                continue;
            }

            if (filter === 'Complete' && project.project_status !== 'Complete') {
                continue;
            }

            shown++;

            var row = progress[project.id];
            var total = row ? num(row.item_count) : 0;
            var done = row ? num(row.assessed_count) : 0;
            var count = row ? num(row.assessment_count) : 0;
            var meta = [];

            meta.push(project.client_name === null ? 'No client assigned' : esc(project.client_name));
            meta.push(count + (count === 1 ? ' assessment' : ' assessments'));

            if (project.end_date_display !== '') {
                meta.push('ends ' + esc(project.end_date_display));
            }

            html += '<li class="proj">'
                + '<div class="proj__head">'
                + '<a class="proj__name" href="/projects/detail/id/' + project.id + '">' + esc(project.project_name) + '</a>'
                + '<span class="proj__meta">' + meta.join(' · ') + '</span>'
                + '<span class="badge ' + (project.project_status === 'Complete' ? 'badge--inactive' : 'badge--active') + '">'
                + esc(project.project_status) + '</span>'
                + '</div>'
                + '<ul class="asmts"><li class="asmt">'
                + '<span class="asmt__label">' + (total === 0 ? 'No controls yet' : 'Controls assessed') + '</span>'
                + '<span class="asmt__progress">'
                + (total === 0
                    ? '<span class="roster__none">&mdash;</span>'
                    : bar(pct_of(done, total)) + '<span class="asmt__count">' + done + ' of ' + total + '</span>')
                + '</span>'
                + '</li></ul>'
                + '</li>';
        }

        $('#project_list').html(html).prop('hidden', shown === 0);
        $('#project_empty').prop('hidden', shown > 0).text(data.projects.length === 0
            ? 'No projects yet. Add a client, then start a project to begin tracking compliance.'
            : 'No projects match this filter.');
    }

    $('#status_filter').on('change', render_projects);

    function load(){
        ApiDataSvc.apiCall('post', 'load_dashboard', {}, function (payload) {

            data = JSON.parse(payload);

            for (var i = 0; i < data.progress.length; i++) {
                progress[data.progress[i].project_id] = data.progress[i];
            }

            render_attention();
            render_kpis();
            render_posture();
            render_assessments();
            render_clients();
            render_projects();
        });
    }

    load();

});
</script>
