<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php echo CSRF::meta(); ?>
<title>Change Password | <?php echo Main::site_name(); ?></title>

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

    $("#do_change").click(function(){

        $("#pw1").removeClass("is-invalid");
        $("#pw2").removeClass("is-invalid");

        if ($("#pw1").val().trim() === "") {
            $("#pw1").addClass("is-invalid");
            return;
        }

        if ($("#pw2").val().trim() === "") {
            $("#pw2").addClass("is-invalid");
            return;
        }

        var obj = {
            pw1: $("#pw1").val().trim(),
            pw2: $("#pw2").val().trim()
        };

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

    <aside class="auth__brandbar">
        <div class="auth__mark"><strong>CISO</strong>.aero</div>
        <p class="auth__tagline">Security and compliance management for aviation organisations.</p>
    </aside>

    <main class="auth__stage">

    <div class="auth__form" id="force_reset_view">
        <h1 class="auth__heading">Change Your Password</h1>
        <p class="auth__note">Your password must be changed before you can continue.</p>
        <div class="auth__fields">
            <div class="field">
                <label for="pw1">New password <abbr title="required">*</abbr></label>
                <input type="password" id="pw1" name="pw1" autocomplete="new-password" required>
                <span class="field__help">At least 12 characters, with an uppercase letter, a lowercase letter and a number.</span>
            </div>
            <div class="field">
                <label for="pw2">Confirm new password <abbr title="required">*</abbr></label>
                <input type="password" id="pw2" name="pw2" autocomplete="new-password" required>
            </div>
        </div>
        <div class="auth__submit">
            <button type="button" id="do_change" class="btn btn--primary btn--block">Change Password</button>
        </div>
    </div>

    </main>

</div>
</div>
</body>
</html>
