-- A control with only some of its objectives answered now counts as
-- Partially Implemented instead of Not Assessed.
--
--     mysql <database> < sql/2026-11-23-partial-objectives.sql

UPDATE assessment_items c
SET
    c.item_result = 'Partially Implemented'
WHERE
    c.control_kind = 'control'
    and
    c.deleted = 0
    and
    c.item_result = 'Not Assessed'
    and
    EXISTS (
        SELECT 1
        FROM (SELECT assessment_id, parent_identifier, item_result, control_kind, deleted FROM assessment_items) o
        WHERE o.assessment_id = c.assessment_id
            and o.parent_identifier = c.control_identifier
            and o.control_kind = 'objective'
            and o.deleted = 0
            and o.item_result <> 'Not Assessed'
    );
