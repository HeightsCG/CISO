<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require Main::lib_path().'/Layout/analytics.php'; ?>
<?php echo CSRF::meta(); ?>
<?php echo Main::head_meta('Book a Demo or Request Pricing | '.Main::site_name(), 'See CISO.aero, the aviation cybersecurity GRC platform, in a live demo or request pricing for your team. EASA Part-IS, TSA, CMMC, NIST and more.', '/demo'); ?>
<?php echo Main::landing_meta('/demo', 'Book a demo or request pricing | '.Main::site_name()); ?>
<title>Book a demo or request pricing | <?php echo Main::site_name(); ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@112..125,500..800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css">
<link rel="stylesheet" href="/css/site.css?v=<?php echo (int) @filemtime(Main::app_path().'/public/css/site.css'); ?>">

<script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
<script src="/js/api.data.js?v=<?php echo (int) @filemtime(Main::app_path().'/public/js/api.data.js'); ?>"></script>

<script>
$(document).ready(function () {

    var lead_type = '<?php echo $this->lead_type === 'pricing' ? 'pricing' : 'demo'; ?>';

    function set_type(type) {
        lead_type = type;
        $(".land__seg-btn").each(function () {
            var on = $(this).data("type") === type;
            $(this).toggleClass("is-active", on).attr("aria-pressed", on ? "true" : "false");
        });
        $("#do_request").text(type === "pricing" ? "Request Pricing" : "Book a Demo");
    }

    $(".land__seg-btn").click(function () {
        set_type($(this).data("type"));
    });

    $("#lead_phone").on("input", function () {
        var cleaned = this.value.replace(/[^0-9+()\-. ]/g, "");
        if (cleaned !== this.value) {
            this.value = cleaned;
        }
    });

    $("#do_request").click(function () {

        var required = ["first_name", "last_name", "lead_email", "company_name", "organization_type"];
        var errors = 0;

        $.each(required, function (i, id) {
            $("#" + id).removeClass("is-invalid");
            if ($("#" + id).val().trim() === "") {
                $("#" + id).addClass("is-invalid");
                errors++;
            }
        });

        if ($("#lead_email").val().trim() !== "" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($("#lead_email").val().trim())) {
            $("#lead_email").addClass("is-invalid");
            errors++;
        }

        if (errors > 0) {
            toastr.error("Please fix the errors before continuing.");
            return;
        }

        var obj = {
            lead_type: lead_type,
            first_name: $("#first_name").val().trim(),
            last_name: $("#last_name").val().trim(),
            lead_email: $("#lead_email").val().trim(),
            lead_phone: $("#lead_phone").val().trim(),
            company_name: $("#company_name").val().trim(),
            organization_type: $("#organization_type").val(),
            lead_message: $("#lead_message").val().trim(),
            company_website: $("#company_website").val()
        };

        $("#do_request").addClass("is-loading").prop("disabled", true);

        ApiDataSvc.apiCall('post', 'request_demo', obj, function (data) {
            var obj = JSON.parse(data);
            $("#do_request").removeClass("is-loading").prop("disabled", false);
            if (obj.success) {
                $("#request_view").prop("hidden", true);
                $("#done_view").prop("hidden", false);
            } else {
                toastr.error(obj.message);
            }
        });

    });

    function stick_nav() {
        $(".land__nav").toggleClass("is-stuck", window.scrollY > 24);
    }

    $(window).on("scroll", stick_nav);
    stick_nav();

    set_type(lead_type);

});
</script>
</head>
<body class="land">

<header class="land__nav is-past-form">
    <div class="land__wrap land__nav-inner">
        <a class="land__brand" href="/"><img src="/images/logo-dark.png" alt="CISO.aero" width="640" height="154"></a>
        <nav class="land__links" aria-label="Sections">
            <a href="/#platform">Platform</a>
            <a href="/#frameworks">Frameworks</a>
            <a href="/#aviation">Aviation</a>
            <a href="/#security">Security</a>
        </nav>
        <a class="land__nav-cta" href="/">Sign In</a>
    </div>
</header>

<main>

<section class="land__hero land__hero--demo">
    <img class="land__hero-img" src="/images/cover-engine.jpg" alt="Jet engine" width="1800" height="1026" fetchpriority="high">
    <div class="land__wrap land__hero-inner land__hero-inner--wide">
        <div class="land__hero-copy">
            <h1 class="land__title">See CISO.aero in action.</h1>
            <p class="land__lede">Tell us about your organization and we will walk you through the platform using the frameworks you work with, or send pricing for your team.</p>
            <ul class="land__checks land__demo-checks">
                <li>A live walkthrough of assessments, evidence and reporting</li>
                <li>Pricing based on your team and the clients you serve</li>
                <li>Answers on EASA Part-IS, TSA, CMMC and the frameworks you are held to</li>
            </ul>
        </div>

        <div class="land__card">
            <div class="land__form" id="request_view">
                <div class="land__seg" role="group" aria-label="Request type">
                    <button type="button" class="land__seg-btn" data-type="demo" aria-pressed="false">Book a Demo</button>
                    <button type="button" class="land__seg-btn" data-type="pricing" aria-pressed="false">Request Pricing</button>
                </div>
                <div class="row gx-3">
                    <div class="col-sm-6 form-group land__field">
                        <label for="first_name">First name <abbr title="required">*</abbr></label>
                        <input type="text" id="first_name" class="form-control" autocomplete="given-name" maxlength="100" required>
                    </div>
                    <div class="col-sm-6 form-group land__field">
                        <label for="last_name">Last name <abbr title="required">*</abbr></label>
                        <input type="text" id="last_name" class="form-control" autocomplete="family-name" maxlength="100" required>
                    </div>
                </div>
                <div class="row gx-3">
                    <div class="col-sm-6 form-group land__field">
                        <label for="lead_email">Work email <abbr title="required">*</abbr></label>
                        <input type="email" id="lead_email" class="form-control" autocomplete="email" autocapitalize="none" spellcheck="false" maxlength="255" required>
                    </div>
                    <div class="col-sm-6 form-group land__field">
                        <label for="lead_phone">Phone</label>
                        <input type="tel" id="lead_phone" class="form-control" autocomplete="tel" inputmode="tel" maxlength="40">
                    </div>
                </div>
                <div class="row gx-3">
                    <div class="col-sm-6 form-group land__field">
                        <label for="company_name">Company <abbr title="required">*</abbr></label>
                        <input type="text" id="company_name" class="form-control" autocomplete="organization" maxlength="200" required>
                    </div>
                    <div class="col-sm-6 form-group land__field">
                        <label for="organization_type">Organization type <abbr title="required">*</abbr></label>
                        <select id="organization_type" class="form-control form-select" required>
                            <option value="">Choose one</option>
                            <?php foreach ($this->organization_types as $organization_type) { ?>
                            <option value="<?php echo htmlspecialchars($organization_type, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($organization_type, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="form-group land__field">
                    <label for="lead_message">What would you like to cover?</label>
                    <textarea id="lead_message" class="form-control" rows="3" maxlength="4000"></textarea>
                </div>
                <div class="land__trap" aria-hidden="true">
                    <label for="company_website">Company website</label>
                    <input type="text" id="company_website" tabindex="-1" autocomplete="off">
                </div>
                <button type="button" id="do_request" class="btn btn--primary btn--block land__submit">Book a Demo</button>
            </div>

            <div class="land__form land__done" id="done_view" hidden>
                <h2 class="land__form-title">Thanks, we have your request.</h2>
                <p class="land__form-note">Someone from our team will reply to the email address you gave us.</p>
                <a class="btn btn--primary btn--block land__submit" href="/">Back to CISO.aero</a>
            </div>
        </div>
    </div>
</section>

</main>

<?php require Main::lib_path().'/Layout/land_footer.php'; ?>

</body>
</html>
