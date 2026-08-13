<?php

class ProjectsController extends Controller {

    public $protected = 1;
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
    }

    public function indexAction() {
        $this->view->room = Plans::room('projects', $this->projects_model->count_projects(Session::get('company_id')));
        $this->view->render();
    }

    public function detailAction() {

        $project = $this->projects_model->get_project(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($project) || count($project) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->project = $project[0];
        $this->view->room = Plans::room(
            'assessments_per_project',
            $this->assessments_model->count_assessments($project[0]['id'], Session::get('company_id'))
        );
        $this->view->render();
    }

    public function formAction() {

        $project_id = Main::get_param('id');

        if (empty($project_id)) {

            $room = Plans::room('projects', $this->projects_model->count_projects(Session::get('company_id')));

            if (!$room['allowed']) {
                Errors::access_denied();
                return;
            }

            $this->view->project = null;
            $this->view->render();
            return;
        }

        $project = $this->projects_model->get_project($project_id, Session::get('company_id'));

        if (!is_array($project) || count($project) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->project = $project[0];
        $this->view->render();
    }

    public function evidenceAction() {

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
















    /**
     * clean_post_data() HTML-encodes every field on the way in; decoding here keeps
     * one encoding in the table and leaves escaping to the views.
     */

}
