<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php require Main::lib_path().'/Layout/analytics.php'; ?>
<?php echo Main::head_meta($this->page['name'].' compliance assessment | '.Main::site_name(), $this->page['description'], '/compliance/'.$this->page['slug']); ?>
<?php echo Main::landing_meta('/compliance/'.$this->page['slug'], $this->page['name'].' compliance assessment', $this->page['faq'], array(array('Home', '/'), array('Frameworks', '/compliance'), array($this->page['name'], '/compliance/'.$this->page['slug']))); ?>
<title><?php echo htmlspecialchars($this->page['name'], ENT_QUOTES, 'UTF-8'); ?> compliance assessment | <?php echo Main::site_name(); ?></title>

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
    <img class="land__hero-img" src="/images/cover-airplane.jpg" alt="Commercial airliner" width="1800" height="1200" fetchpriority="high">
    <div class="land__wrap land__page-hero">
        <nav class="land__crumbs" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="/compliance">Frameworks</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?php echo htmlspecialchars($this->page['name'], ENT_QUOTES, 'UTF-8'); ?></span>
        </nav>
        <h1 class="land__title"><?php echo htmlspecialchars($this->page['name'], ENT_QUOTES, 'UTF-8'); ?> compliance assessment</h1>
        <p class="land__lede"><?php echo htmlspecialchars(implode(' ', array_map(function ($part) { return $part['full_name'].'.'; }, $this->page['parts'])), ENT_QUOTES, 'UTF-8'); ?></p>
        <ul class="land__facts">
            <li><strong><?php echo number_format($this->page['requirements']); ?></strong> <?php echo htmlspecialchars($this->page['parts'][0]['control_term'], ENT_QUOTES, 'UTF-8'); ?> to assess</li>
            <li><strong><?php echo count($this->page['related']); ?></strong> mapped <?php echo count($this->page['related']) === 1 ? 'framework' : 'frameworks'; ?></li>
            <li><strong>PDF, Word, Excel</strong> reports</li>
        </ul>
    </div>
</section>

<?php if (count($this->page['applies']) > 0) { ?>
<section class="land__section">
    <div class="land__wrap land__page-section">
        <h2 class="land__h2"><?php echo htmlspecialchars($this->page['name'], ENT_QUOTES, 'UTF-8'); ?> scope and applicability</h2>
        <ul class="land__points">
            <?php foreach ($this->page['applies'] as $applies) { ?>
            <li><?php echo htmlspecialchars($applies, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php } ?>
        </ul>
    </div>
</section>
<?php } ?>

<section class="land__section land__section--tint">
    <div class="land__wrap land__page-section">
        <h2 class="land__h2">How <?php echo htmlspecialchars($this->page['name'], ENT_QUOTES, 'UTF-8'); ?> is structured</h2>
        <?php foreach ($this->page['parts'] as $part) { ?>
        <div class="land__part">
            <h3 class="land__h3"><?php echo htmlspecialchars($part['name'].' ('.$part['version'].')', ENT_QUOTES, 'UTF-8'); ?></h3>
            <p class="land__part-meta"><?php echo number_format($part['controls']); ?> <?php echo htmlspecialchars($part['control_term'], ENT_QUOTES, 'UTF-8'); ?> in <?php echo count($part['families']); ?> <?php echo htmlspecialchars($part['family_term'], ENT_QUOTES, 'UTF-8'); ?>, issued by <?php echo htmlspecialchars($part['issuing_body'], ENT_QUOTES, 'UTF-8'); ?></p>
            <ul class="land__families">
                <?php foreach ($part['families'] as $family) { ?>
                <li><span class="land__family-name"><?php echo htmlspecialchars(($family['code'] !== '' && $family['code'] !== $family['name'] && !preg_match('/^G\d+$/', $family['code']) ? $family['code'].' ' : '').$family['name'], ENT_QUOTES, 'UTF-8'); ?></span><span class="land__family-count"><?php echo $family['count']; ?></span></li>
                <?php } ?>
            </ul>
        </div>
        <?php } ?>
    </div>
</section>

<section class="land__section">
    <div class="land__wrap land__page-section">
        <h2 class="land__h2">How CISO.aero assesses <?php echo htmlspecialchars($this->page['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <ol class="land__steps">
            <li>
                <h3 class="land__h3">Answer, don't write</h3>
                <p>Each requirement is broken into plain-English points the assessor marks met, not met or not applicable. The result for the requirement is calculated from those answers.</p>
            </li>
            <li>
                <h3 class="land__h3">Attach evidence once</h3>
                <p>Policies and exports go into the client's evidence vault and are attached to the requirements they prove<?php echo count($this->page['related']) > 0 ? ', then offered against the mapped controls of related frameworks' : ''; ?>.</p>
            </li>
            <li>
                <h3 class="land__h3">Report and remediate</h3>
                <p>Results, gap analysis, statement of applicability, remediation plan and executive summary, as PDF, Word or Excel.</p>
            </li>
        </ol>
        <p class="land__page-note"><?php echo htmlspecialchars($this->page['parts'][0]['scoring'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</section>

<?php if (count($this->page['related']) > 0) { ?>
<section class="land__section land__section--tint">
    <div class="land__wrap land__page-section">
        <h2 class="land__h2">Frameworks mapped to <?php echo htmlspecialchars($this->page['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <ul class="land__related">
            <?php foreach ($this->page['related'] as $related) { ?>
            <li><a href="/compliance/<?php echo htmlspecialchars($related['slug'], ENT_QUOTES, 'UTF-8'); ?>"><span class="land__related-name"><?php echo htmlspecialchars($related['name'], ENT_QUOTES, 'UTF-8'); ?></span><span class="land__related-meta"><?php echo number_format($related['links']); ?> control links, from <?php echo htmlspecialchars($related['source'], ENT_QUOTES, 'UTF-8'); ?></span></a></li>
            <?php } ?>
        </ul>
    </div>
</section>
<?php } ?>

<?php if (count($this->page['glossary']) > 0) { ?>
<section class="land__section">
    <div class="land__wrap land__page-section">
        <h2 class="land__h2">Key terms</h2>
        <dl class="land__glossary">
            <?php foreach ($this->page['glossary'] as $term => $definition) { ?>
            <div>
                <dt><?php echo htmlspecialchars($term, ENT_QUOTES, 'UTF-8'); ?></dt>
                <dd><?php echo htmlspecialchars($definition, ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <?php } ?>
        </dl>
    </div>
</section>
<?php } ?>

<section class="land__section land__section--tint" id="faq">
    <div class="land__wrap">
        <h2 class="land__h2"><?php echo htmlspecialchars($this->page['name'], ENT_QUOTES, 'UTF-8'); ?> questions</h2>
        <div class="land__faq">
            <?php foreach ($this->page['faq'] as $faq) { ?>
            <div class="land__faq-item">
                <h3 class="land__h3"><?php echo htmlspecialchars($faq[0], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars($faq[1], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <?php } ?>
        </div>
        <?php foreach ($this->page['notices'] as $notice) { ?>
        <p class="land__page-note"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php } ?>
    </div>
</section>

<section class="land__close">
    <div class="land__wrap land__close-inner">
        <h2 class="land__h2">Assess <?php echo htmlspecialchars($this->page['name'], ENT_QUOTES, 'UTF-8'); ?> in CISO.aero</h2>
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
