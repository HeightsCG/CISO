<?php
/**
 * OWASP ASVS 5.0.0 Level 3; see owasp-asvs-shared.php.
 */
return function (): array {
    $requirements = require __DIR__.'/owasp-asvs-shared.php';

    return $requirements(3);
};
