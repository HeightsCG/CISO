<?php
    $nav_sections = array(
        array(
            'label' => 'Portfolio',
            'items' => array(
                array('controller' => 'index',       'url' => '/',             'label' => 'Dashboard', 'icon' => 'M3 12h6V3H3v9zm0 9h6v-7H3v7zm8 0h10v-9H11v9zm0-18v7h10V3H11z'),
                array('controller' => 'clients',     'url' => '/clients',      'label' => 'Clients',   'icon' => 'M17 20v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm14 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'),
                array('controller' => 'tasks',       'url' => '/tasks',        'label' => 'Tasks',     'icon' => 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11')
            )
        ),
        array(
            'label' => 'Compliance',
            'items' => array(
                array('controller' => 'frameworks',  'url' => '/frameworks',   'label' => 'Frameworks',  'icon' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z'),
                array('controller' => 'assessments', 'url' => '/assessments',  'label' => 'Assessments', 'icon' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM14 2v6h6M16 13H8M16 17H8M10 9H8'),
                array('controller' => 'evidence',    'url' => '/evidence',     'label' => 'Evidence',    'icon' => 'M21 8v13H3V8M1 3h22v5H1zM10 12h4')
            )
        ),
        array(
            'label' => 'Output',
            'items' => array(
                array('controller' => 'reports',     'url' => '/reports',      'label' => 'Reports', 'icon' => 'M3 3v18h18M7 16l4-6 4 3 5-8')
            )
        ),
        array(
            'label' => 'Administration',
            'items' => array(
                array('controller' => 'settings',    'url' => '/settings',     'label' => 'Settings', 'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z')
            )
        )
    );

    $first_name = (string) Session::get('first_name');
    $last_name  = (string) Session::get('last_name');
    $full_name  = trim($first_name . ' ' . $last_name);

    if ($full_name === '') {
        $full_name = (string) Session::get('u_name');
    }

    $initials = strtoupper(substr($first_name !== '' ? $first_name : $full_name, 0, 1) . substr($last_name, 0, 1));
    $page_title = isset($this->page_title) ? $this->page_title : '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php echo CSRF::meta(); ?>
<title><?php echo ($page_title !== '' ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') . ' | ' : ''); ?>CISO.aero</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css">
<link rel="stylesheet" href="/css/site.css">

<script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
<script src="/js/api.data.js"></script>
<script src="/js/site.js"></script>

<script>
$(document).ready(function () {

    $("#nav_toggle").click(function(){
        $("#shell_nav").toggleClass("is-open");
    });

    $("#nav_backdrop").click(function(){
        $("#shell_nav").removeClass("is-open");
    });

    $("#do_logout").click(function(){
        $("#do_logout").addClass("is-loading").prop("disabled", true);
        ApiDataSvc.apiCall('post', 'logout', {}, function () {
            window.location.href = '/';
        });
    });

});
</script>
</head>
<body>

<div class="shell">

    <aside class="shell__nav" id="shell_nav">

        <div class="shell__brand">
            <img class="shell__logo" src="/images/logo-dark.png" alt="CISO.aero">
        </div>

        <nav class="shell__menu" aria-label="Primary">
<?php foreach ($nav_sections as $section) { ?>
<?php
            $visible = array();
            foreach ($section['items'] as $item) {
                if (class_exists(ucfirst($item['controller']) . 'Controller')) {
                    $visible[] = $item;
                }
            }
            if (count($visible) === 0) {
                continue;
            }
?>
            <div class="shell__group">
                <p class="shell__group-label"><?php echo $section['label']; ?></p>
<?php foreach ($visible as $item) { ?>
                <a class="shell__link<?php echo ($this->controller === $item['controller'] ? ' is-active' : ''); ?>" href="<?php echo $item['url']; ?>"<?php echo ($this->controller === $item['controller'] ? ' aria-current="page"' : ''); ?>>
                    <svg class="shell__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?php echo $item['icon']; ?>"></path></svg>
                    <span><?php echo $item['label']; ?></span>
                </a>
<?php } ?>
            </div>
<?php } ?>
        </nav>

    </aside>

    <div class="shell__backdrop" id="nav_backdrop"></div>

    <div class="shell__main">

        <header class="shell__topbar">

            <button type="button" class="shell__nav-toggle" id="nav_toggle" aria-label="Toggle navigation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"></path></svg>
            </button>

            <div class="shell__topbar-spacer"></div>

            <div class="shell__user dropdown">
                <button type="button" class="shell__user-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="shell__avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="shell__user-name"><?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?></span>
                    <svg class="shell__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"></path></svg>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shell__user-menu">
                    <li class="shell__user-meta">
                        <span class="shell__user-meta-name"><?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="shell__user-meta-email"><?php echo htmlspecialchars((string) Session::get('user_email'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button type="button" class="dropdown-item" id="do_logout">Sign out</button></li>
                </ul>
            </div>

        </header>

        <main class="shell__workspace">
