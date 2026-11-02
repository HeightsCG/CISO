<?php
/**
 * CMMC Level 2 uses the NIST SP 800-171 Rev 2 control set (32 CFR 170.14(c)(3)),
 * renumbered DD.L2-REQ with the domain codes from 32 CFR 170.24.
 */
return function (): array {
    $families = (require __DIR__.'/nist-800-171-r2.php')();

    foreach ($families as $f => $family) {
        foreach ($family['controls'] as $c => $control) {
            $control['id'] = $control['cmmc_id'];
            unset($control['cmmc_id']);
            $families[$f]['controls'][$c] = $control;
        }
    }

    return $families;
};
