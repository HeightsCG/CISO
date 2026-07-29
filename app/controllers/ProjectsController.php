<?php

class ProjectsController extends Controller {

    public $protected = 1;
    public $json_actions = array(
        'load_projectsAction',
        'save_project_clientAction',
        'load_assessmentsAction',
        'load_active_standardsAction',
        'create_assessmentAction',
        'save_assessmentAction',
        'delete_assessmentAction',
        'load_itemsAction',
        'save_itemAction',
        'load_item_evidenceAction',
        'link_evidenceAction',
        'unlink_evidenceAction'
    );
    public $projects_model;
    public $companies_model;
    public $standards_model;
    public $assessments_model;
    public $evidence_model;
    public $clients_model;

    public function __construct() {
        parent::__construct();
        $this->projects_model = new ProjectsModel();
        $this->companies_model = new CompaniesModel();
        $this->standards_model = new StandardsModel();
        $this->assessments_model = new AssessmentsModel();
        $this->evidence_model = new EvidenceModel();
        $this->clients_model = new ClientsModel();
        $this->enforce_json_session();
    }

    /**
     * This controller serves pages and JSON from the same class, so the base $json
     * flag cannot be used: it would answer /projects itself with a 401 body instead
     * of letting layout.php render the login form. Gated once here for every
     * endpoint rather than repeated in each action.
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

        $project = $this->projects_model->get_project(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($project) || count($project) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->project = $project[0];
        $this->view->render();
    }

    public function assessmentAction() {

        $assessment = $this->assessments_model->get_assessment(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($assessment) || count($assessment) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->assessment = $assessment[0];
        $this->view->render();
    }

    public function load_projectsAction(){
        echo json_encode($this->projects_model->load_projects(Session::get('company_id')));
    }

    public function load_active_standardsAction(){
        echo json_encode($this->standards_model->active_standards(Session::get('company_id')));
    }

    public function save_project_clientAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $project_id = (int) ($this->post['project_id'] ?? 0);
        $client_id = (int) ($this->post['client_id'] ?? 0);

        if (!$this->owns_project($project_id)) {
            $response['message'] = 'That project could not be found';
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

        $this->projects_model->set_client($project_id, Session::get('company_id'), $client_id, Session::get('user_id'));

        $response['success'] = true;
        $response['message'] = 'Client updated';
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
        $assessment_status = $this->input('assessment_status');

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

        if (!in_array($assessment_status, array('Planned', 'In Progress', 'Complete'), true)) {
            $response['message'] = 'Choose a valid status';
            echo json_encode($response);
            exit;
        }

        $this->assessments_model->update_assessment(
            $assessment_id,
            Session::get('company_id'),
            $assessment_name,
            $assessment_status,
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

    /**
     * clean_post_data() HTML-encodes every field on the way in; decoding here keeps
     * one encoding in the table and leaves escaping to the views.
     */
    private function input(string $key): string
    {
        if (!isset($this->post[$key])) {
            return '';
        }

        return trim(html_entity_decode((string) $this->post[$key], ENT_QUOTES, 'UTF-8'));
    }

}
