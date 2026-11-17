<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require Main::lib_path().'/Layout/analytics.php'; ?>
<?php echo Main::head_meta('Cybersecurity and compliance frameworks | '.Main::site_name(), 'The '.Main::framework_count().' frameworks CISO.aero assesses: EASA Part-IS, TSA, CMMC, FedRAMP, NIST, ISO/IEC 27001, SOC 2, PCI DSS, HIPAA, GDPR, DORA, NIS2, the EU AI Act and more.', '/compliance'); ?>
<?php echo Main::landing_meta('/compliance', 'Cybersecurity and compliance frameworks | '.Main::site_name(), array(), array(array('Home', '/'), array('Frameworks', '/compliance'))); ?>
<title>Cybersecurity and compliance frameworks | <?php echo Main::site_name(); ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@112..125,500..800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="/css/site.css?v=<?php echo (int) @filemtime(Main::app_path().'/public/css/site.css'); ?>">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="land">

<header class="land__nav is-past-form">
    <div class="land__wrap land__nav-inner">
        <a class="land__brand" href="/"><img src="/images/logo-dark.png" alt="CISO.aero" width="640" height="154"></a>
        <nav class="land__links" aria-label="Sections">
            <a href="/#platform">Platform</a>
            <a href="/compliance">Frameworks</a>
            <a href="/#aviation">Aviation</a>
            <a href="/#faq">FAQ</a>
            <a href="/demo">Pricing</a>
        </nav>
        <a class="land__nav-demo" href="/demo">Book a Demo</a>
        <a class="land__nav-cta" href="/">Sign In</a>
    </div>
</header>

<main>

<section class="land__hero land__hero--page">
    <img class="land__hero-img" src="/images/cover-wing.jpg" alt="Aircraft wing above the clouds" width="1800" height="1201" fetchpriority="high">
    <div class="land__wrap land__page-hero">
        <nav class="land__crumbs" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Frameworks</span>
        </nav>
        <h1 class="land__title">Cybersecurity and compliance frameworks</h1>
        <p class="land__lede">CISO.aero assesses <?php echo Main::framework_count(); ?> frameworks, from the aviation rules of EASA Part-IS and TSA to CMMC, NIST, ISO/IEC 27001, SOC 2, privacy law and AI regulation. Every one is built from its official source.</p>
    </div>
</section>

<section class="land__section">
    <div class="land__wrap land__page-section">
        <?php foreach ($this->catalogue as $group) { ?>
        <div class="land__hub-group">
            <h2 class="land__hub-label"><?php echo htmlspecialchars($group['group'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="land__related">
                <?php foreach ($group['names'] as $name) { ?>
                <li><a href="/compliance/<?php echo htmlspecialchars(FrameworkLibrary::slug($name), ENT_QUOTES, 'UTF-8'); ?>"><span class="land__related-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span><span class="land__related-meta">Compliance assessment</span></a></li>
                <?php } ?>
            </ul>
        </div>
        <?php } ?>
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
