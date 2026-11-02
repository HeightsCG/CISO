<?php
$shared = require __DIR__.'/sp800171-shared.php';

return $shared('nist-800-171-r2', function (array $control) {
    return $control['number'];
});
