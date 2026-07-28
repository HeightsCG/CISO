<?php
class ProfileController extends Controller {

    public $protected = 1;
    public $user_model;

    public function __construct(){
        parent::__construct();
        $this->user_model = new UsersModel();
    }

    public function indexAction(){

        $rows = $this->user_model->get_profile(Session::get('user_id'));

        if (empty($rows)) {
            Errors::page_not_found();
            return;
        }

        $this->view->page_title = 'My Profile';
        $this->view->profile = $rows[0];
        $this->view->render();
    }

}
