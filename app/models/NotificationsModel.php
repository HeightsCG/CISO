<?php
class NotificationsModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function app_url()
    {
        $env = Main::get_environment();
        $config = Main::get_config();
        return rtrim($config[$env]['app_url'], '/');
    }

    public function build_email($template_name, $subject, $preheader, $footnote, $variables)
    {
        $notifications = new Notifications();

        $content = $notifications->clean_template($template_name, $variables);

        if ($content === '') {
            error_log('[notifications] template not found: ' . $template_name);
            return '';
        }

        $shell = array(
            'content' => $content,
            'subject' => $subject,
            'preheader' => $preheader,
            'footnote' => $footnote,
            'site_name' => Main::site_name(),
            'logo_url' => $this->app_url() . '/images/logo-light.png',
            'year' => date('Y')
        );

        return $notifications->clean_template('master', $shell);
    }

    public function send_password_reset($user_email, $first_name, $raw_token)
    {
        $subject = 'Reset your ' . Main::site_name() . ' password';

        $variables = array(
            'first_name' => htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'),
            'reset_url' => $this->app_url() . '/account/reset/token/' . $raw_token
        );

        $message = $this->build_email(
            'password_reset',
            $subject,
            'Use the link inside to choose a new password. It expires in 60 minutes.',
            'Did not request this? No action is needed and your password stays the same. If you were not expecting it, tell your administrator.',
            $variables
        );

        if ($message === '') {
            return false;
        }

        $to_array = array(
            array(
                'email' => $user_email,
                'name' => $first_name
            )
        );

        return $notifications = (new Notifications())->send_email($to_array, 0, $subject, $message);
    }

}
