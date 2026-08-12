<?php
/**
 * The presentation half of a plan. Stripe owns the money - the price, currency
 * and interval a subscription is actually charged on - and this owns what the
 * plan says about itself, so the sales copy ships with the application instead of
 * living in a dashboard someone has to remember to edit.
 */
class PlansModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /** Every plan's copy, keyed by the Stripe price it describes. */
    public function load_plans()
    {
        $sql = "SELECT
                    id,
                    stripe_price_id,
                    plan_name,
                    plan_blurb,
                    plan_features,
                    plan_status,
                    sort_order
                FROM
                    platform_plans
                WHERE
                    deleted = 0
                ORDER BY
                    sort_order ASC,
                    id ASC";

        $rows  = parent::select($sql, array());
        $plans = array();

        foreach ($rows as $row) {
            $plans[$row['stripe_price_id']] = $row;
        }

        return $plans;
    }

    public function get_plan($plan_id)
    {
        $where = array(
            'id' => $plan_id
        );
        $sql = "SELECT * FROM platform_plans WHERE id = :id and deleted = 0";

        return parent::select($sql, $where);
    }

    /**
     * One row per Stripe price. Written by price id rather than by row id so that
     * saving copy for a plan that has none yet does not need a separate create
     * path, and so a price can never end up with two descriptions.
     */
    public function save_plan($stripe_price_id, $plan_name, $plan_blurb, $plan_features, $plan_status, $sort_order, $updated_by)
    {
        $now = date('Y-m-d H:i:s');

        $existing = parent::select(
            "SELECT id FROM platform_plans WHERE stripe_price_id = :stripe_price_id",
            array('stripe_price_id' => $stripe_price_id)
        );

        $data = array(
            'plan_name'     => $plan_name,
            'plan_blurb'    => $plan_blurb,
            'plan_features' => $plan_features,
            'plan_status'   => $plan_status,
            'sort_order'    => $sort_order,
            'updated_by'    => $updated_by,
            'date_updated'  => $now,
            'deleted'       => 0
        );

        if (is_array($existing) && count($existing) === 1) {
            return parent::update('platform_plans', $data, 'id = :id', array('id' => $existing[0]['id']));
        }

        $data['stripe_price_id'] = $stripe_price_id;
        $data['created_by']      = $updated_by;
        $data['date_created']    = $now;

        return parent::insert('platform_plans', $data);
    }

}
