<?php
/**
 * ISO/IEC 27018:2025 HCG control set; see iso-hcg-shared.php.
 */
return function (): array {
    $set = require __DIR__.'/iso-hcg-shared.php';

    return $set('iso-27018', 'iso27018');
};
