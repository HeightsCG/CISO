<?php
class AccountController extends Controller {

    public $protected = 1;

    public $user_model;

    public function __construct(){
        parent::__construct();
        $this->user_model = new UsersModel();
    }

    public function resetAction(){
        $this->view->reset_token = Main::get_param('token');
        $this->view->token_valid = (count($this->user_model->get_user_by_reset_token(Main::get_param('token'))) === 1);
        $this->view->reset_password();
    }

}
