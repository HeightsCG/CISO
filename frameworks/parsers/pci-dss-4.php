<?php
/**
 * PCI DSS v4.0.1: requirement IDs and flags from the committed list derived
 * from HCG's licensed copy, with HCG-written titles, descriptions and guidance
 * from frameworks/meta/controls/pci-dss-4/*.json. No PCI requirement text is
 * stored.
 */
return function (): array {
    $list = json_decode(file_get_contents(Source::path('pci/pci-dss-4.0.1-ids.json')), true);
    $content = array();

    foreach (glob(dirname(__DIR__).'/meta/controls/pci-dss-4/*.json') as $file) {
        foreach (json_decode(file_get_contents($file), true) as $id => $entry) {
            if (isset($content[$id])) {
                throw new RuntimeException('PCI DSS '.$id.' is described twice');
            }

            $content[$id] = $entry;
        }
    }

    $families = array();

    foreach ($list['requirements'] as $requirement) {
        $controls = array();

        foreach ($requirement['controls'] as $control) {
            $id = $control['id'];

            if (!isset($content[$id])) {
                throw new RuntimeException('No HCG description for PCI DSS '.$id);
            }

            $controls[] = array_merge(array(
                'id' => $id,
                'number' => $id,
                'title' => $content[$id]['title'],
                'text' => '',
                'description' => $content[$id]['description'],
                'guidance' => $content[$id]['guidance'],
                'objectives' => array()
            ), array_diff_key($control, array(
                'id' => true
            )));
            unset($content[$id]);
        }

        $families[] = array(
            'number' => $requirement['number'],
            'code' => 'REQ-'.$requirement['number'],
            'name' => $requirement['name'],
            'controls' => $controls
        );
    }

    if (count($content) > 0) {
        throw new RuntimeException('HCG descriptions for unknown PCI DSS IDs: '.implode(', ', array_keys($content)));
    }

    return $families;
};
