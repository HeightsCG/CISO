<?php
/**
 * ISO/IEC 42001:2023 HCG control set; see iso-hcg-shared.php.
 */
return function (): array {
    $set = require __DIR__.'/iso-hcg-shared.php';

    return $set('iso-42001', 'iso42001');
};
