<?php
/**
 * FedRAMP Rev 5 Moderate baseline; see fedramp-shared.php.
 */
return function (): array {
    $baseline = require __DIR__.'/fedramp-shared.php';

    return $baseline('Moderate');
};
