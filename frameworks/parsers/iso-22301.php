<?php
/**
 * ISO 22301:2019 HCG control set; see iso-hcg-shared.php.
 */
return function (): array {
    $set = require __DIR__.'/iso-hcg-shared.php';

    return $set('iso-22301', 'iso22301');
};
