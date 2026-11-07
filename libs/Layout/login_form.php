<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require Main::lib_path().'/Layout/analytics.php'; ?>
<?php echo CSRF::meta(); ?>
<?php echo Main::head_meta(Main::site_name().' | Aviation cybersecurity governance and compliance platform'); ?>
<?php echo Main::landing_meta(); ?>
<title><?php echo Main::site_name(); ?> | Aviation cybersecurity governance and compliance platform</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@112..125,500..800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
        set_loading("do_login", false);
        set_loading("do_forgot", false);
    });

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

        set_loading("do_login", true);

        ApiDataSvc.apiCall('post', 'login', obj, function (data) {
            var obj = JSON.parse(data);
            if (obj.success) {
                toastr.success(obj.message);
                set_loading("do_login", true);
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

        set_loading("do_forgot", true);

        ApiDataSvc.apiCall('post', 'forgot_password', obj, function (data) {
            var obj = JSON.parse(data);
            if (obj.success) {
                toastr.success(obj.message);    
                setTimeout(function() {
                    $("#show_login").click();
                }, 2000);
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

    function stick_nav() {
        var card = document.querySelector(".land__card");
        $(".land__nav").toggleClass("is-stuck", window.scrollY > 24);
        $(".land__nav").toggleClass("is-past-form", card.getBoundingClientRect().bottom < 64);
    }

    $(window).on("scroll", stick_nav);
    stick_nav();

    $('[data-action="sign-in"]').click(function () {
        $("#forgot_view").prop("hidden", true);
        $("#login_view").prop("hidden", false);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        $("#u_name").focus({ preventScroll: true });
    });

    $('#reset_email').on('keydown', function (e) {
        if (e.key === 'Enter') {
            $("#do_forgot").click();
        }
    });

});
</script>
</head>
<body class="land">

<header class="land__nav">
    <div class="land__wrap land__nav-inner">
        <a class="land__brand" href="/"><img src="/images/logo-dark.png" alt="CISO.aero" width="640" height="154"></a>
        <nav class="land__links" aria-label="Sections">
            <a href="#platform">Platform</a>
            <a href="#frameworks">Frameworks</a>
            <a href="#aviation">Aviation</a>
            <a href="#security">Security</a>
            <a href="/demo">Pricing</a>
        </nav>
        <a class="land__nav-demo" href="/demo">Book a Demo</a>
        <button type="button" class="land__nav-cta" data-action="sign-in">Sign In</button>
    </div>
</header>

<main>

<section class="land__hero">
    <img class="land__hero-img" src="/images/cover-wing.jpg" alt="Aircraft wing above the clouds" width="1800" height="1201" fetchpriority="high">
    <div class="land__wrap land__hero-inner">
        <div class="land__hero-copy">
            <h1 class="land__title">Cybersecurity governance for aviation.</h1>
            <p class="land__lede">Assess airlines, airports, MROs and their suppliers against EASA Part-IS, TSA, CMMC, NIST and more. Collect evidence once, map it across frameworks, and hand leadership a report they can act on.</p>
            <ul class="land__facts">
                <li><strong>19</strong> frameworks ready to assess</li>
                <li><strong>PDF, Word, Excel</strong> reports in one click</li>
                <li><strong>Client portal</strong> for the organizations you serve</li>
            </ul>
        </div>

        <div class="land__card">
            <div class="land__form" id="login_view">
                <h2 class="land__form-title">Sign in</h2>
                <div class="land__field">
                    <label for="u_name">Username</label>
                    <input type="text" id="u_name" class="form-control" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                </div>
                <div class="land__field">
                    <label for="p_word">Password</label>
                    <input type="password" id="p_word" class="form-control" autocomplete="current-password" required>
                </div>
                <button type="button" id="do_login" class="btn btn--primary btn--block land__submit">Sign In</button>
                <button type="button" id="show_forgot" class="land__text-btn">Forgot Your Password?</button>
                <p class="land__card-foot">New to CISO.aero? <a href="/demo">Book a Demo</a></p>
            </div>

            <div class="land__form" id="forgot_view" hidden>
                <h2 class="land__form-title">Reset your password</h2>
                <p class="land__form-note">We will email you a link to choose a new password. It expires in one hour.</p>
                <div class="land__field">
                    <label for="reset_email">Email address</label>
                    <input type="email" id="reset_email" class="form-control" autocomplete="email" autocapitalize="none" spellcheck="false" required>
                </div>
                <button type="button" id="do_forgot" class="btn btn--primary btn--block land__submit">Send Reset Link</button>
                <button type="button" id="show_login" class="land__text-btn">Back to Sign In</button>
            </div>
        </div>
    </div>
</section>

<section class="land__band" id="frameworks">
    <div class="land__wrap">
        <h2 class="land__band-title">Built on the frameworks aviation answers to</h2>
        <ul class="land__marks">
            <li>EASA Part-IS</li>
            <li>TSA Aviation Cybersecurity</li>
            <li>CMMC 2.0</li>
            <li>NIST CSF 2.0</li>
            <li>NIST SP 800-171</li>
            <li>NIST SP 800-53</li>
            <li>ISO/IEC 27001</li>
            <li>SOC 2</li>
            <li>PCI DSS 4.0.1</li>
            <li>FedRAMP</li>
            <li>HIPAA Security Rule</li>
            <li>NYDFS 500</li>
            <li>FTC Safeguards Rule</li>
        </ul>
    </div>
</section>

<section class="land__section" id="platform">
    <div class="land__wrap">
        <h2 class="land__h2">One platform from first interview to final report</h2>

        <div class="land__feature">
            <div class="land__feature-copy">
                <h3 class="land__h3">Assess by answering, not by writing</h3>
                <p>Every requirement is broken into plain-English points the assessor marks met, not met or not applicable. The result for each control is worked out from those answers, so scores are consistent from one assessor to the next.</p>
            </div>
            <div class="land__mock" aria-hidden="true">
                <div class="mock__head"><span class="mock__id">CC1.1</span><span class="mock__title">Integrity and Ethical Values</span></div>
                <div class="mock__row is-met"><span>Leadership visibly backs ethical and security expectations.</span><span class="mock__pill">Met</span></div>
                <div class="mock__row is-met"><span>A written code of conduct is shared with staff and partners.</span><span class="mock__pill">Met</span></div>
                <div class="mock__row is-gap"><span>Staff acknowledge the code of conduct each year.</span><span class="mock__pill">Not met</span></div>
                <div class="mock__row"><span>Breaches of conduct are handled consistently.</span><span class="mock__pill is-empty">Open</span></div>
            </div>
        </div>

        <div class="land__feature land__feature--flip">
            <div class="land__feature-copy">
                <h3 class="land__h3">Evidence once, credited everywhere</h3>
                <p>Upload a policy or a configuration export to the client's evidence vault, attach it to the controls it proves, and reuse it across every framework the client is held to. Controls are mapped between frameworks, so work done for one assessment carries into the next.</p>
            </div>
            <div class="land__mock" aria-hidden="true">
                <div class="mock__file"><span class="mock__file-name">Information Security Policy v4.pdf</span><span class="mock__tags"><span>CC1.1</span><span>IS.I.OR.200</span><span>3.12.1</span></span></div>
                <div class="mock__file"><span class="mock__file-name">MFA enforcement export.xlsx</span><span class="mock__tags"><span>CC6.1</span><span>IA-2</span><span>TSA-ACC-01</span></span></div>
                <div class="mock__file"><span class="mock__file-name">Incident response plan.docx</span><span class="mock__tags"><span>CC7.4</span><span>IS.I.OR.220</span></span></div>
            </div>
        </div>

        <div class="land__feature">
            <div class="land__feature-copy">
                <h3 class="land__h3">Reports leadership will read</h3>
                <p>Results, gap analysis, statement of applicability, remediation plan and an executive summary, generated from the assessment as it stands. Download them as PDF, Word or Excel and send them straight to the board.</p>
            </div>
            <div class="land__mock" aria-hidden="true">
                <div class="mock__report"><span>Executive Summary</span><span class="mock__formats"><span>PDF</span><span>Word</span></span></div>
                <div class="mock__report"><span>Gap Analysis</span><span class="mock__formats"><span>PDF</span><span>Word</span><span>Excel</span></span></div>
                <div class="mock__report"><span>Statement of Applicability</span><span class="mock__formats"><span>PDF</span><span>Word</span><span>Excel</span></span></div>
                <div class="mock__report"><span>Remediation Plan</span><span class="mock__formats"><span>PDF</span><span>Word</span><span>Excel</span></span></div>
            </div>
        </div>
    </div>
</section>

<section class="land__photo" id="aviation">
    <img class="land__photo-img" src="/images/cover-airplane.jpg" alt="Commercial airliner" width="1800" height="1200" loading="lazy" decoding="async">
    <div class="land__wrap land__photo-inner">
        <div class="land__photo-copy">
            <h2 class="land__h2">Made for the flight line, not just the server room</h2>
            <p>Operators, design and production organisations, airports and maintenance providers face EASA Part-IS in Europe and TSA cybersecurity requirements in the United States, on top of the contracts and certifications their customers demand. CISO.aero holds all of them in one place, with aviation terms and aviation examples throughout.</p>
        </div>
    </div>
</section>

<section class="land__section land__section--tint">
    <div class="land__wrap">
        <h2 class="land__h2">How an engagement runs</h2>
        <ol class="land__steps">
            <li>
                <h3 class="land__h3">Set up the client</h3>
                <p>Add the client, choose the frameworks that apply, and invite the client's team to their own portal.</p>
            </li>
            <li>
                <h3 class="land__h3">Assess and collect evidence</h3>
                <p>Work through each control, mark its points, and attach evidence from the vault as you go.</p>
            </li>
            <li>
                <h3 class="land__h3">Report and follow through</h3>
                <p>Issue the reports, agree remediation with leadership, and reassess against the baseline.</p>
            </li>
        </ol>
    </div>
</section>

<section class="land__photo land__photo--dark" id="security">
    <img class="land__photo-img" src="/images/cover-engine.jpg" alt="Jet engine" width="1800" height="1026" loading="lazy" decoding="async">
    <div class="land__wrap land__photo-inner">
        <div class="land__photo-copy">
            <h2 class="land__h2">Your clients' findings stay theirs</h2>
            <ul class="land__checks">
                <li>Access restricted by role, with each client seeing only their own portal</li>
                <li>Account lockout and password-age policies set per organization</li>
                <li>Passwords stored only as one-way hashes</li>
                <li>Encrypted connections and logged administrative access</li>
            </ul>
        </div>
    </div>
</section>

<section class="land__close">
    <div class="land__wrap land__close-inner">
        <h2 class="land__h2">See it with your own frameworks</h2>
        <div class="land__close-actions">
            <a class="btn btn--secondary land__close-btn" href="/demo/pricing">Request Pricing</a>
            <a class="btn btn--primary land__close-btn" href="/demo">Book a Demo</a>
        </div>
    </div>
</section>

</main>

<?php require Main::lib_path().'/Layout/land_footer.php'; ?>

</body>
</html>
