<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php echo Main::head_meta(); ?>
<title>Page not found |<?php echo Main::site_name(); ?></title>
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
        <p class="errpage__code">404</p>
        <h1 class="page__title">We can&rsquo;t find that page</h1>
        <p class="errpage__body">The link may be out of date, or the record may have been removed.</p>
        <a class="btn btn--primary" href="/">Take me back</a>
    </div>
</div>
</body>
</html>
