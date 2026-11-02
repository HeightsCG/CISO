<?php
/**
 * SOC 2 (2017 TSC, revised points of focus 2022): criterion IDs, groups and
 * point of focus names from the committed list derived from HCG's copy, with
 * HCG titles, descriptions and guidance from frameworks/meta/controls/soc2/.
 * No AICPA criteria text is stored. Points of focus are loaded as objectives,
 * by name only, with the engagement scope they apply to.
 */
return function (): array {
    require_once __DIR__.'/soc2-extract.php';

    $list = json_decode(file_get_contents(Source::path('soc2/tsc-2017-ids.json')), true);
    $content = json_decode(file_get_contents(dirname(__DIR__).'/meta/controls/soc2/hcg.json'), true);
    $codes = array_flip(soc2_groups());
    $families = array();

    foreach ($list['criteria'] as $criterion) {
        $id = $criterion['id'];

        if (!isset($content[$id])) {
            throw new RuntimeException('No HCG description for SOC 2 '.$id);
        }

        $objectives = array();

        foreach ($criterion['points_of_focus'] as $i => $point) {
            $objective = array(
                'id' => $id.'-PF'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'text' => $point['title'],
                'scope' => $point['scope']
            );

            if (!empty($point['controller'])) {
                $objective['controller'] = true;
            }

            if (!empty($point['processor'])) {
                $objective['processor'] = true;
            }

            $objectives[] = $objective;
        }

        $group = $criterion['group'];

        if (!isset($families[$group])) {
            $families[$group] = array(
                'number' => $codes[$group],
                'code' => $codes[$group],
                'name' => $group,
                'controls' => array()
            );
        }

        $families[$group]['controls'][] = array(
            'id' => $id,
            'number' => $id,
            'title' => $content[$id]['title'],
            'text' => '',
            'description' => $content[$id]['description'],
            'guidance' => $content[$id]['guidance'],
            'objectives' => $objectives
        );
        unset($content[$id]);
    }

    if (count($content) > 0) {
        throw new RuntimeException('HCG descriptions for unknown SOC 2 IDs: '.implode(', ', array_keys($content)));
    }

    return array_values($families);
};
