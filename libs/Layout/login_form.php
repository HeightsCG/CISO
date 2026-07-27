<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php echo CSRF::meta(); ?>
<title>Sign In | CISO.aero</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css">
<link rel="stylesheet" href="/css/site.css">

<script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
<script src="/js/api.data.js"></script>

<script>
$(document).ready(function () {

    $("#show_forgot").click(function(){

        $("#login_view").prop("hidden", true);
        $("#forgot_view").prop("hidden", false);
        $("#reset_email").focus();

    });

    $("#show_login").click(function(){

        $("#forgot_view").prop("hidden", true);
        $("#login_view").prop("hidden", false);
        $("#u_name").focus();

    });

    $("#do_login").click(function(){

        $("#u_name").removeClass("is-invalid");
        $("#p_word").removeClass("is-invalid");

        var errors = 0;

        if ($("#u_name").val().trim() === "") {
            $("#u_name").addClass("is-invalid");
            errors++;
        }
        
        if ($("#p_word").val().trim() === "") {
            $("#p_word").addClass("is-invalid");
            errors++;
        }

        if (errors > 0) {
            toastr.error("Please fix the errors before continuing.");
            return;
        }

        var obj = {
            u_name: $("#u_name").val().trim(),
            p_word: $("#p_word").val().trim()
        };

        ApiDataSvc.apiCall('post', 'login', obj, function (data) {
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

    $("#do_forgot").click(function(){
        
        $("#reset_email").removeClass("is-invalid");

        var errors = 0;

        if ($("#reset_email").val().trim() === "") {
            $("#reset_email").addClass("is-invalid");
            errors++;
        }

        if (errors > 0) {
            toastr.error("Please fix the errors before continuing.");
            return;
        }

        var obj = {
            reset_email: $("#reset_email").val().trim()
        };

        ApiDataSvc.apiCall('post', 'forgot_password', obj, function (data) {
            var obj = JSON.parse(data);
            if (obj.success) {
                toastr.success(obj.message);    
            } else {
                toastr.error(obj.message);
            }
        });
        
    });

    $('#u_name, #p_word').on('keydown', function (e) {
        if (e.key === 'Enter') { 
            $("#do_login").click(); 
        }
    });

    $('#reset_email').on('keydown', function (e) {
        if (e.key === 'Enter') {
            $("#do_forgot").click();
        }
    });

});
</script>
</head>
<body>
<div class="auth">
<div class="auth__module">

    <aside class="auth__brandbar">
        <div class="auth__lockup">
            <svg class="auth__logo" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                <path d="M8 27 L20.5 5 L27 5 L27 27 Z" fill="currentColor"/>
                <path d="M2 27 L2 22 L15.5 22 L12.5 27 Z" fill="currentColor" opacity="0.5"/>
            </svg>
            <div class="auth__mark"><strong>CISO</strong><span class="auth__mark-tld">.aero</span></div>
        </div>
    </aside>

    <main class="auth__stage">

        <div class="auth__form" id="login_view">
            <h1 class="auth__heading">Sign In</h1>
            <div class="auth__fields">
                <div class="field">
                    <label for="u_name">Username <abbr title="required">*</abbr></label>
                    <input type="text" id="u_name" name="u_name" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                </div>
                <div class="field">
                    <label for="p_word">Password <abbr title="required">*</abbr></label>
                    <input type="password" id="p_word" name="p_word" autocomplete="current-password" required>
                </div>
            </div>
            <div class="auth__submit">
                <button type="button" id="do_login" class="btn btn--primary btn--block">Sign In</button>
            </div>
            <div class="auth__actions">
                <button type="button" id="show_forgot" class="btn btn--tertiary">Forgot your password?</button>
            </div>
        </div>

        <div class="auth__form" id="forgot_view" hidden>
            <h1 class="auth__heading">Reset Password</h1>
            <p class="auth__note">Enter the email address on your account and we will send a link to choose a new password. It expires in one hour.</p>
            <div class="auth__fields">
                <div class="field">
                    <label for="reset_email">Email address <abbr title="required">*</abbr></label>
                    <input type="email" id="reset_email" name="reset_email" autocomplete="email" autocapitalize="none" spellcheck="false" required>
                </div>
            </div>
            <div class="auth__submit">
                <button type="button" id="do_forgot" class="btn btn--primary btn--block">Send Reset Link</button>
            </div>
            <div class="auth__actions">
                <button type="button" id="show_login" class="btn btn--tertiary">Back to Sign In</button>
            </div>
        </div>

    </main>

</div>
</div>
</body>
</html>
