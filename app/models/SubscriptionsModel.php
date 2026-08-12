<?php
/**
 * Retainers a company bills its own clients, mirrored from its connected Stripe
 * account. The company composes them here; Stripe owns the billing cycle and
 * raises each renewal invoice, which arrives back by webhook.
 *
 * stripe_account_id is stamped on the row rather than read from the company at
 * use time, for the same reason it is on an invoice: a company that disconnects
 * and connects a different account must not have its history silently
 * reinterpreted against the new one.
 */
class SubscriptionsModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /**
     * The label every surface shows. Computed here rather than in each view so a
     * list and a detail page cannot disagree about the same subscription.
     */
    private function status_case(): string
    {
        return "CASE
                    WHEN s.subscription_status = 'Draft' THEN 'Draft'
                    WHEN s.subscription_status = 'Canceled' THEN 'Cancelled'
                    WHEN s.subscription_status = 'Past Due' THEN 'Past Due'
                    WHEN s.subscription_status = 'Unpaid' THEN 'Unpaid'
                    WHEN s.subscription_status = 'Incomplete' THEN 'Incomplete'
                    WHEN s.subscription_status = 'Incomplete Expired' THEN 'Expired'
                    WHEN s.subscription_status = 'Trialing' THEN 'Trial'
                    WHEN s.subscription_status = 'Paused' THEN 'Paused'
                    WHEN s.cancel_at_period_end = 1 THEN 'Ending'
                    ELSE 'Active'
                END";
    }

    private function money_columns(): string
    {
        return "CONCAT(UPPER(s.currency), ' ', FORMAT(s.unit_amount_cents * s.quantity / 100, 2)) AS amount_display";
    }

    public function load_subscriptions($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    s.id,
                    s.client_id,
                    s.project_id,
                    s.subscription_name,
                    s.subscription_status,
                    s.cancel_at_period_end,
                    s.currency,
                    s.unit_amount_cents,
                    s.quantity,
                    s.billing_interval,
                    s.current_period_end,
                    s.date_created,
                    c.company_name AS client_name,
                    p.project_name,
                    ".$this->money_columns().",
                    ".$this->status_case()." AS subscription_status_display,
                    DATE_FORMAT(s.date_created, df.sql_format) AS date_created_display,
                    DATE_FORMAT(s.current_period_end, df.sql_format) AS renews_display
                FROM
                    subscriptions s
                    JOIN companies co ON co.id = s.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    JOIN clients c ON c.id = s.client_id and c.deleted = 0
                    LEFT JOIN projects p ON p.id = s.project_id and p.deleted = 0
                WHERE
                    s.company_id = :company_id
                    and
                    s.deleted = 0
                ORDER BY
                    s.date_created DESC,
                    s.id DESC";
        return parent::select($sql, $where);
    }

    public function get_subscription($subscription_id, $company_id)
    {
        $where = array(
            'id'         => $subscription_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    s.*,
                    c.company_name AS client_name,
                    c.contact_name AS client_contact_name,
                    c.contact_email AS client_contact_email,
                    c.billing_name AS client_billing_name,
                    c.billing_email AS client_billing_email,
                    c.stripe_customer_id AS client_stripe_customer_id,
                    p.project_name,
                    ".$this->money_columns().",
                    ".$this->status_case()." AS subscription_status_display,
                    DATE_FORMAT(s.date_created, df.sql_format) AS date_created_display,
                    DATE_FORMAT(s.start_date, df.sql_format) AS start_date_display,
                    DATE_FORMAT(s.current_period_end, df.sql_format) AS renews_display
                FROM
                    subscriptions s
                    JOIN companies co ON co.id = s.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    JOIN clients c ON c.id = s.client_id and c.deleted = 0
                    LEFT JOIN projects p ON p.id = s.project_id and p.deleted = 0
                WHERE
                    s.id = :id
                    and
                    s.company_id = :company_id
                    and
                    s.deleted = 0";
        return parent::select($sql, $where);
    }

    public function add_subscription($company_id, $client_id, $project_id, $subscription_name, $currency, $unit_amount_cents, $quantity, $billing_interval, $due_days, $start_date, $created_by)
    {
        $now = date('Y-m-d H:i:s');

        $data = array(
            'company_id'          => $company_id,
            'client_id'           => $client_id,
            'project_id'          => $project_id,
            'subscription_name'   => $subscription_name,
            'subscription_status' => 'Draft',
            'currency'            => $currency,
            'unit_amount_cents'   => $unit_amount_cents,
            'quantity'            => $quantity,
            'billing_interval'    => $billing_interval,
            'due_days'            => $due_days,
            'start_date'          => ($start_date === '' ? null : $start_date),
            'created_by'          => $created_by,
            'updated_by'          => $created_by,
            'date_created'        => $now,
            'date_updated'        => $now,
            'deleted'             => 0
        );

        return parent::insert('subscriptions', $data);
    }

    /**
     * Only a draft is editable. Once Stripe holds the subscription the price is
     * fixed against a live billing cycle, and rewriting the row here would leave
     * the two describing different money.
     */
    public function update_subscription($subscription_id, $company_id, $client_id, $project_id, $subscription_name, $unit_amount_cents, $quantity, $billing_interval, $due_days, $start_date, $updated_by)
    {
        $where = array(
            'id'         => $subscription_id,
            'company_id' => $company_id
        );
        $data = array(
            'client_id'         => $client_id,
            'project_id'        => $project_id,
            'subscription_name' => $subscription_name,
            'unit_amount_cents' => $unit_amount_cents,
            'quantity'          => $quantity,
            'billing_interval'  => $billing_interval,
            'due_days'          => $due_days,
            'start_date'        => ($start_date === '' ? null : $start_date),
            'updated_by'        => $updated_by,
            'date_updated'      => date('Y-m-d H:i:s')
        );

        return parent::update('subscriptions', $data, "id = :id and company_id = :company_id and subscription_status = 'Draft'", $where);
    }

    /**
     * Claim the row before going to Stripe, so two admins pressing Activate at the
     * same moment cannot raise two subscriptions against one client. rowCount() of
     * 1 is the caller's permission to proceed.
     */
    public function claim_provision($subscription_id, $company_id, $updated_by)
    {
        $where = array(
            'id'         => $subscription_id,
            'company_id' => $company_id
        );
        $data = array(
            'provision_state' => 'Pending',
            'provision_error' => '',
            'updated_by'      => $updated_by,
            'date_updated'    => date('Y-m-d H:i:s')
        );

        return parent::update(
            'subscriptions',
            $data,
            "id = :id and company_id = :company_id and subscription_status = 'Draft' and provision_state <> 'Pending'",
            $where
        );
    }

    public function fail_provision($subscription_id, $message)
    {
        $where = array(
            'id' => $subscription_id
        );
        $data = array(
            'provision_state' => 'Failed',
            'provision_error' => substr((string) $message, 0, 500),
            'date_updated'    => date('Y-m-d H:i:s')
        );

        return parent::update('subscriptions', $data, 'id = :id', $where);
    }

    /** Mirror what Stripe returned once the subscription exists there. */
    public function apply_stripe_subscription($subscription_id, array $stripe, $account_id, $livemode)
    {
        $item = $stripe['items']['data'][0] ?? array();

        $where = array(
            'id' => $subscription_id
        );
        $data = array(
            'subscription_status'         => StripeService::subscription_status((string) ($stripe['status'] ?? '')),
            'provision_state'             => 'Idle',
            'provision_error'             => '',
            'stripe_subscription_id'      => $stripe['id'] ?? null,
            'stripe_subscription_item_id' => $item['id'] ?? '',
            'stripe_customer_id'          => is_string($stripe['customer'] ?? null) ? $stripe['customer'] : '',
            'stripe_account_id'           => $account_id,
            'stripe_price_id'             => $item['price']['id'] ?? '',
            'stripe_product_id'           => is_string($item['price']['product'] ?? null) ? $item['price']['product'] : '',
            'stripe_livemode'             => $livemode,
            'stripe_synced_at'            => date('Y-m-d H:i:s'),
            'cancel_at_period_end'        => empty($stripe['cancel_at_period_end']) ? 0 : 1,
            'date_updated'                => date('Y-m-d H:i:s')
        );

        /* current_period_end moved onto the subscription item in API
           2026-06-24.dahlia; the top-level field returns null silently. */
        if (!empty($item['current_period_end'])) {
            $data['current_period_end'] = date('Y-m-d H:i:s', (int) $item['current_period_end']);
        }

        if (!empty($stripe['canceled_at'])) {
            $data['canceled_at'] = date('Y-m-d H:i:s', (int) $stripe['canceled_at']);
        }

        return parent::update('subscriptions', $data, 'id = :id', $where);
    }

    /** Reached by Stripe id alone, because a webhook arrives with no session. */
    public function by_stripe_subscription_id($stripe_subscription_id)
    {
        $where = array(
            'stripe_subscription_id' => $stripe_subscription_id
        );
        $sql = "SELECT id, company_id, client_id, project_id, currency, stripe_account_id, stripe_event_created
                FROM subscriptions
                WHERE stripe_subscription_id = :stripe_subscription_id and deleted = 0";

        return parent::select($sql, $where);
    }

    public function delete_subscription($subscription_id, $company_id, $updated_by)
    {
        $where = array(
            'id'         => $subscription_id,
            'company_id' => $company_id
        );
        $data = array(
            'deleted'      => 1,
            'updated_by'   => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );

        return parent::update('subscriptions', $data, "id = :id and company_id = :company_id and subscription_status = 'Draft'", $where);
    }

}
