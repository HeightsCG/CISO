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

        $co->$m();
    }

    /**
     * Errors are never rendered to the client. A single PHP notice echoed ahead of
     * json_encode corrupts the response body and breaks every JSON.parse in
     * api.data.js, so display is off in all environments and everything is logged.
     */
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

    /**
     * Company-wide idle timeout. The limit is written to the session at login so
     * enforcement costs no query per request; a value of 0 disables it.
     */
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

            // An API caller must get JSON back. Redirecting here returns a 302 with an
            // empty body, which every JSON.parse in the browser then chokes on.
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
        // Over plain HTTP a secure cookie is discarded by the browser and the session
        // can never persist, so this tracks the actual request scheme instead of being
        // hardcoded either way.
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

}
