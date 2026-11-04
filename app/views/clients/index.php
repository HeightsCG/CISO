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

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="client_search" class="input roster__search" placeholder="Search clients" autocomplete="off" spellcheck="false" aria-label="Search clients">
        </div>
        <div class="panel__body">
            <div class="tiles" id="clients_list"></div>
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

    function plural(count, one, many) {
        return count + ' ' + (count === 1 ? one : many);
    }

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

            html += '<a class="tile" href="/clients/detail/id/' + clients[i].id + '">'
                + '<span class="tile__title">' + esc(clients[i].company_name) + '</span>'
                + (place === '' ? '' : '<span class="tile__meta">' + esc(place) + '</span>')
                + '<span class="tile__foot">'
                + '<span>' + plural(parseInt(clients[i].assessment_count, 10), 'assessment', 'assessments') + '</span>'
                + '</span>'
                + '</a>';
        }

        $('#clients_list').html(html).prop('hidden', shown === 0);
        $('#clients_empty').prop('hidden', shown > 0).text(clients.length === 0
            ? 'No clients yet. Add your first client to start building the roster.'
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
