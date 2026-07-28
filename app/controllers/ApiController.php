<?php
class ApiController extends Controller {

    public $protected = 1;
    public $user_model;
    public $notifications_model;
    public $companies_model;

    public function __construct(){
        parent::__construct();
        $this->user_model = new UsersModel();
        $this->notifications_model = new NotificationsModel();
        $this->companies_model = new CompaniesModel();
    }

    public function loginAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

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

        $rows = $this->user_model->get_user_by_username($this->post['u_name']);

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

        $user_status = $user['user_status'];

        if ($user_status != 'Active') {
            $response['message'] = 'Your account is not active. Please contact support.';
            echo json_encode($response);
            exit;
        }

        $non_session_fields = array(
            'p_word',
            'reset_token',
            'reset_token_expires'
        );

        foreach ($user as $key => $value) {
            if (!in_array($key, $non_session_fields)) {
                Session::set($key, $value);
            }
        }

        session_regenerate_id(true);

        $response['success'] = true;
        $response['message'] = 'Signed in successfully';
        echo json_encode($response);
        exit;
    }

    public function forgot_passwordAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

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

        $rows = $this->user_model->get_user_by_email($this->post['reset_email']);

        if (is_array($rows) && count($rows) === 1) {
            $user = $rows[0];
            $raw_token = $this->user_model->set_reset_token($user['user_id']);
            $this->notifications_model->send_password_reset($user['user_email'], $user['first_name'], $raw_token);
        }

        $response['success'] = true;
        $response['message'] = 'If that account is on our system, a reset link is on its way.';
        echo json_encode($response);
        exit;
    }

    public function reset_passwordAction(){

        $response = array(
            'success' => false, 
            'message' => 'Something went wrong'
        );

        if (empty($this->post['reset_token'])) {
            $response['message'] = 'That reset link is invalid or has expired';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['pw1'])) {
            $response['message'] = 'Password is required';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['pw2'])) {
            $response['message'] = 'Confirm your new password';
            echo json_encode($response);
            exit;
        }

        if ($this->post['pw1'] !== $this->post['pw2']) {
            $response['message'] = 'The two passwords do not match';
            echo json_encode($response);
            exit;
        }

        if (strlen($this->post['pw1']) < 12) {
            $response['message'] = 'Password must be at least 12 characters';
            echo json_encode($response);
            exit;
        }

        if (!preg_match('/[A-Z]/', $this->post['pw1'])) {
            $response['message'] = 'Password must contain an uppercase letter';
            echo json_encode($response);
            exit;
        }

        if (!preg_match('/[a-z]/', $this->post['pw1'])) {
            $response['message'] = 'Password must contain a lowercase letter';
            echo json_encode($response);
            exit;
        }

        if (!preg_match('/[0-9]/', $this->post['pw1'])) {
            $response['message'] = 'Password must contain a number';
            echo json_encode($response);
            exit;
        }

        if (!preg_match('/[^A-Za-z0-9]/', $this->post['pw1'])) {
            $response['message'] = 'Password must contain a special character';
            echo json_encode($response);
            exit;
        }

        if (strlen($this->post['pw1']) > 72) {
            $response['message'] = 'Password must be 72 characters or fewer';
            echo json_encode($response);
            exit;
        }

        $rows = $this->user_model->get_user_by_reset_token($this->post['reset_token']);

        if (empty($rows)) {
            $response['message'] = 'That reset link is invalid or has expired';
            echo json_encode($response);
            exit;
        }

        $user = $rows[0];
        $this->user_model->change_password($user['user_id'], $this->post['pw1']);

        $response['success'] = true;
        $response['message'] = 'Your password has been changed';
        echo json_encode($response);
        exit;
    }

    public function change_passwordAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $user_id = Session::get('user_id');

        if (empty($user_id)) {
            $response['message'] = 'Your session has expired. Sign in again.';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['pw1'])) {
            $response['message'] = 'Password is required';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['pw2'])) {
            $response['message'] = 'Confirm your new password';
            echo json_encode($response);
            exit;
        }

        if ($this->post['pw1'] !== $this->post['pw2']) {
            $response['message'] = 'The two passwords do not match';
            echo json_encode($response);
            exit;
        }

        if (strlen($this->post['pw1']) < 12) {
            $response['message'] = 'Password must be at least 12 characters';
            echo json_encode($response);
            exit;
        }

        if (!preg_match('/[A-Z]/', $this->post['pw1'])) {
            $response['message'] = 'Password must contain an uppercase letter';
            echo json_encode($response);
            exit;
        }

        if (!preg_match('/[a-z]/', $this->post['pw1'])) {
            $response['message'] = 'Password must contain a lowercase letter';
            echo json_encode($response);
            exit;
        }

        if (!preg_match('/[0-9]/', $this->post['pw1'])) {
            $response['message'] = 'Password must contain a number';
            echo json_encode($response);
            exit;
        }

        if (!preg_match('/[^A-Za-z0-9]/', $this->post['pw1'])) {
            $response['message'] = 'Password must contain a special character';
            echo json_encode($response);
            exit;
        }

        if (strlen($this->post['pw1']) > 72) {
            $response['message'] = 'Password must be 72 characters or fewer';
            echo json_encode($response);
            exit;
        }

        $rows = $this->user_model->get_user_by_id($user_id);

        if (empty($rows)) {
            $response['message'] = 'Your session has expired. Sign in again.';
            echo json_encode($response);
            exit;
        }

        $user = $rows[0];

        if (Session::get('reset_pw') != 1) {

            if (empty($this->post['current_pw'])) {
                $response['message'] = 'Enter your current password';
                echo json_encode($response);
                exit;
            }

            if (!password_verify($this->post['current_pw'], $user['p_word'])) {
                $response['message'] = 'Your current password is incorrect';
                echo json_encode($response);
                exit;
            }
        }

        if (password_verify($this->post['pw1'], $user['p_word'])) {
            $response['message'] = 'Choose a password you have not used before';
            echo json_encode($response);
            exit;
        }

        $this->user_model->change_password($user_id, $this->post['pw1']);

        Session::set('reset_pw', 0);

        $response['success'] = true;
        $response['message'] = 'Your password has been changed';
        echo json_encode($response);
        exit;
    }

    public function save_profileAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $user_id = Session::get('user_id');

        if (empty($user_id)) {
            $response['message'] = 'Your session has expired. Sign in again.';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['first_name'])) {
            $response['message'] = 'First name is required';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['last_name'])) {
            $response['message'] = 'Last name is required';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['u_name'])) {
            $response['message'] = 'Username is required';
            echo json_encode($response);
            exit;
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $this->post['u_name'])) {
            $response['message'] = 'Username must be 3 to 60 characters using letters, numbers, dot, dash or underscore';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['user_email'])) {
            $response['message'] = 'Email address is required';
            echo json_encode($response);
            exit;
        }

        if (!filter_var($this->post['user_email'], FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Enter a valid email address';
            echo json_encode($response);
            exit;
        }

        $taken = $this->user_model->check_username($this->post['u_name'], $user_id);

        if (!empty($taken)) {
            $response['message'] = 'That username is already in use';
            echo json_encode($response);
            exit;
        }

        $taken = $this->user_model->get_email_owner($this->post['user_email'], $user_id);

        if (!empty($taken)) {
            $response['message'] = 'That email address is already in use';
            echo json_encode($response);
            exit;
        }

        $this->user_model->update_profile(
            $user_id,
            $this->post['first_name'],
            $this->post['last_name'],
            $this->post['u_name'],
            $this->post['user_email']
        );

        Session::set('first_name', $this->post['first_name']);
        Session::set('last_name', $this->post['last_name']);
        Session::set('u_name', $this->post['u_name']);
        Session::set('user_email', $this->post['user_email']);

        $response['success'] = true;
        $response['message'] = 'Your profile has been updated';
        echo json_encode($response);
        exit;
    }

    public function get_companyAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        if (empty(Session::get('user_id'))) {
            $response['message'] = 'Your session has expired. Sign in again.';
            echo json_encode($response);
            exit;
        }

        $company = $this->companies_model->get_company(Session::get('company_id'));

        if (is_array($company) && count($company) === 1) {
            $response['message'] = 'That organisation could not be found';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'Organisation loaded';
        $response['data'] = $company[0];
        echo json_encode($response);
        exit;
    }

}
