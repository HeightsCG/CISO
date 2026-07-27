<?php
class LogoutController extends Controller {

    public $protected = 1;

    public function __construct() {
        parent::__construct();
    }

    public function indexAction() {
        Session::destroy();
        header('Location: /');
        exit;
    }

}