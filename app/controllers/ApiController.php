<?php
class ApiController extends Controller {

    public $protected = 1;

    /** user_roles.id for Client; a portal account always carries it. */
    const CLIENT_ROLE_ID = 3;

    /** user_roles.id for Admin - the administrator of a single company. */
    const ADMIN_ROLE_ID = 1;

    public $user_model;
    public $notifications_model;
    public $companies_model;
    public $clients_model;
    public $projects_model;
    public $assessments_model;
    public $evidence_model;
    public $standards_model;
    public $dashboard_model;
    public $invoices_model;

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
        $this->dashboard_model = new DashboardModel();
        $this->invoices_model = new InvoicesModel();
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

        $this->refuse_unless_company_admin();

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

        $this->refuse_unless_company_admin();

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

        $this->refuse_unless_company_admin();

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

        $this->refuse_unless_company_admin();

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

        $this->refuse_unless_company_admin();

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

    /**
     * client_id comes from the session, never from POST: it is the whole of the
     * caller's entitlement, so accepting it as a parameter would let any signed-in
     * client name someone else's.
     */
    public function portal_projectsAction(){

        if (Session::get('user_type') !== 'portal') {
            echo json_encode(array());
            exit;
        }

        $data = $this->projects_model->client_projects(Session::get('client_id'), Session::get('company_id'));
        echo json_encode($data);
    }

    public function portal_assessmentsAction(){

        $project_id = (int) ($this->post['project_id'] ?? 0);

        if (!$this->client_owns_project($project_id)) {
            echo json_encode(array());
            exit;
        }

        $data = $this->assessments_model->load_assessments($project_id, Session::get('company_id'));
        echo json_encode($data);
    }

    public function portal_foldersAction(){

        $project_id = (int) ($this->post['project_id'] ?? 0);

        if (!$this->client_owns_project($project_id)) {
            echo json_encode(array());
            exit;
        }

        $data = $this->evidence_model->portal_folders($project_id, Session::get('client_id'), Session::get('company_id'));
        echo json_encode($data);
    }

    public function portal_evidenceAction(){

        $project_id = (int) ($this->post['project_id'] ?? 0);

        if (!$this->client_owns_project($project_id)) {
            echo json_encode(array());
            exit;
        }

        $data = $this->evidence_model->portal_evidence($project_id, Session::get('client_id'), Session::get('company_id'));
        echo json_encode($data);
    }

    /**
     * A signed URL for one vault file. The row is re-fetched through the client
     * scope rather than by id alone, so a guessed id cannot produce a link, and a
     * file marked private is not reachable at all.
     */
    public function portal_evidence_urlAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $evidence = $this->portal_evidence_row((int) ($this->post['evidence_id'] ?? 0));

        if ($evidence === null) {
            $response['message'] = 'That evidence could not be found';
            echo json_encode($response);
            exit;
        }

        $url = S3Service::presigned_get_url($evidence['file_key'], 300);

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

    /**
     * One vault row the calling client is entitled to see, or null. Reached by id
     * then re-checked against the client's own project, because evidence carries a
     * company id but no client id.
     */
    private function portal_evidence_row($evidence_id)
    {
        if (Session::get('user_type') !== 'portal' || empty($evidence_id)) {
            return null;
        }

        $evidence = $this->evidence_model->get_evidence($evidence_id, Session::get('company_id'));

        if (!is_array($evidence) || count($evidence) !== 1) {
            return null;
        }

        if ((int) $evidence[0]['evidence_private'] === 1) {
            return null;
        }

        if (!$this->client_owns_project($evidence[0]['project_id'])) {
            return null;
        }

        return $evidence[0];
    }

    public function portal_item_evidenceAction(){

        if ($this->portal_item_row((int) ($this->post['item_id'] ?? 0)) === null) {
            echo json_encode(array());
            exit;
        }

        $data = $this->evidence_model->portal_item_evidence(
            (int) $this->post['item_id'],
            Session::get('client_id'),
            Session::get('company_id')
        );

        echo json_encode($data);
    }

    public function portal_link_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $item = $this->portal_item_row((int) ($this->post['item_id'] ?? 0));
        $evidence = $this->portal_evidence_row((int) ($this->post['evidence_id'] ?? 0));

        if ($item === null || $evidence === null) {
            $response['message'] = 'That evidence could not be attached to this control';
            echo json_encode($response);
            exit;
        }

        $link_id = $this->evidence_model->link_evidence(
            $evidence['id'],
            $item['id'],
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

    public function portal_unlink_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $item = $this->portal_item_row((int) ($this->post['item_id'] ?? 0));
        $evidence = $this->portal_evidence_row((int) ($this->post['evidence_id'] ?? 0));

        if ($item === null || $evidence === null) {
            $response['message'] = 'That attachment could not be found';
            echo json_encode($response);
            exit;
        }

        $rows = $this->evidence_model->unlink_evidence($evidence['id'], $item['id'], Session::get('company_id'));

        if ($rows === 0) {
            $response['message'] = 'That attachment could not be found';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'Evidence detached';
        echo json_encode($response);
    }

    /**
     * A client may withdraw a file they uploaded themselves, and only while no
     * control has been evidenced with it: delete_evidence purges the link rows, so
     * removing an attached file would silently unpick an assessor's work.
     */
    public function portal_delete_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $evidence = $this->portal_evidence_row((int) ($this->post['evidence_id'] ?? 0));

        if ($evidence === null) {
            $response['message'] = 'That evidence could not be found';
            echo json_encode($response);
            exit;
        }

        if ((int) $evidence['uploaded_by'] !== (int) Session::get('user_id')) {
            $response['message'] = 'You can only remove files you uploaded';
            echo json_encode($response);
            exit;
        }

        if (count($this->evidence_model->load_evidence_links($evidence['id'], Session::get('company_id'))) > 0) {
            $response['message'] = 'This file is attached to a control. Ask us to remove it.';
            echo json_encode($response);
            exit;
        }

        $this->evidence_model->delete_evidence($evidence['id'], Session::get('company_id'), Session::get('user_id'));
        S3Service::delete_key($evidence['file_key']);

        $response['success'] = true;
        $response['message'] = 'Evidence removed';
        echo json_encode($response);
    }

    /**
     * One control the calling client is entitled to. get_item already joins up to
     * projects and returns client_id, so the check needs no extra query - and its
     * ai.* includes notes, which stays server-side.
     */
    private function portal_item_row($item_id)
    {
        if (Session::get('user_type') !== 'portal' || empty($item_id)) {
            return null;
        }

        $item = $this->assessments_model->get_item($item_id, Session::get('company_id'));

        if (!is_array($item) || count($item) !== 1) {
            return null;
        }

        if ((int) $item[0]['client_id'] !== (int) Session::get('client_id')) {
            return null;
        }

        return $item[0];
    }

    /**
     * The portal's way in to the shared upload. owns_project inside it only proves
     * the project belongs to this organisation, which every client's project does,
     * so the narrower client test happens here first.
     */
    public function portal_upload_evidenceAction(){

        if (!$this->client_owns_project((int) ($this->post['project_id'] ?? 0))) {
            echo json_encode(array(
                'success' => false,
                'message' => 'That project could not be found'
            ));
            exit;
        }

        $this->upload_evidenceAction();
    }

    public function portal_itemsAction(){

        if (Session::get('user_type') !== 'portal') {
            echo json_encode(array());
            exit;
        }

        $data = $this->assessments_model->portal_items(
            (int) ($this->post['assessment_id'] ?? 0),
            Session::get('client_id'),
            Session::get('company_id')
        );

        echo json_encode($data);
    }

    /**
     * Every portal action that names a project id passes through here, so the
     * entitlement is expressed once. Mirrors owns_project() but on the client
     * rather than the organisation.
     */
    private function client_owns_project($project_id): bool
    {
        if (Session::get('user_type') !== 'portal' || empty($project_id)) {
            return false;
        }

        $project = $this->projects_model->client_project(
            $project_id,
            Session::get('client_id'),
            Session::get('company_id')
        );

        return is_array($project) && count($project) === 1;
    }


    /**
     * One client's projects for the client record page. Reuses the same scoped
     * query the portal uses, so staff and the client see the same set.
     */
    public function load_client_projectsAction(){

        $client_id = (int) ($this->post['client_id'] ?? 0);

        if (!$this->owns_client($client_id)) {
            echo json_encode(array());
            exit;
        }

        $data = $this->projects_model->client_projects($client_id, Session::get('company_id'));
        echo json_encode($data);
    }

    public function load_client_assessmentsAction(){

        $client_id = (int) ($this->post['client_id'] ?? 0);

        if (!$this->owns_client($client_id)) {
            echo json_encode(array());
            exit;
        }

        $data = $this->assessments_model->client_assessments($client_id, Session::get('company_id'));
        echo json_encode($data);
    }

    /**
     * Everything the dashboard draws, in one request. Six grouped queries rather
     * than one per client or per project, so the page cost does not grow with the
     * size of the organisation.
     */
    public function load_dashboardAction(){

        $company_id = Session::get('company_id');
        $totals = $this->dashboard_model->totals($company_id);

        $response = array(
            'totals' => (count($totals) === 1 ? $totals[0] : array()),
            'results' => $this->dashboard_model->result_totals($company_id),
            'assessment_status' => $this->dashboard_model->assessment_status($company_id),
            'assessments' => $this->dashboard_model->assessments($company_id),
            'clients' => $this->dashboard_model->by_client($company_id),
            'attention' => $this->dashboard_model->attention($company_id),
            'projects' => $this->projects_model->load_projects($company_id),
            'progress' => $this->assessments_model->project_progress($company_id)
        );

        echo json_encode($response);
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

        $billing_name  = $this->input('billing_name');
        $billing_email = html_entity_decode((string) ($this->post['billing_email'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($billing_email !== '' && !filter_var($billing_email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Enter a valid billing email address';
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
                $billing_name,
                $billing_email,
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
            $billing_name,
            $billing_email,
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

                /**
         * A client with money outstanding cannot simply disappear: Stripe would go
         * on emailing and dunning for an invoice this application could no longer
         * show as settled.
         */
        $outstanding = $this->invoices_model->count_open_client_invoices((int) ($this->post['client_id'] ?? 0), Session::get('company_id'));

        if ($outstanding > 0) {
            echo json_encode(array(
                'success' => false,
                'message' => 'This client has '.$outstanding.' invoice(s) still awaiting payment. Settle or void them first.'
            ));
            return;
        }

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

        $this->refuse_unless_company_admin();
        echo json_encode($this->user_model->get_users_by_company(Session::get('company_id')));
    }

    public function load_rolesAction(){
        echo json_encode($this->user_model->get_roles());
    }

    public function save_userAction(){

        $this->refuse_unless_company_admin();

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

        /**
         * A client account is the pairing of user_type, the Client role and a real
         * client record; the database enforces all three together, so they are
         * settled here rather than trusted individually from the form.
         */

        $user_type = ($this->post['user_type'] === 'portal' ? 'portal' : 'staff');
        $client_id = ($user_type === 'portal' ? (int) ($this->post['client_id'] ?? 0) : 0);
        $role_id = ($user_type === 'portal' ? self::CLIENT_ROLE_ID : (int) $this->post['role_id']);

        if ($user_type === 'portal' && !$this->owns_client($client_id)) {
            $response['message'] = 'Choose the client this account belongs to';
            echo json_encode($response);
            exit;
        }

        if ($user_type === 'staff' && $role_id === self::CLIENT_ROLE_ID) {
            $response['message'] = 'The Client role is only for client portal accounts';
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
                $role_id,
                $this->post['first_name'],
                $this->post['last_name'],
                $this->post['u_name'],
                $this->post['user_email'],
                $this->post['user_status'],
                $user_type,
                $client_id
            );
            $response['success'] = true;
            $response['message'] = 'User updated';
            echo json_encode($response);
            exit;
        } else if ($toDo === 'add') {

            $user_id = $this->user_model->add_user(
                Session::get('company_id'),
                $role_id,
                $this->post['first_name'],
                $this->post['last_name'],
                $this->post['u_name'],
                $this->post['user_email'],
                $this->post['user_status'],
                $user_type,
                $client_id
            );

            /**
             * add_user sets a random password and reset_pw, so the account is
             * unusable until the invite link is followed. Sent only once the row
             * exists, and only to an active account.
             */

            $invited = false;

            if (!empty($user_id) && $this->post['user_status'] === 'Active') {
                $raw_token = $this->user_model->set_reset_token($user_id);
                $this->notifications_model->send_password_reset($this->input('user_email'), $this->input('first_name'), $raw_token);
                $invited = true;
            }

            $response['success'] = true;
            $response['message'] = ($invited
                ? 'User added and invited'
                : 'User added. Set the account to Active to send the invite.');
            echo json_encode($response);
            exit;
        }

        echo json_encode($response);
    }

    /**
     * The global_ actions below are the only ones in the app that read across
     * tenants. Every one of them opens with this, and it answers rather than
     * returning a flag so that a missing check reads as a broken feature instead
     * of an open door.
     */
    private function refuse_unless_global_admin(): void
    {
        if ((int) Session::get('global_admin') === 1) {
            return;
        }

        echo json_encode(array(
            'success' => false,
            'message' => 'You do not have access to that'
        ));
        exit;
    }

    /**
     * Administering one's own organisation - its billing, its settings, its staff
     * accounts. Deliberately not global_admin: that flag is the cross-tenant
     * operator, and a company administrator must be able to run their own billing
     * without the ability to read every other organisation.
     *
     * This is the first thing in the application to read role_id, which until now
     * was a label shown in a dropdown. Answers rather than returning a flag, for
     * the same reason the global check does.
     */
    private function refuse_unless_company_admin(): void
    {
        if (Session::get('user_type') === 'staff' && (int) Session::get('role_id') === self::ADMIN_ROLE_ID) {
            return;
        }

        echo json_encode(array(
            'success' => false,
            'message' => 'You do not have access to that'
        ));
        exit;
    }

    public function global_usersAction(){

        $this->refuse_unless_global_admin();

        echo json_encode($this->user_model->get_all_users());
    }

    public function global_companiesAction(){

        $this->refuse_unless_global_admin();

        echo json_encode($this->companies_model->get_companies());
    }

    /**
     * The client list belongs to whichever company the account is being placed in,
     * not to the admin's own, so it is fetched per company rather than from the
     * session like load_clients does.
     */
    public function global_clientsAction(){

        $this->refuse_unless_global_admin();

        $company_id = (int) ($this->post['company_id'] ?? 0);

        if ($company_id < 1) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->clients_model->load_clients($company_id));
    }

    public function global_save_userAction(){

        $this->refuse_unless_global_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $user_id = (int) ($this->post['user_id'] ?? 0);
        $toDo = ($user_id > 0 ? 'update' : 'add');

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

        $company_id = (int) ($this->post['company_id'] ?? 0);
        $company = $this->companies_model->get_company($company_id);

        if (!is_array($company) || count($company) !== 1) {
            $response['message'] = 'Choose the company this account belongs to';
            echo json_encode($response);
            exit;
        }

        $existing = array();

        if ($toDo === 'update') {

            $rows = $this->user_model->get_any_user($user_id);

            if (!is_array($rows) || count($rows) !== 1) {
                $response['message'] = 'That user could not be found';
                echo json_encode($response);
                exit;
            }

            $existing = $rows[0];
        }

        $check_email = ($toDo === 'add'
            ? $this->user_model->check_email($this->post['user_email'])
            : $this->user_model->check_email($this->post['user_email'], $user_id));

        if (count($check_email) > 0) {
            $response['message'] = 'That email address is already in use';
            echo json_encode($response);
            exit;
        }

        $check_username = ($toDo === 'add'
            ? $this->user_model->check_username($this->post['u_name'])
            : $this->user_model->check_username($this->post['u_name'], $user_id));

        if (count($check_username) > 0) {
            $response['message'] = 'That username is already in use';
            echo json_encode($response);
            exit;
        }

        $user_type = ($this->post['user_type'] === 'portal' ? 'portal' : 'staff');
        $client_id = ($user_type === 'portal' ? (int) ($this->post['client_id'] ?? 0) : 0);
        $role_id = ($user_type === 'portal' ? self::CLIENT_ROLE_ID : (int) $this->post['role_id']);
        $global_admin = ($user_type === 'staff' && (int) ($this->post['global_admin'] ?? 0) === 1 ? 1 : 0);

        if ($user_type === 'portal') {

            $client = $this->clients_model->get_client($client_id, $company_id);

            if (!is_array($client) || count($client) !== 1) {
                $response['message'] = 'Choose the client this account belongs to';
                echo json_encode($response);
                exit;
            }
        }

        if ($user_type === 'staff' && $role_id === self::CLIENT_ROLE_ID) {
            $response['message'] = 'The Client role is only for client portal accounts';
            echo json_encode($response);
            exit;
        }

        /**
         * An admin cannot lock themselves out of this page or delete their own way
         * in. Both are checked here as well as hidden in the view.
         */

        if ($toDo === 'update' && $user_id === (int) Session::get('user_id')) {

            if ($global_admin !== 1) {
                $response['message'] = 'You cannot remove your own global admin access';
                echo json_encode($response);
                exit;
            }

            if ($this->post['user_status'] !== 'Active') {
                $response['message'] = 'You cannot disable your own account';
                echo json_encode($response);
                exit;
            }
        }

        if ($toDo === 'update') {

            $this->user_model->update_user(
                $user_id,
                (int) $existing['company_id'],
                $role_id,
                $this->post['first_name'],
                $this->post['last_name'],
                $this->post['u_name'],
                $this->post['user_email'],
                $this->post['user_status'],
                $user_type,
                $client_id
            );

            if ($company_id !== (int) $existing['company_id']) {
                $this->user_model->set_company($user_id, $company_id);
            }

            $this->user_model->set_global_admin($user_id, $global_admin);

            $response['success'] = true;
            $response['message'] = 'User updated';
            echo json_encode($response);
            exit;
        }

        $new_user_id = $this->user_model->add_user(
            $company_id,
            $role_id,
            $this->post['first_name'],
            $this->post['last_name'],
            $this->post['u_name'],
            $this->post['user_email'],
            $this->post['user_status'],
            $user_type,
            $client_id
        );

        if (empty($new_user_id)) {
            echo json_encode($response);
            exit;
        }

        if ($global_admin === 1) {
            $this->user_model->set_global_admin($new_user_id, $global_admin);
        }

        $invited = false;

        if ($this->post['user_status'] === 'Active') {
            $raw_token = $this->user_model->set_reset_token($new_user_id);
            $this->notifications_model->send_password_reset($this->input('user_email'), $this->input('first_name'), $raw_token);
            $invited = true;
        }

        $response['success'] = true;
        $response['message'] = ($invited
            ? 'User added and invited'
            : 'User added. Set the account to Active to send the invite.');
        echo json_encode($response);
    }

    public function global_delete_userAction(){

        $this->refuse_unless_global_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $user_id = (int) ($this->post['user_id'] ?? 0);

        if ($user_id === (int) Session::get('user_id')) {
            $response['message'] = 'You cannot remove your own account';
            echo json_encode($response);
            exit;
        }

        $rows = $this->user_model->get_any_user($user_id);

        if (!is_array($rows) || count($rows) !== 1) {
            $response['message'] = 'That user could not be found';
            echo json_encode($response);
            exit;
        }

        $this->user_model->delete_user($user_id, $rows[0]['company_id']);

        $response['success'] = true;
        $response['message'] = 'User deleted';
        echo json_encode($response);
    }

    public function global_reset_passwordAction(){

        $this->refuse_unless_global_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $rows = $this->user_model->get_any_user((int) ($this->post['user_id'] ?? 0));

        if (!is_array($rows) || count($rows) !== 1) {
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

    public function delete_userAction(){

        $this->refuse_unless_company_admin();

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

        $this->refuse_unless_company_admin();

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

        $folder_id = (isset($this->post['folder_id']) && $this->post['folder_id'] !== '' ? (int) $this->post['folder_id'] : null);
        $term = $this->input('search');
        $limit = (int) ($this->post['limit'] ?? 50);
        $offset = (int) ($this->post['offset'] ?? 0);
        $sort = $this->input('sort');
        $dir = $this->input('dir');

        // The total only changes when the search term does, so it is counted on
        // the first page and carried by the client for the rest. Re-counting on
        // every scroll page doubled the work for a number that never moved.
        $response = array(
            'rows' => $this->evidence_model->search_evidence($project_id, Session::get('company_id'), $term, $limit, $offset, $sort, $dir, $folder_id)
        );

        if ($offset === 0) {
            $response['total'] = $this->evidence_model->count_evidence($project_id, Session::get('company_id'), $term, $folder_id);
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

        $max_bytes = 26214400;
        $allowed_extensions = array(
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic',
            'zip', 'gz', 'tgz', '7z', 'json', 'xml', 'log', 'msg', 'eml'
        );

        if ($_FILES['evidence_file']['size'] > $max_bytes) {
            $response['message'] = 'The file must be 25MB or smaller';
            echo json_encode($response);
            exit;
        }

        $original_name = basename((string) $_FILES['evidence_file']['name']);
        $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, $allowed_extensions, true)) {
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

    /**
     * Marks a vault file as internal. Every portal query filters on this, so it is
     * the switch that keeps working papers out of the client's view.
     */
    public function save_evidence_privateAction(){

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

        $evidence_private = ((int) ($this->post['evidence_private'] ?? 0) === 1 ? 1 : 0);

        $this->evidence_model->set_evidence_private($evidence_id, Session::get('company_id'), $evidence_private, Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = ($evidence_private === 1 ? 'Marked private' : 'Shared with the client');
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

    /**
     * Pull the connected account's current state into the company row. Called
     * after onboarding returns and whenever the panel is refreshed, so the local
     * mirror is never the only thing that decides whether sending is allowed.
     */
    private function sync_connect_state($company_id, $stripe_connect_account_id): array
    {
        $account = StripeService::retrieve_account($stripe_connect_account_id);

        if (empty($account)) {
            return array();
        }

        $this->companies_model->set_connect_state(
            $company_id,
            StripeService::connect_status($account),
            empty($account['charges_enabled']) ? 0 : 1,
            empty($account['details_submitted']) ? 0 : 1,
            empty($account['payouts_enabled']) ? 0 : 1,
            StripeService::requirements_summary($account),
            $account['default_currency'] ?? 'usd'
        );

        return $account;
    }

    /**
     * Begin or resume Stripe onboarding. The account is created on first use and
     * reused thereafter; the link is minted fresh every time because Stripe's
     * onboarding links are single use and expire within minutes.
     */
    public function stripe_connect_startAction(){

        $this->refuse_unless_company_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        if (!StripeService::configured()) {
            $response['message'] = 'Stripe is not configured on this installation';
            echo json_encode($response);
            return;
        }

        $company_id = Session::get('company_id');
        $company    = $this->companies_model->get_company($company_id);

        if (!is_array($company) || count($company) !== 1) {
            echo json_encode($response);
            return;
        }

        $company    = $company[0];
        $account_id = $company['stripe_connect_account_id'];

        if ($account_id === null) {

            $account = StripeService::create_connected_account(
                $company_id,
                $company['company_name'],
                Session::get('user_email'),
                StripeService::country_code($company['country'])
            );

            if (empty($account['id'])) {
                $response['message'] = 'Stripe could not create the account: '.StripeService::last_error();
                echo json_encode($response);
                return;
            }

            $claimed = $this->companies_model->set_connect_account($company_id, $account['id'], StripeService::livemode());

            /**
             * Another administrator won the race and wrote first. Their account is
             * the one of record; this request adopts it rather than leaving a
             * second account behind.
             */
            if ($claimed === 0) {
                $company    = $this->companies_model->get_company($company_id)[0];
                $account_id = $company['stripe_connect_account_id'];
            } else {
                $account_id = $account['id'];
            }
        }

        $base = Main::get_base_domain();
        $link = StripeService::create_account_link($account_id, $base.'/settings/stripe_refresh', $base.'/settings/stripe_return');

        if (empty($link['url'])) {
            $response['message'] = 'Stripe could not start onboarding: '.StripeService::last_error();
            echo json_encode($response);
            return;
        }

        $response['success'] = true;
        $response['message'] = 'ok';
        $response['url'] = $link['url'];
        echo json_encode($response);
    }

    /** Re-read the account from Stripe so the panel reflects reality, not the mirror. */
    public function stripe_connect_refreshAction(){

        $this->refuse_unless_company_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company_id = Session::get('company_id');
        $company    = $this->companies_model->get_company($company_id);

        if (!is_array($company) || count($company) !== 1 || $company[0]['stripe_connect_account_id'] === null) {
            $response['message'] = 'No Stripe account is connected yet';
            echo json_encode($response);
            return;
        }

        if (empty($this->sync_connect_state($company_id, $company[0]['stripe_connect_account_id']))) {
            $response['message'] = 'Stripe could not be reached: '.StripeService::last_error();
            echo json_encode($response);
            return;
        }

        $response['success'] = true;
        $response['message'] = 'Stripe account refreshed';
        echo json_encode($response);
    }

    /**
     * Unlink without touching Stripe. A Standard account belongs to the company,
     * not to this platform, so it is never deleted from here - disconnecting only
     * stops this application acting on it.
     */
    public function stripe_disconnectAction(){

        $this->refuse_unless_company_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company_id = Session::get('company_id');
        $open       = $this->invoices_model->count_open_invoices($company_id);

        if ($open > 0) {
            $response['message'] = 'There are '.$open.' invoice(s) still awaiting payment. Void or settle them before disconnecting.';
            echo json_encode($response);
            return;
        }

        $this->companies_model->clear_connect_account($company_id);

        $response['success'] = true;
        $response['message'] = 'Stripe disconnected';
        echo json_encode($response);
    }

    /**
     * Billing is gated on global_admin, matching Standards and User Accounts.
     * Unlike the global_ actions this reads nothing across tenants - it is still
     * scoped to the caller's own company - so the gate is about who may bill,
     * not about who may see another organisation.
     */
    public function load_invoicesAction(){

        $this->refuse_unless_company_admin();

        echo json_encode($this->invoices_model->load_invoices(Session::get('company_id')));
    }

    public function load_client_invoicesAction(){

        $this->refuse_unless_company_admin();

        $client_id = (int) ($this->post['client_id'] ?? 0);

        if (!$this->owns_client($client_id)) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->invoices_model->client_invoices($client_id, Session::get('company_id')));
    }

    /**
     * Header and lines together, because the invoice view has no use for one
     * without the other and two round trips would let them be drawn from
     * different moments.
     */
    public function load_invoiceAction(){

        $this->refuse_unless_company_admin();

        $invoice_id = (int) ($this->post['invoice_id'] ?? 0);
        $invoice    = $this->invoices_model->get_invoice($invoice_id, Session::get('company_id'));

        if (!is_array($invoice) || count($invoice) !== 1) {
            echo json_encode(array());
            exit;
        }

        echo json_encode(array(
            'invoice' => $invoice[0],
            'items'   => $this->invoices_model->get_invoice_items($invoice_id)
        ));
    }

    /**
     * Insert and update in one action, branching on invoice_id, and drafts only -
     * once Stripe has finalised an invoice its lines are the client's copy and
     * the record here stops being the thing that decides them.
     *
     * Lines arrive as one JSON string rather than a posted array because
     * clean_post_data() runs htmlentities over scalars but passes arrays through
     * untouched, which would put two different encodings in the same invoice.
     */
    public function save_invoiceAction(){

        $this->refuse_unless_company_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company_id = Session::get('company_id');
        $invoice_id = (int) ($this->post['invoice_id'] ?? 0);
        $client_id  = (int) ($this->post['client_id'] ?? 0);
        $project_id = (int) ($this->post['project_id'] ?? 0);
        $due_days   = (int) ($this->post['due_days'] ?? 30);

        if (!$this->owns_client($client_id)) {
            $response['message'] = 'That client could not be found';
            echo json_encode($response);
            return;
        }

        $client = $this->clients_model->get_client($client_id, $company_id);

        if ($project_id > 0) {

            if (!$this->owns_project($project_id)) {
                $response['message'] = 'That project could not be found';
                echo json_encode($response);
                return;
            }

            $project = $this->projects_model->get_project($project_id, $company_id);

            if ((int) $project[0]['client_id'] !== $client_id) {
                $response['message'] = 'That project belongs to a different client';
                echo json_encode($response);
                return;
            }
        }

        /**
         * Terms and a due date are two ways of saying the same thing. Either may be
         * given; an explicit date wins, because it is the one the client reads.
         */
        $due_date = $this->input('due_date');

        if ($due_date !== '') {

            $parsed = date_create_from_format('Y-m-d', $due_date);

            if ($parsed === false || $parsed->format('Y-m-d') !== $due_date) {
                $response['message'] = 'Enter the due date as a real calendar date';
                echo json_encode($response);
                return;
            }

            $due_days = max(0, (int) ceil((strtotime($due_date) - strtotime(date('Y-m-d'))) / 86400));

        } else {
            $due_date = null;
        }

        if ($due_days < 0 || $due_days > 180) {
            $response['message'] = 'Payment terms must be between 0 and 180 days';
            echo json_encode($response);
            return;
        }

        $lines = json_decode($this->input('lines_json'), true);

        if (!is_array($lines) || count($lines) === 0) {
            $response['message'] = 'Add at least one line item';
            echo json_encode($response);
            return;
        }

        if (count($lines) > 100) {
            $response['message'] = 'An invoice cannot carry more than 100 line items';
            echo json_encode($response);
            return;
        }

        $clean = array();

        foreach ($lines as $index => $line) {

            $description = trim((string) ($line['item_description'] ?? ''));

            if ($description === '' || mb_strlen($description) > 500) {
                $response['message'] = 'Every line needs a description of 500 characters or less';
                $response['line'] = $index;
                echo json_encode($response);
                return;
            }

            $quantity = Money::to_quantity_milli($line['quantity'] ?? '');

            if ($quantity === null) {
                $response['message'] = 'Line '.($index + 1).' has an invalid quantity';
                $response['line'] = $index;
                echo json_encode($response);
                return;
            }

            $unit_amount = Money::to_cents($line['unit_amount'] ?? '');

            if ($unit_amount === null || $unit_amount < 0) {
                $response['message'] = 'Line '.($index + 1).' has an invalid unit price';
                $response['line'] = $index;
                echo json_encode($response);
                return;
            }

            $clean[] = array(
                'item_description'  => $description,
                'quantity_milli'    => $quantity,
                'unit_amount_cents' => $unit_amount,
                'service_id'        => 0
            );
        }

        /**
         * The currency is the company's, not the client's: invoices are raised on
         * the company's own connected Stripe account, which settles in one
         * currency, so a per-client choice could produce an invoice that account
         * cannot take payment in.
         */
        $company  = $this->companies_model->get_company($company_id);
        $currency = (is_array($company) && count($company) === 1) ? $company[0]['default_currency'] : 'usd';
        $memo     = $this->input('invoice_memo');
        $footer   = $this->input('invoice_footer');

        if ($invoice_id > 0) {

            $invoice = $this->invoices_model->get_invoice($invoice_id, $company_id);

            if (!is_array($invoice) || count($invoice) !== 1) {
                $response['message'] = 'That invoice could not be found';
                echo json_encode($response);
                return;
            }

            if ($invoice[0]['invoice_status'] !== 'Draft') {
                $response['message'] = 'A sent invoice cannot be edited. Void it and raise a new one.';
                echo json_encode($response);
                return;
            }

            $this->invoices_model->update_invoice($invoice_id, $company_id, $client_id, $project_id, $currency, $memo, $footer, $due_days, $due_date, Session::get('user_id'));

        } else {

            $invoice_id = (int) $this->invoices_model->add_invoice($company_id, $client_id, $project_id, $currency, $memo, $footer, $due_days, $due_date, Session::get('user_id'));
        }

        $this->invoices_model->save_invoice_lines($invoice_id, $clean, Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Invoice saved';
        $response['invoice_id'] = $invoice_id;
        echo json_encode($response);
    }

    /**
     * A customer for this client inside its company's connected account, created
     * on first use. Returns '' when it could not be established, which the caller
     * turns into a refusal rather than sending an invoice to nobody.
     */
    private function connected_customer_for_client(array $client, $account_id): string
    {
        if ($client['stripe_customer_id'] !== null && (int) $client['stripe_livemode'] === StripeService::livemode()) {
            return (string) $client['stripe_customer_id'];
        }

        $email = $client['billing_email'] !== '' ? $client['billing_email'] : $client['contact_email'];
        $name  = $client['billing_name'] !== '' ? $client['billing_name'] : $client['company_name'];

        $customer = StripeService::create_customer(
            $account_id,
            $client['company_id'],
            $client['id'],
            $name,
            $email,
            $client['address_1'],
            $client['address_2'],
            $client['city'],
            $client['state'],
            $client['postal_code'],
            $client['country']
        );

        if (empty($customer['id'])) {
            return '';
        }

        $claimed = $this->clients_model->set_stripe_customer($client['id'], $client['company_id'], $customer['id'], StripeService::livemode());

        if ($claimed === 0) {
            $fresh = $this->clients_model->get_client($client['id'], $client['company_id']);
            return (string) ($fresh[0]['stripe_customer_id'] ?? $customer['id']);
        }

        return (string) $customer['id'];
    }

    /**
     * Push a draft to the company's Stripe account, finalize it and let Stripe send
     * it. One action rather than three: three buttons would each leave a state the
     * user cannot see, and the intermediate failures have no distinct remedy.
     */
    public function send_invoiceAction(){

        $this->refuse_unless_company_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company_id = Session::get('company_id');
        $invoice_id = (int) ($this->post['invoice_id'] ?? 0);

        $invoice = $this->invoices_model->get_invoice($invoice_id, $company_id);

        if (!is_array($invoice) || count($invoice) !== 1) {
            $response['message'] = 'That invoice could not be found';
            echo json_encode($response);
            return;
        }

        $invoice = $invoice[0];

        if ($invoice['invoice_status'] !== 'Draft') {
            $response['message'] = 'Only a draft can be sent';
            echo json_encode($response);
            return;
        }

        $company = $this->companies_model->get_company($company_id);
        $company = (is_array($company) && count($company) === 1) ? $company[0] : array();

        if (empty($company['stripe_connect_account_id']) || $company['stripe_connect_status'] !== 'Connected') {
            $response['message'] = 'Connect a Stripe account in Settings before sending invoices';
            echo json_encode($response);
            return;
        }

        if ((int) $company['stripe_charges_enabled'] !== 1) {
            $response['message'] = 'Stripe has not enabled payments on this account yet';
            echo json_encode($response);
            return;
        }

        $items = $this->invoices_model->get_invoice_items($invoice_id);

        if (count($items) === 0 || (int) $invoice['total_cents'] <= 0) {
            $response['message'] = 'Add at least one line with an amount before sending';
            echo json_encode($response);
            return;
        }

        $client = $this->clients_model->get_client($invoice['client_id'], $company_id);

        if (!is_array($client) || count($client) !== 1) {
            $response['message'] = 'That client could not be found';
            echo json_encode($response);
            return;
        }

        $client = $client[0];
        $email  = $client['billing_email'] !== '' ? $client['billing_email'] : $client['contact_email'];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Add a valid billing or contact email for this client before sending';
            echo json_encode($response);
            return;
        }

        /**
         * The mutex. Only the request that moves the invoice out of Draft proceeds,
         * so a double-clicked Send makes exactly one set of Stripe calls.
         */
        if ((int) $this->invoices_model->claim_finalize($invoice_id, $company_id, Session::get('user_id')) !== 1) {
            $response['message'] = 'That invoice is already being sent';
            echo json_encode($response);
            return;
        }

        $account_id = $company['stripe_connect_account_id'];

        try {

            $customer_id = $this->connected_customer_for_client($client, $account_id);

            if ($customer_id === '') {
                $this->invoices_model->fail_finalize($invoice_id, 'Stripe customer could not be created: '.StripeService::last_error(), true);
                $response['message'] = 'Stripe could not create the customer: '.StripeService::last_error();
                echo json_encode($response);
                return;
            }

            $stripe_invoice_id = $invoice['stripe_invoice_id'];

            if ($stripe_invoice_id === null) {

                $draft = StripeService::create_invoice(
                    $account_id,
                    $customer_id,
                    $invoice['currency'],
                    $invoice['invoice_memo'],
                    $invoice['invoice_footer'],
                    $invoice['due_days'],
                    $invoice['due_date'],
                    $company_id,
                    $invoice_id,
                    $invoice['client_id'],
                    $invoice['project_id']
                );

                if (empty($draft['id'])) {
                    $this->invoices_model->fail_finalize($invoice_id, StripeService::last_error(), true);
                    $response['message'] = 'Stripe could not create the invoice: '.StripeService::last_error();
                    echo json_encode($response);
                    return;
                }

                $stripe_invoice_id = $draft['id'];

                $this->invoices_model->set_stripe_invoice($invoice_id, $stripe_invoice_id, $account_id, $customer_id, StripeService::livemode());
            }

            foreach ($items as $item) {

                $line = StripeService::add_invoice_line(
                    $account_id,
                    $stripe_invoice_id,
                    $customer_id,
                    $invoice['currency'],
                    $item['item_description'],
                    $item['quantity_milli'],
                    $item['unit_amount_cents'],
                    $item['amount_cents'],
                    $company_id,
                    $invoice_id,
                    $item['id']
                );

                if (empty($line['id'])) {
                    $this->invoices_model->fail_finalize($invoice_id, 'Line rejected: '.StripeService::last_error(), true);
                    $response['message'] = 'Stripe rejected a line item: '.StripeService::last_error();
                    echo json_encode($response);
                    return;
                }
            }

            $finalized = StripeService::finalize_invoice($account_id, $stripe_invoice_id, $company_id, $invoice_id);

            if (empty($finalized['id'])) {
                /**
                 * Left in Finalizing on purpose. Stripe may have finalized and
                 * emailed it, and a retry past the idempotency window would raise a
                 * second invoice against the same client.
                 */
                $this->invoices_model->fail_finalize($invoice_id, StripeService::last_error(), false);
                $response['message'] = 'We could not confirm this with Stripe. Refresh in a moment before trying again.';
                echo json_encode($response);
                return;
            }

            StripeService::send_invoice($account_id, $stripe_invoice_id);

            $current = StripeService::retrieve_invoice($account_id, $stripe_invoice_id);

            $this->invoices_model->apply_stripe_invoice($invoice_id, empty($current) ? $finalized : $current, 0);

        } catch (\Throwable $e) {
            $this->invoices_model->fail_finalize($invoice_id, $e->getMessage(), false);
            error_log('[billing] send failed for invoice '.$invoice_id.': '.$e->getMessage());
            $response['message'] = 'We could not confirm this with Stripe. Refresh in a moment before trying again.';
            echo json_encode($response);
            return;
        }

        $response['success'] = true;
        $response['message'] = 'Invoice sent';
        $response['invoice_id'] = $invoice_id;
        echo json_encode($response);
    }

    /** Voiding is final in Stripe; the local mirror follows the account, not the other way round. */
    public function void_invoiceAction(){

        $this->refuse_unless_company_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company_id = Session::get('company_id');
        $invoice_id = (int) ($this->post['invoice_id'] ?? 0);
        $invoice    = $this->invoices_model->get_invoice($invoice_id, $company_id);

        if (!is_array($invoice) || count($invoice) !== 1) {
            $response['message'] = 'That invoice could not be found';
            echo json_encode($response);
            return;
        }

        $invoice = $invoice[0];

        if (!in_array($invoice['invoice_status'], array('Open', 'Uncollectible'), true) || $invoice['stripe_invoice_id'] === null) {
            $response['message'] = 'Only a sent invoice can be voided';
            echo json_encode($response);
            return;
        }

        $voided = StripeService::void_invoice($invoice['stripe_account_id'], $invoice['stripe_invoice_id'], $company_id, $invoice_id);

        if (empty($voided['id'])) {
            $response['message'] = 'Stripe could not void that invoice: '.StripeService::last_error();
            echo json_encode($response);
            return;
        }

        $this->invoices_model->apply_stripe_invoice($invoice_id, $voided, 0);

        $response['success'] = true;
        $response['message'] = 'Invoice voided';
        echo json_encode($response);
    }

    /**
     * Resolve one invoice against Stripe. Returns a short description of what
     * happened, so both the single-invoice button and the sweep can report it.
     *
     * The fifteen minute grace before returning a stuck invoice to Draft is
     * deliberately generous: sending it back sooner risks a second invoice going
     * to the client, whereas waiting only inconveniences staff.
     */
    private function reconcile_invoice(array $invoice, $company_id): string
    {
        if ($invoice['stripe_invoice_id'] !== null) {

            $current = StripeService::retrieve_invoice($invoice['stripe_account_id'], $invoice['stripe_invoice_id']);

            if (empty($current)) {
                return 'unreachable';
            }

            $this->invoices_model->apply_stripe_invoice($invoice['id'], $current, 0);

            return 'synced';
        }

        $orphan = StripeService::find_orphan_invoice(
            $invoice['stripe_account_id'],
            $invoice['stripe_customer_id'],
            $company_id,
            $invoice['id'],
            strtotime($invoice['date_updated']) - 3600
        );

        if (!empty($orphan['id'])) {

            $this->invoices_model->set_stripe_invoice(
                $invoice['id'],
                $orphan['id'],
                $invoice['stripe_account_id'],
                (string) ($orphan['customer'] ?? $invoice['stripe_customer_id']),
                StripeService::livemode()
            );

            $this->invoices_model->apply_stripe_invoice($invoice['id'], $orphan, 0);

            return 'adopted';
        }

        if ((time() - strtotime($invoice['date_updated'])) < 900) {
            return 'waiting';
        }

        $this->invoices_model->fail_finalize($invoice['id'], 'Not sent - Stripe never received it', true);

        return 'returned to draft';
    }

    /** The Refresh button on an invoice that is stuck mid-send. */
    public function sync_invoiceAction(){

        $this->refuse_unless_company_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company_id = Session::get('company_id');
        $invoice    = $this->invoices_model->get_invoice((int) ($this->post['invoice_id'] ?? 0), $company_id);

        if (!is_array($invoice) || count($invoice) !== 1) {
            $response['message'] = 'That invoice could not be found';
            echo json_encode($response);
            return;
        }

        if ($invoice[0]['stripe_account_id'] === '') {
            $response['message'] = 'That invoice was never sent to Stripe';
            echo json_encode($response);
            return;
        }

        $outcome = $this->reconcile_invoice($invoice[0], $company_id);

        $messages = array(
            'synced'           => 'Checked with Stripe and brought up to date',
            'adopted'          => 'Found it at Stripe - it had been sent after all',
            'waiting'          => 'Stripe has not confirmed it yet. Try again in a few minutes.',
            'returned to draft' => 'Stripe never received it, so it is a draft again and safe to send',
            'unreachable'      => 'Stripe could not be reached: '.StripeService::last_error()
        );

        $response['success'] = $outcome !== 'unreachable';
        $response['message'] = $messages[$outcome] ?? $outcome;
        echo json_encode($response);
    }

    /**
     * Sweep every invoice this company has left in an unresolved state. Safe to run
     * repeatedly - each step is either an idempotent apply or a guarded update.
     */
    public function billing_reconcileAction(){

        $this->refuse_unless_company_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $company_id = Session::get('company_id');
        $outcomes   = array();

        foreach ($this->invoices_model->stuck_finalizing($company_id, 120) as $invoice) {
            $outcome = $this->reconcile_invoice($invoice, $company_id);
            $outcomes[$outcome] = ($outcomes[$outcome] ?? 0) + 1;
        }

        $refreshed = 0;

        foreach ($this->invoices_model->stale_open($company_id, 21600) as $invoice) {

            $current = StripeService::retrieve_invoice($invoice['stripe_account_id'], $invoice['stripe_invoice_id']);

            if (!empty($current)) {
                $this->invoices_model->apply_stripe_invoice($invoice['id'], $current, 0);
                $refreshed++;
            }
        }

        $parts = array();

        foreach ($outcomes as $outcome => $count) {
            $parts[] = $count.' '.$outcome;
        }

        if ($refreshed > 0) {
            $parts[] = $refreshed.' refreshed';
        }

        $response['success'] = true;
        $response['message'] = count($parts) === 0 ? 'Everything is already up to date' : implode(', ', $parts);
        echo json_encode($response);
    }

    public function delete_invoiceAction(){

        $this->refuse_unless_company_admin();

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $invoice_id = (int) ($this->post['invoice_id'] ?? 0);
        $invoice    = $this->invoices_model->get_invoice($invoice_id, Session::get('company_id'));

        if (!is_array($invoice) || count($invoice) !== 1) {
            $response['message'] = 'That invoice could not be found';
            echo json_encode($response);
            return;
        }

        if ($invoice[0]['invoice_status'] !== 'Draft') {
            $response['message'] = 'Only a draft can be deleted. Void a sent invoice instead.';
            echo json_encode($response);
            return;
        }

        $this->invoices_model->delete_invoice($invoice_id, Session::get('company_id'), Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Invoice deleted';
        echo json_encode($response);
    }

    /**
     * One invoice the calling client is entitled to see, or null.
     *
     * Mirrors portal_evidence_row: prove the caller is a portal user, fetch the
     * record scoped to the company, then re-prove it belongs to this client. A
     * draft is excluded outright - it is staff work in progress that Stripe has
     * never sent, and may carry an internal memo.
     */
    private function portal_invoice_row($invoice_id)
    {
        if (Session::get('user_type') !== 'portal' || empty($invoice_id)) {
            return null;
        }

        $invoice = $this->invoices_model->get_invoice($invoice_id, Session::get('company_id'));

        if (!is_array($invoice) || count($invoice) !== 1) {
            return null;
        }

        if ((int) $invoice[0]['client_id'] !== (int) Session::get('client_id')) {
            return null;
        }

        if (in_array($invoice[0]['invoice_status'], array('Draft', 'Finalizing'), true)) {
            return null;
        }

        return $invoice[0];
    }

    /**
     * client_id comes from the session, never from POST: it is the whole of the
     * caller's entitlement, so accepting it as a parameter would let any signed-in
     * client name someone else's.
     */
    public function portal_invoicesAction(){

        if (Session::get('user_type') !== 'portal') {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->invoices_model->portal_invoices(Session::get('client_id'), Session::get('company_id')));
    }

    /**
     * The hosted payment page, minted per click rather than carried in the list.
     * It is a capability URL - possession is authorisation - and Stripe has already
     * emailed this same link to this same client, so handing it to them after an
     * entitlement check widens nothing. Keeping it out of the list payload keeps
     * the blast radius at one invoice rather than all of them.
     */
    public function portal_invoice_urlAction(){

        $response = array(
            'success' => false,
            'message' => 'That invoice could not be found'
        );

        $invoice = $this->portal_invoice_row((int) ($this->post['invoice_id'] ?? 0));

        if ($invoice === null) {
            echo json_encode($response);
            exit;
        }

        $link_type = ($this->post['link_type'] ?? 'pay') === 'pdf' ? 'pdf' : 'pay';

        /**
         * Re-read from Stripe so a voided invoice cannot still be paid from a stale
         * local copy. The stored URL is the fallback when Stripe cannot be reached.
         */
        $current = StripeService::retrieve_invoice($invoice['stripe_account_id'], $invoice['stripe_invoice_id']);

        $url = $link_type === 'pdf'
            ? (string) ($current['invoice_pdf'] ?? $invoice['invoice_pdf_url'])
            : (string) ($current['hosted_invoice_url'] ?? $invoice['hosted_invoice_url']);

        if ($url === '') {
            $response['message'] = 'That invoice could not be opened';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'ok';
        $response['url'] = $url;
        echo json_encode($response);
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
