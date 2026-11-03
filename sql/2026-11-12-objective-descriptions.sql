-- Copies the new objective explanations from the framework library onto
-- objectives in existing assessments. Run after reloading the library:
--
--     APPLICATION_ENV=production php load_frameworks.php all
--     mysql <database> < sql/2026-11-12-objective-descriptions.sql

UPDATE assessment_items ai
    JOIN assessments a ON a.id = ai.assessment_id
    JOIN standard_controls sc ON sc.standard_id = a.standard_id
        and sc.control_identifier = ai.control_identifier
        and sc.control_kind = 'objective'
        and sc.deleted = 0
SET ai.description = sc.description
WHERE ai.control_kind = 'objective';
