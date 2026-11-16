<?php
/**
 * OWASP ASVS 5.0.0 Level 2; see owasp-asvs-shared.php.
 */
return function (): array {
    $requirements = require __DIR__.'/owasp-asvs-shared.php';

    return $requirements(2);
};
