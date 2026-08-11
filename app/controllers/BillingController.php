<?php
class BillingController extends Controller {

    public $protected = 1;
    public $invoices_model;
    public $companies_model;

    public function __construct(){
        parent::__construct();
        $this->invoices_model = new InvoicesModel();
        $this->companies_model = new CompaniesModel();
    }

    /**
     * The company's own row, which carries both its Stripe connection state and
     * the currency every invoice it raises is denominated in.
     */
    private function company(): array
    {
        $company = $this->companies_model->get_company(Session::get('company_id'));

        return (is_array($company) && count($company) === 1) ? $company[0] : array();
    }

    /** user_roles.id for Admin - the administrator of a single company. */
    const ADMIN_ROLE_ID = 1;

    /**
     * Billing belongs to whoever administers the company, not to the cross-tenant
     * operator: a company runs its own Stripe account and bills its own clients,
     * and its administrator must be able to do that without being able to read
     * every other organisation.
     *
     * Repeated per action rather than hooked once, because there is no
     * controller-level hook to hang it on and the nav check in site_header.php is
     * not the boundary - hiding the link only hides the link.
     */
    private function refuse_unless_company_admin(): bool
    {
        if (Session::get('user_type') === 'staff' && (int) Session::get('role_id') === self::ADMIN_ROLE_ID) {
            return true;
        }

        Errors::access_denied();
        return false;
    }

    /**
     * The company is a customer of this platform as well as a merchant on it, so a
     * customer record is minted here the first time anyone opens Billing.
     *
     * Deliberately not on the login path: a Stripe outage there would delay or
     * break sign-in for everyone, including portal clients with no billing at all.
     * Nothing is charged - the record exists so that billing companies later needs
     * no backfill - so a failure is logged and the page carries on.
     */
    private function ensure_platform_customer(array $company): array
    {
        if (empty($company) || $company['platform_customer_id'] !== null) {
            return $company;
        }

        if (!StripeService::configured()) {
            return $company;
        }

        try {

            $customer = StripeService::create_platform_customer(
                $company['id'],
                $company['company_name'],
                Session::get('user_email'),
                $company['address_1'],
                $company['address_2'],
                $company['city'],
                $company['state'],
                $company['postal_code'],
                $company['country']
            );

            if (empty($customer['id'])) {
                return $company;
            }

            $claimed = $this->companies_model->set_platform_customer($company['id'], $customer['id'], StripeService::livemode());

            if ($claimed === 0) {
                return $this->company();
            }

            $company['platform_customer_id'] = $customer['id'];
            $company['platform_livemode'] = StripeService::livemode();

        } catch (\Throwable $e) {
            error_log('[billing] platform customer could not be established: '.$e->getMessage());
        }

        return $company;
    }

    public function indexAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $this->view->company = $this->ensure_platform_customer($this->company());
        $this->view->render();
    }

    /**
     * The editor opens on drafts only. A sent invoice is answered as not found
     * rather than opened read-only, because its lines live in Stripe from
     * finalisation onward and an editor showing them would imply otherwise.
     */
    public function formAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $invoice_id = Main::get_param('id');

        if (empty($invoice_id)) {
            $this->view->invoice = null;
            $this->view->items = array();
            $this->view->company = $this->company();
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
        $this->view->company = $this->company();
        $this->view->render();
    }

    public function invoiceAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $invoice = $this->invoices_model->get_invoice(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($invoice) || count($invoice) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->invoice = $invoice[0];
        $this->view->items = $this->invoices_model->get_invoice_items($invoice[0]['id']);
        $this->view->company = $this->company();
        $this->view->render();
    }

}
