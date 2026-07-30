<div class="page page--list">
    <div class="page__head">
        <div>
            <h1 class="page__title">User Accounts</h1>
            <div class="client__meta">
                <span class="client__segment" id="roster_summary">&nbsp;</span>
            </div>
        </div>
        <button type="button" class="btn btn--primary" id="add"><i class="fa-regular fa-plus"></i> Add User</button>
    </div>

    <div class="panel">
        <div class="panel__head roster__toolbar">
            <input type="search" id="user_search" class="input roster__search" placeholder="Search by name, username or email..." autocomplete="off" spellcheck="false">
            <div class="roster__filters">
                <div class="roster__filter">
                    <select id="company_filter" class="form-control" aria-label="Filter by company">
                        <option value="">All companies</option>
                    </select>
                </div>
                <div class="roster__filter">
                    <select id="type_filter" class="form-control" aria-label="Filter by account type">
                        <option value="">All types</option>
                        <option value="staff">Staff</option>
                        <option value="portal">Client</option>
                    </select>
                </div>
                <div class="roster__filter">
                    <select id="status_filter" class="form-control" aria-label="Filter by status">
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
                            <th scope="col">Company</th>
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
                        <label for="company_id">Company</label>
                        <select class="form-control" id="company_id"></select>
                    </div>
                    <div class="col-md-6 form-group" id="client_row" hidden>
                        <label for="client_id">Client</label>
                        <select class="form-control" id="client_id"></select>
                    </div>
                    <div class="col-md-6 form-group" id="role_row">
                        <label for="role_id">Role</label>
                        <select class="form-control" id="role_id"></select>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" class="form-control" id="first_name" placeholder="Jane">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" class="form-control" id="last_name" placeholder="Meier">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label for="u_name">Username</label>
                        <input type="text" class="form-control" id="u_name" autocapitalize="none" spellcheck="false" placeholder="jmeier">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="user_email">Email</label>
                        <input type="email" class="form-control" id="user_email" autocapitalize="none" spellcheck="false" placeholder="jane.meier@example.com">
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label for="user_type">Account Type</label>
                        <select class="form-control" id="user_type">
                            <option value="staff">Staff</option>
                            <option value="portal">Client Portal</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="user_status">Status</label>
                        <select class="form-control" id="user_status">
                            <option value="Active">Active</option>
                            <option value="Disabled">Disabled</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-4" id="admin_row">
                    <div class="col-md-12 form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="global_admin">
                            <label class="form-check-label" for="global_admin">Global admin</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="me-auto modal-footer__aux">
                    <button type="button" id="open_reset" class="btn btn--secondary"><i class="fa-regular fa-key"></i> Reset</button>
                    <button type="button" class="btn btn--destructive" id="open_delete"><i class="fa-regular fa-trash"></i> Delete</button>
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
                <button type="button" id="reset" class="btn btn--primary">Reset Password</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete User</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="delete_user_id" value="0">
                <p>Delete <strong id="delete_name"></strong> from <strong id="delete_company"></strong>? They lose access immediately.</p>
                <p class="import__hint">Their name stays on anything they have already recorded, so assessment history is not rewritten.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="delete" class="btn btn--destructive">Delete User</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var current_user_id = <?php echo (int) Session::get('user_id'); ?>;
    var reopen_user = false;
    var companies = [];

    var table = $('#users_table').DataTable({
        dom: "t",
        paging: false,
        scrollY: "520px",
        scrollCollapse: true,
        deferRender: true,
        order: [[2, 'asc'], [1, 'asc']],
        columnDefs: [
            {
                targets: 0,
                visible: false
            },{
                targets: 1,
                width: '18%'
            },{
                targets: 2,
                width: '18%'
            },{
                targets: 3,
                width: '12%'
            },{
                targets: 4,
                width: '20%'
            },{
                targets: 5,
                width: '16%',
                render: {
                    display: function (data) {
                        if (!data) {
                            return '<span class="roster__none">&mdash;</span>';
                        }
                        return data;
                    }
                }
            },{
                targets: 6,
                width: '10%',
                render: {
                    _: 'sort',
                    display: 'display'
                }
            },{
                targets: 7,
                width: '8%',
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
            emptyTable: 'No user accounts yet.'
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

    $("#company_filter").change(function () {
        table.column(2).search(this.value === "" ? "" : "^" + this.value + "$", true, false).draw();
    });

    $("#type_filter").change(function () {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function (settings, row, index) {
        var type = $("#type_filter").val();
        if (settings.nTable.id !== "users_table" || type === "") {
            return true;
        }
        return table.row(index).data()[11] === type;
    });

    $("#status_filter").change(function () {
        table.column(7).search(this.value === "" ? "" : "^" + this.value + "$", true, false).draw();
    });

    $('#users_table tbody').on('click', 'tr', function () {
        var data = table.row(this).data();
        $("#user_modal_title").text("Edit User");
        $("#user_id").val(data[0]);
        $("#company_id").val(data[13]);
        $("#first_name").val(data[8]);
        $("#last_name").val(data[9]);
        $("#u_name").val(data[3]);
        $("#user_email").val(data[4]);
        $("#role_id").val(data[10]);
        $("#user_type").val(data[11]);
        $("#global_admin").prop("checked", parseInt(data[14], 10) === 1);
        $("#user_status").val(data[7]);
        load_clients(data[13], data[12]);
        sync_type();
        $("#open_reset").show();
        $("#user_modal").modal("show");
    });

    $("#add").click(function () {
        $("#user_modal_title").text("Add User");
        $("#user_id").val(0);
        $("#company_id").prop("selectedIndex", 0);
        $("#first_name").val("");
        $("#last_name").val("");
        $("#u_name").val("");
        $("#user_email").val("");
        $("#role_id").prop("selectedIndex", 0);
        $("#user_type").val("staff");
        $("#global_admin").prop("checked", false);
        $("#user_status").val("Active");
        load_clients($("#company_id").val(), 0);
        sync_type();
        $("#open_reset").hide();
        $("#open_delete").hide();
        $("#user_modal").modal("show");
        setTimeout(function () {
            $("#first_name").focus();
        }, 100);
    });

    $("#company_id").on("change", function () {
        load_clients($(this).val(), 0);
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
        $("#delete_company").text($("#company_id option:selected").text());
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

        if ($("#company_id").val() === null || $("#company_id").val() === "") {
            toastr.error("Company is required");
            return;
        }

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

        if ($("#user_type").val() === "portal" && ($("#client_id").val() === null || $("#client_id").val() === "")) {
            toastr.error($("#client_id").prop("disabled")
                ? "Add a client to " + $("#company_id option:selected").text() + " before giving anyone portal access"
                : "Choose the client this account belongs to");
            return;
        }

        var user = {
            user_id: $("#user_id").val(),
            company_id: $("#company_id").val(),
            role_id: $("#role_id").val(),
            user_type: $("#user_type").val(),
            client_id: $("#client_id").val(),
            global_admin: ($("#global_admin").is(":checked") ? 1 : 0),
            first_name: $("#first_name").val(),
            last_name: $("#last_name").val(),
            u_name: $("#u_name").val(),
            user_email: $("#user_email").val(),
            user_status: $("#user_status").val()
        };

        set_loading("#save", true);

        ApiDataSvc.apiCall('post', 'global_save_user', user, function (data) {
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

        ApiDataSvc.apiCall('post', 'global_reset_password', { user_id: $("#reset_user_id").val() }, function (data) {
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

        ApiDataSvc.apiCall('post', 'global_delete_user', { user_id: $("#delete_user_id").val() }, function (data) {
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

    function sync_type() {
        var portal = $("#user_type").val() === 'portal';
        $("#client_row").prop("hidden", !portal);
        $("#role_row").prop("hidden", portal);
        $("#admin_row").prop("hidden", portal);
        if (portal) {
            $("#global_admin").prop("checked", false);
        }
    }

    $("#user_type").on("change", sync_type);

    function load_clients(company_id, selected_client_id) {
        ApiDataSvc.apiCall('post', 'global_clients', { company_id: company_id }, function (data) {
            var obj = JSON.parse(data);
            $("#client_id").empty();

            if (obj.length === 0) {
                $("#client_id").append($("<option>").attr("value", "").text("No clients in this company yet"));
                $("#client_id").prop("disabled", true);
                return;
            }

            $("#client_id").prop("disabled", false);

            for (var i = 0; i < obj.length; i++) {
                $("#client_id").append($("<option>").attr("value", obj[i].id).text(obj[i].company_name));
            }

            if (parseInt(selected_client_id, 10) > 0) {
                $("#client_id").val(selected_client_id);
            }
        });
    }

    function load_roles() {
        ApiDataSvc.apiCall('post', 'load_roles', {}, function (data) {
            var obj = JSON.parse(data);
            $("#role_id").empty();
            for (var i = 0; i < obj.length; i++) {
                $("#role_id").append($("<option>").attr("value", obj[i].id).text(obj[i].role_name));
            }
        });
    }

    function load_companies() {
        ApiDataSvc.apiCall('post', 'global_companies', {}, function (data) {
            companies = JSON.parse(data);
            $("#company_id").empty();
            for (var i = 0; i < companies.length; i++) {
                $("#company_filter").append($("<option>").attr("value", companies[i].company_name).text(companies[i].company_name));
                $("#company_id").append($("<option>").attr("value", companies[i].id).text(companies[i].company_name));
            }
            load_users();
        });
    }

    function load_users() {
        ApiDataSvc.apiCall('post', 'global_users', {}, function (data) {
            var obj = JSON.parse(data);
            var admins = 0;
            table.clear();
            for (var i = 0; i < obj.length; i++) {
                if (parseInt(obj[i].global_admin, 10) === 1) {
                    admins++;
                }
                table.row.add([
                    obj[i].user_id,
                    obj[i].first_name + ' ' + obj[i].last_name,
                    obj[i].company_name,
                    obj[i].u_name,
                    obj[i].user_email,
                    (obj[i].user_type === 'portal'
                        ? 'Client' + (obj[i].client_name === null ? '' : ' · ' + obj[i].client_name)
                        : obj[i].role_name + (parseInt(obj[i].global_admin, 10) === 1
                            ? ' <span class="badge badge--admin">Global</span>'
                            : '')),
                    { sort: obj[i].date_created, display: obj[i].date_created_display },
                    obj[i].user_status,
                    obj[i].first_name,
                    obj[i].last_name,
                    obj[i].role_id,
                    obj[i].user_type,
                    obj[i].client_id,
                    obj[i].company_id,
                    obj[i].global_admin
                ]);
            }
            table.draw();
            $("#roster_summary").text(obj.length + (obj.length === 1 ? ' account' : ' accounts')
                + ' across ' + companies.length + (companies.length === 1 ? ' company' : ' companies')
                + ' · ' + admins + (admins === 1 ? ' global admin' : ' global admins'));
        });
    }

    load_roles();
    load_companies();

});
</script>
