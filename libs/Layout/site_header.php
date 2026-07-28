<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php echo CSRF::meta(); ?>
<title>CISO.aero</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.2.4/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css">
<link rel="stylesheet" href="/css/site.css">

<script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.4/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.4/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
<script src="https://kit.fontawesome.com/5ec0dddd80.js" crossorigin="anonymous"></script>
<script src="/js/api.data.js"></script>
<script src="/js/address.autocomplete.js"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>

<script>
$(document).ready(function () {

    $("#nav_toggle").click(function(){
        $("#shell_nav").toggleClass("is-open");
    });

    $("#nav_backdrop").click(function(){
        $("#shell_nav").removeClass("is-open");
    });

    $("#logout").click(function(){
        window.location.href = '/logout';
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
            <div class="shell__group">
                <p class="shell__group-label">Menu</p>
                <a class="shell__link<?php echo $this->controller == 'index' ? ' is-active' : ''; ?>" href="/">
                    <i class="fa-thin fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a class="shell__link<?php echo $this->controller == 'clients' ? ' is-active' : ''; ?>" href="/clients">
                    <i class="fa-thin fa-shield-halved"></i>
                    <span>Clients</span>
                </a>
                <a class="shell__link<?php echo $this->controller == 'projects' ? ' is-active' : ''; ?>" href="/projects">
                    <i class="fa-thin fa-project-diagram"></i>
                    <span>Projects</span>
                </a>
            </div>
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
                    <span class="shell__avatar"><?php echo htmlspecialchars(strtoupper(substr(Session::get('first_name'), 0, 1) . substr(Session::get('last_name'), 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="shell__user-name"><?php echo htmlspecialchars(Session::get('first_name') . ' ' . Session::get('last_name'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <svg class="shell__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"></path></svg>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shell__user-menu">
                    <li>
                        <a class="dropdown-item" href="/profile">
                            <i class="fa-thin fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="/settings/users">
                            <i class="fa-thin fa-users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="/settings">
                            <i class="fa-thin fa-gear"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button type="button" class="dropdown-item shell__menu-danger" id="logout">
                            <i class="fa-thin fa-right-from-bracket"></i>
                            <span>Logout</span>
                        </button>
                    </li>
                </ul>
            </div>
        </header>
        <main class="shell__workspace">
