<?php
class SubscriptionController extends Controller {

    public $protected = 1;
    public $companies_model;

    public function __construct(){
        parent::__construct();
        $this->companies_model = new CompaniesModel();
    }

    /** user_roles.id for Admin - the administrator of a single company. */
    const ADMIN_ROLE_ID = 1;

    private function company(): array
    {
        $company = $this->companies_model->get_company(Session::get('company_id'));

        return (is_array($company) && count($company) === 1) ? $company[0] : array();
    }

    /**
     * This is what the company pays CISO.aero, so it belongs to whoever
     * administers the company. Repeated per action rather than hooked once, for
     * the same reason it is in BillingController: there is no controller-level
     * hook, and hiding the menu item only hides the menu item.
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
     * The customer record is minted on first sight here as well as on the Billing
     * page, because either can be the first one an administrator opens. It is
     * conditional on the column still being empty and backed by an idempotency
     * key, so two pages racing cannot mint two customers for one company.
     *
     * A Stripe failure is logged and the page carries on: nothing is charged yet,
     * and a subscription page that will not load is worse than one that reports
     * it could not reach Stripe.
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
            error_log('[subscription] platform customer could not be established: '.$e->getMessage());
        }

        return $company;
    }

    public function indexAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $company = $this->ensure_platform_customer($this->company());
        $customer_id = $company['platform_customer_id'] ?? '';

        $this->view->company        = $company;
        $this->view->configured     = StripeService::configured();
        $this->view->publish_key    = StripeService::publish_key();
        $this->view->subscription   = StripeService::platform_subscription($customer_id);
        $this->view->plans          = StripeService::platform_plans();
        $this->view->invoices       = StripeService::platform_invoices($customer_id);
        $this->view->payment_method = StripeService::platform_payment_method($customer_id);
        $this->view->cards          = StripeService::platform_payment_methods($customer_id);
        $this->view->transactions   = StripeService::platform_transactions($customer_id);
        $this->view->render('subscription/index');
    }

}
