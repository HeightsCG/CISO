<?php if (!$this->billing_allowed) { ?>
<div class="page">
    <div class="page__head">
        <div>
            <h1 class="page__title">Billing</h1>
        </div>
    </div>

    <div class="panel">
        <div class="panel__body">
            <div class="upsell">
                <div class="upsell__copy">
                    <h2 class="upsell__title">Invoice your clients from the work you already track</h2>
                    <p class="upsell__lede">Billing turns an engagement into an invoice without rekeying any of it. It is included on every paid plan.</p>

                    <ul class="upsell__points">
                        <li>Raise invoices against a client and project, priced by line with per-line discounts</li>
                        <li>Take payment by card or bank transfer, straight into your own account</li>
                        <li>Bill once or on a schedule, with renewals raised for you</li>
                        <li>Clients see and settle what they owe where they already sign in</li>
                    </ul>

                    <div class="upsell__actions">
                        <a class="btn btn--primary" href="/subscription">See Plans</a>
                    </div>
                </div>

                <div class="upsell__art" aria-hidden="true">
                    <div class="specimen">
                        <div class="specimen__head">
                            <span class="specimen__word">Invoice</span>
                            <span class="specimen__bar specimen__bar--no"></span>
                        </div>
                        <div class="specimen__meta">
                            <span class="specimen__bar"></span>
                            <span class="specimen__bar specimen__bar--short"></span>
                        </div>
                        <ul class="specimen__lines">
                            <li><span class="specimen__bar"></span><span class="specimen__bar specimen__bar--amt"></span></li>
                            <li><span class="specimen__bar specimen__bar--short"></span><span class="specimen__bar specimen__bar--amt"></span></li>
                            <li><span class="specimen__bar"></span><span class="specimen__bar specimen__bar--amt"></span></li>
                        </ul>
                        <div class="specimen__total">
                            <span class="specimen__word">Total</span>
                            <span class="specimen__bar specimen__bar--amt"></span>
                        </div>
                        <div class="specimen__pay">Pay Invoice</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } else { ?>
<div class="page page--list">
    <div class="page__head">
        <div>
            <h1 class="page__title">Billing</h1>
        </div>
        <div class="page__actions">
            <button type="button" class="btn btn--secondary" id="do_reconcile"><i class="fa-regular fa-rotate"></i> Check for updates</button>
            <a class="btn btn--primary" href="/billing/form"><i class="fa-regular fa-plus"></i> New Invoice</a>
        </div>
    </div>

    <?php if (($this->company['stripe_connect_status'] ?? 'Not Connected') !== 'Connected') { ?>
    <div class="alert <?php echo (($this->company['stripe_connect_status'] ?? '') === 'Restricted' ? 'alert--critical' : 'alert--warn'); ?>" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <?php if (($this->company['stripe_connect_status'] ?? 'Not Connected') === 'Restricted') { ?>
        <p class="alert__title">Payments are on hold</p>
        <p class="alert__text">Payments are paused on this account, so invoices cannot be sent. Drafts are unaffected.</p>
        <?php } elseif (($this->company['stripe_connect_status'] ?? 'Not Connected') === 'Onboarding') { ?>
        <p class="alert__title">Payment setup is not finished</p>
        <p class="alert__text">Build drafts now if you like. Sending needs the setup completed first.</p>
        <?php } else { ?>
        <p class="alert__title">No payment account is connected</p>
        <p class="alert__text">Connect one to send invoices and take payment. Drafts can be built in the meantime.</p>
        <?php } ?>
        <div class="alert__actions">
            <a class="btn btn--primary btn--sm" href="/settings#billing"><?php echo (($this->company['stripe_connect_status'] ?? 'Not Connected') === 'Not Connected' ? 'Connect payments' : 'Finish payment setup'); ?></a>
        </div>
    </div>
    <?php } ?>

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="invoice_search" class="input roster__search" placeholder="Search..." autocomplete="off" spellcheck="false">
            <div class="roster__filters">
                <div class="roster__filter">
                    <select id="origin_filter" class="form-control" aria-label="Filter by kind">
                        <option value="">All invoices</option>
                        <option value="Manual">One-off</option>
                        <option value="Subscription">Recurring</option>
                    </select>
                </div>
                <div class="roster__filter">
                    <select id="status_filter" class="form-control" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <option value="Draft">Draft</option>
                        <option value="Awaiting Payment">Awaiting Payment</option>
                        <option value="Payment Processing">Payment Processing</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Paid">Paid</option>
                        <option value="Void">Void</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data" id="invoices_table">
                    <thead>
                        <tr>
                            <th scope="col">Id</th>
                            <th scope="col">Invoice</th>
                            <th scope="col">Client</th>
                            <th scope="col">Project</th>
                            <th scope="col">Created</th>
                            <th scope="col">Due</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col">Origin</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php } ?>

<?php if ($this->billing_allowed) { ?>
<script>
$(document).ready(function () {

    function esc(value) {
        return $('<div>').text(value === null ? '' : value).html();
    }

    function dash(data) {
        if (!data) {
            return '';
        }
        return data;
    }

    function set_loading(target, loading) {
        if (loading) {
            $(target).addClass("is-loading").prop("disabled", true);
        } else {
            $(target).removeClass("is-loading").prop("disabled", false);
        }
    }

    /**
     * An invoice either happens once or repeats, so the recurrence is a property
     * of the invoice rather than a second kind of record kept somewhere else.
     * A recurring one says so under its number.
     */
    function reference_cell(data, type, row) {
        if (type !== 'display') {
            return data;
        }
        var label = data ? esc(data) : 'Draft';
        if (row[8] === 'Subscription') {
            return label + '<span class="roster__sub">Recurring</span>';
        }
        return label;
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
        } else if (data === 'Payment Processing' || data === 'Sending' || data === 'Action Needed') {
            tone = 'badge--admin';
        } else if (data === 'Awaiting Payment') {
            tone = 'badge--onboarding';
        } else if (data === 'Void' || data === 'Written Off') {
            tone = 'badge--inactive';
        }
        return '<span class="badge ' + tone + '">' + esc(data) + '</span>';
    }

    var table = $('#invoices_table').DataTable({
        dom: "t",
        paging: false,
        scrollY: "520px",
        scrollCollapse: true,
        deferRender: true,
        order: [[4, 'desc']],
        columnDefs: [
            {
                targets: 0,
                visible: false
            },{
                targets: 1,
                width: '14%',
                render: reference_cell
            },{
                targets: 2,
                width: '22%'
            },{
                targets: 3,
                width: '18%',
                render: {
                    display: dash
                }
            },{
                targets: [4, 5],
                width: '11%',
                render: {
                    _: 'sort',
                    display: 'display'
                }
            },{
                targets: 6,
                width: '12%',
                className: 'num',
                render: {
                    _: 'sort',
                    display: 'display'
                }
            },{
                targets: 7,
                width: '13%',
                render: status_badge
            },{
                targets: 8,
                visible: false,
                searchable: false
            }
        ],
        language: {
            zeroRecords: 'No invoices match your search',
            emptyTable: 'No invoices yet. Raise your first invoice to start billing.'
        },
        createdRow: function (row) {
            $(row).attr('tabindex', 0);
        }
    });

    $('#invoices_table tbody').on('click', 'tr', function () {
        var data = table.row(this).data();
        if (!data) {
            return;
        }
        window.location.href = '/billing/invoice/id/' + data[0];
    });

    $('#invoice_search').on('input', function () {
        table.search(this.value).draw();
    });

    $('#origin_filter').change(function () {
        table.column(8).search(this.value === '' ? '' : '^' + this.value + '$', true, false).draw();
    });

    $('#status_filter').change(function () {
        table.column(7).search(this.value === '' ? '' : '^' + this.value + '$', true, false).draw();
    });

    function load_invoices() {
        ApiDataSvc.apiCall('post', 'load_invoices', {}, function (data) {
            var obj = JSON.parse(data);
            var rows = [];
            for (var i = 0; i < obj.length; i++) {
                rows.push([
                    obj[i].id,
                    obj[i].invoice_number,
                    obj[i].client_name,
                    obj[i].project_name,
                    { sort: obj[i].date_created, display: obj[i].date_created_display },
                    { sort: obj[i].due_date === null ? '' : obj[i].due_date, display: obj[i].due_date_display === null ? '' : obj[i].due_date_display },
                    { sort: obj[i].total_cents, display: obj[i].total_display },
                    obj[i].invoice_status_display,
                    obj[i].invoice_origin
                ]);
            }
            table.clear().rows.add(rows).draw();
        });
    }

    $('#do_reconcile').click(function () {

        set_loading('#do_reconcile', true);

        ApiDataSvc.apiCall('post', 'billing_reconcile', {}, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_reconcile', false);

            if (obj.success) {
                toastr.success(obj.message);
                load_invoices();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    load_invoices();

});
</script>
<?php } ?>
