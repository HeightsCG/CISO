<div class="page page--list">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title">Leads</h1>
        </div>
    </header>

    <div class="panel listpage">
        <div class="panel__head roster__toolbar">
            <input type="search" id="lead_search" class="input roster__search" placeholder="Search names, companies or emails" autocomplete="off" spellcheck="false" aria-label="Search leads">
            <div class="roster__filters">
                <div class="roster__filter">
                    <select id="type_filter" class="form-control" aria-label="Filter by request">
                        <option value="">All requests</option>
                        <option value="demo">Demo</option>
                        <option value="pricing">Pricing</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="panel__body panel__body--flush listpage__body">
            <table class="data listpage__table" id="leads_table" hidden>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Request</th>
                        <th scope="col">Company</th>
                        <th scope="col">Organization</th>
                        <th scope="col">Email</th>
                        <th scope="col">Received</th>
                    </tr>
                </thead>
                <tbody id="leads_list"></tbody>
            </table>
            <p class="tiles__empty" id="leads_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading leads&hellip;</p>
        </div>
    </div>
</div>

<div class="modal fade" id="lead_modal" tabindex="-1" aria-labelledby="lead_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="lead_modal_title"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="datalist datalist--record" id="lead_details"></dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary me-auto" data-bs-dismiss="modal">Close</button>
                <a class="btn btn--primary" id="lead_reply" href="#">Reply by Email</a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var leads = [];

    var TYPES = {
        demo: {
            label: 'Demo',
            tone: 'badge--prospect'
        },
        pricing: {
            label: 'Pricing',
            tone: 'badge--admin'
        }
    };

    function type_badge(type) {
        var t = TYPES[type] || TYPES.demo;
        return '<span class="badge ' + t.tone + '">' + t.label + '</span>';
    }

    function detail(label, value) {
        if (value === '') {
            return '';
        }
        return '<dt>' + label + '</dt><dd>' + value + '</dd>';
    }

    function open_lead(id) {

        var lead = null;

        for (var i = 0; i < leads.length; i++) {
            if (String(leads[i].id) === String(id)) {
                lead = leads[i];
            }
        }

        if (lead === null) {
            return;
        }

        $('#lead_modal_title').text(lead.first_name + ' ' + lead.last_name);
        $('#lead_details').html(
            detail('Request', type_badge(lead.lead_type))
            + detail('Received', esc(lead.received_display))
            + detail('Company', esc(lead.company_name))
            + detail('Organization', esc(lead.organization_type))
            + detail('Email', '<a href="mailto:' + esc(lead.lead_email) + '">' + esc(lead.lead_email) + '</a>')
            + detail('Phone', esc(lead.lead_phone))
            + detail('Message', esc(lead.lead_message).replace(/\n/g, '<br>'))
        );
        $('#lead_reply').attr('href', 'mailto:' + lead.lead_email);

        modal('lead_modal').show();
    }

    function render() {

        var term = $('#lead_search').val().trim().toLowerCase();
        var filter = $('#type_filter').val();
        var html = '';
        var shown = 0;

        for (var i = 0; i < leads.length; i++) {

            var l = leads[i];
            var text = (l.first_name + ' ' + l.last_name + ' ' + l.company_name + ' ' + l.lead_email).toLowerCase();

            if (term !== '' && text.indexOf(term) === -1) {
                continue;
            }

            if (filter !== '' && l.lead_type !== filter) {
                continue;
            }

            shown++;

            html += '<tr class="rowlink" data-id="' + l.id + '" tabindex="0">'
                + '<td><span class="listpage__name">' + esc(l.first_name + ' ' + l.last_name) + '</span></td>'
                + '<td>' + type_badge(l.lead_type) + '</td>'
                + '<td>' + esc(l.company_name) + '</td>'
                + '<td>' + esc(l.organization_type) + '</td>'
                + '<td>' + esc(l.lead_email) + '</td>'
                + '<td>' + esc(l.received_display) + '</td>'
                + '</tr>';
        }

        $('#leads_list').html(html);
        $('#leads_table').prop('hidden', shown === 0);
        $('#leads_empty').prop('hidden', shown > 0).text(leads.length === 0
            ? 'No leads yet.'
            : 'No leads match your search.');
    }

    $('#lead_search').on('input', render);
    $('#type_filter').on('change', render);

    $('#leads_list').on('click', '.rowlink', function () {
        open_lead($(this).data('id'));
    });

    $('#leads_list').on('keydown', '.rowlink', function (e) {
        if (e.key === 'Enter') {
            open_lead($(this).data('id'));
        }
    });

    ApiDataSvc.apiCall('post', 'global_leads', {}, function (data) {

        var response = JSON.parse(data);

        if (!Array.isArray(response)) {
            toastr.error(response.message || 'Something went wrong. Please try again.');
            $('#leads_empty').text('Leads could not be loaded.');
            return;
        }

        leads = response;
        render();
    });

});
</script>
