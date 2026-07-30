<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Request could not be completed | <?php echo Main::site_name(); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="/css/site.css?v=<?php echo (int) @filemtime(Main::app_path().'/public/css/site.css'); ?>">
</head>
<body>
<div class="errpage">
    <div class="panel errpage__card">
        <img class="errpage__logo" src="/images/logo-dark.png" alt="<?php echo Main::site_name(); ?>">
        <p class="errpage__code">400</p>
        <h1 class="page__title">That request couldn&rsquo;t be completed</h1>
        <p class="errpage__body">This usually means the page was left open too long. Reload and try again.</p>
        <a class="btn btn--primary" href="/">Reload</a>
    </div>
</div>
</body>
</html>
