<?php
class SettingsController extends Controller {

    public $protected = 1;

    /** user_roles.id for Admin - the administrator of a single company. */
    const ADMIN_ROLE_ID = 1;

    public function __construct(){
        parent::__construct();
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

        $this->view->page_title = 'Settings';
        $this->view->render();
    }

    public function usersAction(){

        if (!$this->refuse_unless_company_admin()) {
            return;
        }

        $this->view->render();
    }

}
