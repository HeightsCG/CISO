<?php
class NotificationsModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function send_password_reset($user_email, $first_name, $raw_token)
    {
        $env = Main::get_environment();
        $config = Main::get_config();

        $variables = array(
            'site_name' => Main::site_name(),
            'first_name' => htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'),
            'reset_url' => rtrim($config[$env]['app_url'], '/') . '/account/reset/token/' . $raw_token,
            'year' => date('Y')
        );

        $to_array = array(
            array(
                'email' => $user_email,
                'name' => $first_name
            )
        );

        $notifications = new Notifications();
        $message = $notifications->clean_template('password_reset', $variables);

        return $notifications->send_email($to_array, 0, 'Reset your ' . Main::site_name() . ' password', $message);
    }

}
