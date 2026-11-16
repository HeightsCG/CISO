<?php
class FrameworksController extends Controller {

    public $protected = 1;
    public $standards_model;

    public function __construct() {
        parent::__construct();
        $this->standards_model = new StandardsModel();
    }

    public function indexAction() {
        $this->view->render();
    }

    public function detailAction() {

        $standard = $this->standards_model->get_standard(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($standard) || count($standard) !== 1 || $standard[0]['standard_status'] !== 'Active') {
            Errors::page_not_found();
            return;
        }

        $this->view->standard = $standard[0];
        $this->view->render();
    }
}
