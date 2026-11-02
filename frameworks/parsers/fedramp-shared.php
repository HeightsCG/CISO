<?php
/**
 * FedRAMP Rev 5 baselines (Low, Moderate, High). Shared by fedramp-low.php,
 * fedramp-moderate.php and fedramp-high.php.
 *
 * - Baseline membership, FedRAMP-defined parameter values and Additional FedRAMP
 *   Requirements and Guidance: FedRAMP Security Controls Baseline workbook
 *   (fedramp.gov/legacy), one sheet per baseline.
 * - Control statement, discussion, family and SP 800-53A objectives: joined by ID to
 *   NIST SP 800-53 Rev 5.2.0 (sp800-53-shared.php), as FedRAMP baselines select from it.
 */
return function (string $level): array {
    $sheets = array(
        'High' => 2,
        'Moderate' => 3,
        'Low' => 4
    );
    $rows = Source::xlsx_rows('fedramp/FedRAMP_Security_Controls_Baseline.xlsx', $sheets[$level]);

    // Guard against a re-ordered workbook: the sheet must announce its own baseline.
    if (trim($rows[0]['E'] ?? '') !== 'FedRAMP® '.$level.' Baseline') {
        throw new RuntimeException('Baseline sheet '.$sheets[$level].' is not the '.$level.' baseline');
    }

    $catalog = (require __DIR__.'/sp800-53-shared.php')();

    // Workbook cells use line breaks between parameter and requirement entries.
    $lines = function (string $cell): string {
        $out = array();

        foreach (preg_split('/\R/', $cell) as $line) {
            $line = Source::normalise($line);

            if ($line !== '') {
                $out[] = $line;
            }
        }

        return implode("\n", $out);
    };

    $selected = array();

    foreach (array_slice($rows, 2) as $row) {
        $cell = trim($row['C'] ?? '');

        if ($cell === '') {
            continue;
        }

        // The workbook writes enhancements as "AC-2 (1)"; NIST cites AC-2(1).
        $id = str_replace(' ', '', $cell);

        if (!isset($catalog['controls'][$id])) {
            throw new RuntimeException('FedRAMP '.$level.' selects '.$id.', which is not an active SP 800-53 Rev 5 control');
        }

        $control = $catalog['controls'][$id];
        $parameters = $lines($row['G'] ?? '');
        $requirements = $lines($row['H'] ?? '');

        if ($parameters !== '') {
            $control['fedramp_parameters'] = $parameters;
        }

        if ($requirements !== '') {
            $control['fedramp_requirements'] = $requirements;
        }

        $selected[$id] = $control;
    }

    $families = array();

    foreach ($catalog['families'] as $code => $family) {
        $controls = array();

        // Catalog order, so enhancements follow their base control.
        foreach ($family['ids'] as $id) {
            if (isset($selected[$id])) {
                $controls[] = $selected[$id];
            }
        }

        if (count($controls) === 0) {
            continue;
        }

        $families[] = array(
            'number' => $code,
            'code' => $code,
            'name' => $family['name'],
            'controls' => $controls
        );
    }

    return $families;
};
