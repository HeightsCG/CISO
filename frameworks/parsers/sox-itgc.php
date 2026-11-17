<?php
/**
 * SOX ITGC HCG control set; see hcg-set-shared.php.
 */
return function (): array {
    $set = require __DIR__.'/hcg-set-shared.php';

    return $set('sox-itgc');
};
