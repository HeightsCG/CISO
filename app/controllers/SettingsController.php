<?php
class SettingsController extends Controller {

    public $protected = 1;

    public function __construct(){
        parent::__construct();
    }

    public function indexAction(){
        $this->view->page_title = 'Settings';
        $this->view->render();
    }

}
