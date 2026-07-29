<?php
class StandardsController extends Controller {

    public $protected = 1;
    public $standards_model;

    public function __construct() {
        parent::__construct();
        $this->standards_model = new StandardsModel();
    }

    /**
     * This controller serves pages and JSON from the same class, so the base
     * $json flag cannot be used: it would answer /standards itself with a 401
     * body instead of letting layout.php render the login form. Gated once here
     * for every endpoint rather than repeated in each action.
     */

    public function indexAction() {
        $this->view->render();
    }

    public function detailAction() {

        $standard = $this->standards_model->get_standard(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($standard) || count($standard) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->standard = $standard[0];
        $this->view->render();
    }

    public function formAction() {

        $standard_id = Main::get_param('id');

        if (empty($standard_id)) {
            $this->view->standard = null;
            $this->view->render();
            return;
        }

        $standard = $this->standards_model->get_standard($standard_id, Session::get('company_id'));

        if (!is_array($standard) || count($standard) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->standard = $standard[0];
        $this->view->render();
    }










    /**
     * clean_post_data() HTML-encodes every field on the way in, but CSV rows arrive
     * through $_FILES and bypass it entirely. Decoding here keeps a single encoding
     * in the table whichever route the text took; the views escape on output.
     */


}
