<?php
/**
 * NIST SP 800-53 Rev 5 (release 5.2.0): every active control and control enhancement,
 * each its own row; enhancements carry `parent`. Withdrawn entries are excluded.
 * Structure and SP 800-53A objectives from the OSCAL catalog, statement and discussion
 * from NIST's CPRT export (see sp800-53-shared.php).
 */
return function (): array {
    $catalog = (require __DIR__.'/sp800-53-shared.php')();
    $families = array();

    foreach ($catalog['families'] as $code => $family) {
        $controls = array();

        foreach ($family['ids'] as $id) {
            $controls[] = $catalog['controls'][$id];
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
