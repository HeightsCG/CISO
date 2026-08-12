<?php
/**
 * What each plan allows, held in the application rather than in Stripe or a
 * table. Stripe stays the authority on what is charged - price, currency and
 * interval - because that is what actually moves money and a second copy could
 * disagree with it. What a plan *permits* is product definition: it changes with
 * a release, belongs in version control, and is read all over the system.
 *
 * Keyed on the product name rather than the price id on purpose. Price ids differ
 * between test and live mode, so a keyed-by-id catalogue would silently describe
 * nothing the moment the production key was swapped in. The name is the one
 * identifier that survives that.
 *
 * The limits are the single source: the sentences printed on a pricing card are
 * generated from the same numbers the system enforces, so the card can never
 * promise a ceiling the application does not apply.
 */
class Plans {

    /** A limit of null is no limit. */
    private static function catalog(): array
    {
        return array(

            'CISO.aero Pro' => array(
                'order'  => 1,
                'limits' => array(
                    'clients'                 => null,
                    'projects'                => null,
                    'assessments_per_project' => null
                )
            ),

            'CISO.aero Standard' => array(
                'order'  => 2,
                'limits' => array(
                    'clients'                 => 5,
                    'projects'                => 5,
                    'assessments_per_project' => 2
                )
            )

        );
    }

    /** The plan a company is on, by name. Unknown names get the tightest limits. */
    public static function limits(string $plan_name): array
    {
        $catalog = self::catalog();

        return $catalog[$plan_name]['limits'] ?? array(
            'clients'                 => 0,
            'projects'                => 0,
            'assessments_per_project' => 0
        );
    }

    /** Whether one more of something is allowed, given how many exist already. */
    public static function allows(string $plan_name, string $limit, int $existing): bool
    {
        $limits = self::limits($plan_name);
        $cap    = $limits[$limit] ?? 0;

        return $cap === null || $existing < $cap;
    }

    /**
     * The sentences a pricing card shows, written from the limits themselves so
     * the two cannot drift. A plan with no ceilings says so once rather than
     * listing "unlimited" three times.
     */
    public static function features(string $plan_name): array
    {
        $limits = self::limits($plan_name);

        if (count(array_filter($limits, function ($cap) { return $cap !== null; })) === 0) {
            return array('Unlimited access to everything');
        }

        $labels = array(
            'clients'                 => 'client',
            'projects'                => 'project',
            'assessments_per_project' => 'assessment per project'
        );

        $features = array();

        foreach ($limits as $key => $cap) {

            if (!isset($labels[$key])) {
                continue;
            }

            if ($cap === null) {
                $features[] = 'Unlimited '.$labels[$key].'s';
                continue;
            }

            /* "2 assessments per project", not "2 assessment per projects". */
            $label = $labels[$key];
            $plural = strpos($label, ' ') === false
                ? $label.'s'
                : preg_replace('/^(\S+)/', '$1s', $label);

            $features[] = 'Up to '.$cap.' '.($cap === 1 ? $label : $plural);
        }

        return $features;
    }

    /**
     * Merge the catalogue onto the plans Stripe returned, and put them in the
     * order given here. Anything Stripe offers that is not described sorts last,
     * since an undescribed plan is usually one nobody has finished setting up.
     */
    public static function describe(array $stripe_plans): array
    {
        $catalog = self::catalog();
        $plans   = array();

        foreach ($stripe_plans as $plan) {

            $plan['features'] = isset($catalog[$plan['name']]) ? self::features($plan['name']) : array();
            $plan['order']    = $catalog[$plan['name']]['order'] ?? 99;

            $plans[] = $plan;
        }

        usort($plans, function ($a, $b) {
            return $a['order'] === $b['order'] ? strcmp($a['name'], $b['name']) : $a['order'] - $b['order'];
        });

        return $plans;
    }

}
