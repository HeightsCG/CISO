<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php echo CSRF::meta(); ?>
<?php echo Main::head_meta(); ?>
<title>Change Password |<?php echo Main::site_name(); ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css">
<link rel="stylesheet" href="/css/site.css?v=<?php echo (int) @filemtime(Main::app_path().'/public/css/site.css'); ?>">

<script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
<script src="/js/api.data.js"></script>

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
        set_loading("do_change", false);
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

    $("#do_change").click(function(){

        $("#pw1").removeClass("is-invalid");
        $("#pw2").removeClass("is-invalid");

        if (!rules_pass()) {
            $("#pw1").addClass("is-invalid");
            $("#pw2").addClass("is-invalid");
            toastr.error("Your new password does not meet all requirements.");
            return;
        }

        var obj = {
            pw1: $("#pw1").val().trim(),
            pw2: $("#pw2").val().trim()
        };

        set_loading("do_change", true);

        ApiDataSvc.apiCall('post', 'change_password', obj, function (data) {
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

    $("#pw1, #pw2").on("keydown", function (e) {
        if (e.key === "Enter") {
            $("#do_change").click();
        }
    });

    $("#pw1").focus();

});
</script>
</head>
<body>
<div class="auth">
<div class="auth__module">

    <main class="auth__stage">

        <header class="auth__intro">
            <img class="auth__logo-img" src="/images/logo-light.png" alt="CISO.aero">
            <h1 class="auth__pitch">Aviation cybersecurity governance, centralized.</h1>
            <p class="auth__sub">Manage risk, compliance, evidence, and executive oversight from one secure platform.</p>
        </header>

        <div class="auth__form" id="force_reset_view">
            <h2 class="auth__heading">Change Your Password</h2>
            <p class="auth__note">Your password must be changed before you can continue.</p>
            <div class="auth__fields">
                <div class="field">
                    <label for="pw1">New password <abbr title="required">*</abbr></label>
                    <input type="password" id="pw1" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label for="pw2">Confirm new password <abbr title="required">*</abbr></label>
                    <input type="password" id="pw2" autocomplete="new-password" required>
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
            <div class="auth__submit">
                <button type="button" id="do_change" class="btn btn--primary btn--block">Change Password</button>
            </div>
        </div>

        <footer class="auth__footer">
            &copy; 2026 CISO.aero
            &nbsp;&middot;&nbsp;
            <button type="button" class="auth__footer-link" data-bs-toggle="modal" data-bs-target="#privacy_modal">Privacy</button>
            &nbsp;&middot;&nbsp;
            <button type="button" class="auth__footer-link" data-bs-toggle="modal" data-bs-target="#terms_modal">Terms of Use</button>
        </footer>

    </main>

</div>

<div class="auth__visual">
    <img class="auth__visual-img" src="/images/auth-cover.jpg" alt="">
    <div class="auth__caption">
        <p class="auth__caption-title">Built for aviation security leadership</p>
        <p class="auth__caption-sub">Operational clarity from the boardroom to the flight line.</p>
    </div>
</div>

</div>

<div class="modal fade policy-modal" id="privacy_modal" tabindex="-1" aria-labelledby="privacy_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="privacy_modal_title">Privacy Policy</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="policy-modal__meta">Last updated 28 July 2026</p>

                <h3>1. Scope</h3>
                <p>This policy explains what personal data CISO.aero collects when you use the platform, why it is held, and how long it is kept. It applies to the CISO.aero application and its supporting services.</p>

                <h3>2. Data we collect</h3>
                <p>Account records: your first and last name, username, email address, the organization you belong to, your assigned role and your account status. Passwords are stored only as a one-way hash and are never held in readable form.</p>
                <p>Authentication records: session identifiers, the time of your last sign-in, password reset requests and their expiry, and whether a password change is required.</p>
                <p>Client program data: the compliance, risk, assessment and evidence records that your organization enters into the platform.</p>

                <h3>3. How data is used</h3>
                <p>To authenticate you, to enforce access control, to operate the compliance and reporting features you use, to send transactional email such as password resets, and to maintain security and audit records.</p>

                <h3>4. Legal basis</h3>
                <p>Processing is carried out to perform the contract between your organization and CISO.aero, and to meet our legitimate interest in operating and securing the service.</p>

                <h3>5. Sharing</h3>
                <p>Data is not sold. It is shared only with the infrastructure and email providers required to deliver the service, and where disclosure is required by law.</p>

                <h3>6. Retention</h3>
                <p>Account and program data is retained for the life of your organization's agreement and for the period afterwards required by applicable record-keeping obligations. Password reset tokens expire one hour after issue and are single use.</p>

                <h3>7. Your rights</h3>
                <p>Depending on your jurisdiction you may request access to, correction of, or deletion of your personal data, and may object to or restrict certain processing. Requests are handled through your organization's administrator.</p>

                <h3>8. Security</h3>
                <p>Access is restricted by role, transport is encrypted, passwords are hashed, and administrative access to client data is logged.</p>

                <h3>9. Contact</h3>
                <p>Questions about this policy should be directed to your organization's administrator or to the CISO.aero support address.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade policy-modal" id="terms_modal" tabindex="-1" aria-labelledby="terms_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="terms_modal_title">Terms of Use</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="policy-modal__meta">Last updated 28 July 2026</p>

                <h3>1. Acceptance</h3>
                <p>By accessing CISO.aero you agree to these terms. If you are using the platform on behalf of an organization, you confirm you are authorised to accept them for that organization.</p>

                <h3>2. Accounts and access</h3>
                <p>Accounts are issued to named individuals and must not be shared. You are responsible for keeping your credentials confidential and for activity carried out under your account. Report suspected compromise to your administrator immediately.</p>

                <h3>3. Acceptable use</h3>
                <p>You may use the platform only for your organization's own security and compliance program. You must not attempt to access another organization's data, probe or test the platform's security without written authorisation, interfere with its operation, or reverse engineer any part of it.</p>

                <h3>4. Client data</h3>
                <p>Your organization retains ownership of the compliance, risk and evidence records it enters. You are responsible for ensuring you hold the rights to upload that material, including any licensed standards text or restricted information.</p>

                <h3>5. Availability</h3>
                <p>The platform is provided on the basis set out in your organization's agreement. Maintenance windows and service levels are governed by that agreement rather than by these terms.</p>

                <h3>6. Intellectual property</h3>
                <p>CISO.aero, its software, framework mappings and assessment content remain the property of the operator. No licence is granted beyond the right to use the platform for its intended purpose.</p>

                <h3>7. Suspension</h3>
                <p>Access may be suspended where these terms are breached, where an account is compromised, or where required to protect the platform or its other users.</p>

                <h3>8. Liability</h3>
                <p>The platform supports compliance decision-making but does not constitute legal, regulatory or audit advice. Responsibility for regulatory compliance rests with your organization.</p>

                <h3>9. Changes</h3>
                <p>These terms may be updated. Continued use after a change takes effect constitutes acceptance of the revised terms.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
