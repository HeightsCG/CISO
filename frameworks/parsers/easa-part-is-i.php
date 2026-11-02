<?php
/**
 * EASA Part-IS.I.OR: Annex II to Commission Implementing Regulation (EU) 2023/203
 * (consolidated version of 22 February 2026), with AMC & GM from the EASA Easy
 * Access Rules for Information Security (December 2025). See easa-part-is-shared.php.
 */
return function (): array {
    $shared = require __DIR__.'/easa-part-is-shared.php';

    return $shared(
        'I',
        'easa/reg-2023-203-consolidated-2026-02-22.html',
        'easa/ear-information-security-2025-12.pdf',
        'ANNEX II — ORGANISATION REQUIREMENTS (PART-IS.I.OR)',
        'ANNEX III — Annexes VI (PartARA) and VII (Part-ORA) to'
    );
};
