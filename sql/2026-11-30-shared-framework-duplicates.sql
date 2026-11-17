-- Removes duplicate rows in the shared framework library. Twelve frameworks had
-- a second company_id 0 row (ids 135 to 150) that predates the shared library
-- and that load_frameworks.php does not update. For each framework_key the
-- lowest id is kept; any assessment on another row is repointed to it, then
-- the other rows and their controls are removed.
--
--     mysql <database> < sql/2026-11-30-shared-framework-duplicates.sql

CREATE TEMPORARY TABLE shared_keep AS
    SELECT
        framework_key,
        MIN(id) AS keep_id
    FROM
        standards
    WHERE
        framework_key <> ''
        and
        company_id = 0
        and
        deleted = 0
    GROUP BY
        framework_key;

UPDATE assessments a
    JOIN standards s ON s.id = a.standard_id
    JOIN shared_keep k ON k.framework_key = s.framework_key
SET
    a.standard_id = k.keep_id
WHERE
    s.company_id = 0
    and
    s.id <> k.keep_id;

DELETE sc FROM standard_controls sc
    JOIN standards s ON s.id = sc.standard_id
    JOIN shared_keep k ON k.framework_key = s.framework_key
WHERE
    s.company_id = 0
    and
    s.id <> k.keep_id;

DELETE s FROM standards s
    JOIN shared_keep k ON k.framework_key = s.framework_key
WHERE
    s.company_id = 0
    and
    s.id <> k.keep_id;

DROP TEMPORARY TABLE shared_keep;
