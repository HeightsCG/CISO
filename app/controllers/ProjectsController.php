<?php

class ProjectsController extends Controller {

    public $protected = 1;
    public $projects_model;
    public $companies_model;

    public function __construct() {
        parent::__construct();
        $this->projects_model = new ProjectsModel();
        $this->companies_model = new CompaniesModel();
    }

    public function indexAction() {
        $this->view->render();
    }

    public function detailAction() {

        $id = Main::get_param('id');
        if (!$id) {
            Header('Location: /projects');
            return;
        }

        $project = $this->projects_model->get_project($id, Session::get('company_id'));

        if (!is_array($project) || count($project) !== 1) {
            Header('Location: /projects');
            return;
        }

        $this->view->project = $project[0];
        $this->view->render();
    }


}
