<div class="page page--narrow">

    <div class="page__head">
        <div>
            <h1 class="page__title">My Profile</h1>
            <p class="page__desc">Your account details and password.</p>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Personal details</h2>
        </div>
        <div class="panel__body">
            <div class="field">
                <label for="first_name">First name <abbr title="required">*</abbr></label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($this->profile['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="field">
                <label for="last_name">Last name <abbr title="required">*</abbr></label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($this->profile['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="field">
                <label for="user_email">Email address <abbr title="required">*</abbr></label>
                <input type="email" id="user_email" name="user_email" value="<?php echo htmlspecialchars($this->profile['user_email'], ENT_QUOTES, 'UTF-8'); ?>" autocapitalize="none" spellcheck="false" required>
                <span class="field__help">Used for password resets and system notifications.</span>
            </div>
            <div class="panel__actions">
                <button type="button" id="do_save_profile" class="btn btn--primary">Save Changes</button>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Change password</h2>
        </div>
        <div class="panel__body">
            <div class="field">
                <label for="current_pw">Current password <abbr title="required">*</abbr></label>
                <input type="password" id="current_pw" name="current_pw" autocomplete="current-password" required>
            </div>
            <div class="field">
                <label for="pw1">New password <abbr title="required">*</abbr></label>
                <input type="password" id="pw1" name="pw1" autocomplete="new-password" required>
                <span class="field__help">At least 12 characters, with an uppercase letter, a lowercase letter and a number.</span>
            </div>
            <div class="field">
                <label for="pw2">Confirm new password <abbr title="required">*</abbr></label>
                <input type="password" id="pw2" name="pw2" autocomplete="new-password" required>
            </div>
            <div class="panel__actions">
                <button type="button" id="do_change_pw" class="btn btn--primary">Update Password</button>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Account</h2>
        </div>
        <div class="panel__body">
            <dl class="datalist">
                <dt>Username</dt>
                <dd><?php echo htmlspecialchars($this->profile['u_name'], ENT_QUOTES, 'UTF-8'); ?></dd>
                <dt>Organisation</dt>
                <dd><?php echo htmlspecialchars((string) $this->profile['company_name'], ENT_QUOTES, 'UTF-8'); ?></dd>
                <dt>Role</dt>
                <dd><?php echo htmlspecialchars((string) $this->profile['role_name'], ENT_QUOTES, 'UTF-8'); ?></dd>
                <dt>Status</dt>
                <dd><span class="status status--ok"><?php echo htmlspecialchars($this->profile['user_status'], ENT_QUOTES, 'UTF-8'); ?></span></dd>
                <dt>Member since</dt>
                <dd><?php echo date('j F Y', strtotime($this->profile['date_created'])); ?></dd>
            </dl>
        </div>
    </div>

</div>

<script>
$(document).ready(function () {

    function set_loading(button_id, loading) {
        if (loading) {
            $("#" + button_id).addClass("is-loading").prop("disabled", true);
        } else {
            $("#" + button_id).removeClass("is-loading").prop("disabled", false);
        }
    }

    $(document).ajaxComplete(function () {
        set_loading("do_save_profile", false);
        set_loading("do_change_pw", false);
    });

    $("#do_save_profile").click(function(){

        $("#first_name").removeClass("is-invalid");
        $("#last_name").removeClass("is-invalid");
        $("#user_email").removeClass("is-invalid");

        var errors = 0;

        if ($("#first_name").val().trim() === "") {
            $("#first_name").addClass("is-invalid");
            errors++;
        }

        if ($("#last_name").val().trim() === "") {
            $("#last_name").addClass("is-invalid");
            errors++;
        }

        if ($("#user_email").val().trim() === "") {
            $("#user_email").addClass("is-invalid");
            errors++;
        }

        if (errors > 0) {
            toastr.error("Please fix the errors before continuing.");
            return;
        }

        var obj = {
            first_name: $("#first_name").val().trim(),
            last_name: $("#last_name").val().trim(),
            user_email: $("#user_email").val().trim()
        };

        set_loading("do_save_profile", true);

        ApiDataSvc.apiCall('post', 'save_profile', obj, function (data) {
            var obj = JSON.parse(data);
            if (obj.success) {
                toastr.success(obj.message);
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                toastr.error(obj.message);
            }
        });

    });

    $("#do_change_pw").click(function(){

        $("#current_pw").removeClass("is-invalid");
        $("#pw1").removeClass("is-invalid");
        $("#pw2").removeClass("is-invalid");

        var errors = 0;

        if ($("#current_pw").val().trim() === "") {
            $("#current_pw").addClass("is-invalid");
            errors++;
        }

        if ($("#pw1").val().trim() === "") {
            $("#pw1").addClass("is-invalid");
            errors++;
        }

        if ($("#pw2").val().trim() === "") {
            $("#pw2").addClass("is-invalid");
            errors++;
        }

        if (errors > 0) {
            toastr.error("Please fix the errors before continuing.");
            return;
        }

        var obj = {
            current_pw: $("#current_pw").val().trim(),
            pw1: $("#pw1").val().trim(),
            pw2: $("#pw2").val().trim()
        };

        set_loading("do_change_pw", true);

        ApiDataSvc.apiCall('post', 'change_password', obj, function (data) {
            var obj = JSON.parse(data);
            if (obj.success) {
                toastr.success(obj.message);
                $("#current_pw").val("");
                $("#pw1").val("");
                $("#pw2").val("");
            } else {
                toastr.error(obj.message);
            }
        });

    });

});
</script>
