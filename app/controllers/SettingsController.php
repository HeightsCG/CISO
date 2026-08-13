<?php
class SettingsController extends Controller {

    public $protected = 1;

    /** user_roles.id for Admin - the administrator of a single company. */
    const ADMIN_ROLE_ID = 1;

    public $companies_model;

    public function __construct(){
        parent::__construct();
        $this->companies_model = new CompaniesModel();
    }

    /**
     * These screens change the organisation itself - its security policy, its
     * branding, and who holds an account in it - so they belong to the company
     * administrator. Until now they were reachable by any signed-in staff member,
     * which meant anyone could open the user list and raise their own role.
     *
     * Repeated per action rather than hooked once: there is no controller-level
     * hook, and the dropdown entry in site_header.php is not the boundary.
     */
    private function refuse_unless_company_admin(): bool
    {
        if (Session::get('user_type') === 'staff' && (int) Session::get('role_id') === self::ADMIN_ROLE_ID) {
            return true;
        }

        Errors::access_denied();
        return false;
    }

    public function indexAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        /**
         * Re-read the connected account whenever this page is opened rather than
         * trusting the stored mirror. Stripe moves requirements between due and
         * past due on its own schedule, and without an inbound webhook the mirror
         * silently ages - which shows an administrator a bare "Restricted" with no
         * reason attached. One API call on a low-traffic admin page is the cheapest
         * way to make what is displayed always current.
         */
        $this->sync_connect_state();

        $this->view->page_title = 'Settings';
        $this->view->render();
    }

    private function sync_connect_state(): void
    {
        $company = $this->companies_model->get_company(Session::get('company_id'));

        if (!is_array($company) || count($company) !== 1 || $company[0]['stripe_connect_account_id'] === null) {
            return;
        }

        $account = StripeService::retrieve_account($company[0]['stripe_connect_account_id']);

        if (empty($account)) {
            return;
        }

        $this->companies_model->set_connect_state(
            Session::get('company_id'),
            StripeService::connect_status($account),
            empty($account['charges_enabled']) ? 0 : 1,
            empty($account['details_submitted']) ? 0 : 1,
            empty($account['payouts_enabled']) ? 0 : 1,
            StripeService::requirements_summary($account),
            $account['default_currency'] ?? 'usd'
        );

        $this->companies_model->set_connect_reason(Session::get('company_id'), StripeService::disabled_reason($account));
    }

    public function usersAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        /* A free account has one seat, so there is no roster to manage - the page
           states what more seats are for instead of listing a single row. */
        $this->view->users_allowed = Plans::room('users', 1)['allowed'];
        $this->view->render();
    }

    /**
     * Where Stripe sends the administrator back after onboarding. A plain top-level
     * GET on purpose: the session cookie is SameSite=Lax, so it survives a
     * navigation back from Stripe but would not survive a cross-site POST.
     *
     * Returning here does not mean onboarding finished - the administrator may have
     * abandoned it - so the account is re-read rather than assumed complete.
     */
    public function stripe_returnAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $company = $this->companies_model->get_company(Session::get('company_id'));

        if (is_array($company) && count($company) === 1 && $company[0]['stripe_connect_account_id'] !== null) {

            $account = StripeService::retrieve_account($company[0]['stripe_connect_account_id']);

            if (!empty($account)) {
                $this->companies_model->set_connect_state(
                    Session::get('company_id'),
                    StripeService::connect_status($account),
                    empty($account['charges_enabled']) ? 0 : 1,
                    empty($account['details_submitted']) ? 0 : 1,
                    empty($account['payouts_enabled']) ? 0 : 1,
                    StripeService::requirements_summary($account),
                    $account['default_currency'] ?? 'usd'
                );
            }
        }

        header('Location: /settings#billing');
        exit;
    }

    /**
     * Stripe sends the administrator here when an onboarding link has expired.
     * Links are single use and short lived, so the answer is always a fresh one.
     */
    public function stripe_refreshAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $company = $this->companies_model->get_company(Session::get('company_id'));

        if (!is_array($company) || count($company) !== 1 || $company[0]['stripe_connect_account_id'] === null) {
            header('Location: /settings#billing');
            exit;
        }

        $base = Main::get_base_domain();
        $link = StripeService::create_account_link(
            $company[0]['stripe_connect_account_id'],
            $base.'/settings/stripe_refresh',
            $base.'/settings/stripe_return'
        );

        header('Location: '.(empty($link['url']) ? '/settings#billing' : $link['url']));
        exit;
    }

}
