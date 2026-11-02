<?php
/**
 * EASA Part-IS.D.OR: Annex to Commission Delegated Regulation (EU) 2022/1645
 * (consolidated version of 16 October 2025), with AMC & GM from the EASA Easy
 * Access Rules for Information Security (December 2025). See easa-part-is-shared.php.
 */
return function (): array {
    $shared = require __DIR__.'/easa-part-is-shared.php';

    return $shared(
        'D',
        'easa/reg-2022-1645-consolidated-2025-10-16.html',
        'easa/ear-information-security-2025-12.pdf',
        'ANNEX — ORGANISATION REQUIREMENTS (PART-IS.D.OR)',
        ''
    );
};
