<div class="page page--list">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title">Frameworks</h1>
        </div>
    </header>

    <div class="panel listpage">
        <div class="panel__head roster__toolbar">
            <input type="search" id="framework_search" class="input roster__search" placeholder="Search by name or issuer" autocomplete="off" spellcheck="false" aria-label="Search frameworks">
        </div>
        <div class="panel__body panel__body--flush listpage__body">
            <table class="data listpage__table" id="frameworks_table">
                <thead>
                    <tr>
                        <th scope="col">Framework</th>
                        <th scope="col">Issued By</th>
                        <th scope="col">Version</th>
                        <th scope="col" class="num">Controls</th>
                    </tr>
                </thead>
                <tbody id="frameworks_list"></tbody>
            </table>
            <p class="tiles__empty" id="frameworks_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading frameworks&hellip;</p>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var frameworks = [];

    function render() {

        var term = $('#framework_search').val().trim().toLowerCase();
        var html = '';
        var shown = 0;

        for (var i = 0; i < frameworks.length; i++) {

            var f = frameworks[i];
            var text = (f.standard_name + ' ' + (f.full_name || '') + ' ' + (f.issuing_body || '')).toLowerCase();

            if (term !== '' && text.indexOf(term) === -1) {
                continue;
            }

            shown++;

            html += '<tr class="rowlink">'
                + '<td><a class="rowlink__a listpage__name" href="/frameworks/detail/id/' + f.id + '">' + esc(f.standard_name) + '</a>'
                + (f.full_name && f.full_name !== f.standard_name ? '<span class="listpage__sub">' + esc(f.full_name) + '</span>' : '')
                + '</td>'
                + '<td>' + esc(f.issuing_body || '') + '</td>'
                + '<td>' + esc(f.version || '') + '</td>'
                + '<td class="num">' + (parseInt(f.control_count, 10) || 0).toLocaleString('en-US') + '</td>'
                + '</tr>';
        }

        $('#frameworks_list').html(html);
        $('#frameworks_table').prop('hidden', shown === 0);
        $('#frameworks_empty').prop('hidden', shown > 0).text(frameworks.length === 0
            ? 'No frameworks are available yet.'
            : 'No frameworks match your search.');
    }

    $('#framework_search').on('input', render);

    ApiDataSvc.apiCall('post', 'load_framework_library', {}, function (data) {

        frameworks = JSON.parse(data);
        frameworks.sort(function (a, b) {
            return a.standard_name.localeCompare(b.standard_name);
        });

        render();
    });

});
</script>
