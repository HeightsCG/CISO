<?php
/**
 * Company-admin gate for the HTML view controllers (Billing, Settings,
 * Subscription). These screens change the organisation itself - its billing,
 * its security policy, its seats - so they belong to the company administrator,
 * not to any signed-in staff member. The check is made per action rather than
 * hooked once: there is no controller-level hook, and hiding the nav or menu
 * entry only hides the entry, not the route.
 *
 * On refusal it renders the access-denied page and returns false, so an action
 * bails with `if (!$this->refuse_unless_company_admin()) { return; }`.
 * ApiController keeps its own JSON-and-exit variant, because a JSON endpoint has
 * no page to render. self::ADMIN_ROLE_ID is resolved from the using class.
 */
trait CompanyAdminGate {

    private function refuse_unless_company_admin(): bool
    {
        if (Session::get('user_type') === 'staff' && (int) Session::get('role_id') === self::ADMIN_ROLE_ID) {
            return true;
        }

        Errors::access_denied();
        return false;
    }

}
