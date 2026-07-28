<?php

class ProjectsController extends Controller {

    public $protected = 1;

    public function __construct() {
        parent::__construct();
    }

    public function indexAction() {
        $this->view->render();
    }
}