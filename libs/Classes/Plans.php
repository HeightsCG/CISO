<?php
class Plans {

    /** What a company is on when it has never subscribed. */
    const FREE = 'Free';

    private static function catalog(): array
    {
        return array(

            'CISO.aero Pro' => array(
                'order'   => 1,
                'billing' => true,
                'limits'  => array(
                    'clients'                 => null,
                    'projects'                => null,
                    'assessments_per_project' => null,
                    'users'                   => null
                )
            ),

            'CISO.aero Standard' => array(
                'order'   => 2,
                'billing' => true,
                'limits'  => array(
                    'clients'                 => 5,
                    'projects'                => 5,
                    'assessments_per_project' => 2,
                    'users'                   => null
                )
            ),

            /* No subscription is a plan of its own rather than an absence of one:
               the work can be tried at one of each, and invoicing - the thing the
               subscription pays for - is not part of it. */
            self::FREE => array(
                'order'   => 3,
                'billing' => false,
                'limits'  => array(
                    'clients'                 => 1,
                    'projects'                => 1,
                    'assessments_per_project' => 1,
                    'users'                   => 1
                )
            )

        );
    }

    /**
     * The plan the signed-in company is on, held in the session between checks.
     *
     * This is read on every page render to decide whether Billing appears at all,
     * so the payment service is asked at most once every few minutes rather than
     * once per request. Anything that changes the subscription calls forget().
     *
     * Returns '' when the service could not be reached, which callers read as
     * "unknown" - never as "free", or an outage would take billing away from a
     * company that pays for it.
     */
    public static function current(): string
    {
        $cached = Session::get('plan_name');
        $at     = (int) Session::get('plan_name_at');

        if (is_string($cached) && (time() - $at) < 300) {
            return $cached;
        }

        $company_id = Session::get('company_id');

        if (empty($company_id)) {
            return '';
        }

        $model   = new CompaniesModel();
        $company = $model->get_company($company_id);
        $customer_id = (is_array($company) && count($company) === 1)
            ? (string) ($company[0]['platform_customer_id'] ?? '')
            : '';

        /* Never subscribed, so there is nothing to ask about. */
        if ($customer_id === '') {
            return self::remember(self::FREE);
        }

        $lookup = StripeService::subscription_lookup($customer_id);

        if (!$lookup['reachable']) {
            return '';
        }

        if (empty($lookup['subscription']['id'])) {
            return self::remember(self::FREE);
        }

        $price_id = $lookup['subscription']['items']['data'][0]['price']['id'] ?? '';

        foreach (StripeService::platform_plans() as $plan) {
            if ($plan['price_id'] === $price_id) {
                return self::remember($plan['name']);
            }
        }

        return '';
    }

    private static function remember(string $plan_name): string
    {
        Session::set('plan_name', $plan_name);
        Session::set('plan_name_at', time());

        return $plan_name;
    }

    /** Called wherever the subscription changes, so the next read is fresh. */
    public static function forget(): void
    {
        Session::set('plan_name', null);
        Session::set('plan_name_at', 0);
    }

    /**
     * How much room the signed-in company has left against one limit.
     *
     * Pages ask this to decide whether the control that creates another one is
     * offered. It is not the boundary - the API refuses independently - but a
     * button that cannot work should say so before it is pressed.
     */
    public static function room(string $limit, int $existing): array
    {
        $plan_name = self::current();

        /* Unknown means unreachable, and an outage must not take the control
           away from a company that pays for it. */
        if ($plan_name === '') {
            return array('allowed' => true, 'cap' => null, 'plan' => '');
        }

        $cap = self::limits($plan_name)[$limit] ?? 0;

        return array(
            'allowed' => self::allows($plan_name, $limit, $existing),
            'cap'     => $cap,
            'plan'    => $plan_name
        );
    }

    /** Whether this plan may raise invoices at all. */
    public static function allows_billing(string $plan_name): bool
    {
        $catalog = self::catalog();

        return !empty($catalog[$plan_name]['billing']);
    }

    public static function limits(string $plan_name): array
    {
        $catalog = self::catalog();

        return $catalog[$plan_name]['limits'] ?? array(
            'clients'                 => 0,
            'projects'                => 0,
            'assessments_per_project' => 0,
            'users'                   => 0
        );
    }

    public static function allows(string $plan_name, string $limit, int $existing): bool
    {
        $limits = self::limits($plan_name);

        if (!array_key_exists($limit, $limits)) {
            return false;
        }

        $cap = $limits[$limit];

        return $cap === null || $existing < $cap;
    }

    public static function features(string $plan_name): array
    {
        $labels = array(
            'clients'                 => 'client',
            'projects'                => 'project',
            'assessments_per_project' => 'assessment per project',
            'users'                   => 'user'
        );

        $features = array();

        foreach (self::limits($plan_name) as $key => $cap) {

            if (!isset($labels[$key])) {
                continue;
            }

            $label  = $labels[$key];
            $plural = strpos($label, ' ') === false
                ? $label.'s'
                : preg_replace('/^(\S+)/', '$1s', $label);

            if ($cap === null) {
                $features[] = 'Unlimited '.$plural;
                continue;
            }

            $features[] = 'Up to '.$cap.' '.($cap === 1 ? $label : $plural);
        }

        return $features;
    }

    public static function describe(array $stripe_plans): array
    {
        $catalog = self::catalog();
        $plans   = array();

        foreach ($stripe_plans as $plan) {

            $plan['features'] = isset($catalog[$plan['name']]) ? self::features($plan['name']) : array();
            $plan['order']    = $catalog[$plan['name']]['order'] ?? 99;
            $plan['is_free']  = false;

            $plans[] = $plan;
        }

        /* The free tier stands alongside the paid ones so that leaving a plan is a
           choice on the same page, in the same shape, as joining one. It exists
           only here - nothing about it is held at the payment service. */
        $plans[] = array(
            'price_id'       => '',
            'name'           => self::FREE,
            'description'    => '',
            'currency'       => 'usd',
            'unit_amount'    => 0,
            'interval'       => 'month',
            'interval_count' => 1,
            'features'       => self::features(self::FREE),
            'order'          => $catalog[self::FREE]['order'] ?? 99,
            'is_free'        => true
        );

        usort($plans, function ($a, $b) {
            return $a['order'] === $b['order'] ? strcmp($a['name'], $b['name']) : $a['order'] - $b['order'];
        });

        return $plans;
    }

}
