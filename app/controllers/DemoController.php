<?php
class DemoController extends Controller {

    public $protected = 0;

    public function __construct(){
        parent::__construct();
    }

    public function indexAction(){
        $this->view->lead_type = 'demo';
        $this->view->organization_types = LeadsModel::ORGANIZATION_TYPES;
        $this->view->demo_page();
    }

    public function pricingAction(){
        $this->view->lead_type = 'pricing';
        $this->view->organization_types = LeadsModel::ORGANIZATION_TYPES;
        $this->view->demo_page();
    }

}
