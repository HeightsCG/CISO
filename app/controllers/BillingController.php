<?php
class BillingController extends Controller {

    public $protected = 1;
    public $invoices_model;

    public function __construct(){
        parent::__construct();
        $this->invoices_model = new InvoicesModel();
    }

    /**
     * Repeated per action rather than hooked once, because there is no
     * controller-level hook to hang it on and the nav check in site_header.php is
     * not the boundary - hiding the link only hides the link.
     */
    private function refuse_unless_global_admin(): bool
    {
        if ((int) Session::get('global_admin') === 1) {
            return true;
        }

        Errors::access_denied();
        return false;
    }

    public function indexAction(){

        if (!$this->refuse_unless_global_admin()) {
            return;
        }

        $this->view->render();
    }

    /**
     * The editor opens on drafts only. A sent invoice is answered as not found
     * rather than opened read-only, because its lines live in Stripe from
     * finalisation onward and an editor showing them would imply otherwise.
     */
    public function formAction(){

        if (!$this->refuse_unless_global_admin()) {
            return;
        }

        $invoice_id = Main::get_param('id');

        if (empty($invoice_id)) {
            $this->view->invoice = null;
            $this->view->items = array();
            $this->view->render();
            return;
        }

        $invoice = $this->invoices_model->get_invoice($invoice_id, Session::get('company_id'));

        if (!is_array($invoice) || count($invoice) !== 1 || $invoice[0]['invoice_status'] !== 'Draft') {
            Errors::page_not_found();
            return;
        }

        $this->view->invoice = $invoice[0];
        $this->view->items = $this->invoices_model->get_invoice_items($invoice[0]['id']);
        $this->view->render();
    }

    public function invoiceAction(){

        if (!$this->refuse_unless_global_admin()) {
            return;
        }

        $invoice = $this->invoices_model->get_invoice(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($invoice) || count($invoice) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->invoice = $invoice[0];
        $this->view->items = $this->invoices_model->get_invoice_items($invoice[0]['id']);
        $this->view->render();
    }

}
