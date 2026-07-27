<?php
class AccountController extends Controller {

    public $protected = 1;

    public function __construct(){
        parent::__construct();
    }

    public function resetAction(){
        $this->view->reset_token = Main::get_param('token');
        $this->view->reset_password();
    }

}
