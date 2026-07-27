<?php
class Bootstrap
{

    public function __construct()
    {
        $this->start_app();
    }

    public function start_app()
    {
        date_default_timezone_set('UTC');
        $env = Main::get_environment();
        $config = Main::get_config();
        $domain = $config[$env]['domain'];
        ini_set('session.cookie_domain', '.' . $domain);
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Lax');
        Session::init();
        $c = Main::controller_name();
        $m = Main::method_name();
        if (class_exists($c)) {
            $co = new $c();
            if (method_exists($c, $m)) {
                $co->$m();
            } else {
                Errors::page_not_found();
            }
        } else {
            Errors::page_not_found();
        }
    }
}
