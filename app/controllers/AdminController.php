<?php
class AdminController extends Controller {

    public $protected = 1;

    public function __construct(){
        parent::__construct();
    }

    public function indexAction(){

        if ((int) Session::get('global_admin') !== 1) {
            Errors::access_denied();
            return;
        }

        $this->view->render();
    }

    public function financialsAction(){

        if ((int) Session::get('global_admin') !== 1) {
            Errors::access_denied();
            return;
        }

        $this->view->render();
    }

    public function leadsAction(){

        if ((int) Session::get('global_admin') !== 1) {
            Errors::access_denied();
            return;
        }

        $this->view->render();
    }

}
