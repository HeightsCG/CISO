<div class="page page--list">
    <div class="page__head">
        <div>
            <h1 class="page__title">Invoices</h1>
            <div class="client__meta">
                <span class="client__segment"><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="invoice_search" class="input roster__search" placeholder="Search..." autocomplete="off" spellcheck="false">
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data" id="invoices_table">
                    <thead>
                        <tr>
                            <th scope="col">Id</th>
                            <th scope="col">Invoice</th>
                            <th scope="col">Project</th>
                            <th scope="col">Issued</th>
                            <th scope="col">Due</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="actions"></th>
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

    function status_badge(data, type) {
        if (type !== 'display') {
            return data;
        }
        var tone = 'badge--prospect';
        if (data === 'Paid') {
            tone = 'badge--active';
        } else if (data === 'Overdue' || data === 'Payment Failed') {
            tone = 'badge--critical';
        } else if (data === 'Payment Processing' || data === 'Action Needed') {
            tone = 'badge--admin';
        } else if (data === 'Awaiting Payment') {
            tone = 'badge--onboarding';
        } else if (data === 'Void' || data === 'Written Off') {
            tone = 'badge--inactive';
        }
        return '<span class="badge ' + tone + '">' + esc(data) + '</span>';
    }

    function actions_cell(data, type, row) {
        if (type !== 'display') {
            return '';
        }
        var html = '<button type="button" class="btn btn--tertiary btn--sm" data-action="pdf" data-id="' + row[0] + '">Invoice</button>';
        if (row[6] !== 'Paid' && row[6] !== 'Void' && row[6] !== 'Written Off') {
            html += ' <button type="button" class="btn btn--tertiary btn--sm" data-action="pay" data-id="' + row[0] + '">Pay</button>';
        }
        return html;
    }

    var table = $('#invoices_table').DataTable({
        dom: "t",
        paging: false,
        scrollY: "520px",
        scrollCollapse: true,
        deferRender: true,
        order: [[3, 'desc']],
        columnDefs: [
            {
                targets: 0,
                visible: false
            },{
                targets: 1,
                width: '16%'
            },{
                targets: 2,
                width: '22%',
                render: {
                    display: dash
                }
            },{
                targets: [3, 4],
                width: '13%',
                render: {
                    _: 'sort',
                    display: 'display'
                }
            },{
                targets: 5,
                width: '14%',
                className: 'num',
                render: {
                    _: 'sort',
                    display: 'display'
                }
            },{
                targets: 6,
                width: '14%',
                render: status_badge
            },{
                targets: 7,
                width: '12%',
                className: 'actions',
                orderable: false,
                searchable: false,
                render: actions_cell
            }
        ],
        language: {
            zeroRecords: 'No invoices match your search',
            emptyTable: 'No invoices yet.'
        },
        createdRow: function (row) {
            $(row).attr('tabindex', 0);
        }
    });

    $('#invoice_search').on('input', function () {
        table.search(this.value).draw();
    });

    $('#invoices_table').on('click', '[data-action]', function () {

        var button = this;

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'portal_invoice_url', {
            invoice_id: $(this).attr('data-id'),
            link_type: $(this).attr('data-action')
        }, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {
                window.open(obj.url, '_blank', 'noopener');
            } else {
                toastr.error(obj.message);
            }
        });
    });

    function load(){
        ApiDataSvc.apiCall('post', 'portal_invoices', {}, function (data) {
            var obj = JSON.parse(data);
            table.clear();
            for (var i = 0; i < obj.length; i++) {
                table.row.add([
                    obj[i].id,
                    obj[i].invoice_number,
                    obj[i].project_name,
                    { sort: obj[i].due_date === null ? '' : obj[i].due_date, display: obj[i].issued_display === null ? '' : obj[i].issued_display },
                    { sort: obj[i].due_date === null ? '' : obj[i].due_date, display: obj[i].due_date === null ? '' : obj[i].due_date_display },
                    { sort: parseInt(obj[i].total_cents, 10), display: obj[i].total_display },
                    obj[i].invoice_status_display,
                    ''
                ]);
            }
            table.draw();
        });
    }

    load();

});
</script>
