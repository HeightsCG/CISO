-- Shared, read-only framework library. Built-in frameworks (standards with a
-- framework_key) are stored once with company_id 0 and read by every
-- organisation; nothing in the app edits them. An assessment copies a
-- framework's controls when it is created, and any change is made there.
--
--     mysql <database> < sql/2026-11-30-shared-framework-library.sql
--
-- For each framework_key the lowest-id copy becomes the shared row; every
-- assessment that pointed at any copy is repointed to it; the other copies and
-- their controls are removed. Run once, after deploying the code that reads
-- company_id 0, then reload with: php load_frameworks.php

CREATE TEMPORARY TABLE shared_frameworks AS
    SELECT
        framework_key,
        MIN(id) AS keep_id
    FROM
        standards
    WHERE
        framework_key <> ''
        and
        deleted = 0
    GROUP BY
        framework_key;

UPDATE assessments a
    JOIN standards s ON s.id = a.standard_id
    JOIN shared_frameworks k ON k.framework_key = s.framework_key
SET
    a.standard_id = k.keep_id;

UPDATE standards s
    JOIN shared_frameworks k ON k.keep_id = s.id
SET
    s.company_id = 0;

DELETE sc FROM standard_controls sc
    JOIN standards s ON s.id = sc.standard_id
WHERE
    s.framework_key <> ''
    and
    s.company_id <> 0;

DELETE FROM standards
WHERE
    framework_key <> ''
    and
    company_id <> 0;

DROP TEMPORARY TABLE shared_frameworks;
