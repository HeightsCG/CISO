<?php
class ComplianceController extends Controller {

    public $protected = 0;

    public function __construct() {
        parent::__construct();
    }

    public function indexAction() {
        $this->view->catalogue = FrameworkLibrary::catalogue();
        $this->view->compliance_index();
    }

    public function frameworkAction() {
        $page = FrameworkLibrary::page((string) Main::get_param('framework'));

        if ($page === null) {
            Errors::page_not_found();
            return;
        }

        $this->view->page = $page;
        $this->view->compliance_page();
    }
}
