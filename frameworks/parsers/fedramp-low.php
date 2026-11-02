<?php
/**
 * FedRAMP Rev 5 Low baseline; see fedramp-shared.php.
 */
return function (): array {
    $baseline = require __DIR__.'/fedramp-shared.php';

    return $baseline('Low');
};
