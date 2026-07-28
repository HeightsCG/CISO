<div class="page">
    <div class="page__head">
        <div>
            <h1 class="page__title">Clients</h1>
        </div>
        <a class="btn btn--primary" href="/clients/form"><i class="fa-regular fa-plus"></i> Add Client</a>
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
                            <th scope="col">City / State</th>
                            <th scope="col">Created</th>
                            <th scope="col">Updated</th>
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
                targets: [3, 4],
                render: {
                    _: 'sort',
                    display: 'display'
                }
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
                    [obj[i].city, obj[i].state].filter(Boolean).join(', '),
                    { sort: obj[i].date_created, display: obj[i].date_created_display },
                    { sort: obj[i].date_updated, display: obj[i].date_updated_display }
                ]);
            }
            table.draw();
        });
    }

    load();

});
</script>
