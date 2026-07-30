<?php
class Bootstrap
{

    public function __construct()
    {
        $this->start_app();
    }

    public function start_app()
    {
        $env    = Main::get_environment();
        $config = Main::get_config();

        $this->configure_errors();
        date_default_timezone_set($config['global']['timezone'] ?? 'UTC');
        $this->configure_session($config, $env);

        Session::init();
        $this->enforce_idle_timeout();

        $c = Main::controller_name();
        $m = Main::method_name();

        if (!class_exists($c)) {
            Errors::page_not_found();
            return;
        }

        $co = new $c();

        if (!method_exists($co, $m)) {
            Errors::page_not_found();
            return;
        }

        if ($this->is_post() && !$this->is_csrf_exempt($co, $m) && !CSRF::validate()) {
            Errors::bad_request('CSRF validation failed for '.$c.'::'.$m);
            return;
        }

        $this->enforce_portal_scope($c, $m);

        $co->$m();
    }

    private function configure_errors(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_exception_handler(function (\Throwable $e) {
            error_log('[uncaught] '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo 'An unexpected error occurred.';
        });
    }

    private function enforce_idle_timeout(): void
    {
        if (empty(Session::get('user_id'))) {
            return;
        }

        $minutes = (int) Session::get('session_timeout_minutes');

        if ($minutes <= 0) {
            Session::set('last_activity', time());
            return;
        }

        $last_activity = (int) Session::get('last_activity');

        if ($last_activity > 0 && (time() - $last_activity) > ($minutes * 60)) {

            Session::destroy();

            if (strtolower(Main::controller_name()) === 'apicontroller') {

                $response = array(
                    'success' => false,
                    'message' => 'Your session has expired. Sign in again.'
                );

                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }

            header('Location: /');
            exit;
        }

        Session::set('last_activity', time());
    }

    private function configure_session(array $config, string $env): void
    {
        $domain = $config[$env]['domain'] ?? '';
        if ($domain !== '') {
            ini_set('session.cookie_domain', '.'.$domain);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $this->is_https() ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
    }

    private function is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        return false;
    }

    private function is_post(): bool
    {
        return isset($_SERVER['REQUEST_METHOD'])
            && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST';
    }

    private function is_csrf_exempt($controller, string $method): bool
    {
        if (!isset($controller->csrfExempt) || !is_array($controller->csrfExempt)) {
            return false;
        }
        return in_array($method, $controller->csrfExempt, true);
    }

    /**
     * A client reaches the portal, their own profile, signing in and out, and any
     * api action named portal_*. The prefix is the rule, so a portal feature added
     * later needs no list kept in step with it. Refusals exit, or the action would
     * still run and append its payload to the error page.
     */
    private function enforce_portal_scope(string $controller_name, string $method): void
    {
        if (Session::get('user_type') !== 'portal') {
            return;
        }

        $name = strtolower($controller_name);

        if (in_array($name, array('portalcontroller', 'profilecontroller', 'accountcontroller', 'logoutcontroller'), true)) {
            return;
        }

        if ($name === 'apicontroller' && strpos($method, 'portal_') === 0) {
            return;
        }

        /**
         * change_password cannot take the prefix: force_reset.php posts to it by
         * that name, and an invited client arrives with reset_pw set, so refusing
         * it would leave them stuck on the forced change with no way through. It
         * only ever acts on Session user_id, so it cannot reach another account.
         */

        if ($name === 'apicontroller' && $method === 'change_passwordAction') {
            return;
        }

        if ($name === 'indexcontroller' && !$this->is_post()) {
            header('Location: /portal');
            exit;
        }

        Errors::access_denied();
        exit;
    }

}
