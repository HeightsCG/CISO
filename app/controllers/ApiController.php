<?php
class ApiController extends Controller {

    public $protected = 0;

    public function __construct(){
        parent::__construct();
    }

    public function loginAction(){

        $response = array('success' => false, 'message' => 'Something went wrong');

        if (empty($this->post['u_name'])) {
            $response['message'] = 'Username is required';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['p_word'])) {
            $response['message'] = 'Password is required';
            echo json_encode($response);
            exit;
        }

        $users = new UsersModel();
        $rows = $users->get_user_by_username($this->post['u_name']);

        if (empty($rows)) {
            $response['message'] = 'Username or password is incorrect';
            echo json_encode($response);
            exit;
        }

        $user = $rows[0];

        if (!password_verify($this->post['p_word'], $user['p_word'])) {
            $response['message'] = 'Username or password is incorrect';
            echo json_encode($response);
            exit;
        }

        session_regenerate_id(true);

        unset($user['p_word']);
        unset($user['reset_token']);
        unset($user['reset_token_expires']);

        foreach ($user as $key => $value) {
            Session::set($key, $value);
        }

        $response['success'] = true;
        $response['message'] = 'Signed in';
        echo json_encode($response);
        exit;
    }

    public function forgot_passwordAction(){

        $response = array('success' => false, 'message' => 'Something went wrong');

        if (empty($this->post['reset_email'])) {
            $response['message'] = 'Email address is required';
            echo json_encode($response);
            exit;
        }

        if (!filter_var($this->post['reset_email'], FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Enter a valid email address';
            echo json_encode($response);
            exit;
        }

        $users = new UsersModel();
        $rows = $users->get_user_by_email($this->post['reset_email']);

        if (!empty($rows)) {
            $user = $rows[0];
            $raw_token = $users->set_reset_token($user['user_id']);
            $notifications = new NotificationsModel();
            $notifications->send_password_reset($user['user_email'], $user['first_name'], $raw_token);
        }

        $response['success'] = true;
        $response['message'] = 'If that address is on file, a reset link is on its way';
        echo json_encode($response);
        exit;
    }

    public function reset_passwordAction(){

        $response = array('success' => false, 'message' => 'Something went wrong');

        if (empty($this->post['reset_token'])) {
            $response['message'] = 'That reset link is invalid or has expired';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['p_word'])) {
            $response['message'] = 'Password is required';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['p_word_two'])) {
            $response['message'] = 'Confirm your new password';
            echo json_encode($response);
            exit;
        }

        if ($this->post['p_word'] !== $this->post['p_word_two']) {
            $response['message'] = 'The two passwords do not match';
            echo json_encode($response);
            exit;
        }

        $users = new UsersModel();
        $rows = $users->get_user_by_reset_token($this->post['reset_token']);

        if (empty($rows)) {
            $response['message'] = 'That reset link is invalid or has expired';
            echo json_encode($response);
            exit;
        }

        $user = $rows[0];
        $users->change_password($user['user_id'], $this->post['p_word']);

        $response['success'] = true;
        $response['message'] = 'Your password has been changed';
        echo json_encode($response);
        exit;
    }

    public function logoutAction(){

        $response = array('success' => true, 'message' => 'Signed out');
        Main::do_logout();
        echo json_encode($response);
        exit;
    }

}
