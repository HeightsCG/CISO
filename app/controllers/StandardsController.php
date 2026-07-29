<?php
class StandardsController extends Controller {

    public $protected = 1;
    public $json_actions = array(
        'load_standardsAction',
        'save_standardAction',
        'delete_standardAction',
        'duplicate_standardAction',
        'load_controlsAction',
        'save_controlAction',
        'delete_controlAction',
        'import_controlsAction'
    );
    public $standards_model;

    public function __construct() {
        parent::__construct();
        $this->standards_model = new StandardsModel();
        $this->enforce_json_session();
    }

    /**
     * This controller serves pages and JSON from the same class, so the base
     * $json flag cannot be used: it would answer /standards itself with a 401
     * body instead of letting layout.php render the login form. Gated once here
     * for every endpoint rather than repeated in each action.
     */
    private function enforce_json_session(): void
    {
        if (!in_array(Main::method_name(), $this->json_actions, true)) {
            return;
        }

        if (!empty(Session::get('user_id'))) {
            return;
        }

        http_response_code(401);
        echo json_encode(array(
            'success' => false,
            'message' => 'Your session has expired. Sign in again.'
        ));
        exit;
    }

    public function indexAction() {
        $this->view->render();
    }

    public function detailAction() {

        $standard = $this->standards_model->get_standard(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($standard) || count($standard) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->standard = $standard[0];
        $this->view->render();
    }

    public function formAction() {

        $standard_id = Main::get_param('id');

        if (empty($standard_id)) {
            $this->view->standard = null;
            $this->view->render();
            return;
        }

        $standard = $this->standards_model->get_standard($standard_id, Session::get('company_id'));

        if (!is_array($standard) || count($standard) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->standard = $standard[0];
        $this->view->render();
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

    private function owns_standard($standard_id): bool
    {
        if (empty($standard_id)) {
            return false;
        }

        $standard = $this->standards_model->get_standard($standard_id, Session::get('company_id'));

        return is_array($standard) && count($standard) === 1;
    }

    /**
     * clean_post_data() HTML-encodes every field on the way in, but CSV rows arrive
     * through $_FILES and bypass it entirely. Decoding here keeps a single encoding
     * in the table whichever route the text took; the views escape on output.
     */
    private function input(string $key): string
    {
        if (!isset($this->post[$key])) {
            return '';
        }

        return trim(html_entity_decode((string) $this->post[$key], ENT_QUOTES, 'UTF-8'));
    }

    private function clean_cell($value): string
    {
        return trim(mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8'));
    }

}
