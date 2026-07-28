<?php
class ProfileController extends Controller {

    public $protected = 1;
    public $user_model;

    public function __construct(){
        parent::__construct();
        $this->user_model = new UsersModel();
    }

    public function indexAction(){
        $profile = $this->user_model->get_profile(Session::get('user_id'));
        if (is_array($profile) && count($profile) === 1) {
            $this->view->profile = $profile[0];
            $this->view->render();
        } else {
            Errors::page_not_found();
        }
    }

}
