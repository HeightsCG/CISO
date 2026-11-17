<?php
/**
 * ISO/IEC 27017:2026 HCG control set; see iso-hcg-shared.php.
 */
return function (): array {
    $set = require __DIR__.'/iso-hcg-shared.php';

    return $set('iso-27017', 'iso27017');
};
