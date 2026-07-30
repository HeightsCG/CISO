<?php
class PortalController extends Controller {

    public $protected = 1;

    public $clients_model;
    public $projects_model;
    public $assessments_model;

    public function __construct(){
        parent::__construct();
        $this->clients_model = new ClientsModel();
        $this->projects_model = new ProjectsModel();
        $this->assessments_model = new AssessmentsModel();
    }

    public function indexAction(){

        $client = $this->client();

        if ($client === null) {
            $this->refuse();
            return;
        }

        $this->view->client = $client;
        $this->view->render();
    }

    public function projectAction(){

        $client = $this->client();

        if ($client === null) {
            $this->refuse();
            return;
        }

        $project = $this->projects_model->client_project(Main::get_param('id'), $client['id'], Session::get('company_id'));

        if (!is_array($project) || count($project) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->client = $client;
        $this->view->project = $project[0];
        $this->view->render();
    }

    public function evidenceAction(){

        $client = $this->client();

        if ($client === null) {
            $this->refuse();
            return;
        }

        $project = $this->projects_model->client_project(Main::get_param('id'), $client['id'], Session::get('company_id'));

        if (!is_array($project) || count($project) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->client = $client;
        $this->view->project = $project[0];
        $this->view->render();
    }

    public function assessmentAction(){

        $client = $this->client();

        if ($client === null) {
            $this->refuse();
            return;
        }

        $assessment = $this->assessments_model->client_assessment(Main::get_param('id'), $client['id'], Session::get('company_id'));

        if (!is_array($assessment) || count($assessment) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->client = $client;
        $this->view->assessment = $assessment[0];
        $this->view->render();
    }

    /**
     * No session means the visitor is simply not signed in, so the layout renders
     * the login form as it does anywhere else. A session that exists but is not a
     * client's has genuinely asked for something that is not there.
     */
    private function refuse(): void
    {
        if (empty(Session::get('user_id'))) {
            $this->view->render();
            return;
        }

        // Staff have no portal of their own, so this is a wrong door rather than
        // a missing page: send them back to their own home.
        if (Session::get('user_type') !== 'portal') {
            header('Location: /');
            exit;
        }

        Errors::page_not_found();
    }

    /**
     * The signed-in client's own record, or null for a staff session. Read once
     * per request so a client soft-deleted mid-session loses access immediately
     * rather than at the next sign-in.
     */
    private function client()
    {
        if (Session::get('user_type') !== 'portal') {
            return null;
        }

        $client = $this->clients_model->get_client(Session::get('client_id'), Session::get('company_id'));

        if (!is_array($client) || count($client) !== 1) {
            return null;
        }

        return $client[0];
    }

}
