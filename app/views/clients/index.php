<div class="page page--list">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title">Clients</h1>
        </div>
        <div class="headline__actions">
            <?php if ($this->room['allowed']) { ?>
            <button type="button" class="btn btn--primary btn--add" data-bs-toggle="modal" data-bs-target="#client_add_modal" aria-label="New client" title="New client"><i class="fa-regular fa-plus"></i></button>
            <?php } else { ?>
            <a class="btn btn--primary btn--pill" href="/subscription">Upgrade</a>
            <?php } ?>
        </div>
    </header>

    <div class="panel listpage">
        <div class="panel__head roster__toolbar">
            <input type="search" id="client_search" class="input roster__search" placeholder="Search clients" autocomplete="off" spellcheck="false" aria-label="Search clients">
        </div>
        <div class="panel__body panel__body--flush listpage__body">
            <table class="data listpage__table" id="clients_table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Location</th>
                        <th scope="col" class="num">Assessments</th>
                        <th scope="col" class="num">Gaps</th>
                    </tr>
                </thead>
                <tbody id="clients_list"></tbody>
            </table>
            <p class="tiles__empty" id="clients_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading clients&hellip;</p>
        </div>
    </div>
</div>

<?php if ($this->room['allowed']) { ?>
<?php require Main::app_path().'/app/views/partials/client_add_modal.php'; ?>
<?php } ?>

<script>
$(document).ready(function () {

    var clients = [];

    function render() {

        var term = $('#client_search').val().trim().toLowerCase();
        var html = '';
        var shown = 0;

        for (var i = 0; i < clients.length; i++) {

            var place = [clients[i].city, clients[i].state].filter(Boolean).join(', ');
            var text = (clients[i].company_name + ' ' + place).toLowerCase();

            if (term !== '' && text.indexOf(term) === -1) {
                continue;
            }

            shown++;

            html += '<tr class="rowlink">'
                + '<td><a class="rowlink__a listpage__name" href="/clients/detail/id/' + clients[i].id + '">' + esc(clients[i].company_name) + '</a></td>'
                + '<td>' + esc(place) + '</td>'
                + '<td class="num">' + (parseInt(clients[i].assessment_count, 10) || 0).toLocaleString('en-US') + '</td>'
                + '<td class="num">' + (parseInt(clients[i].gap_count, 10) || 0).toLocaleString('en-US') + '</td>'
                + '</tr>';
        }

        $('#clients_list').html(html);
        $('#clients_table').prop('hidden', shown === 0);
        $('#clients_empty').prop('hidden', shown > 0).text(clients.length === 0
            ? 'No clients yet.'
            : 'No clients match your search.');
    }

    $('#client_search').on('input', render);

    ApiDataSvc.apiCall('post', 'load_clients', {}, function (data) {

        clients = JSON.parse(data);
        clients.sort(function (a, b) {
            return a.company_name.localeCompare(b.company_name);
        });

        render();
    });

});
</script>
