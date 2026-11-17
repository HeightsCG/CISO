<?php
/**
 * HITRUST CSF readiness HCG control set; see hcg-set-shared.php.
 */
return function (): array {
    $set = require __DIR__.'/hcg-set-shared.php';

    return $set('hitrust');
};
