<div class="page page--list">
    <div class="page__head">
        <div>
            <h1 class="page__title">Users</h1>
        </div>
        <button type="button" class="btn btn--primary" id="add"><i class="fa-regular fa-plus"></i> Add User</button>
    </div>

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="user_search" class="input roster__search" placeholder="Search by name, username or email..." autocomplete="off" spellcheck="false">
            <div class="roster__filters">
                <div class="roster__filter">
                    <select id="status_filter" class="form-control">
                        <option value="">All users</option>
                        <option value="Active">Active</option>
                        <option value="Disabled">Disabled</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="data" id="users_table">
                    <thead>
                        <tr>
                            <th scope="col">Id</th>
                            <th scope="col">Name</th>
                            <th scope="col">Username</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Added</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="user_modal" tabindex="-1" aria-labelledby="user_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="user_modal_title">Add User</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="user_id" value="0">
                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label for="first_name">First Name <abbr title="required">*</abbr></label>
                        <input type="text" class="form-control" id="first_name" placeholder="Jane">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="last_name">Last Name <abbr title="required">*</abbr></label>
                        <input type="text" class="form-control" id="last_name" placeholder="Meier">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label for="u_name">Username <abbr title="required">*</abbr></label>
                        <input type="text" class="form-control" id="u_name" autocapitalize="none" spellcheck="false" placeholder="jmeier">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="user_email">Email <abbr title="required">*</abbr></label>
                        <input type="email" class="form-control" id="user_email" autocapitalize="none" spellcheck="false" placeholder="jane.meier@example.com">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label for="role_id">Role</label>
                        <select class="form-control" id="role_id"></select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="user_status">Status</label>
                        <select class="form-control" id="user_status">
                            <option value="Active">Active</option>
                            <option value="Disabled">Disabled</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="me-auto">
                    <button type="button" id="open_reset" class="btn btn--secondary"><i class="fa-regular fa-key"></i> Password Reset</button>
                    <button type="button" id="open_delete" class="btn btn--destructive"><i class="fa-regular fa-trash"></i></button>
                </span>
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal"><i class="fa-regular fa-xmark"></i> Cancel</button>
                <button type="button" id="save" class="btn btn--primary"><i class="fa-regular fa-floppy-disk"></i> Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="reset_modal" tabindex="-1" aria-labelledby="reset_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="reset_modal_title">Send Password Reset</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reset_user_id" value="0">
                <p>Email <strong id="reset_name"></strong> a link to choose a new password?</p>
                <p class="import__hint">The link goes to their address on file and expires in 60 minutes. Their current password keeps working until they use it.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="reset" class="btn btn--primary">Send Reset Link</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Remove User</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="delete_user_id" value="0">
                <p>Remove <strong id="delete_name"></strong>? They lose access immediately.</p>
                <p class="import__hint">Their name stays on anything they have already recorded, so assessment history is not rewritten.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="delete" class="btn btn--destructive">Remove User</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var current_user_id = <?php echo (int) Session::get('user_id'); ?>;
    var reopen_user = false;

    var table = $('#users_table').DataTable({
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
                width: '22%',
            },{
                targets: 2,
                width: '16%'
            },{
                targets: 3,
                width: '26%'
            },{
                targets: 4,
                width: '14%',
                render: {
                    display: function (data) {
                        if (!data) {
                            return '<span class="roster__none">&mdash;</span>';
                        }
                        return data;
                    }
                }
            },{
                targets: 5,
                width: '12%',
                render: {
                    _: 'sort',
                    display: 'display'
                }
            },{
                targets: 6,
                width: '10%',
                render: {
                    display: function (data) {
                        if (data === 'Disabled') {
                            return '<span class="badge badge--inactive">Disabled</span>';
                        }
                        return '<span class="badge badge--active">Active</span>';
                    }
                }
            }
        ],
        language: {
            zeroRecords: 'No users match your search',
            emptyTable: 'No users yet. Add your first user to give them access.'
        },
        createdRow: function (row) {
            $(row).attr('tabindex', 0);
        }
    });

    function set_loading(target, loading) {
        if (loading) {
            $(target).addClass("is-loading").prop("disabled", true);
        } else {
            $(target).removeClass("is-loading").prop("disabled", false);
        }
    }

    $('#user_search').on('input', function () {
        table.search(this.value).draw();
    });

    $("#status_filter").change(function () {
        table.column(6).search(this.value === "" ? "" : "^" + this.value + "$", true, false).draw();
    });

    $('#users_table tbody').on('click', 'tr', function () {

        var data = table.row(this).data();

        if (!data) {
            return;
        }

        $("#user_modal_title").text("Edit User");
        $("#user_id").val(data[0]);
        $("#first_name").val(data[7]);
        $("#last_name").val(data[8]);
        $("#u_name").val(data[2]);
        $("#user_email").val(data[3]);
        $("#role_id").val(data[9]);
        $("#user_status").val(data[6]);
        $("#open_reset").show();
        $("#open_delete").toggle(parseInt(data[0], 10) !== current_user_id);
        $("#user_modal").modal("show");
    });

    $("#add").click(function () {
        $("#user_modal_title").text("Add User");
        $("#user_id").val(0);
        $("#first_name").val("");
        $("#last_name").val("");
        $("#u_name").val("");
        $("#user_email").val("");
        $("#role_id").prop("selectedIndex", 0);
        $("#user_status").val("Active");
        $("#open_reset").hide();
        $("#open_delete").hide();
        $("#user_modal").modal("show");
        setTimeout(function () {
            $("#first_name").focus();
        }, 100);
    });

    function switch_modal(hide_id, show_id) {
        $(hide_id).one("hidden.bs.modal", function () {
            setTimeout(function () {
                $(show_id).modal("show");
            }, 150);
        });
        $(hide_id).modal("hide");
    }

    $("#open_reset").click(function () {
        reopen_user = true;
        $("#reset_user_id").val($("#user_id").val());
        $("#reset_name").text($("#first_name").val() + " " + $("#last_name").val());
        switch_modal("#user_modal", "#reset_modal");
    });

    $("#open_delete").click(function () {
        reopen_user = true;
        $("#delete_user_id").val($("#user_id").val());
        $("#delete_name").text($("#first_name").val() + " " + $("#last_name").val());
        switch_modal("#user_modal", "#delete_modal");
    });

    $("#reset_modal, #delete_modal").on("hidden.bs.modal", function () {
        if (reopen_user) {
            reopen_user = false;
            setTimeout(function () {
                $("#user_modal").modal("show");
            }, 150);
        }
    });

    $("#save").click(function () {

        if ($("#first_name").val() === "") {
            toastr.error("First name is required");
            return;
        }

        if ($("#last_name").val() === "") {
            toastr.error("Last name is required");
            return;
        }

        if ($("#u_name").val() === "") {
            toastr.error("Username is required");
            return;
        }

        if ($("#user_email").val() === "") {
            toastr.error("Email is required");
            return;
        }

        if ($("#user_status").val() === "") {
            toastr.error("Status is required");
            return;
        }

        var user = {
            toDo: (parseInt($("#user_id").val(), 10) > 0 ? "update" : "add"),
            user_id: $("#user_id").val(),
            role_id: $("#role_id").val(),
            first_name: $("#first_name").val(),
            last_name: $("#last_name").val(),
            u_name: $("#u_name").val(),
            user_email: $("#user_email").val(),
            user_status: $("#user_status").val()
        };

        set_loading("#save", true);

        ApiDataSvc.apiCall('post', 'save_user', user, function (data) {
            var obj = JSON.parse(data);
            set_loading("#save", false);
            if (obj.success) {
                toastr.success(obj.message);
                $("#user_modal").modal("hide");
                load_users();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $("#reset").click(function () {

        set_loading("#reset", true);

        ApiDataSvc.apiCall('post', 'reset_user_password', { user_id: $("#reset_user_id").val() }, function (data) {
            var obj = JSON.parse(data);
            set_loading("#reset", false);
            if (obj.success) {
                toastr.success(obj.message);
                $("#reset_modal").modal("hide");
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $("#delete").click(function () {

        set_loading("#delete", true);

        ApiDataSvc.apiCall('post', 'delete_user', { user_id: $("#delete_user_id").val() }, function (data) {
            var obj = JSON.parse(data);
            set_loading("#delete", false);
            if (obj.success) {
                reopen_user = false;
                toastr.success(obj.message);
                $("#delete_modal").modal("hide");
                load_users();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    function load_roles() {
        ApiDataSvc.apiCall('post', 'load_roles', {}, function (data) {
            var obj = JSON.parse(data);
            $("#role_id").empty();
            for (var i = 0; i < obj.length; i++) {
                $("#role_id").append($("<option>").attr("value", obj[i].id).text(obj[i].role_name));
            }
        });
    }

    function load_users() {
        ApiDataSvc.apiCall('post', 'load_users', {}, function (data) {
            var obj = JSON.parse(data);
            table.clear();
            for (var i = 0; i < obj.length; i++) {
                table.row.add([
                    obj[i].user_id,
                    obj[i].first_name + ' ' + obj[i].last_name,
                    obj[i].u_name,
                    obj[i].user_email,
                    obj[i].role_name,
                    { sort: obj[i].date_created, display: obj[i].date_created_display },
                    obj[i].user_status,
                    obj[i].first_name,
                    obj[i].last_name,
                    obj[i].role_id
                ]);
            }
            table.draw();
        });
    }

    load_roles();
    load_users();

});
</script>
