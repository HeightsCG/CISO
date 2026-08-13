<div class="page page--list">
    <div class="page__head">
        <div>
            <h1 class="page__title">Clients</h1>
        </div>
        <div class="page__actions">
            <?php if ($this->room['allowed']) { ?>
            <a class="btn btn--primary" href="/clients/form"><i class="fa-regular fa-plus"></i> Add Client</a>
            <?php } else { ?>
            <span class="limit">
                <span class="limit__text"><?php echo htmlspecialchars($this->room['plan'].' includes '.$this->room['cap'].' '.($this->room['cap'] === 1 ? rtrim('clients', 's') : 'clients'), ENT_QUOTES, 'UTF-8'); ?></span>
                <a class="btn btn--primary" href="/subscription">Upgrade</a>
            </span>
            <?php } ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="client_search" class="input roster__search" placeholder="Search..." autocomplete="off" spellcheck="false">
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data" id="clients_table">
                    <thead>
                        <tr>
                            <th scope="col">Id</th>
                            <th scope="col">Company</th>
                            <th scope="col">Projects</th>
                            <th scope="col">Assessments</th>
                            <th scope="col">City / State</th>
                            <th scope="col">Created</th>
                            <th scope="col">Updated</th>
                            <th scope="col">Open projects</th>
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

    function count_cell(data, type) {
        if (type !== 'display') {
            return data;
        }
        return data > 0 ? '<span class="chip">' + data + '</span>' : '<span class="chip chip--empty">0</span>';
    }

    /* Every project counts, whatever its status; how many are still open is the
       secondary line so a client with finished work does not read as inactive. */
    function projects_cell(data, type, row) {

        if (type !== 'display') {
            return data;
        }

        if (data === 0) {
            return '<span class="chip chip--empty">0</span>';
        }

        return '<span class="chip">' + data + '</span>'
            + '<span class="roster__sub">' + (row[7] > 0 ? row[7] + ' open' : 'all complete') + '</span>';
    }

    var table = $('#clients_table').DataTable({
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
                width: '28%'
            },{
                targets: 2,
                width: '14%',
                render: projects_cell
            },{
                targets: 3,
                width: '12%',
                render: count_cell
            },{
                targets: 4,
                width: '20%',
                render: {
                    display: dash
                }
            },{
                targets: [5, 6],
                width: '13%',
                render: {
                    _: 'sort',
                    display: 'display'
                }
            },{
                targets: 7,
                visible: false,
                searchable: false
            }
        ],
        language: {
            zeroRecords: 'No clients match your search',
            emptyTable: 'No clients yet. Add your first client to start building the roster.'
        },
        createdRow: function (row) {
            $(row).attr('tabindex', 0);
        }
    });

    $('#clients_table tbody').on('click', 'tr', function () {
        var data = table.row(this).data();
        if (data) {
            window.location.href = '/clients/detail/id/' + data[0];
        }
    });

    $('#client_search').on('input', function () {
        table.search(this.value).draw();
    });

    function load(){
        ApiDataSvc.apiCall('post', 'load_clients', {}, function (data) {
            var obj = JSON.parse(data);
            table.clear();
            for (var i = 0; i < obj.length; i++) {
                table.row.add([
                    obj[i].id,
                    obj[i].company_name,
                    parseInt(obj[i].project_count, 10),
                    parseInt(obj[i].assessment_count, 10),
                    [obj[i].city, obj[i].state].filter(Boolean).join(', '),
                    { sort: obj[i].date_created, display: obj[i].date_created_display },
                    { sort: obj[i].date_updated, display: obj[i].date_updated_display },
                    parseInt(obj[i].open_project_count, 10)
                ]);
            }
            table.draw();
        });
    }

    load();

});
</script>
