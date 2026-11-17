<div class="page page--list">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title"><?php echo htmlspecialchars($this->standard['standard_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="headline__sub">
                <?php echo ($this->standard['issuing_body'] === '' ? '' : '<span>'.htmlspecialchars($this->standard['issuing_body'], ENT_QUOTES, 'UTF-8').'</span>'); ?>
                <?php echo ($this->standard['version'] === '' ? '' : '<span>'.htmlspecialchars($this->standard['version'], ENT_QUOTES, 'UTF-8').'</span>'); ?>
                <span id="control_count"><?php echo (int) $this->standard['control_count']; ?> controls</span>
            </p>
        </div>
    </header>

    <?php echo ($this->standard['description'] === '' ? '' : '<div class="panel record__panel"><div class="panel__body"><p class="standard__description">'.nl2br(htmlspecialchars($this->standard['description'], ENT_QUOTES, 'UTF-8')).'</p></div></div>'); ?>

    <div class="panel listpage">
        <div class="panel__head roster__toolbar">
            <input type="search" id="control_search" class="input roster__search" placeholder="Search by identifier or title..." autocomplete="off" spellcheck="false">
        </div>
        <div class="panel__body panel__body--flush listpage__body">
            <div class="table-wrap">
                <table class="data controls__table">
                    <thead>
                        <tr>
                            <th scope="col">Identifier</th>
                            <th scope="col">Control</th>
                        </tr>
                    </thead>
                    <tbody id="controls_body"></tbody>
                </table>
            </div>
            <p class="controls__empty" id="controls_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading controls&hellip;</p>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var standard_id = <?php echo (int) $this->standard['id']; ?>;

    function control_row(control) {

        var body = control.description === ''
            ? '<span class="controls__title">' + esc(control.control_title) + '</span>'
            : '<details class="controls__detail"><summary>' + esc(control.control_title) + '</summary>'
                + '<p class="controls__desc">' + esc(control.description) + '</p></details>';

        return '<tr class="controls__row">'
            + '<td class="controls__id">' + esc(control.control_identifier) + '</td>'
            + '<td>' + body + '</td>'
            + '</tr>';
    }

    function render(controls) {

        var html = '';
        var current = null;

        for (var i = 0; i < controls.length; i++) {

            var family = controls[i].family === '' ? 'Ungrouped' : controls[i].family;

            if (family !== current) {
                current = family;
                html += '<tr class="controls__family"><td colspan="2">' + esc(family) + '</td></tr>';
            }

            html += control_row(controls[i]);
        }

        $('#controls_body').html(html);
        $('#control_count').text(controls.length + (controls.length === 1 ? ' control' : ' controls'));
    }

    function filter() {

        var term = $('#control_search').val().trim().toLowerCase();
        var visible = 0;

        $('#controls_body .controls__family').each(function () {

            var matched = 0;

            $(this).nextUntil('.controls__family', '.controls__row').each(function () {

                var text = $(this).find('.controls__id').text().toLowerCase()
                    + ' ' + $(this).find('summary, .controls__title').text().toLowerCase();

                var hit = term === '' || text.indexOf(term) !== -1;

                $(this).toggle(hit);

                if (hit) {
                    matched++;
                }
            });

            $(this).toggle(matched > 0);
            visible += matched;
        });

        $('.controls__table').toggle(visible > 0);
        $('#controls_empty').toggle(visible === 0);

        if (visible === 0) {
            $('#controls_empty').text(term === '' ? 'This framework has no controls yet.' : 'No controls match your search');
        }
    }

    function load() {
        ApiDataSvc.apiCall('post', 'load_controls', { standard_id: standard_id }, function (data) {
            render(JSON.parse(data));
            filter();
        });
    }

    $('#control_search').on('input', filter);

    load();

});
</script>
