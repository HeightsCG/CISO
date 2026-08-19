<div class="page">
    <a class="page__back" href="/portal"><i class="fa-regular fa-arrow-left"></i> Back to Your Projects</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="client__meta">
                <span class="badge <?php echo ($this->project['project_status'] === 'Complete' ? 'badge--inactive' : 'badge--active'); ?>"><?php echo htmlspecialchars($this->project['project_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo htmlspecialchars($this->project['start_date_display'], ENT_QUOTES, 'UTF-8'); ?> &ndash; <?php echo htmlspecialchars($this->project['end_date_display'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="page__actions">
            <a class="btn btn--secondary" href="/portal/evidence/id/<?php echo (int) $this->project['id']; ?>"><i class="fa-regular fa-folder-open"></i> Evidence</a>
        </div>
    </div>

    <div class="panel record__panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="assessment_search" class="input roster__search" placeholder="Search assessments..." autocomplete="off" spellcheck="false">
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data" id="assessments_table">
                    <thead>
                        <tr>
                            <th scope="col">Id</th>
                            <th scope="col">Assessment</th>
                            <th scope="col">Standard</th>
                            <th scope="col">Progress</th>
                            <th scope="col">Status</th>
                            <th scope="col">Controls assessed</th>
                            <th scope="col">Controls total</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="controls__empty" id="assessments_empty">Loading assessments&hellip;</p>
        </div>
    </div>

    <?php if ($this->project['description'] !== '') { ?>
    <div class="panel record__panel">
        <div class="panel__head">
            <h2 class="panel__title">About this project</h2>
        </div>
        <div class="panel__body">
            <p class="assess__text"><?php echo nl2br(htmlspecialchars($this->project['description'], ENT_QUOTES, 'UTF-8')); ?></p>
        </div>
    </div>
    <?php } ?>
</div>

<script>
$(document).ready(function () {

    var project_id = <?php echo (int) $this->project['id']; ?>;

    function progress_cell(data, type, row) {

        if (type !== 'display') {
            return data;
        }

        return '<div class="progress"><div class="progress__bar" style="width:' + data + '%"></div></div>'
            + '<p class="progress__label">' + row[5] + ' of ' + row[6] + ' \u00b7 ' + data + '%</p>';
    }

    function status_cell(data, type) {

        if (type !== 'display') {
            return data;
        }

        var tone = 'badge--inactive';

        if (data === 'Complete') {
            tone = 'badge--active';
        } else if (data === 'In Progress') {
            tone = 'badge--onboarding';
        }

        return '<span class="badge ' + tone + '">' + esc(data) + '</span>';
    }

    var table = $('#assessments_table').DataTable({
        dom: "t",
        paging: false,
        scrollY: "460px",
        scrollCollapse: true,
        deferRender: true,
        order: [[1, 'asc']],
        columnDefs: [
            {
                targets: 0,
                visible: false
            },{
                targets: 1,
                width: '34%',
                render: text_cell
            },{
                targets: 2,
                width: '28%',
                render: text_cell
            },{
                targets: 3,
                width: '20%',
                render: progress_cell
            },{
                targets: 4,
                width: '18%',
                render: status_cell
            },{
                targets: [5, 6],
                visible: false,
                searchable: false
            }
        ],
        language: {
            zeroRecords: 'No assessments match your search'
        },
        createdRow: function (row) {
            $(row).attr('tabindex', 0);
        }
    });

    $('#assessments_table tbody').on('click', 'tr', function () {
        var data = table.row(this).data();
        if (data) {
            window.location.href = '/portal/assessment/id/' + data[0];
        }
    });

    $('#assessment_search').on('input', function () {
        table.search(this.value).draw();
    });

    function load(){
        ApiDataSvc.apiCall('post', 'portal_assessments', { project_id: project_id }, function (data) {

            var obj = JSON.parse(data);

            table.clear();

            for (var i = 0; i < obj.length; i++) {

                var total = parseInt(obj[i].item_count, 10);
                var done = parseInt(obj[i].assessed_count, 10);

                table.row.add([
                    obj[i].id,
                    obj[i].assessment_name,
                    obj[i].short_code + (obj[i].version === '' ? '' : ' \u00b7 ' + obj[i].version),
                    total > 0 ? Math.round(done / total * 100) : 0,
                    obj[i].assessment_status,
                    done,
                    total
                ]);
            }

            table.draw();
            $('#assessments_empty').prop('hidden', obj.length > 0).text('No assessments yet.');
        });
    }

    load();

});
</script>
