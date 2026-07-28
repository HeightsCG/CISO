<?php
class ApiController extends Controller {

    public $protected = 1;
    public $user_model;
    public $notifications_model;

    public function __construct(){
        parent::__construct();
        $this->user_model = new UsersModel();
        $this->notifications_model = new NotificationsModel();
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

        $companies_model = new CompaniesModel();
        $rows = $companies_model->get_company(Session::get('company_id'));

        if (empty($rows)) {
            $response['message'] = 'That organisation could not be found';
            echo json_encode($response);
            exit;
        }

        $company = $rows[0];

        $response['success'] = true;
        $response['message'] = 'Organisation loaded';
        $response['timezones'] = timezone_identifiers_list();
        $response['company'] = array(
            'company_name' => $company['company_name'],
            'address_1' => $company['address_1'],
            'address_2' => $company['address_2'],
            'city' => $company['city'],
            'state_region' => $company['state_region'],
            'postal_code' => $company['postal_code'],
            'country' => $company['country'],
            'website' => $company['website'],
            'timezone' => $company['timezone'],
            'logo_path' => $company['logo_path'],
            'brand_color' => $company['brand_color']
        );
        echo json_encode($response);
        exit;
    }

    public function save_companyAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        if (empty(Session::get('user_id'))) {
            $response['message'] = 'Your session has expired. Sign in again.';
            echo json_encode($response);
            exit;  
        }

        if (empty($this->post['company_name'])) {
            $response['message'] = 'Organisation name is required';
            echo json_encode($response);
            exit;
        }

        if (strlen($this->post['company_name']) > 200) {
            $response['message'] = 'Organisation name must be 200 characters or fewer';
            echo json_encode($response);
            exit;
        }

        $lengths = array(
            'address_1' => 200,
            'address_2' => 200,
            'city' => 120,
            'state_region' => 120,
            'postal_code' => 40,
            'country' => 120,
            'website' => 255
        );

        foreach ($lengths as $field => $max) {

            if (!isset($this->post[$field])) {
                $this->post[$field] = '';
            }

            if (strlen($this->post[$field]) > $max) {
                $response['message'] = 'That ' . str_replace('_', ' ', $field) . ' is too long';
                echo json_encode($response);
                exit;
            }
        }

        if ($this->post['website'] !== '' && !filter_var($this->post['website'], FILTER_VALIDATE_URL)) {
            $response['message'] = 'Enter a valid website address, including https://';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['timezone']) || !in_array($this->post['timezone'], timezone_identifiers_list())) {
            $response['message'] = 'Select a valid timezone';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['brand_color']) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $this->post['brand_color'])) {
            $response['message'] = 'Enter a valid brand colour as a six digit hex value';
            echo json_encode($response);
            exit;
        }

        $companies_model = new CompaniesModel();

        $companies_model->update_company(
            Session::get('company_id'),
            $this->post['company_name'],
            $this->post['address_1'],
            $this->post['address_2'],
            $this->post['city'],
            $this->post['state_region'],
            $this->post['postal_code'],
            $this->post['country'],
            $this->post['website'],
            $this->post['timezone'],
            strtoupper($this->post['brand_color']),
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Organisation details updated';
        echo json_encode($response);
        exit;
    }

    public function save_logoAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        if (empty(Session::get('user_id'))) {
            $response['message'] = 'Your session has expired. Sign in again.';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['logo_data'])) {
            $response['message'] = 'Choose a logo file to upload';
            echo json_encode($response);
            exit;
        }

        $matched = preg_match('/^data:image\/(png|jpeg);base64,([A-Za-z0-9+\/=]+)$/', $this->post['logo_data'], $parts);

        if (!$matched) {
            $response['message'] = 'The logo must be a PNG or JPG image';
            echo json_encode($response);
            exit;
        }

        $binary = base64_decode($parts[2], true);

        if ($binary === false) {
            $response['message'] = 'That file could not be read';
            echo json_encode($response);
            exit;
        }

        if (strlen($binary) > 2097152) {
            $response['message'] = 'The logo must be 2MB or smaller';
            echo json_encode($response);
            exit;
        }

        $size = getimagesizefromstring($binary);

        if ($size === false || !in_array($size[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG))) {
            $response['message'] = 'That file is not a valid PNG or JPG image';
            echo json_encode($response);
            exit;
        }

        if ($size[0] < 120 || $size[1] < 40) {
            $response['message'] = 'The logo must be at least 120 by 40 pixels to print cleanly on reports';
            echo json_encode($response);
            exit;
        }

        $company_id = Session::get('company_id');
        $extension = ($size[2] === IMAGETYPE_PNG ? 'png' : 'jpg');
        $file_name = 'logo_' . $company_id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $directory = dirname(__DIR__, 2) . '/public/uploads/branding';

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_put_contents($directory . '/' . $file_name, $binary) === false) {
            $response['message'] = 'The logo could not be saved';
            echo json_encode($response);
            exit;
        }

        $companies_model = new CompaniesModel();
        $rows = $companies_model->get_company($company_id);

        if (!empty($rows) && $rows[0]['logo_path'] !== '') {

            $previous = $directory . '/' . basename($rows[0]['logo_path']);

            if (is_file($previous)) {
                unlink($previous);
            }
        }

        $logo_path = '/uploads/branding/' . $file_name;
        $companies_model->update_logo($company_id, $logo_path, Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Logo updated';
        $response['logo_path'] = $logo_path;
        echo json_encode($response);
        exit;
    }

    public function remove_logoAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        if (empty(Session::get('user_id'))) {
            $response['message'] = 'Your session has expired. Sign in again.';
            echo json_encode($response);
            exit;
        }

        $company_id = Session::get('company_id');
        $companies_model = new CompaniesModel();
        $rows = $companies_model->get_company($company_id);

        if (!empty($rows) && $rows[0]['logo_path'] !== '') {

            $previous = dirname(__DIR__, 2) . '/public/uploads/branding/' . basename($rows[0]['logo_path']);

            if (is_file($previous)) {
                unlink($previous);
            }
        }

        $companies_model->update_logo($company_id, '', Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Logo removed';
        echo json_encode($response);
        exit;
    }

}
