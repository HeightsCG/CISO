<div class="page page--list">
    <div class="page__head">
        <div>
            <h1 class="page__title">Projects</h1>
        </div>
        <div class="page__actions">
            <?php if ($this->room['allowed']) { ?>
            <a class="btn btn--primary" href="/projects/form"><i class="fa-regular fa-plus"></i> Add Project</a>
            <?php } else { ?>
            <span class="limit">
                <span class="limit__text"><?php echo htmlspecialchars($this->room['plan'].' includes '.$this->room['cap'].' '.($this->room['cap'] === 1 ? rtrim('projects', 's') : 'projects'), ENT_QUOTES, 'UTF-8'); ?></span>
                <a class="btn btn--primary" href="/subscription">Upgrade</a>
            </span>
            <?php } ?>
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
                            <th scope="col">Client</th>
                            <th scope="col">Start</th>
                            <th scope="col">End</th>
                            <th scope="col">Status</th>
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
        return data;
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
                width: '32%'
            },{
                targets: 2,
                width: '26%',
                render: {
                    display: dash
                }
            },{
                targets: [3, 4],
                width: '15%',
                render: {
                    _: 'sort',
                    display: 'display'
                }
            },{
                targets: 5,
                width: '12%',
                render: {
                    display: dash
                }
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
            window.location.href = '/projects/detail/id/' + data[0];
        }
    });

    $('#project_search').on('input', function () {
        table.search(this.value).draw();
    });

    function load(){
        ApiDataSvc.apiCall('post', 'load_projects', {}, function (data) {
            var obj = JSON.parse(data);
            table.clear();
            for (var i = 0; i < obj.length; i++) {
                table.row.add([
                    obj[i].id,
                    obj[i].project_name,
                    obj[i].client_name,
                    { sort: obj[i].start_date, display: obj[i].start_date_display },
                    { sort: obj[i].end_date, display: obj[i].end_date_display },
                    obj[i].project_status
                ]);
            }
            table.draw();
        });
    }

    load();

});
</script>
