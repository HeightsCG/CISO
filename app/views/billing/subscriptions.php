<div class="page page--list">
    <div class="page__head">
        <div>
            <h1 class="page__title">Retainers</h1>
        </div>
        <div class="page__actions">
            <a class="btn btn--secondary" href="/billing"><i class="fa-regular fa-file-invoice-dollar"></i> Invoices</a>
            <a class="btn btn--primary" href="/billing/subscriptionform"><i class="fa-regular fa-plus"></i> New Retainer</a>
        </div>
    </div>

    <?php if (($this->company['stripe_connect_status'] ?? 'Not Connected') !== 'Connected') { ?>
    <div class="alert alert--warn" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <p class="alert__title">No payment account is connected</p>
        <p class="alert__text">Connect one to start a retainer. Drafts can be built in the meantime.</p>
        <div class="alert__actions">
            <a class="btn btn--primary btn--sm" href="/settings#billing">Connect payments</a>
        </div>
    </div>
    <?php } ?>

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="subscription_search" class="input roster__search" placeholder="Search..." autocomplete="off" spellcheck="false">
            <div class="roster__filters">
                <div class="roster__filter">
                    <select id="status_filter" class="form-control" aria-label="Filter by status">
                        <option value="">All retainers</option>
                        <option value="Draft">Draft</option>
                        <option value="Active">Active</option>
                        <option value="Ending">Ending</option>
                        <option value="Past Due">Past Due</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data" id="subscriptions_table">
                    <thead>
                        <tr>
                            <th scope="col">Id</th>
                            <th scope="col">Retainer</th>
                            <th scope="col">Client</th>
                            <th scope="col">Project</th>
                            <th scope="col">Billing</th>
                            <th scope="col">Renews</th>
                            <th scope="col">Amount</th>
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

    function esc(value) {
        return $('<div>').text(value === null ? '' : value).html();
    }

    function status_badge(data, type) {
        if (type !== 'display') {
            return data;
        }
        var tone = 'badge--prospect';
        if (data === 'Active') {
            tone = 'badge--active';
        } else if (data === 'Past Due' || data === 'Unpaid') {
            tone = 'badge--critical';
        } else if (data === 'Ending' || data === 'Trial') {
            tone = 'badge--admin';
        } else if (data === 'Cancelled' || data === 'Expired') {
            tone = 'badge--inactive';
        }
        return '<span class="badge ' + tone + '">' + esc(data) + '</span>';
    }

    function cadence(data) {
        if (data === 'Quarter') {
            return 'Quarterly';
        }
        if (data === 'Year') {
            return 'Yearly';
        }
        return 'Monthly';
    }

    var table = $('#subscriptions_table').DataTable({
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
                width: '22%'
            },{
                targets: 2,
                width: '20%'
            },{
                targets: 3,
                width: '18%'
            },{
                targets: 4,
                width: '10%',
                render: function (data, type) {
                    return type === 'display' ? cadence(data) : data;
                }
            },{
                targets: 5,
                width: '11%'
            },{
                targets: 6,
                width: '11%',
                className: 'num'
            },{
                targets: 7,
                width: '12%',
                render: status_badge
            }
        ],
        language: {
            zeroRecords: 'No retainers match your search',
            emptyTable: 'No retainers yet. Set one up to bill a client on a schedule.'
        },
        createdRow: function (row) {
            $(row).attr('tabindex', 0);
        }
    });

    $('#subscriptions_table tbody').on('click', 'tr', function () {
        var data = table.row(this).data();
        if (!data) {
            return;
        }
        window.location.href = '/billing/subscription/id/' + data[0];
    });

    $('#subscription_search').on('input', function () {
        table.search(this.value).draw();
    });

    $('#status_filter').change(function () {
        table.column(7).search(this.value === '' ? '' : '^' + this.value + '$', true, false).draw();
    });

    function load_subscriptions() {
        ApiDataSvc.apiCall('post', 'load_subscriptions', {}, function (data) {
            var obj = JSON.parse(data);
            var rows = [];
            for (var i = 0; i < obj.length; i++) {
                rows.push([
                    obj[i].id,
                    obj[i].subscription_name,
                    obj[i].client_name,
                    obj[i].project_name === null ? '' : obj[i].project_name,
                    obj[i].billing_interval,
                    obj[i].renews_display === null ? '' : obj[i].renews_display,
                    obj[i].amount_display,
                    obj[i].subscription_status_display
                ]);
            }
            table.clear().rows.add(rows).draw();
        });
    }

    load_subscriptions();

});
</script>
