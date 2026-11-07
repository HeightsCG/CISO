<?php
class PortalController extends Controller {

    public $protected = 1;

    public $clients_model;
    public $assessments_model;

    public function __construct(){
        parent::__construct();
        $this->clients_model = new ClientsModel();
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

    /**
     * Staff open a client's portal as that client sees it. Only reads are let
     * through for a preview; every portal write still requires a client account.
     */
    public function previewAction(){

        if (Session::get('user_type') !== 'staff') {
            header('Location: /');
            exit;
        }

        $client = $this->clients_model->get_client((int) Main::get_param('id'), Session::get('company_id'));

        if (!is_array($client) || count($client) !== 1) {
            Errors::page_not_found();
            return;
        }

        Session::set('portal_preview_client_id', (int) $client[0]['id']);
        header('Location: /portal');
        exit;
    }

    public function exitAction(){

        $client_id = (int) Session::get('portal_preview_client_id');
        Session::set('portal_preview_client_id', 0);

        header('Location: '.($client_id > 0 ? '/clients/detail/id/'.$client_id : '/clients'));
        exit;
    }

    public function invoicesAction(){

        $client = $this->client();

        if ($client === null) {
            $this->refuse();
            return;
        }

        $this->view->client = $client;
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
        $preview = Session::get('user_type') === 'staff' && (int) Session::get('portal_preview_client_id') > 0;

        if (Session::get('user_type') !== 'portal' && !$preview) {
            return null;
        }

        $client_id = $preview ? (int) Session::get('portal_preview_client_id') : Session::get('client_id');
        $client = $this->clients_model->get_client($client_id, Session::get('company_id'));

        if (!is_array($client) || count($client) !== 1) {
            return null;
        }

        if ($preview) {
            $this->view->preview_client = $client[0];
        }

        return $client[0];
    }

}
