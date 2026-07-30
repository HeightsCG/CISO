<?php
class UsersController extends Controller {

    public $protected = 1;

    public function __construct(){
        parent::__construct();
    }

    /**
     * The roster spans every tenant, so the nav check in site_header.php is not the
     * boundary - hiding the link only hides the link. Anyone typing the address is
     * turned away here.
     */
    public function indexAction(){

        if ((int) Session::get('global_admin') !== 1) {
            Errors::access_denied();
            return;
        }

        $this->view->render();
    }

}