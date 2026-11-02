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
        $this->revalidate_session();
        $this->enforce_forced_reset();

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
            Errors::bad_request('CSRF validation failed for '.$c.'::'.$m.$this->csrf_failure_context());
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

            /* API callers read JSON, so a crash answers in the same shape as a
               refusal and the screen can show a message instead of failing
               silently. The details stay in the log. */
            if (strtolower(Main::controller_name()) === 'apicontroller') {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(array(
                    'success' => false,
                    'message' => 'Something went wrong on the server, so that was not saved. The error has been logged.'
                ));
                return;
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
            $this->end_session('Your session has expired. Sign in again.');
        }

        Session::set('last_activity', time());
    }

    /**
     * The session was filled from the user row at sign-in. Everything access
     * control reads from it is re-read here, so a user who is deleted, disabled,
     * demoted or moved loses the old access on their next request rather than
     * when the session happens to end.
     */
    private function revalidate_session(): void
    {
        $user_id = (int) Session::get('user_id');

        if ($user_id <= 0) {
            return;
        }

        $users_model = new UsersModel();
        $rows = $users_model->get_session_state($user_id);

        if (!is_array($rows) || count($rows) !== 1) {
            $this->end_session('Your session has ended. Sign in again.');
        }

        $user = $rows[0];

        $ended = $user['user_status'] !== 'Active'
            || (int) $user['company_deleted'] === 1
            || ($user['user_type'] === 'portal' && (empty($user['client_id']) || $user['client_deleted'] === null || (int) $user['client_deleted'] === 1));

        if ($ended) {
            $this->end_session('Your session has ended. Sign in again.');
        }

        $fields = array(
            'company_id',
            'role_id',
            'user_type',
            'client_id',
            'global_admin',
            'user_status',
            'reset_pw'
        );

        foreach ($fields as $field) {
            Session::set($field, $user[$field]);
        }
    }

    /**
     * A forced password change is shown by the layout, but the API answers
     * without one, so until the password is changed the only API action left
     * open is the change itself.
     */
    private function enforce_forced_reset(): void
    {
        if ((int) Session::get('reset_pw') !== 1) {
            return;
        }

        if (strtolower(Main::controller_name()) !== 'apicontroller' || Main::method_name() === 'change_passwordAction') {
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'message' => 'Change your password to continue.'
        ));
        exit;
    }

    private function end_session(string $message): void
    {
        Session::destroy();

        if (strtolower(Main::controller_name()) === 'apicontroller') {

            $response = array(
                'success' => false,
                'message' => $message
            );

            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        header('Location: /');
        exit;
    }

    private function configure_session(array $config, string $env): void
    {
        /**
         * Its own cookie name, because the domain below is the parent and the
         * default PHPSESSID is shared with every other app on a sibling subdomain.
         * When one of those writes the cookie, this app is handed a session id it
         * never issued, use_strict_mode discards it, and the CSRF token minted for
         * the page that is being submitted no longer exists.
         */
        session_name('CISOSESSID');

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

    /**
     * A CSRF failure has two unrelated causes: the session did not survive from
     * the page that issued the token to the request that spends it, or it did and
     * the tokens differ. Nothing in the failure itself says which, so the facts
     * that separate them go in the log line. No token value is ever written.
     *
     * cookie_copies counts the raw header rather than $_COOKIE, which collapses
     * duplicates - two session cookies at different domain scopes is the usual
     * reason a token issued on one request is unreadable on the next.
     */
    private function csrf_failure_context(): string
    {
        $token = Session::get(CSRF::SESSION_KEY);
        $name = session_name();

        if (!empty($_POST[CSRF::FIELD])) {
            $sent = 'post';
        } elseif (!empty($_SERVER[CSRF::HEADER])) {
            $sent = 'header';
        } else {
            $sent = 'none';
        }

        return ' [session_token='.(is_string($token) && $token !== '' ? 'present' : 'missing')
            .' request_token='.$sent
            .' cookie='.(isset($_COOKIE[$name]) ? 'sent' : 'absent')
            .' cookie_copies='.substr_count((string) ($_SERVER['HTTP_COOKIE'] ?? ''), $name.'=')
            .' session_files='.(is_writable(session_save_path() === '' ? sys_get_temp_dir() : session_save_path()) ? 'writable' : 'unwritable')
            .' https='.($this->is_https() ? '1' : '0')
            .' host='.preg_replace('/[^A-Za-z0-9\.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''))
            .']';
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

        /* The assistant's actions are shared with staff and scope themselves to
           the caller's client inside AiAssistant. */
        if ($name === 'apicontroller' && strpos($method, 'ai_') === 0) {
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
