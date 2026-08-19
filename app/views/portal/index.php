<div class="page page--list">
    <div class="page__head">
        <div>
            <h1 class="page__title">Your Projects</h1>
            <div class="client__meta">
                <span class="client__segment"><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </div>

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
            <input type="search" id="project_search" class="input roster__search" placeholder="Search..." autocomplete="off" spellcheck="false">
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data" id="projects_table">
                    <thead>
                        <tr>
                            <th scope="col">Id</th>
                            <th scope="col">Project</th>
                            <th scope="col">Assessed</th>
                            <th scope="col">Evidence</th>
                            <th scope="col">End</th>
                            <th scope="col">Status</th>
                            <th scope="col">Controls assessed</th>
                            <th scope="col">Controls total</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    function dash(data) {
        if (!data) {
            return '<span class="roster__none">&mdash;</span>';
        }
        return esc(data);
    }

    function progress_cell(data, type, row) {

        if (type !== 'display') {
            return data;
        }

        return '<div class="progress"><div class="progress__bar" style="width:' + data + '%"></div></div>'
            + '<p class="progress__label">' + row[6] + ' of ' + row[7] + ' \u00b7 ' + data + '%</p>';
    }

    function chip_cell(data, type) {

        if (type !== 'display') {
            return data;
        }

        return '<span class="chip' + (data > 0 ? '' : ' chip--empty') + '">' + data + '</span>';
    }

    var table = $('#projects_table').DataTable({
        dom: "t",
        paging: false,
        scrollY: "520px",
        scrollCollapse: true,
        deferRender: true,
        order: [[1, 'asc']],
        columnDefs: [
            {
                targets: 0,
                visible: false
            },{
                targets: 1,
                width: '34%',
                render: text_cell
            },{
                targets: 2,
                width: '24%',
                render: progress_cell
            },{
                targets: 3,
                width: '12%',
                render: chip_cell
            },{
                targets: 4,
                width: '14%',
                render: {
                    _: 'sort',
                    display: 'display'
                }
            },{
                targets: 5,
                width: '16%',
                render: {
                    display: dash
                }
            },{
                targets: [6, 7],
                visible: false,
                searchable: false
            }
        ],
        language: {
            zeroRecords: 'No projects match your search',
            emptyTable: 'No projects yet.'
        },
        createdRow: function (row) {
            $(row).attr('tabindex', 0);
        }
    });

    $('#projects_table tbody').on('click', 'tr', function () {
        var data = table.row(this).data();
        if (data) {
            window.location.href = '/portal/project/id/' + data[0];
        }
    });

    $('#project_search').on('input', function () {
        table.search(this.value).draw();
    });

    function stat(count, label, dot) {
        return '<span class="rollup__stat">'
            + (dot === '' ? '' : '<span class="rollup__dot ' + dot + '"></span>')
            + '<span class="rollup__n">' + count + '</span>'
            + '<span class="rollup__label">' + label + '</span></span>';
    }

    function render_rollup(rows) {

        var projects = rows.length;
        var items = 0;
        var assessed = 0;
        var evidence = 0;
        var open = 0;

        for (var i = 0; i < rows.length; i++) {
            items += parseInt(rows[i].item_count, 10);
            assessed += parseInt(rows[i].assessed_count, 10);
            evidence += parseInt(rows[i].evidence_count, 10);
            if (rows[i].project_status !== 'Complete') {
                open++;
            }
        }

        var pct = items > 0 ? Math.round(assessed / items * 100) : 0;

        $('#rollup').html(
            stat(projects, projects === 1 ? 'project' : 'projects', '')
            + stat(open, 'in progress', 'is-partial')
            + stat(items - assessed, 'controls outstanding', 'is-none')
            + stat(assessed, 'controls assessed', 'is-ok')
            + '<span class="rollup__stat rollup__stat--split"><i class="fa-regular fa-paperclip"></i>'
            + '<span class="rollup__n">' + evidence + '</span>'
            + '<span class="rollup__label">evidence files</span></span>'
        );

        $('#rollup_bar').css('width', pct + '%');
        $('#rollup_label').text(items === 0
            ? 'No controls yet'
            : assessed + ' of ' + items + ' controls assessed · ' + pct + '%');
    }

    function load(){
        ApiDataSvc.apiCall('post', 'portal_projects', {}, function (data) {

            var obj = JSON.parse(data);

            render_rollup(obj);

            table.clear();

            for (var i = 0; i < obj.length; i++) {

                var total = parseInt(obj[i].item_count, 10);
                var done = parseInt(obj[i].assessed_count, 10);

                table.row.add([
                    obj[i].id,
                    obj[i].project_name,
                    total > 0 ? Math.round(done / total * 100) : 0,
                    parseInt(obj[i].evidence_count, 10),
                    { sort: obj[i].end_date, display: obj[i].end_date_display },
                    obj[i].project_status,
                    done,
                    total
                ]);
            }

            table.draw();
        });
    }

    load();

});
</script>
