<div class="page page--list" id="financials">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title">Financials</h1>
        </div>
    </header>

    <div class="panel record__panel summary">
        <dl class="kpi" id="kpis"></dl>
    </div>

    <div class="panel listpage">
        <div class="panel__head roster__toolbar">
            <input type="search" id="subscription_search" class="input roster__search" placeholder="Search companies or plans" autocomplete="off" spellcheck="false" aria-label="Search companies or plans">
            <div class="roster__filters">
                <div class="roster__filter">
                    <select id="status_filter" class="form-control" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="trialing">Trialing</option>
                        <option value="past_due">Past due</option>
                        <option value="cancelling">Cancelling</option>
                        <option value="canceled">Cancelled</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="panel__body panel__body--flush listpage__body">
            <table class="data listpage__table" id="subscriptions_table" hidden>
                <thead>
                    <tr>
                        <th scope="col">Company</th>
                        <th scope="col">Plan</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Monthly</th>
                        <th scope="col">Started</th>
                        <th scope="col">Renews</th>
                    </tr>
                </thead>
                <tbody id="subscriptions_list"></tbody>
            </table>
            <p class="tiles__empty" id="subscriptions_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading subscriptions&hellip;</p>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var subscriptions = [];
    var currency = 'usd';

    var STATUSES = {
        active: {
            label: 'Active',
            tone: 'badge--active'
        },
        trialing: {
            label: 'Trialing',
            tone: 'badge--prospect'
        },
        past_due: {
            label: 'Past due',
            tone: 'badge--onboarding'
        },
        unpaid: {
            label: 'Unpaid',
            tone: 'badge--onboarding'
        },
        paused: {
            label: 'Paused',
            tone: 'badge--inactive'
        },
        canceled: {
            label: 'Cancelled',
            tone: 'badge--inactive'
        }
    };

    function money(cents) {
        return (cents / 100).toLocaleString('en-US', {
            style: 'currency',
            currency: currency.toUpperCase(),
            maximumFractionDigits: 0
        });
    }

    function kpi(label, value, sub, tone) {
        return '<div class="kpi__cell">'
            + '<dt class="kpi__label">' + label + '</dt>'
            + '<dd class="kpi__value' + (tone === '' ? '' : ' ' + tone) + '">' + value + '</dd>'
            + '<dd class="kpi__sub">' + sub + '</dd>'
            + '</div>';
    }

    function render_kpis() {

        var mrr = 0;
        var paying = 0;
        var active = 0;
        var trialing = 0;
        var past_due = 0;
        var churned = 0;
        var month_ago = Math.floor(Date.now() / 1000) - (30 * 86400);

        for (var i = 0; i < subscriptions.length; i++) {

            var s = subscriptions[i];

            if (s.subscription_status === 'active' || s.subscription_status === 'past_due') {
                mrr += s.monthly_cents;
                paying++;
            }

            if (s.subscription_status === 'active') {
                active++;
            }

            if (s.subscription_status === 'trialing') {
                trialing++;
            }

            if (s.subscription_status === 'past_due' || s.subscription_status === 'unpaid') {
                past_due++;
            }

            if (s.subscription_status === 'canceled' && s.ended_at >= month_ago) {
                churned++;
            }
        }

        $('#kpis').html(
            kpi('MRR', money(mrr), paying + ' paying', '')
            + kpi('ARR', money(mrr * 12), 'Annual run rate', '')
            + kpi('Active', active.toLocaleString('en-US'), 'Companies', '')
            + kpi('Trialing', trialing.toLocaleString('en-US'), 'Not yet billed', '')
            + kpi('Past Due', past_due.toLocaleString('en-US'), 'Payment failed', past_due > 0 ? 'is-due' : '')
            + kpi('Churned', churned.toLocaleString('en-US'), 'Last 30 days', churned > 0 ? 'is-gap' : '')
        );
    }

    function status_badge(s) {

        if (s.cancelling && s.subscription_status !== 'canceled') {
            return '<span class="badge badge--onboarding">Cancelling</span>';
        }

        var status = STATUSES[s.subscription_status] || {
            label: s.subscription_status,
            tone: 'badge--inactive'
        };

        return '<span class="badge ' + status.tone + '">' + esc(status.label) + '</span>';
    }

    function renews(s) {

        if (s.subscription_status === 'canceled') {
            return s.ended_display === '' ? '' : 'Ended ' + esc(s.ended_display);
        }

        if (s.cancelling) {
            return s.renews_display === '' ? '' : 'Ends ' + esc(s.renews_display);
        }

        return esc(s.renews_display);
    }

    function matches_status(s, filter) {

        if (filter === '') {
            return true;
        }

        if (filter === 'cancelling') {
            return s.cancelling && s.subscription_status !== 'canceled';
        }

        if (filter === 'past_due') {
            return s.subscription_status === 'past_due' || s.subscription_status === 'unpaid';
        }

        return s.subscription_status === filter;
    }

    function render() {

        var term = $('#subscription_search').val().trim().toLowerCase();
        var filter = $('#status_filter').val();
        var html = '';
        var shown = 0;

        for (var i = 0; i < subscriptions.length; i++) {

            var s = subscriptions[i];
            var text = (s.company_name + ' ' + s.plan_name).toLowerCase();

            if (term !== '' && text.indexOf(term) === -1) {
                continue;
            }

            if (!matches_status(s, filter)) {
                continue;
            }

            shown++;

            html += '<tr>'
                + '<td><span class="listpage__name">' + esc(s.company_name) + '</span></td>'
                + '<td>' + esc(s.plan_name) + '</td>'
                + '<td>' + status_badge(s) + '</td>'
                + '<td class="num">' + money(s.monthly_cents) + '</td>'
                + '<td>' + esc(s.started_display) + '</td>'
                + '<td>' + renews(s) + '</td>'
                + '</tr>';
        }

        $('#subscriptions_list').html(html);
        $('#subscriptions_table').prop('hidden', shown === 0);
        $('#subscriptions_empty').prop('hidden', shown > 0).text(subscriptions.length === 0
            ? 'No subscriptions yet.'
            : 'No subscriptions match your search.');
    }

    $('#subscription_search').on('input', render);
    $('#status_filter').on('change', render);

    ApiDataSvc.apiCall('post', 'global_financials', {}, function (data) {

        var response = JSON.parse(data);

        if (!response.success) {
            toastr.error(response.message);
            $('#subscriptions_empty').text(response.message + '.');
            return;
        }

        subscriptions = response.subscriptions;
        subscriptions.sort(function (a, b) {
            return a.company_name.localeCompare(b.company_name);
        });

        if (subscriptions.length > 0) {
            currency = subscriptions[0].currency;
        }

        render_kpis();
        render();
    });

});
</script>
