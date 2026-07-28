<?php
class ClientsController extends Controller {

    public $protected = 1;
    public $clients_model;

    public function __construct(){
        parent::__construct();
        $this->clients_model = new ClientsModel();
    }

    public function indexAction(){
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

}
