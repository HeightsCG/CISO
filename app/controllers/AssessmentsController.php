<?php

class AssessmentsController extends Controller {

    public $protected = 1;
    public $assessments_model;

    public function __construct() {
        parent::__construct();
        $this->assessments_model = new AssessmentsModel();
    }

    public function indexAction() {
        $this->view->render();
    }

    public function detailAction() {

        $assessment = $this->assessments_model->get_assessment(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($assessment) || count($assessment) !== 1) {
            Errors::page_not_found();
            return;
        }

        $definition = FrameworkLibrary::get((string) $assessment[0]['framework_key']);

        $this->view->assessment = $assessment[0];
        $this->view->vault_client_id = (int) $assessment[0]['client_id'];
        $this->view->features = FrameworkLibrary::assessment_features((string) $assessment[0]['framework_key']);
        $this->view->terminology = $definition['terminology'] ?? array();
        $this->view->render();
    }
}
