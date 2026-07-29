<div class="page page--list">
    <div class="page__head">
        <div>
            <h1 class="page__title">Standards</h1>
        </div>
        <a class="btn btn--primary" href="/standards/form"><i class="fa-regular fa-plus"></i> Add Standard</a>
    </div>

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="standard_search" class="input roster__search" placeholder="Search by name or code..." autocomplete="off" spellcheck="false">
            <div class="roster__filters">
                <div class="roster__filter">
                    <select id="status_filter" class="form-select">
                        <option value="Active" selected>Active</option>
                        <option value="Archived">Archived</option>
                        <option value="">All</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data" id="standards_table">
                    <thead>
                        <tr>
                            <th scope="col">Id</th>
                            <th scope="col">Standard</th>
                            <th scope="col">Code</th>
                            <th scope="col">Version</th>
                            <th scope="col">Controls</th>
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

    function badge(data) {
        if (data === 'Archived') {
            return '<span class="badge badge--inactive">Archived</span>';
        }
        return '<span class="badge badge--active">Active</span>';
    }

    var table = $('#standards_table').DataTable({
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
                width: '38%'
            },{
                targets: 2,
                width: '18%',
                render: {
                    display: dash
                }
            },{
                targets: 3,
                width: '14%',
                searchable: false,
                render: {
                    display: dash
                }
            },{
                targets: 4,
                width: '14%',
                searchable: false
            },{
                targets: 5,
                width: '16%',
                render: {
                    display: badge
                }
            }
        ],
        language: {
            zeroRecords: 'No standards match your search',
            emptyTable: 'No standards yet. Add your first standard to start building the library.'
        },
        createdRow: function (row) {
            $(row).attr('tabindex', 0);
        }
    });

    $('#standards_table tbody').on('click', 'tr', function () {
        var data = table.row(this).data();
        if (data) {
            window.location.href = '/standards/detail/id/' + data[0];
        }
    });

    $('#standard_search').on('input', function () {
        table.search(this.value).draw();
    });

    $('#status_filter').on('change', function () {
        if (this.value === '') {
            table.column(5).search('').draw();
            return;
        }
        table.column(5).search('^' + this.value + '$', true, false).draw();
    });

    function load(){
        ApiDataSvc.apiCall('post', 'load_standards', {}, function (data) {
            var obj = JSON.parse(data);
            table.clear();
            for (var i = 0; i < obj.length; i++) {
                table.row.add([
                    obj[i].id,
                    obj[i].standard_name,
                    obj[i].short_code,
                    obj[i].version,
                    obj[i].control_count,
                    obj[i].standard_status
                ]);
            }
            table.column(5).search('^Active$', true, false).draw();
        });
    }

    load();

});
</script>
