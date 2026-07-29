<?php
class ApiController extends Controller {

    public $protected = 1;

    // Evidence is whatever the assessor was handed: policies, screenshots, config
    // dumps, signed contracts. Anything executable is refused outright.
    const MAX_UPLOAD_BYTES = 26214400;
    const ALLOWED_EXTENSIONS = array(
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic',
        'zip', 'gz', 'tgz', '7z', 'json', 'xml', 'log', 'msg', 'eml'
    );

    public $public_actions = array(
        'loginAction',
        'forgot_passwordAction',
        'reset_passwordAction'
    );

    public $user_model;
    public $notifications_model;
    public $companies_model;
    public $clients_model;
    public $projects_model;
    public $assessments_model;
    public $evidence_model;
    public $standards_model;

    public function __construct(){
        parent::__construct();
        $this->user_model = new UsersModel();
        $this->notifications_model = new NotificationsModel();
        $this->companies_model = new CompaniesModel();
        $this->clients_model = new ClientsModel();
        $this->projects_model = new ProjectsModel();
        $this->assessments_model = new AssessmentsModel();
        $this->standards_model = new StandardsModel();
        $this->evidence_model = new EvidenceModel();
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
        $response['date_formats'] = $this->companies_model->get_date_formats();
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

        $date_format_ids = array_column($this->companies_model->get_date_formats(), 'id');

        if (!in_array((int) ($this->post['date_format_id'] ?? 0), array_map('intval', $date_format_ids), true)) {
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
            (int) $this->post['date_format_id'],
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
        $data = $this->clients_model->load_clients(Session::get('company_id'));
        echo json_encode($data);
    }

    public function load_projectsAction(){
        $data = $this->projects_model->load_projects(Session::get('company_id'));
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

    /* ------------------------------------------------------------ user admin */



    public function complete_assessmentAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $assessment_id = (int) ($this->post['assessment_id'] ?? 0);
        $assessment = $this->assessments_model->get_assessment($assessment_id, Session::get('company_id'));

        if (count($assessment) !== 1) {
            $response['message'] = 'That assessment could not be found';
            echo json_encode($response);
            exit;
        }

        $unassessed = $this->assessments_model->unassessed_count($assessment_id, Session::get('company_id'));

        if ($unassessed > 0) {
            $response['message'] = $unassessed . ' ' . ($unassessed === 1 ? 'control is' : 'controls are') . ' still unanswered';
            echo json_encode($response);
            exit;
        }

        $this->assessments_model->set_assessment_status($assessment_id, Session::get('company_id'), 'Complete', Session::get('user_id'));

        $assessment = $this->assessments_model->get_assessment($assessment_id, Session::get('company_id'));

        $response['project_status'] = $this->projects_model->sync_project_status(
            $assessment[0]['project_id'],
            Session::get('company_id'),
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Assessment complete';
        $response['assessment_status'] = 'Complete';
        echo json_encode($response);
    }

    public function load_project_usersAction(){

        $project = $this->projects_model->get_project($this->post['project_id'], Session::get('company_id'));

        if (count($project) !== 1) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->projects_model->load_project_users($this->post['project_id'], Session::get('company_id')));
    }

    public function load_available_usersAction(){

        $project = $this->projects_model->get_project($this->post['project_id'], Session::get('company_id'));

        if (count($project) !== 1) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->projects_model->available_users($this->post['project_id'], Session::get('company_id')));
    }

    public function save_project_userAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company_id = Session::get('company_id');

        $project_roles = array(
            'Project Lead',
            'Lead Assessor',
            'Assessor',
            'Reviewer',
            'Contributor',
            'Observer'
        );

        if (!in_array($this->post['project_role'], $project_roles)) {
            $response['message'] = 'Choose a project role';
            echo json_encode($response);
            exit;
        }

        if ($this->post['id'] > 0) {

            $project_user = $this->projects_model->get_project_user($this->post['id'], $company_id);

            if (count($project_user) !== 1) {
                $response['message'] = 'That team member could not be found';
                echo json_encode($response);
                exit;
            }

            $this->projects_model->update_project_user($this->post['id'], $company_id, $this->post['project_role'], Session::get('user_id'));

            $response['success'] = true;
            $response['message'] = 'Project role updated';
            echo json_encode($response);
            exit;
        }

        $project = $this->projects_model->get_project($this->post['project_id'], $company_id);

        if (count($project) !== 1) {
            $response['message'] = 'That project could not be found';
            echo json_encode($response);
            exit;
        }

        if (count($this->user_model->get_company_user($this->post['user_id'], $company_id)) !== 1) {
            $response['message'] = 'That user could not be found';
            echo json_encode($response);
            exit;
        }

        if (count($this->projects_model->check_project_user($this->post['project_id'], $this->post['user_id'])) > 0) {
            $response['message'] = 'They are already on this project';
            echo json_encode($response);
            exit;
        }

        $this->projects_model->add_project_user(
            $company_id,
            $this->post['project_id'],
            $this->post['user_id'],
            $this->post['project_role'],
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'User added to the project';
        echo json_encode($response);
    }

    public function delete_project_userAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company_id = Session::get('company_id');

        if (count($this->projects_model->get_project_user($this->post['id'], $company_id)) !== 1) {
            $response['message'] = 'That team member could not be found';
            echo json_encode($response);
            exit;
        }

        $this->projects_model->delete_project_user($this->post['id'], $company_id, Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'User removed from the project';
        echo json_encode($response);
    }

    public function load_usersAction(){
        echo json_encode($this->user_model->get_users_by_company(Session::get('company_id')));
    }

    public function load_rolesAction(){
        echo json_encode($this->user_model->get_roles());
    }

    public function save_userAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $toDo = $this->post['toDo'];
        
        if ($toDo === '') {
            $response['message'] = 'Something went wrong';
            echo json_encode($response);
            exit;
        }

        if ($this->post['first_name'] === '') {
            $response['message'] = 'First name is required';
            echo json_encode($response);
            exit;
        }

        if ($this->post['last_name'] === '') {
            $response['message'] = 'Last name is required';
            echo json_encode($response);
            exit;
        }
        
        if ($this->post['u_name'] === '') {
            $response['message'] = 'Username is required';
            echo json_encode($response);
            exit;
        }

        if ($this->post['user_email'] === '') {
            $response['message'] = 'Email is required';
            echo json_encode($response);
            exit;
        }

        if ($this->post['user_status'] === '') {
            $response['message'] = 'Status is required';
            echo json_encode($response);
            exit;
        }

        $company_id = Session::get('company_id');

        $check_email = ($toDo === 'add' ? $this->user_model->check_email($this->post['user_email']) : $this->user_model->check_email($this->post['user_email'], $this->post['user_id']));
        if (count($check_email) > 0) {
            $response['message'] = 'That email address is already in use';
            echo json_encode($response);
            exit;
        }

        $check_username = ($toDo === 'add' ? $this->user_model->check_username($this->post['u_name']) : $this->user_model->check_username($this->post['u_name'], $this->post['user_id']));
        if (count($check_username) > 0) {
            $response['message'] = 'That username is already in use';
            echo json_encode($response);
            exit;
        }

        if ($toDo === 'update') {

            if (empty($this->post['user_id'])) {
                $response['message'] = 'User ID is required';
                echo json_encode($response);
                exit;
            }

            $this->user_model->update_user(
                $this->post['user_id'],
                $company_id,
                $this->post['role_id'],
                $this->post['first_name'],
                $this->post['last_name'],
                $this->post['u_name'],
                $this->post['user_email'],
                $this->post['user_status']
            );
            $response['success'] = true;
            $response['message'] = 'User updated';
            echo json_encode($response);
            exit;
        } else if ($toDo === 'add') {
            $this->user_model->add_user(
                Session::get('company_id'),
                $this->post['role_id'],
                $this->post['first_name'],
                $this->post['last_name'],
                $this->post['u_name'],
                $this->post['user_email'],
                $this->post['user_status']
            );

            $response['success'] = true;
            $response['message'] = 'User added';
            echo json_encode($response);
            exit;
        }

        echo json_encode($response);
    }

    public function delete_userAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $this->user_model->delete_user($this->post['user_id'], Session::get('company_id'));

        $response['success'] = true;
        $response['message'] = 'User removed';
        echo json_encode($response);
    }

    public function reset_user_passwordAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $rows = $this->user_model->get_company_user($this->post['user_id'], Session::get('company_id'));

        if (count($rows) !== 1) {
            $response['message'] = 'That user could not be found';
            echo json_encode($response);
            exit;
        }

        $user = $rows[0];
        $raw_token = $this->user_model->set_reset_token($user['user_id']);
        $this->notifications_model->send_password_reset($user['user_email'], $user['first_name'], $raw_token);

        $response['success'] = true;
        $response['message'] = 'A reset link has been sent to ' . $user['user_email'];
        echo json_encode($response);
    }

    public function address_suggestAction(){

        $query = trim((string) html_entity_decode((string) ($this->post['query'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $country = trim((string) html_entity_decode((string) ($this->post['country'] ?? ''), ENT_QUOTES, 'UTF-8'));

        if (strlen($query) < 4) {
            echo json_encode(array('success' => true, 'message' => '', 'data' => array()));
            return;
        }

        $result = PlacesService::autocomplete($query, self::country_code($country));

        echo json_encode(array(
            'success' => ($result['error'] === ''),
            'message' => $result['error'],
            'data'    => $result['items']
        ));
    }

    public function address_placeAction(){

        $response = array(
            'success' => false,
            'message' => 'That address could not be loaded'
        );

        $result = PlacesService::get_place(html_entity_decode((string) ($this->post['place_id'] ?? ''), ENT_QUOTES, 'UTF-8'));

        if ($result['error'] !== '') {
            $response['message'] = $result['error'];
            echo json_encode($response);
            return;
        }

        $response['success'] = true;
        $response['message'] = 'Address loaded';
        $response['data'] = $result['address'];
        echo json_encode($response);
    }

    /**
     * Amazon Location filters countries by ISO code. Users type a country name in
     * the form, so a name is mapped to its code when recognised; anything else is
     * ignored so international addresses are never blocked.
     */
    private static function country_code($country): string
    {
        $country = trim($country);

        if ($country === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]{3}$/', $country)) {
            return strtoupper($country);
        }

        $map = array(
            'switzerland' => 'CHE',
            'united states' => 'USA',
            'usa' => 'USA',
            'united kingdom' => 'GBR',
            'uk' => 'GBR',
            'germany' => 'DEU',
            'france' => 'FRA',
            'austria' => 'AUT',
            'norway' => 'NOR',
            'sweden' => 'SWE',
            'denmark' => 'DNK',
            'netherlands' => 'NLD',
            'belgium' => 'BEL',
            'ireland' => 'IRL',
            'italy' => 'ITA',
            'spain' => 'ESP',
            'portugal' => 'PRT',
            'poland' => 'POL',
            'czechia' => 'CZE',
            'czech republic' => 'CZE',
            'canada' => 'CAN',
            'australia' => 'AUS',
            'new zealand' => 'NZL'
        );

        return (string) ($map[strtolower($country)] ?? '');
    }



    public function save_projectAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $project_id = (int) ($this->post['project_id'] ?? 0);
        $client_id = (int) ($this->post['client_id'] ?? 0);
        $project_name = $this->input('project_name');
        $description = $this->input('description');
        $start_date = $this->input('start_date');
        $end_date = $this->input('end_date');

        if ($project_name === '') {
            $response['message'] = 'Project name is required';
            echo json_encode($response);
            exit;
        }

        foreach (array('start_date' => $start_date, 'end_date' => $end_date) as $label => $value) {
            if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $response['message'] = 'Enter a valid '.str_replace('_', ' ', $label);
                echo json_encode($response);
                exit;
            }
        }

        if ($end_date < $start_date) {
            $response['message'] = 'The end date cannot fall before the start date';
            echo json_encode($response);
            exit;
        }

        if ($client_id > 0) {

            $client = $this->clients_model->get_client($client_id, Session::get('company_id'));

            if (!is_array($client) || count($client) !== 1) {
                $response['message'] = 'That client could not be found';
                echo json_encode($response);
                exit;
            }
        }

        $company_id = Session::get('company_id');
        $user_id = Session::get('user_id');

        if ($project_id === 0) {

            $new_id = $this->projects_model->add_project(
                $company_id, $client_id, $project_name, $description,
                $start_date, $end_date, $user_id
            );

            $response['success'] = true;
            $response['message'] = 'Project created';
            $response['project_id'] = (int) $new_id;
            echo json_encode($response);
            exit;
        }

        if (!$this->owns_project($project_id)) {
            $response['message'] = 'That project could not be found';
            echo json_encode($response);
            exit;
        }

        $this->projects_model->update_project(
            $project_id, $company_id, $client_id, $project_name, $description,
            $start_date, $end_date, $user_id
        );

        $response['success'] = true;
        $response['message'] = 'Project updated';
        $response['project_id'] = $project_id;
        echo json_encode($response);
    }

    public function delete_projectAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $project_id = (int) ($this->post['project_id'] ?? 0);

        if (!$this->owns_project($project_id)) {
            $response['message'] = 'That project could not be found';
            echo json_encode($response);
            exit;
        }

        $assessments = $this->assessments_model->load_assessments($project_id, Session::get('company_id'));

        $this->projects_model->delete_project($project_id, Session::get('company_id'), Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Project deleted'
            .(count($assessments) > 0
                ? ', '.count($assessments).' '.(count($assessments) === 1 ? 'assessment' : 'assessments').' removed'
                : '');
        echo json_encode($response);
    }

    public function load_assessmentsAction(){

        $project_id = (int) ($this->post['project_id'] ?? 0);

        if (!$this->owns_project($project_id)) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->assessments_model->load_assessments($project_id, Session::get('company_id')));
    }

    public function load_active_standardsAction(){
        echo json_encode($this->standards_model->active_standards(Session::get('company_id')));
    }

    public function create_assessmentAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $project_id = (int) ($this->post['project_id'] ?? 0);
        $standard_id = (int) ($this->post['standard_id'] ?? 0);
        $assessment_name = $this->input('assessment_name');

        if (!$this->owns_project($project_id)) {
            $response['message'] = 'That project could not be found';
            echo json_encode($response);
            exit;
        }

        if ($assessment_name === '') {
            $response['message'] = 'Assessment name is required';
            echo json_encode($response);
            exit;
        }

        if ($standard_id === 0) {
            $response['message'] = 'Choose a standard to assess against';
            echo json_encode($response);
            exit;
        }

        $assessment_id = $this->assessments_model->create_assessment(
            $project_id,
            Session::get('company_id'),
            $standard_id,
            $assessment_name,
            Session::get('user_id')
        );

        if (empty($assessment_id)) {
            $response['message'] = 'That standard is not available, or has no controls to assess';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'Assessment created';
        $response['assessment_id'] = (int) $assessment_id;
        echo json_encode($response);
    }

    public function save_assessmentAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $assessment_id = (int) ($this->post['assessment_id'] ?? 0);
        $assessment_name = $this->input('assessment_name');

        if (!$this->owns_assessment($assessment_id)) {
            $response['message'] = 'That assessment could not be found';
            echo json_encode($response);
            exit;
        }

        if ($assessment_name === '') {
            $response['message'] = 'Assessment name is required';
            echo json_encode($response);
            exit;
        }

        $this->assessments_model->update_assessment(
            $assessment_id,
            Session::get('company_id'),
            $assessment_name,
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Assessment updated';
        echo json_encode($response);
    }

    public function delete_assessmentAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $assessment_id = (int) ($this->post['assessment_id'] ?? 0);

        if (!$this->owns_assessment($assessment_id)) {
            $response['message'] = 'That assessment could not be found';
            echo json_encode($response);
            exit;
        }

        $this->assessments_model->delete_assessment($assessment_id, Session::get('company_id'), Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Assessment deleted';
        echo json_encode($response);
    }

    public function load_itemsAction(){

        $assessment_id = (int) ($this->post['assessment_id'] ?? 0);

        if (!$this->owns_assessment($assessment_id)) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->assessments_model->load_items($assessment_id, Session::get('company_id')));
    }

    public function save_itemAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $item_id = (int) ($this->post['item_id'] ?? 0);
        $item_result = $this->input('item_result');
        $notes = $this->input('notes');

        $allowed = array('Not Assessed', 'Implemented', 'Partially Implemented', 'Not Implemented', 'Not Applicable');

        if (!in_array($item_result, $allowed, true)) {
            $response['message'] = 'Choose a valid result';
            echo json_encode($response);
            exit;
        }

        $rows = $this->assessments_model->save_item(
            $item_id,
            Session::get('company_id'),
            $item_result,
            $notes,
            Session::get('user_id')
        );

        if ($rows === 0) {
            $response['message'] = 'That item could not be found';
            echo json_encode($response);
            exit;
        }

        $item = $this->assessments_model->get_item($item_id, Session::get('company_id'));
        $assessment = $this->assessments_model->get_assessment($item[0]['assessment_id'], Session::get('company_id'));

        $response['assessment_status'] = $this->assessments_model->sync_assessment_status(
            $item[0]['assessment_id'],
            Session::get('company_id'),
            Session::get('user_id')
        );

        $this->projects_model->sync_project_status(
            $assessment[0]['project_id'],
            Session::get('company_id'),
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Item saved';
        echo json_encode($response);
    }

    public function load_item_evidenceAction(){

        $item_id = (int) ($this->post['item_id'] ?? 0);
        $item = $this->assessments_model->get_item($item_id, Session::get('company_id'));

        if (count($item) !== 1) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->evidence_model->load_item_evidence($item_id, Session::get('company_id')));
    }

    public function link_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $item_id = (int) ($this->post['item_id'] ?? 0);
        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);

        $link_id = $this->evidence_model->link_evidence(
            $evidence_id,
            $item_id,
            Session::get('company_id'),
            Session::get('user_id')
        );

        if (empty($link_id)) {
            $response['message'] = 'That evidence could not be attached to this control';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'Evidence attached';
        echo json_encode($response);
    }

    public function unlink_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $item_id = (int) ($this->post['item_id'] ?? 0);
        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);

        $rows = $this->evidence_model->unlink_evidence($evidence_id, $item_id, Session::get('company_id'));

        if ($rows === 0) {
            $response['message'] = 'That attachment could not be found';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'Evidence detached';
        echo json_encode($response);
    }

    public function load_standardsAction() {
        echo json_encode($this->standards_model->load_standards(Session::get('company_id')));
    }

    public function save_standardAction() {

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $standard_id = (int) ($this->post['standard_id'] ?? 0);
        $standard_name = $this->input('standard_name');
        $short_code = $this->input('short_code');
        $version = $this->input('version');
        $description = $this->input('description');
        $standard_status = $this->input('standard_status');

        if ($standard_name === '') {
            $response['message'] = 'Standard name is required';
            echo json_encode($response);
            exit;
        }

        if (!in_array($standard_status, array('Active', 'Archived'), true)) {
            $response['message'] = 'Choose a valid status';
            echo json_encode($response);
            exit;
        }

        $company_id = Session::get('company_id');
        $user_id = Session::get('user_id');

        if ($standard_id === 0) {

            $new_id = $this->standards_model->add_standard(
                $company_id,
                $standard_name,
                $short_code,
                $version,
                $description,
                $standard_status,
                $user_id
            );

            $response['success'] = true;
            $response['message'] = 'Standard added';
            $response['standard_id'] = (int) $new_id;
            echo json_encode($response);
            exit;
        }

        if (!$this->owns_standard($standard_id)) {
            $response['message'] = 'That standard could not be found';
            echo json_encode($response);
            exit;
        }

        $this->standards_model->update_standard(
            $standard_id,
            $company_id,
            $standard_name,
            $short_code,
            $version,
            $description,
            $standard_status,
            $user_id
        );

        $response['success'] = true;
        $response['message'] = 'Standard updated';
        $response['standard_id'] = $standard_id;
        echo json_encode($response);
    }

    public function delete_standardAction() {

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $standard_id = (int) ($this->post['standard_id'] ?? 0);

        if (!$this->owns_standard($standard_id)) {
            $response['message'] = 'That standard could not be found';
            echo json_encode($response);
            exit;
        }

        $this->standards_model->delete_standard($standard_id, Session::get('company_id'), Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Standard deleted';
        echo json_encode($response);
    }

    public function duplicate_standardAction() {

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $standard_id = (int) ($this->post['standard_id'] ?? 0);
        $standard_name = $this->input('standard_name');
        $short_code = $this->input('short_code');
        $version = $this->input('version');

        if (!$this->owns_standard($standard_id)) {
            $response['message'] = 'That standard could not be found';
            echo json_encode($response);
            exit;
        }

        if ($standard_name === '') {
            $response['message'] = 'Standard name is required';
            echo json_encode($response);
            exit;
        }

        $new_id = $this->standards_model->duplicate_standard(
            $standard_id,
            Session::get('company_id'),
            $standard_name,
            $short_code,
            $version,
            Session::get('user_id')
        );

        if (empty($new_id)) {
            $response['message'] = 'That standard could not be duplicated';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'Standard duplicated';
        $response['standard_id'] = (int) $new_id;
        echo json_encode($response);
    }

    public function load_controlsAction() {

        $standard_id = (int) ($this->post['standard_id'] ?? 0);

        if (!$this->owns_standard($standard_id)) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->standards_model->load_controls($standard_id, Session::get('company_id')));
    }

    public function save_controlAction() {

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $standard_id = (int) ($this->post['standard_id'] ?? 0);
        $control_id = (int) ($this->post['control_id'] ?? 0);
        $control_identifier = $this->input('control_identifier');
        $control_title = $this->input('control_title');
        $description = $this->input('description');
        $family = $this->input('family');

        if (!$this->owns_standard($standard_id)) {
            $response['message'] = 'That standard could not be found';
            echo json_encode($response);
            exit;
        }

        if ($control_identifier === '') {
            $response['message'] = 'Control identifier is required';
            echo json_encode($response);
            exit;
        }

        if ($control_title === '') {
            $response['message'] = 'Control title is required';
            echo json_encode($response);
            exit;
        }

        $sort_order = 0;

        if ($control_id > 0) {

            $control = $this->standards_model->get_control($control_id, Session::get('company_id'));

            if (!is_array($control) || count($control) !== 1 || (int) $control[0]['standard_id'] !== $standard_id) {
                $response['message'] = 'That control could not be found';
                echo json_encode($response);
                exit;
            }

            $sort_order = (int) $control[0]['sort_order'];
        }

        $identifiers = $this->standards_model->control_identifiers($standard_id, $control_id);

        if (isset($identifiers[mb_strtolower($control_identifier)])) {
            $response['message'] = $control_identifier.' already exists in this standard';
            echo json_encode($response);
            exit;
        }

        if ($control_id === 0) {

            $this->standards_model->add_control(
                $standard_id,
                $control_identifier,
                $control_title,
                $description,
                $family,
                $this->standards_model->max_sort_order($standard_id) + 1,
                Session::get('user_id')
            );

            $response['success'] = true;
            $response['message'] = 'Control added';
            echo json_encode($response);
            exit;
        }

        $this->standards_model->update_control(
            $control_id,
            $standard_id,
            $control_identifier,
            $control_title,
            $description,
            $family,
            $sort_order,
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Control updated';
        echo json_encode($response);
    }

    public function delete_controlAction() {

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $control_id = (int) ($this->post['control_id'] ?? 0);
        $control = $this->standards_model->get_control($control_id, Session::get('company_id'));

        if (!is_array($control) || count($control) !== 1) {
            $response['message'] = 'That control could not be found';
            echo json_encode($response);
            exit;
        }

        $this->standards_model->delete_control($control_id, $control[0]['standard_id'], Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Control deleted';
        echo json_encode($response);
    }

    public function import_controlsAction() {

        $response = array(
            'success' => false,
            'message' => 'Something went wrong',
            'imported' => 0,
            'skipped' => 0,
            'errors' => array()
        );

        $standard_id = (int) ($this->post['standard_id'] ?? 0);

        if (!$this->owns_standard($standard_id)) {
            $response['message'] = 'That standard could not be found';
            echo json_encode($response);
            exit;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'Choose a CSV file to import';
            echo json_encode($response);
            exit;
        }

        $tmp_name = $_FILES['csv_file']['tmp_name'];

        if (!is_uploaded_file($tmp_name)) {
            $response['message'] = 'That upload could not be read';
            echo json_encode($response);
            exit;
        }

        if ($_FILES['csv_file']['size'] > 5242880) {
            $response['message'] = 'The file must be 5MB or smaller';
            echo json_encode($response);
            exit;
        }

        $handle = fopen($tmp_name, 'r');

        if ($handle === false) {
            $response['message'] = 'That file could not be opened';
            echo json_encode($response);
            exit;
        }

        $existing = $this->standards_model->control_identifiers($standard_id);
        $controls = array();
        $errors = array();
        $seen = array();
        $skipped = 0;
        $line = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {

            $line++;

            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            if ($line === 1 && strcasecmp(trim((string) $row[0]), 'identifier') === 0) {
                continue;
            }

            if (count($row) < 2) {
                $errors[] = array(
                    'row' => $line,
                    'reason' => 'Expected identifier, title, description, family'
                );
                continue;
            }

            $control_identifier = $this->clean_cell($row[0]);
            $control_title = $this->clean_cell($row[1]);
            $description = $this->clean_cell($row[2] ?? '');
            $family = $this->clean_cell($row[3] ?? '');

            if ($control_identifier === '') {
                $errors[] = array(
                    'row' => $line,
                    'reason' => 'Identifier is required'
                );
                continue;
            }

            if ($control_title === '') {
                $errors[] = array(
                    'row' => $line,
                    'reason' => 'Title is required'
                );
                continue;
            }

            $key = mb_strtolower($control_identifier);

            if (isset($existing[$key]) || isset($seen[$key])) {
                $skipped++;
                continue;
            }

            $seen[$key] = 1;

            $controls[] = array(
                'control_identifier' => $control_identifier,
                'control_title' => $control_title,
                'description' => $description,
                'family' => $family
            );
        }

        fclose($handle);

        // Nothing is written while a single row is still in error, so a rejected
        // file leaves the standard exactly as it was.
        if (count($errors) > 0) {
            $response['message'] = 'Nothing was imported. Fix the rows below and upload the file again.';
            $response['errors'] = $errors;
            echo json_encode($response);
            exit;
        }

        if (count($controls) === 0 && $skipped === 0) {
            $response['message'] = 'That file has no rows to import';
            echo json_encode($response);
            exit;
        }

        $imported = 0;

        if (count($controls) > 0) {
            $imported = $this->standards_model->import_controls($standard_id, $controls, Session::get('user_id'));
        }

        $response['success'] = true;
        $response['imported'] = $imported;
        $response['skipped'] = $skipped;
        $response['message'] = $imported.' '.($imported === 1 ? 'control' : 'controls').' imported';

        if ($skipped > 0) {
            $response['message'] .= ', '.$skipped.' skipped as duplicates';
        }

        echo json_encode($response);
    }


    public function load_foldersAction(){

        $project_id = (int) ($this->post['project_id'] ?? 0);

        if (!$this->owns_project($project_id)) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->evidence_model->load_folders($project_id, Session::get('company_id')));
    }

    public function save_folderAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $folder_id = (int) ($this->post['folder_id'] ?? 0);
        $project_id = (int) ($this->post['project_id'] ?? 0);
        $parent_id = (int) ($this->post['parent_id'] ?? 0);
        $folder_name = $this->input('folder_name');
        $company_id = Session::get('company_id');

        if (!$this->owns_project($project_id)) {
            $response['message'] = 'That project could not be found';
            echo json_encode($response);
            exit;
        }

        if ($folder_name === '') {
            $response['message'] = 'Folder name is required';
            echo json_encode($response);
            exit;
        }

        if ($parent_id > 0) {

            $parent = $this->evidence_model->get_folder($parent_id, $company_id);

            if (count($parent) !== 1 || (int) $parent[0]['project_id'] !== $project_id) {
                $response['message'] = 'That parent folder could not be found';
                echo json_encode($response);
                exit;
            }
        }

        if (count($this->evidence_model->check_folder_name($project_id, $parent_id, $folder_name, $folder_id)) > 0) {
            $response['message'] = 'A folder with that name is already here';
            echo json_encode($response);
            exit;
        }

        if ($folder_id > 0) {

            $folder = $this->evidence_model->get_folder($folder_id, $company_id);

            if (count($folder) !== 1) {
                $response['message'] = 'That folder could not be found';
                echo json_encode($response);
                exit;
            }

            if (in_array($parent_id, $this->evidence_model->folder_subtree($folder_id, $company_id), true)) {
                $response['message'] = 'A folder cannot be moved inside itself';
                echo json_encode($response);
                exit;
            }

            $this->evidence_model->update_folder($folder_id, $company_id, $parent_id, $folder_name, Session::get('user_id'));

            $response['success'] = true;
            $response['message'] = 'Folder updated';
            $response['folder_id'] = $folder_id;
            echo json_encode($response);
            exit;
        }

        $response['folder_id'] = (int) $this->evidence_model->add_folder($company_id, $project_id, $parent_id, $folder_name, Session::get('user_id'));
        $response['success'] = true;
        $response['message'] = 'Folder created';
        echo json_encode($response);
    }

    public function delete_folderAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $folder_id = (int) ($this->post['folder_id'] ?? 0);
        $company_id = Session::get('company_id');

        if (count($this->evidence_model->get_folder($folder_id, $company_id)) !== 1) {
            $response['message'] = 'That folder could not be found';
            echo json_encode($response);
            exit;
        }

        $removed = $this->evidence_model->delete_folder($folder_id, $company_id, Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Folder deleted'
            .($removed > 1 ? ' with '.($removed - 1).' '.($removed - 1 === 1 ? 'subfolder' : 'subfolders') : '')
            .'. Any files inside moved up a level.';
        echo json_encode($response);
    }

    public function move_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);
        $folder_id = (int) ($this->post['folder_id'] ?? 0);
        $company_id = Session::get('company_id');

        $evidence = $this->evidence_model->get_evidence($evidence_id, $company_id);

        if (count($evidence) !== 1) {
            $response['message'] = 'That evidence could not be found';
            echo json_encode($response);
            exit;
        }

        if ($folder_id > 0) {

            $folder = $this->evidence_model->get_folder($folder_id, $company_id);

            if (count($folder) !== 1 || (int) $folder[0]['project_id'] !== (int) $evidence[0]['project_id']) {
                $response['message'] = 'That folder could not be found';
                echo json_encode($response);
                exit;
            }
        }

        $this->evidence_model->move_evidence($evidence_id, $company_id, $folder_id, Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Evidence moved';
        echo json_encode($response);
    }

    public function load_evidenceAction(){

        $project_id = (int) ($this->post['project_id'] ?? 0);

        if (!$this->owns_project($project_id)) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->evidence_model->load_evidence($project_id, Session::get('company_id')));
    }

    public function search_evidenceAction(){

        $project_id = (int) ($this->post['project_id'] ?? 0);

        if (!$this->owns_project($project_id)) {
            echo json_encode(array('total' => 0, 'rows' => array()));
            exit;
        }

        $term = $this->input('search');
        $limit = (int) ($this->post['limit'] ?? 50);
        $offset = (int) ($this->post['offset'] ?? 0);
        $sort = $this->input('sort');
        $dir = $this->input('dir');

        // The total only changes when the search term does, so it is counted on
        // the first page and carried by the client for the rest. Re-counting on
        // every scroll page doubled the work for a number that never moved.
        $response = array(
            'rows' => $this->evidence_model->search_evidence($project_id, Session::get('company_id'), $term, $limit, $offset, $sort, $dir)
        );

        if ($offset === 0) {
            $response['total'] = $this->evidence_model->count_evidence($project_id, Session::get('company_id'), $term);
        }

        echo json_encode($response);
    }

    public function upload_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $project_id = (int) ($this->post['project_id'] ?? 0);
        $folder_id = (int) ($this->post['folder_id'] ?? 0);
        $evidence_title = $this->input('evidence_title');
        $description = $this->input('description');

        if (!$this->owns_project($project_id)) {
            $response['message'] = 'That project could not be found';
            echo json_encode($response);
            exit;
        }

        if ($evidence_title === '') {
            $response['message'] = 'Evidence title is required';
            echo json_encode($response);
            exit;
        }

        if ($folder_id > 0) {

            $folder = $this->evidence_model->get_folder($folder_id, Session::get('company_id'));

            if (count($folder) !== 1 || (int) $folder[0]['project_id'] !== $project_id) {
                $response['message'] = 'That folder could not be found';
                echo json_encode($response);
                exit;
            }
        }

        if (!isset($_FILES['evidence_file']) || $_FILES['evidence_file']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'Choose a file to upload';
            echo json_encode($response);
            exit;
        }

        $tmp_name = $_FILES['evidence_file']['tmp_name'];

        if (!is_uploaded_file($tmp_name)) {
            $response['message'] = 'That upload could not be read';
            echo json_encode($response);
            exit;
        }

        if ($_FILES['evidence_file']['size'] > self::MAX_UPLOAD_BYTES) {
            $response['message'] = 'The file must be 25MB or smaller';
            echo json_encode($response);
            exit;
        }

        $original_name = basename((string) $_FILES['evidence_file']['name']);
        $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $response['message'] = 'That file type is not accepted as evidence';
            echo json_encode($response);
            exit;
        }

        if (!S3Service::configured()) {
            $response['message'] = 'File storage is not configured';
            echo json_encode($response);
            exit;
        }

        $content_type = 'application/octet-stream';

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $tmp_name);
            finfo_close($finfo);
            if (is_string($detected) && $detected !== '') {
                $content_type = $detected;
            }
        }

        // Private, and keyed by company then project so a stray key can never be
        // read across orgs even if the bucket policy is loosened later.
        $key = 'evidence/'.Session::get('company_id').'/'.$project_id.'/'
            .bin2hex(random_bytes(12)).'.'.$extension;

        if (!S3Service::put_private($key, $tmp_name, $content_type)) {
            $response['message'] = 'That file could not be stored';
            echo json_encode($response);
            exit;
        }

        $evidence_id = $this->evidence_model->add_evidence(
            Session::get('company_id'),
            $project_id,
            $folder_id,
            $evidence_title,
            $description,
            $key,
            $original_name,
            (int) $_FILES['evidence_file']['size'],
            $content_type,
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Evidence uploaded';
        $response['evidence_id'] = (int) $evidence_id;
        echo json_encode($response);
    }

    public function save_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);
        $evidence_title = $this->input('evidence_title');
        $description = $this->input('description');

        $evidence = $this->evidence_model->get_evidence($evidence_id, Session::get('company_id'));

        if (count($evidence) !== 1) {
            $response['message'] = 'That evidence could not be found';
            echo json_encode($response);
            exit;
        }

        if ($evidence_title === '') {
            $response['message'] = 'Evidence title is required';
            echo json_encode($response);
            exit;
        }

        $this->evidence_model->update_evidence(
            $evidence_id,
            Session::get('company_id'),
            $evidence_title,
            $description,
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Evidence updated';
        echo json_encode($response);
    }

    public function delete_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);
        $evidence = $this->evidence_model->get_evidence($evidence_id, Session::get('company_id'));

        if (count($evidence) !== 1) {
            $response['message'] = 'That evidence could not be found';
            echo json_encode($response);
            exit;
        }

        $links = $this->evidence_model->load_evidence_links($evidence_id, Session::get('company_id'));

        $this->evidence_model->delete_evidence($evidence_id, Session::get('company_id'), Session::get('user_id'));

        if ($evidence[0]['file_key'] !== '') {
            S3Service::delete_key($evidence[0]['file_key']);
        }

        $response['success'] = true;
        $response['message'] = 'Evidence deleted'
            .(count($links) > 0 ? ', '.count($links).' '.(count($links) === 1 ? 'attachment' : 'attachments').' removed' : '');
        $response['unlinked'] = count($links);
        echo json_encode($response);
    }

    public function load_evidence_linksAction(){

        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);
        $evidence = $this->evidence_model->get_evidence($evidence_id, Session::get('company_id'));

        if (count($evidence) !== 1) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->evidence_model->load_evidence_links($evidence_id, Session::get('company_id')));
    }

    public function evidence_urlAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);
        $evidence = $this->evidence_model->get_evidence($evidence_id, Session::get('company_id'));

        if (count($evidence) !== 1) {
            $response['message'] = 'That evidence could not be found';
            echo json_encode($response);
            exit;
        }

        // Short-lived signed URL: the object itself stays private, so a link that
        // leaks stops working within minutes.
        $url = S3Service::presigned_get_url($evidence[0]['file_key'], 300);

        if ($url === '') {
            $response['message'] = 'That file could not be opened';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'ok';
        $response['url'] = $url;
        echo json_encode($response);
    }

    private function input(string $key): string
    {
        if (!isset($this->post[$key])) {
            return '';
        }

        return trim(html_entity_decode((string) $this->post[$key], ENT_QUOTES, 'UTF-8'));
    }

    private function owns_project($project_id): bool
    {
        if (empty($project_id)) {
            return false;
        }

        $project = $this->projects_model->get_project($project_id, Session::get('company_id'));

        return is_array($project) && count($project) === 1;
    }

    private function owns_assessment($assessment_id): bool
    {
        if (empty($assessment_id)) {
            return false;
        }

        $assessment = $this->assessments_model->get_assessment($assessment_id, Session::get('company_id'));

        return is_array($assessment) && count($assessment) === 1;
    }

    private function owns_standard($standard_id): bool
    {
        if (empty($standard_id)) {
            return false;
        }

        $standard = $this->standards_model->get_standard($standard_id, Session::get('company_id'));

        return is_array($standard) && count($standard) === 1;
    }

    private function owns_client($client_id): bool
    {
        if (empty($client_id)) {
            return false;
        }

        $client = $this->clients_model->get_client($client_id, Session::get('company_id'));

        return is_array($client) && count($client) === 1;
    }


    private function clean_cell($value): string
    {
        return trim(mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8'));
    }

}
