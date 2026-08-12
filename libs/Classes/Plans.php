<?php
class Plans {

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

    public static function limits(string $plan_name): array
    {
        $catalog = self::catalog();

        return $catalog[$plan_name]['limits'] ?? array(
            'clients'                 => 0,
            'projects'                => 0,
            'assessments_per_project' => 0
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
            'assessments_per_project' => 'assessment per project'
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

            $plans[] = $plan;
        }

        usort($plans, function ($a, $b) {
            return $a['order'] === $b['order'] ? strcmp($a['name'], $b['name']) : $a['order'] - $b['order'];
        });

        return $plans;
    }

}
