<?php
/**
 * HCG control sets for frameworks with no public control text (HITRUST CSF, CIS Controls,
 * SOX ITGC). Every area, control, description, guidance note and test point is HCG-written
 * and lives in frameworks/meta/controls/<key>.json (outside meta/*.json so FrameworkBuilder
 * does not treat it as a framework). Controls carry HCG numbering only: <area code>-NN.
 */
return function (string $key): array {
    $set = json_decode(file_get_contents(dirname(__DIR__).'/meta/controls/'.$key.'.json'), true);

    if (!is_array($set)) {
        throw new RuntimeException('meta/controls/'.$key.'.json is not valid JSON');
    }

    $families = array();
    $seen = array();

    foreach ($set['families'] as $family) {
        if (!preg_match('/^[A-Z][A-Z0-9]*(?:-[A-Z0-9]+)+$/', $family['code'])) {
            throw new RuntimeException('Area code is not an HCG code: '.$family['code']);
        }

        $controls = array();

        foreach ($family['controls'] as $control) {
            if (!preg_match('/^'.preg_quote($family['code'], '/').'-\d{2}$/', $control['id'])) {
                throw new RuntimeException('Control ID does not match its area: '.$control['id']);
            }

            if (isset($seen[$control['id']])) {
                throw new RuntimeException('Duplicate control ID: '.$control['id']);
            }

            $seen[$control['id']] = true;
            $objectives = array();

            foreach ($control['objectives'] as $o => $text) {
                $objectives[] = array(
                    'id' => $control['id'].'.'.chr(97 + $o),
                    'text' => $text,
                    'source' => 'hcg'
                );
            }

            $controls[] = array(
                'id' => $control['id'],
                'number' => $control['id'],
                'title' => $control['title'],
                'text' => '',
                'description' => $control['description'],
                'guidance' => $control['guidance'],
                'type' => 'hcg',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives,
                'source' => 'hcg'
            );
        }

        $families[] = array(
            'number' => $family['code'],
            'code' => $family['code'],
            'name' => $family['name'],
            'controls' => $controls
        );
    }

    return $families;
};
