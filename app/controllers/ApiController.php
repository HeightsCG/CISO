<?php
class ApiController extends Controller {

    public $protected = 1;
    public $json = 1;
    public $public_actions = array(
        'loginAction',
        'forgot_passwordAction',
        'reset_passwordAction'
    );
    public $user_model;
    public $notifications_model;
    public $companies_model;
    public $clients_model;

    public function __construct(){
        parent::__construct();
        $this->user_model = new UsersModel();
        $this->notifications_model = new NotificationsModel();
        $this->companies_model = new CompaniesModel();
        $this->clients_model = new ClientsModel();
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

        $company = $this->companies_model->get_company($user['company_id']);

        if (is_array($company) && count($company) === 1) {
            Session::set('session_timeout_minutes', (int) $company[0]['session_timeout_minutes']);
        }

        Session::set('last_activity', time());

        $response['success'] = true;
        $response['message'] = 'Signed in successfully';
        echo json_encode($response);
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
    }

    public function change_passwordAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $user_id = Session::get('user_id');

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

        $taken = $this->user_model->check_email($this->post['user_email'], $user_id);

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
    }

    private function timezone_list(): array
    {
        $groups = array(
            'America'    => 'Americas',
            'Atlantic'   => 'Americas',
            'Europe'     => 'Europe',
            'Africa'     => 'Africa',
            'Asia'       => 'Asia',
            'Indian'     => 'Asia',
            'Australia'  => 'Australia',
            'Pacific'    => 'Pacific'
        );

        $now = new DateTime('now');
        $list = array();

        foreach (timezone_identifiers_list() as $id) {

            $zone = new DateTimeZone($id);
            $prefix = explode('/', $id)[0];
            $group = $groups[$prefix] ?? 'Other';

            $city = str_replace('_', ' ', substr($id, strrpos($id, '/') === false ? 0 : strrpos($id, '/') + 1));
            $name = IntlTimeZone::createTimeZone($id)->getDisplayName(false, IntlTimeZone::DISPLAY_LONG_GENERIC, 'en_US');

            $current = $zone->getOffset($now);
            $standard = $current;
            $daylight = $current;

            foreach ($zone->getTransitions($now->getTimestamp() - 31536000, $now->getTimestamp() + 31536000) as $transition) {
                if ($transition['isdst']) {
                    $daylight = $transition['offset'];
                } else {
                    $standard = $transition['offset'];
                }
            }

            $offset = $this->format_offset($standard);

            if ($daylight !== $standard) {
                $offset .= ' / ' . $this->format_offset($daylight) . ' DST';
            }

            $location = $zone->getLocation();
            $country_code = (string) ($location['country_code'] ?? '');
            $country = ($country_code !== '' && $country_code !== '??') ? (string) Locale::getDisplayRegion('-' . $country_code, 'en') : '';

            $list[] = array(
                'id' => $id,
                'group' => $group,
                'label' => $name . ' — ' . $city . ' (' . $offset . ')',
                'tokens' => trim($city . ' ' . $country . ' ' . $country_code . ' ' . $name . ' ' . $this->format_offset($current) . ' ' . str_replace('_', ' ', $id) . ' ' . $now->setTimezone($zone)->format('T'))
            );
        }

        usort($list, function ($a, $b) {
            return ($a['group'] === $b['group']) ? strcmp($a['label'], $b['label']) : strcmp($a['group'], $b['group']);
        });

        return $list;
    }

    private function format_offset($seconds): string
    {
        $sign = ($seconds < 0) ? "\xE2\x88\x92" : '+';
        $seconds = abs($seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return 'UTC' . $sign . $hours . ($minutes > 0 ? ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) : '');
    }

    public function get_companyAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company = $this->companies_model->get_company(Session::get('company_id'));

        if (!is_array($company) || count($company) !== 1) {
            $response['message'] = 'That organization could not be found';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'Organization loaded';
        $response['data'] = $company[0];
        $response['timezones'] = $this->timezone_list();
        echo json_encode($response);
    }

    public function save_companyAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        if (empty($this->post['company_name'])) {
            $response['message'] = 'Organization name is required';
            echo json_encode($response);
            exit;
        }

        if (!empty($this->post['website']) && !filter_var($this->post['website'], FILTER_VALIDATE_URL)) {
            $response['message'] = 'Enter a valid website address, including https://';
            echo json_encode($response);
            exit;
        }

        if (!empty($this->post['email_domain']) && !preg_match('/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/', $this->post['email_domain'])) {
            $response['message'] = 'Enter a valid email domain, such as example.aero';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['timezone']) || !in_array($this->post['timezone'], timezone_identifiers_list())) {
            $response['message'] = 'Select a valid timezone';
            echo json_encode($response);
            exit;
        }

        $date_formats = array('d M Y', 'D, d M Y', 'j F Y', 'F j, Y', 'd/m/Y', 'm/d/Y', 'd.m.Y', 'Y-m-d');

        if (empty($this->post['date_format']) || !in_array($this->post['date_format'], $date_formats)) {
            $response['message'] = 'Select a valid date format';
            echo json_encode($response);
            exit;
        }

        $time_formats = array('H:i', 'H:i:s', 'g:i A', 'g:i a', 'g:i:s A');

        if (empty($this->post['time_format']) || !in_array($this->post['time_format'], $time_formats)) {
            $response['message'] = 'Select a valid time format';
            echo json_encode($response);
            exit;
        }

        if (empty($this->post['brand_color']) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $this->post['brand_color'])) {
            $response['message'] = 'Enter a valid primary brand color as a six digit hex value';
            echo json_encode($response);
            exit;
        }

        if (!empty($this->post['brand_color_secondary']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $this->post['brand_color_secondary'])) {
            $response['message'] = 'Enter a valid secondary brand color as a six digit hex value';
            echo json_encode($response);
            exit;
        }

        if (!empty($this->post['brand_color_accent']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $this->post['brand_color_accent'])) {
            $response['message'] = 'Enter a valid accent brand color as a six digit hex value';
            echo json_encode($response);
            exit;
        }

        $this->companies_model->update_company(
            Session::get('company_id'),
            $this->post['company_name'],
            $this->post['trading_name'],
            $this->post['address_1'],
            $this->post['address_2'],
            $this->post['city'],
            $this->post['state'],
            $this->post['postal_code'],
            $this->post['country'],
            $this->post['website'],
            strtolower($this->post['email_domain']),
            $this->post['timezone'],
            $this->post['date_format'],
            $this->post['time_format'],
            strtoupper($this->post['brand_color']),
            strtoupper($this->post['brand_color_secondary']),
            strtoupper($this->post['brand_color_accent']),
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Organization details updated';
        echo json_encode($response);
    }

    public function save_securityAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $session_timeout_enabled = (empty($this->post['session_timeout_enabled']) ? 0 : 1);
        $password_expiry_enabled = (empty($this->post['password_expiry_enabled']) ? 0 : 1);
        $account_lockout_enabled = (empty($this->post['account_lockout_enabled']) ? 0 : 1);
        $mfa_enabled = (empty($this->post['mfa_enabled']) ? 0 : 1);

        $required = array();

        if ($session_timeout_enabled === 1) {
            $required['session_timeout_minutes'] = array('Sign out after', 5, 1440);
        }

        if ($password_expiry_enabled === 1) {
            $required['password_expiry_days'] = array('Change every', 1, 3650);
        }

        if ($account_lockout_enabled === 1) {
            $required['lockout_attempts'] = array('Failed attempts', 1, 100);
            $required['lockout_minutes'] = array('Lock for', 1, 1440);
        }

        foreach ($required as $field => $rule) {

            $value = (string) ($this->post[$field] ?? '');

            if ($value === '' || !ctype_digit($value) || (int) $value < 1) {
                $response['message'] = $rule[0] . ' must be a whole number greater than zero';
                echo json_encode($response);
                exit;
            }

            if ((int) $value < $rule[1] || (int) $value > $rule[2]) {
                $response['message'] = $rule[0] . ' must be between ' . $rule[1] . ' and ' . $rule[2];
                echo json_encode($response);
                exit;
            }
        }

        $allowed_methods = array('authenticator', 'email');
        $methods = array();

        foreach (explode(',', (string) ($this->post['mfa_methods'] ?? '')) as $method) {

            $method = trim($method);

            if ($method !== '' && in_array($method, $allowed_methods) && !in_array($method, $methods)) {
                $methods[] = $method;
            }
        }

        if ($mfa_enabled === 1 && count($methods) === 0) {
            $response['message'] = 'Choose at least one multi-factor method';
            echo json_encode($response);
            exit;
        }

        $this->companies_model->update_security(
            Session::get('company_id'),
            $session_timeout_enabled,
            (int) ($this->post['session_timeout_minutes'] ?? 0),
            $password_expiry_enabled,
            (int) ($this->post['password_expiry_days'] ?? 0),
            $account_lockout_enabled,
            (int) ($this->post['lockout_attempts'] ?? 0),
            (int) ($this->post['lockout_minutes'] ?? 0),
            $mfa_enabled,
            implode(',', $methods),
            Session::get('user_id')
        );

        Session::set('session_timeout_minutes', ($session_timeout_enabled === 1 ? (int) $this->post['session_timeout_minutes'] : 0));

        $response['success'] = true;
        $response['message'] = 'Security settings updated';
        echo json_encode($response);
    }

    public function save_logoAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'Choose a logo file to upload';
            echo json_encode($response);
            exit;
        }

        $tmp_name = $_FILES['logo_file']['tmp_name'];

        if (!is_uploaded_file($tmp_name)) {
            $response['message'] = 'That upload could not be read';
            echo json_encode($response);
            exit;
        }

        if ($_FILES['logo_file']['size'] > 2097152) {
            $response['message'] = 'The logo must be 2MB or smaller';
            echo json_encode($response);
            exit;
        }

        $size = getimagesize($tmp_name);

        if ($size === false || !in_array($size[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG))) {
            $response['message'] = 'The logo must be a PNG or JPG image';
            echo json_encode($response);
            exit;
        }

        if ($size[0] < 120 || $size[1] < 40) {
            $response['message'] = 'The logo must be at least 120 by 40 pixels to print cleanly on reports';
            echo json_encode($response);
            exit;
        }

        if (!S3Service::configured()) {
            $response['message'] = 'File storage is not configured';
            echo json_encode($response);
            exit;
        }

        $company_id = Session::get('company_id');
        $extension = ($size[2] === IMAGETYPE_PNG ? 'png' : 'jpg');
        $content_type = ($size[2] === IMAGETYPE_PNG ? 'image/png' : 'image/jpeg');
        $key = 'branding/' . $company_id . '/logo_' . bin2hex(random_bytes(8)) . '.' . $extension;

        $original_name = basename((string) $_FILES['logo_file']['name']);
        $logo_size = (int) $_FILES['logo_file']['size'];
        $logo_path = S3Service::upload_file($key, $tmp_name, $content_type);

        if ($logo_path === '') {
            $response['message'] = 'The logo could not be saved';
            echo json_encode($response);
            exit;
        }

        $company = $this->companies_model->get_company($company_id);

        if (is_array($company) && count($company) === 1 && $company[0]['logo_path'] !== '') {
            S3Service::delete_by_url($company[0]['logo_path']);
        }

        $this->companies_model->update_logo($company_id, $logo_path, $original_name, $logo_size, Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Logo updated';
        $response['logo_path'] = $logo_path;
        $response['logo_filename'] = $original_name;
        $response['logo_size'] = $logo_size;
        echo json_encode($response);
    }

    public function remove_logoAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company_id = Session::get('company_id');
        $company = $this->companies_model->get_company($company_id);

        if (is_array($company) && count($company) === 1 && $company[0]['logo_path'] !== '') {
            S3Service::delete_by_url($company[0]['logo_path']);
        }

        $this->companies_model->update_logo($company_id, '', '', 0, Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Logo removed';
        echo json_encode($response);
    }

    public function load_clientsAction(){

        $data = $this->clients_model->get_clients(Session::get('company_id'));
        $company = $this->companies_model->get_company(Session::get('company_id'));
        $format = 'd M Y';

        if (is_array($company) && count($company) === 1) {
            $format = $company[0]['date_format'];
        }

        foreach ($data as $index => $row) {
            $data[$index]['date_created_display'] = ($row['date_created'] === null ? '' : date($format, strtotime($row['date_created'])));
            $data[$index]['date_updated_display'] = ($row['date_updated'] === null ? '' : date($format, strtotime($row['date_updated'])));
        }

        echo json_encode($data);
    }

    public function save_clientAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $client_id = (int) ($this->post['client_id'] ?? 0);
        $company_id = Session::get('company_id');

        if (empty($this->post['company_name'])) {
            $response['message'] = 'Company name is required';
            echo json_encode($response);
            return;
        }

        $email = html_entity_decode((string) ($this->post['contact_email'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Enter a valid contact email address';
            echo json_encode($response);
            return;
        }

        $website = html_entity_decode((string) ($this->post['website'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $response['message'] = 'Enter a valid website address, including https://';
            echo json_encode($response);
            return;
        }

        if ($client_id > 0) {

            $client = $this->clients_model->get_client($client_id, $company_id);

            if (!is_array($client) || count($client) !== 1) {
                $response['message'] = 'That client could not be found';
                echo json_encode($response);
                return;
            }

            $this->clients_model->update_client(
                $client_id,
                $company_id,
                $this->post['company_name'],
                $this->post['contact_name'],
                $this->post['contact_title'],
                $this->post['contact_email'],
                $this->post['contact_phone'],
                $this->post['website'],
                $this->post['address_1'],
                $this->post['address_2'],
                $this->post['city'],
                $this->post['state'],
                $this->post['postal_code'],
                $this->post['country'],
                Session::get('user_id')
            );

            $response['success'] = true;
            $response['message'] = 'Client updated';
            $response['client_id'] = $client_id;
            echo json_encode($response);
            return;
        }

        $response['client_id'] = $this->clients_model->add_client(
            $company_id,
            $this->post['company_name'],
            $this->post['contact_name'],
            $this->post['contact_title'],
            $this->post['contact_email'],
            $this->post['contact_phone'],
            $this->post['website'],
            $this->post['address_1'],
            $this->post['address_2'],
            $this->post['city'],
            $this->post['state'],
            $this->post['postal_code'],
            $this->post['country'],
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Client added';
        echo json_encode($response);
    }

    public function delete_clientAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $client_id = (int) ($this->post['client_id'] ?? 0);
        $company_id = Session::get('company_id');
        $client = $this->clients_model->get_client($client_id, $company_id);

        if (!is_array($client) || count($client) !== 1) {
            $response['message'] = 'That client could not be found';
            echo json_encode($response);
            return;
        }

        $this->clients_model->delete_client($client_id, $company_id, Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Client deleted';
        echo json_encode($response);
    }

}
