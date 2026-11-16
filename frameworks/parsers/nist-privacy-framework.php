<?php
/**
 * NIST Privacy Framework 1.0 Core, from NIST's Privacy Framework Reference Tool (CPRT) export.
 *
 * - Groups are the 18 Categories (ID.IM-P ...), each carrying its Function (IDENTIFY-P, ID-P).
 * - Controls are the 100 Subcategories (ID.IM-P1 ...). The Core publishes no implementation
 *   examples, so there is no guidance text.
 * - The export's narrow columns in front of each level carry the Cybersecurity Framework
 *   alignment mark printed in Table 2 of the publication (identical, aligned, or none);
 *   it is not loaded.
 */
return function (): array {
    $rows = Source::xlsx_rows('privacy-framework/cprt-pf-1.0.xlsx', 1);
    $families = array();
    $function = null;
    $category = null;

    foreach (array_slice($rows, 1) as $row) {
        $b = Source::normalise($row['B'] ?? '');
        $d = Source::normalise($row['D'] ?? '');
        $f = Source::normalise($row['F'] ?? '');

        if ($b !== '') {
            if (!preg_match('/^([A-Z]+-P) \(([A-Z]{2}-P)\): (.+)$/', $b, $m)) {
                throw new RuntimeException('Unrecognised function row: '.$b);
            }

            $function = array(
                'name' => $m[1],
                'code' => $m[2],
                'description' => $m[3]
            );
        }

        if ($d !== '') {
            if (!preg_match('/^(.+?) \(([A-Z]{2})\.([A-Z]{2})-P\): (.+)$/', $d, $m)) {
                throw new RuntimeException('Unrecognised category row: '.$d);
            }

            if ($m[2].'-P' !== $function['code']) {
                throw new RuntimeException('Category '.$m[2].'.'.$m[3].'-P is outside function '.$function['code']);
            }

            $category = $m[2].'.'.$m[3].'-P';
            $families[$category] = array(
                'number' => $category,
                'code' => $category,
                'name' => $m[1],
                'description' => $m[4],
                'function' => $function['name'],
                'function_code' => $function['code'],
                'function_description' => $function['description'],
                'controls' => array()
            );
        }

        if ($f === '') {
            continue;
        }

        if (!preg_match('/^(([A-Z]{2}\.[A-Z]{2}-P)\d+): (.+)$/', $f, $m)) {
            throw new RuntimeException('Unrecognised subcategory row: '.$f);
        }

        if ($m[2] !== $category) {
            throw new RuntimeException('Subcategory '.$m[1].' is outside category '.$category);
        }

        $families[$category]['controls'][] = array(
            'id' => $m[1],
            'number' => $m[1],
            'text' => $m[3],
            'type' => 'subcategory',
            'weight' => null,
            'partial_weight' => null,
            'objectives' => array()
        );
    }

    return array_values($families);
};
