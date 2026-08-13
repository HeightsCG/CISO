<?php
class ClientsController extends Controller {

    public $protected = 1;
    public $clients_model;
    public $evidence_model;


    public function __construct(){
        parent::__construct();
        $this->clients_model = new ClientsModel();
        $this->evidence_model = new EvidenceModel();
    }

    public function indexAction(){
        $this->view->room = Plans::room('clients', $this->clients_model->count_clients(Session::get('company_id')));
        $this->view->render();
    }

    public function detailAction(){

        $client = $this->clients_model->get_client(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($client) || count($client) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->client = $client[0];
        $this->view->render();
    }

    public function formAction(){

        $client_id = Main::get_param('id');

        if (empty($client_id)) {

            $room = Plans::room('clients', $this->clients_model->count_clients(Session::get('company_id')));

            if (!$room['allowed']) {
                Errors::access_denied();
                return;
            }

            $this->view->client = null;
            $this->view->render();
            return;
        }

        $client = $this->clients_model->get_client($client_id, Session::get('company_id'));

        if (!is_array($client) || count($client) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->client = $client[0];
        $this->view->render();
    }



    /** Paged, filtered vault lookup - the picker never pulls the whole vault. */







    /**
     * clean_post_data() HTML-encodes every field on the way in; decoding here keeps
     * one encoding in the table and leaves escaping to the views.
     */

}
