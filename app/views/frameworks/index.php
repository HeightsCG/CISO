<div class="page page--list">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title">Frameworks</h1>
        </div>
    </header>

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="framework_search" class="input roster__search" placeholder="Search by name or issuer..." autocomplete="off" spellcheck="false">
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data" id="frameworks_table">
                    <thead>
                        <tr>
                            <th scope="col">Id</th>
                            <th scope="col">Framework</th>
                            <th scope="col">Issued By</th>
                            <th scope="col">Version</th>
                            <th scope="col">Controls</th>
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

    function framework_cell(data, type, row) {
        if (type !== 'display') {
            return data + ' ' + row[5];
        }
        if (!row[5] || row[5] === data) {
            return esc(data);
        }
        return esc(data) + '<div class="roster__sub">' + esc(row[5]) + '</div>';
    }

    function plain(data) {
        return data ? esc(data) : '';
    }

    var table = $('#frameworks_table').DataTable({
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
                width: '50%',
                render: framework_cell
            },{
                targets: 2,
                width: '26%',
                render: {
                    display: plain
                }
            },{
                targets: 3,
                width: '14%',
                searchable: false,
                render: {
                    display: plain
                }
            },{
                targets: 4,
                width: '10%',
                searchable: false
            }
        ],
        language: {
            zeroRecords: 'No frameworks match your search',
            emptyTable: 'No frameworks are available yet.'
        },
        createdRow: function (row) {
            $(row).attr('tabindex', 0);
        }
    });

    $('#frameworks_table tbody').on('click', 'tr', function () {
        var data = table.row(this).data();
        if (data) {
            window.location.href = '/frameworks/detail/id/' + data[0];
        }
    });

    $('#framework_search').on('input', function () {
        table.search(this.value).draw();
    });

    function load() {
        ApiDataSvc.apiCall('post', 'load_framework_library', {}, function (data) {
            var obj = JSON.parse(data);
            table.clear();
            for (var i = 0; i < obj.length; i++) {
                table.row.add([
                    obj[i].id,
                    obj[i].standard_name,
                    obj[i].issuing_body,
                    obj[i].version,
                    obj[i].control_count,
                    obj[i].full_name
                ]);
            }
            table.draw();
        });
    }

    load();

});
</script>
