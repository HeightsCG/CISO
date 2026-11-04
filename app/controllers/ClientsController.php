<?php
class ClientsController extends Controller {

    public $protected = 1;
    public $clients_model;
    public $evidence_model;
    public $companies_model;
    public $assessments_model;


    public function __construct(){
        parent::__construct();
        $this->clients_model = new ClientsModel();
        $this->evidence_model = new EvidenceModel();
        $this->companies_model = new CompaniesModel();
        $this->assessments_model = new AssessmentsModel();
    }

    public function indexAction(){
        $this->view->room = Plans::room('clients', $this->clients_model->count_clients(Session::get('company_id')));
        $this->view->countries = Regions::countries();
        $this->view->render();
    }

    public function detailAction(){

        $client = $this->clients_model->get_client(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($client) || count($client) !== 1) {
            Errors::page_not_found();
            return;
        }

        $contacts = array();

        foreach (array('primary' => 'contact', 'billing' => 'billing') as $which => $prefix) {
            $contacts[$which] = array(
                'name' => html_entity_decode((string) $client[0][$prefix.'_name'], ENT_QUOTES, 'UTF-8'),
                'title' => html_entity_decode((string) $client[0][$prefix.'_title'], ENT_QUOTES, 'UTF-8'),
                'email' => html_entity_decode((string) $client[0][$prefix.'_email'], ENT_QUOTES, 'UTF-8'),
                'phone' => html_entity_decode((string) $client[0][$prefix.'_phone'], ENT_QUOTES, 'UTF-8')
            );
        }

        $found = Regions::find(html_entity_decode((string) $client[0]['country'], ENT_QUOTES, 'UTF-8'));

        $this->view->client = $client[0];
        $this->view->client_id = (int) $client[0]['id'];
        $this->view->invoice = null;
        $this->view->items = array();
        $this->view->invoice_in_modal = true;
        $company = $this->companies_model->get_company(Session::get('company_id'));
        $this->view->company = (is_array($company) && count($company) === 1 ? $company[0] : array());
        $this->view->contacts = $contacts;
        $this->view->address = array(
            'address_1' => html_entity_decode((string) $client[0]['address_1'], ENT_QUOTES, 'UTF-8'),
            'address_2' => html_entity_decode((string) $client[0]['address_2'], ENT_QUOTES, 'UTF-8'),
            'city' => html_entity_decode((string) $client[0]['city'], ENT_QUOTES, 'UTF-8'),
            'state' => html_entity_decode((string) $client[0]['state'], ENT_QUOTES, 'UTF-8'),
            'postal_code' => html_entity_decode((string) $client[0]['postal_code'], ENT_QUOTES, 'UTF-8'),
            'country' => ($found === null ? '' : $found['name'])
        );
        $this->view->countries = Regions::countries();
        $this->view->country_code = ($found === null ? 'us' : strtolower($found['code']));
        $this->view->room = Plans::room(
            'assessments_per_client',
            $this->assessments_model->count_assessments($client[0]['id'], Session::get('company_id'))
        );
        $this->view->render();
    }

    /* Adding a client happens in a popup on the clients list. */
    public function addAction(){
        header('Location: /clients');
        exit;
    }

    /** Paged, filtered vault lookup - the picker never pulls the whole vault. */







    /**
     * clean_post_data() HTML-encodes every field on the way in; decoding here keeps
     * one encoding in the table and leaves escaping to the views.
     */

}
