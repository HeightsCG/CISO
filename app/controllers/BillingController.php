<?php
class BillingController extends Controller {

    public $protected = 1;
    public $invoices_model;
    public $companies_model;
    public $subscriptions_model;

    public function __construct(){
        parent::__construct();
        $this->invoices_model = new InvoicesModel();
        $this->companies_model = new CompaniesModel();
        $this->subscriptions_model = new SubscriptionsModel();
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

    /**
     * Serve the invoice PDF through this application rather than redirecting to
     * Stripe's own URL.
     *
     * That URL is a capability URL - possession is authorisation - so redirecting
     * would leave it in the browser history and address bar, where it can be copied
     * and forwarded to someone with no entitlement at all. Proxying keeps our own
     * session-protected address as the only one the user ever sees.
     *
     * A GET, so it carries no CSRF token and must therefore have no side effects.
     */
    public function pdfAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $invoice = $this->invoices_model->get_invoice(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($invoice) || count($invoice) !== 1 || $invoice[0]['invoice_pdf_url'] === '') {
            Errors::page_not_found();
            return;
        }

        $this->stream_invoice_pdf($invoice[0]['invoice_pdf_url'], $invoice[0]['invoice_number']);
    }

    /**
     * The origin allow-list is not optional. Without it a corrupted or
     * attacker-influenced URL in that column turns this action into a server-side
     * request forgery primitive, fetching arbitrary internal addresses with the web
     * server's network position.
     */
    private function stream_invoice_pdf($url, $invoice_number): void
    {
        if (!preg_match('#^https://[a-z0-9.\-]+\.stripe\.com/#i', $url)) {
            error_log('[billing] refused to proxy a PDF from an unexpected origin');
            Errors::page_not_found();
            return;
        }

        $pdf = @file_get_contents($url);

        if ($pdf === false || $pdf === '') {
            Errors::page_not_found();
            return;
        }

        /** The number comes from Stripe and lands in a header, so CR/LF cannot survive. */
        $filename = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $invoice_number);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="'.($filename === '' ? 'invoice' : $filename).'.pdf"');
        header('Content-Length: '.strlen($pdf));
        header('X-Content-Type-Options: nosniff');

        echo $pdf;
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


    /* ------------------------------------------------------- subscriptions */

    public function subscriptionsAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $this->view->company = $this->company();
        $this->view->render();
    }

    /**
     * The editor opens on drafts only, for the same reason the invoice editor
     * does: once Stripe holds the subscription it is billing a live cycle, and an
     * editor showing its price would imply that price could still be changed.
     */
    public function subscriptionformAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $subscription_id = Main::get_param('id');

        if (empty($subscription_id)) {
            $this->view->subscription = null;
            $this->view->company = $this->company();
            $this->view->render();
            return;
        }

        $subscription = $this->subscriptions_model->get_subscription($subscription_id, Session::get('company_id'));

        if (!is_array($subscription) || count($subscription) !== 1 || $subscription[0]['subscription_status'] !== 'Draft') {
            Errors::page_not_found();
            return;
        }

        $this->view->subscription = $subscription[0];
        $this->view->company = $this->company();
        $this->view->render();
    }

    public function subscriptionAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $subscription = $this->subscriptions_model->get_subscription(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($subscription) || count($subscription) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->subscription = $subscription[0];
        $this->view->company = $this->company();
        $this->view->render();
    }

}
