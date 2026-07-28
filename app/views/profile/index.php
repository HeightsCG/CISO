<div class="page">
    <div class="page__head">
        <div>
            <h1 class="page__title">My Profile</h1>
            <p class="page__desc">Your account details and password.</p>
        </div>
    </div>
    <div class="page__grid">
        <div class="page__col">
            <div class="panel">
                <div class="panel__head">
                    <h2 class="panel__title">Personal Details</h2>
                    <button type="button" class="btn btn--secondary btn--sm" data-bs-toggle="modal" data-bs-target="#password_modal">Change Password</button>
                </div>
                <div class="panel__body">
                    <div class="form-row">
                        <div class="field">
                            <label for="first_name">First Name <abbr title="required">*</abbr></label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($this->profile['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="field">
                            <label for="last_name">Last Name <abbr title="required">*</abbr></label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($this->profile['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="u_name">Username <abbr title="required">*</abbr></label>
                            <input type="text" id="u_name" name="u_name" value="<?php echo htmlspecialchars($this->profile['u_name'], ENT_QUOTES, 'UTF-8'); ?>" autocapitalize="none" spellcheck="false" required>
                        </div>
                        <div class="field">
                            <label for="user_email">Email Address <abbr title="required">*</abbr></label>
                            <input type="email" id="user_email" name="user_email" value="<?php echo htmlspecialchars($this->profile['user_email'], ENT_QUOTES, 'UTF-8'); ?>" autocapitalize="none" spellcheck="false" required>
                        </div>
                    </div>
                    <div class="panel__actions">
                        <button type="button" id="do_save_profile" class="btn btn--primary">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
        <aside class="page__aside">

            <div class="panel">
                <div class="panel__head">
                    <h2 class="panel__title">Account</h2>
                </div>
                <div class="panel__body">
                    <dl class="datalist datalist--stacked">
                        <dt>Organization</dt>
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
        </aside>
    </div>
</div>

<div class="modal fade" id="password_modal" tabindex="-1" aria-labelledby="password_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="password_modal_title">Change Password</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="field">
                    <label for="current_pw">Current Password <abbr title="required">*</abbr></label>
                    <input type="password" id="current_pw" name="current_pw" autocomplete="current-password" required>
                </div>
                <div class="field">
                    <label for="pw1">New Password <abbr title="required">*</abbr></label>
                    <input type="password" id="pw1" name="pw1" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label for="pw2">Confirm New Password <abbr title="required">*</abbr></label>
                    <input type="password" id="pw2" name="pw2" autocomplete="new-password" required>
                </div>
                <ul class="pwcheck" id="pw_rules" aria-live="polite">
                    <li class="pwcheck__item" id="rule_length"><span class="pwcheck__mark"></span>At least 12 characters</li>
                    <li class="pwcheck__item" id="rule_upper"><span class="pwcheck__mark"></span>One uppercase letter</li>
                    <li class="pwcheck__item" id="rule_lower"><span class="pwcheck__mark"></span>One lowercase letter</li>
                    <li class="pwcheck__item" id="rule_number"><span class="pwcheck__mark"></span>One number</li>
                    <li class="pwcheck__item" id="rule_special"><span class="pwcheck__mark"></span>One special character</li>
                    <li class="pwcheck__item" id="rule_match"><span class="pwcheck__mark"></span>Both entries match</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_change_pw" class="btn btn--primary">Update Password</button>
            </div>
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

    function set_rule(rule_id, passed) {
        if (passed) {
            $("#" + rule_id).addClass("is-met");
        } else {
            $("#" + rule_id).removeClass("is-met");
        }
    }

    function check_rules() {

        var pw1 = $("#pw1").val();
        var pw2 = $("#pw2").val();

        set_rule("rule_length", pw1.length >= 12);
        set_rule("rule_upper", /[A-Z]/.test(pw1));
        set_rule("rule_lower", /[a-z]/.test(pw1));
        set_rule("rule_number", /[0-9]/.test(pw1));
        set_rule("rule_special", /[^A-Za-z0-9]/.test(pw1));
        set_rule("rule_match", pw1 !== "" && pw1 === pw2);
    }

    function rules_pass() {
        return $("#pw_rules .pwcheck__item:not(.is-met)").length === 0;
    }

    $("#pw1, #pw2").on("keyup change", function () {
        check_rules();
    });

    $("#password_modal").on("hidden.bs.modal", function () {
        $("#current_pw").val("");
        $("#pw1").val("");
        $("#pw2").val("");
        $("#current_pw, #pw1, #pw2").removeClass("is-invalid");
        check_rules();
    });

    $("#password_modal").on("shown.bs.modal", function () {
        $("#current_pw").focus();
    });

    $("#do_save_profile").click(function(){

        $("#first_name").removeClass("is-invalid");
        $("#last_name").removeClass("is-invalid");
        $("#u_name").removeClass("is-invalid");
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

        if ($("#u_name").val().trim() === "") {
            $("#u_name").addClass("is-invalid");
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
            u_name: $("#u_name").val().trim(),
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

        if ($("#current_pw").val().trim() === "") {
            $("#current_pw").addClass("is-invalid");
            toastr.error("Enter your current password.");
            return;
        }

        if (!rules_pass()) {
            $("#pw1").addClass("is-invalid");
            $("#pw2").addClass("is-invalid");
            toastr.error("Your new password does not meet all requirements.");
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
                bootstrap.Modal.getInstance(document.getElementById("password_modal")).hide();
            } else {
                toastr.error(obj.message);
            }
        });

    });

    check_rules();

});
</script>
