<div class="page" id="dash">
    <h1 class="visually-hidden">Dashboard</h1>
    <div class="panel record__panel attention" id="attention_panel" hidden>
        <div class="panel__head">
            <h2 class="panel__title"><i class="fa-regular fa-triangle-exclamation"></i> Needs attention</h2>
        </div>
        <div class="panel__body attention__body">
            <ul class="metriclist" id="attention"></ul>
        </div>
    </div>

    <div class="panel record__panel summary">
        <dl class="kpi" id="kpis"></dl>
    </div>

    <div class="dashgrid">
        <section class="dashcol">
            <div class="panel__head">
                <h2 class="panel__title">Projects</h2>
            </div>
            <div class="dashscroll">
                <ul class="projlist" id="project_list"></ul>
                <p class="controls__empty" id="project_empty">Loading&hellip;</p>
            </div>
        </section>
        <section class="dashcol">
            <div class="panel__head">
                <h2 class="panel__title">Clients</h2>
            </div>
            <div class="dashscroll">
                <ul class="metriclist" id="clients"></ul>
                <p class="controls__empty" id="clients_empty" hidden></p>
            </div>
        </section>
        <section class="dashcol">
            <div class="panel__head">
                <h2 class="panel__title">Assessments</h2>
            </div>
            <div class="dashscroll">
                <ul class="metriclist" id="assessments"></ul>
                <p class="controls__empty" id="assessments_empty" hidden></p>
            </div>
        </section>
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
        return '<li class="metric' + (label.indexOf('rowlink__a') === -1 ? '' : ' rowlink') + '">'
            + '<span class="metric__label">' + label + (sub === '' ? '' : '<span class="metric__sub">' + sub + '</span>') + '</span>'
            + (pct === null ? '' : '<span class="metric__bar">' + bar(pct) + '</span>')
            + (value === '' ? '' : '<span class="metric__value">' + value + '</span>')
            + '</li>';
    }

    /**
     * The three figures a consultant acts on. Gaps are an exception, so they carry
     * a tone and an icon once non-zero and stay quiet at zero.
     */
    function render_kpis() {

        var t = data.totals;
        var counts = counts_by_result();
        var statuses = counts_by_status();
        var clients = num(t.client_count);
        var projects = num(t.project_count);
        var assessments = num(t.assessment_count);
        var gaps = counts['Not Implemented'];
        var pipeline = [];

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
                'Gaps',
                (gaps > 0 ? '<i class="fa-regular fa-circle-exclamation"></i>' : '') + gaps,
                gaps === 1 ? 'control not implemented' : 'controls not implemented',
                gaps > 0 ? 'is-gap' : ''
            )
            + kpi(
                'Open Projects',
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

    function render_assessments() {

        var html = '';

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

            html += metric(
                '<a class="rowlink__a" href="/projects/assessment/id/' + a.id + '">' + esc(a.assessment_name) + '</a>',
                sub.join(' · '),
                total === 0 ? null : pct_of(done, total),
                total === 0 ? '<span class="roster__none">no controls</span>' : pct_of(done, total) + '%'
            );
        }

        $('#assessments').html(html);
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
            var sub = [];

            sub.push(num(c.project_count) + (num(c.project_count) === 1 ? ' project' : ' projects'));
            sub.push(num(c.assessment_count) + (num(c.assessment_count) === 1 ? ' assessment' : ' assessments'));
            sub.push(num(c.evidence_count) + ' files');

            html += metric(
                '<a class="rowlink__a" href="/clients/detail/id/' + c.id + '">' + esc(c.company_name) + '</a>',
                sub.join(' · '),
                null,
                ''
            );
        }

        $('#clients').html(html);
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

            var why = num(a.assessment_count) === 0
                ? 'No assessments started'
                : num(a.assessed_pct) + '% assessed' + (days < 0 ? '' : ' with ' + num(a.elapsed_pct) + '% of the time used');

            html += '<li class="metric rowlink">'
                + '<span class="metric__label"><a class="rowlink__a" href="/projects/detail/id/' + a.id + '">' + esc(a.project_name) + '</a>'
                + '<span class="metric__sub">' + (a.client_name === null ? 'No client assigned' : esc(a.client_name)) + '</span></span>'
                + '<span class="attention__why">' + why + '</span>'
                + '<span class="badge ' + tone + '">' + when + '</span>'
                + '<i class="fa-regular fa-chevron-right rowlink__go" aria-hidden="true"></i>'
                + '</li>';
        }

        $('#attention').html(html);
        $('#attention_panel').prop('hidden', data.attention.length === 0);
        $('#dash').toggleClass('has-attention', data.attention.length > 0);
    }

    function render_projects() {

        var html = '';
        var shown = 0;

        for (var i = 0; i < data.projects.length; i++) {

            var project = data.projects[i];

            if (project.project_status === 'Complete') {
                continue;
            }

            shown++;

            var row = progress[project.id];
            var count = row ? num(row.assessment_count) : 0;
            var meta = [];

            meta.push(project.client_name === null ? 'No client assigned' : esc(project.client_name));
            meta.push(count + (count === 1 ? ' assessment' : ' assessments'));

            if (project.end_date_display !== '') {
                meta.push('ends ' + esc(project.end_date_display));
            }

            html += '<li class="proj rowlink">'
                + '<div class="proj__head">'
                + '<a class="proj__name rowlink__a" href="/projects/detail/id/' + project.id + '">' + esc(project.project_name) + '</a>'
                + '<span class="proj__meta">' + meta.join(' · ') + '</span>'
                + '<span class="badge ' + (project.project_status === 'Complete' ? 'badge--inactive' : 'badge--active') + '">'
                + esc(project.project_status) + '</span>'
                + '</div>'
                + '</li>';
        }

        $('#project_list').html(html).prop('hidden', shown === 0);
        $('#project_empty').prop('hidden', shown > 0).text(data.projects.length === 0
            ? 'No projects yet. Add a client, then start a project to begin tracking compliance.'
            : 'No open projects.');
    }

    function load(){
        ApiDataSvc.apiCall('post', 'load_dashboard', {}, function (payload) {

            data = JSON.parse(payload);

            for (var i = 0; i < data.progress.length; i++) {
                progress[data.progress[i].project_id] = data.progress[i];
            }

            render_attention();
            render_kpis();
            render_assessments();
            render_clients();
            render_projects();
        });
    }

    load();

});
</script>
